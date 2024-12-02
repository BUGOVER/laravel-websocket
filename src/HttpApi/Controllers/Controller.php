<?php

declare(strict_types=1);

namespace BeyondCode\LaravelWebSockets\HttpApi\Controllers;

use BeyondCode\LaravelWebSockets\Apps\App;
use BeyondCode\LaravelWebSockets\QueryParameters;
use BeyondCode\LaravelWebSockets\WebSockets\Channels\ChannelManager;
use Exception;
use GuzzleHttp\Psr7\Message;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Psr\Http\Message\RequestInterface;
use Pusher\Pusher;
use Ratchet\ConnectionInterface;
use Ratchet\Http\HttpServerInterface;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Component\HttpKernel\Exception\HttpException;

abstract class Controller implements HttpServerInterface
{
    /**
 * @var string
*/
    protected $requestBuffer = '';

    /**
 * @var RequestInterface
*/
    protected $request;

    /**
 * @var int
*/
    protected $contentLength;

    public function __construct(protected ChannelManager $channelManager)
    {
    }

    public function onOpen(ConnectionInterface $connection, RequestInterface $request = null)
    {
        $this->request = $request;

        $this->contentLength = $this->findContentLength($request->getHeaders());

        $this->requestBuffer = (string) $request->getBody();

        $this->checkContentLength($connection);
    }

    protected function findContentLength(array $headers): int
    {
        return Collection::make($headers)->first(function ($values, $header) {
            return 'content-length' === strtolower($header);
        })[0] ?? 0;
    }

    protected function checkContentLength(ConnectionInterface $connection)
    {
        if (strlen($this->requestBuffer) === $this->contentLength) {
            $serverRequest = (new ServerRequest(
                $this->request->getMethod(),
                $this->request->getUri(),
                $this->request->getHeaders(),
                $this->requestBuffer,
                $this->request->getProtocolVersion()
            ))->withQueryParams(QueryParameters::create($this->request)->all());

            $laravelRequest = Request::createFromBase((new HttpFoundationFactory())->createRequest($serverRequest));

            $this
                ->ensureValidAppId($laravelRequest->appId)
                ->ensureValidSignature($laravelRequest);

            $response = new JsonResponse($this($laravelRequest));

            $content = $response->content();

            $response->header('Content-Length', strlen($content));

            $connection->send($response);
            $connection->close();
        }
    }

    protected function ensureValidSignature(Request $request)
    {
        /*
         * The `auth_signature` & `body_md5` parameters are not included when calculating the `auth_signature` value.
         *
         * The `appId`, `appKey` & `channelName` parameters are actually route paramaters and are never supplied by the client.
         */
        $params = Arr::except($request->query(), ['auth_signature', 'body_md5', 'appId', 'appKey', 'channelName']);

        if ('' !== $request->getContent()) {
            $params['body_md5'] = md5($request->getContent());
        }

        ksort($params);

        $signature = "{$request->getMethod()}\n/{$request->path()}\n" . Pusher::array_implode('=', '&', $params);

        $authSignature = hash_hmac('sha256', $signature, App::findById($request->get('appId'))->secret);

        if ($authSignature !== $request->get('auth_signature')) {
            throw new HttpException(401, 'Invalid auth signature is provided.');
        }

        return $this;
    }

    public function ensureValidAppId(string $appId)
    {
        if (!App::findById($appId)) {
            throw new HttpException(401, "Unknown app ID `{$appId}` provided.");
        }

        return $this;
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        $this->requestBuffer .= $msg;

        $this->checkContentLength($from);
    }

    public function onClose(ConnectionInterface $conn)
    {
    }

    public function onError(ConnectionInterface $conn, Exception $e)
    {
        if (!$e instanceof HttpException) {
            return;
        }

        $responseData = json_encode([
            'error' => $e->getMessage(),
        ]);

        $response = new Response($e->getStatusCode(), [
            'Content-Type' => 'application/json',
            'Content-Length' => strlen($responseData),
        ], $responseData);

        $conn->send(Message::toString($response));

        $conn->close();
    }

    abstract public function __invoke(Request $request);
}
