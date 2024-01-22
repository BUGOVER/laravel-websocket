<?php

declare(strict_types=1);

namespace BeyondCode\LaravelWebSockets\Manager;

use BeyondCode\LaravelWebSockets\Guzzle;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use JetBrains\PhpStorm\ArrayShape;
use JsonException;
use Pusher\ApiErrorException;
use Pusher\Pusher;
use Pusher\PusherCrypto;
use Pusher\PusherException;

use function array_key_exists;
use function extension_loaded;
use function in_array;

class PusherInstance extends Pusher
{
    /**
     * @var array Settings
     */
    public $settings = [
        'scheme' => 'http',
        'port' => 80,
        'path' => '',
        'timeout' => 30,
    ];
    /**
     * @var ClientInterface|Client|null
     */
    public ClientInterface|Client|null $client = null;

    /**
     * Initializes a new Pusher instance with key, secret, app ID and channel.
     *
     * @param string $auth_key
     * @param string $secret
     * @param string $app_id
     * @param array $options [optional]
     *                         Options to configure the Pusher instance.
     *                         scheme - e.g. http or https
     *                         host - the host e.g. api-mt1.pusher.com. No trailing forward slash.
     *                         port - the http port
     *                         timeout - the http timeout
     *                         useTLS - quick option to use scheme of https and port 443 (default is true).
     *                         cluster - cluster name to connect to.
     *                         encryption_master_key_base64 - a 32 byte key, encoded as base64. This key, along with the channel name, are used to derive per-channel encryption keys. Per-channel keys are used to encrypt event data on encrypted channels.
     * @param ClientInterface|null $client [optional] - a Guzzle client to use for all HTTP requests
     *
     * @throws PusherException Throws exception if any required dependencies are missing
     */
    public function __construct(
        string $auth_key,
        string $secret,
        string $app_id,
        array $options = [],
        ClientInterface $client = null
    ) {
        $this->check_compatibility();

        if (null !== $client) {
            $this->client = $client;
        } else {
            $this->client = new Guzzle();
        }

        $use_tls = true;

        if (isset($options['useTLS'])) {
            $use_tls = true === $options['useTLS'];
        }

        if ($use_tls && !isset($options['scheme']) && !isset($options['port'])) {
            $options['scheme'] = 'https';
            $options['port'] = 443;
        }

        $this->settings['auth_key'] = $auth_key;
        $this->settings['secret'] = $secret;
        $this->settings['app_id'] = $app_id;
        $this->settings['base_path'] = '/apps/' . $this->settings['app_id'];

        foreach ($options as $key => $value) {
            if (isset($this->settings[$key])) {
                $this->settings[$key] = $value;
            }
        }

        if (!array_key_exists('host', $this->settings)) {
            if (array_key_exists('host', $options)) {
                $this->settings['host'] = $options['host'];
            } elseif (array_key_exists('cluster', $options)) {
                $this->settings['host'] = 'api-' . $options['cluster'] . '.pusher.com';
            } else {
                $this->settings['host'] = 'api-mt1.pusher.com';
            }
        }

        // ensure host doesn't have a scheme prefix
        $this->settings['host'] = preg_replace('/http[s]?\:\/\//', '', $this->settings['host'], 1);

        if (!array_key_exists('encryption_master_key_base64', $options)) {
            $options['encryption_master_key_base64'] = '';
        }

        if ('' !== $options['encryption_master_key_base64']) {
            $parsedKey = PusherCrypto::parse_master_key($options['encryption_master_key_base64']);
            $this->crypto = new PusherCrypto($parsedKey);
        }

        parent::__construct($auth_key, $secret, $app_id, $options, $this->client);
    }

    /**
     * Check if the current PHP setup is sufficient to run this class.
     *
     * @throws PusherException If any required dependencies are missing
     */
    private function check_compatibility(): void
    {
        if (!extension_loaded('json')) {
            throw new PusherException('The Pusher library requires the PHP JSON module. Please ensure it is installed');
        }

        if (!in_array('sha256', hash_algos(), true)) {
            throw new PusherException(
                'SHA256 appears to be unsupported - make sure you have support for it, or upgrade your version of PHP.'
            );
        }
    }

    /**
     * Trigger an event by providing event name and payload.
     * Optionally provide a socket ID to exclude a client (most likely the sender).
     *
     * @param array|string $channels A channel name or an array of channel names to publish the event on.
     * @param string $event
     * @param mixed $data Event data
     * @param array $params [optional]
     * @param bool $already_encoded [optional]
     *
     * @return object
     * @throws ApiErrorException Throws ApiErrorException if the Channels HTTP API responds with an error
     * @throws PusherException Throws PusherException if $channels is an array of size 101 or above or $socket_id is invalid
     * @throws GuzzleException
     */
    public function trigger($channels, string $event, $data, array $params = [], bool $already_encoded = false): object
    {
        $request = $this->make_request($channels, $event, $data, $params, $already_encoded);

        try {
            $response = $this->client->send($request, $this->curl_options());
        } catch (ConnectException $e) {
            throw new ApiErrorException($e->getMessage());
        }

        $status = $response->getStatusCode();

        if (200 !== $status) {
            $body = (string)$response->getBody();
            throw new ApiErrorException($body, $status);
        }

        try {
            $result = json_decode((string)$response->getBody(), false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new PusherException('Data encoding error.');
        }

        if ($result && property_exists($result, 'channels')) {
            $result->channels = get_object_vars($result->channels);
        }

        return $result;
    }

    /**
     * @return array
     */
    #[ArrayShape([
        'http_errors' => 'false',
        'base_uri' => 'string',
        'verify' => 'false',
        'curl' => 'array',
    ])]
    private function curl_options(): array
    {
        return [
            'base_uri' => $this->channels_url_prefix(),
            'http_errors' => false,
            'verify' => false,
        ];
    }

    /**
     * Build the Channels url prefix.
     *
     * @return string
     */
    private function channels_url_prefix(): string
    {
        return $this->settings['scheme'] . '://' . $this->settings['host'] . ':' . $this->settings['port'] . $this->settings['path'];
    }
}
