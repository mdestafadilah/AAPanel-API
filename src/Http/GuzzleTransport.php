<?php

declare(strict_types=1);

namespace MythicalSystems\AaPanelApi\Http;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use MythicalSystems\AaPanelApi\AaPanelException;

/**
 * Optional transport backed by guzzlehttp/guzzle.
 *
 * Not a hard dependency: pass an instance to AaPanel::setTransport() when the
 * project already ships Guzzle and you want its middleware (retries, logging,
 * proxies) in front of the panel calls.
 */
class GuzzleTransport implements TransportInterface
{
    private ClientInterface $client;

    public function __construct(?ClientInterface $client = null)
    {
        if (!class_exists(Client::class)) {
            throw new AaPanelException('guzzlehttp/guzzle is not installed. Run: composer require guzzlehttp/guzzle');
        }

        $this->client = $client ?? new Client();
    }

    public function post(string $url, array $fields, float $timeout = 30.0, bool $verifySsl = true): string
    {
        $multipart = [];
        foreach ($fields as $name => $value) {
            $multipart[] = ['name' => (string) $name, 'contents' => (string) $value];
        }

        try {
            $response = $this->client->request('POST', $url, [
                'multipart' => $multipart,
                'timeout' => $timeout,
                'verify' => $verifySsl,
                'http_errors' => false,
                'headers' => ['Accept' => 'application/json'],
            ]);
        } catch (GuzzleException $e) {
            throw new AaPanelException('Guzzle request failed: ' . $e->getMessage(), -1, null, $e);
        }

        $status = $response->getStatusCode();
        $body = (string) $response->getBody();

        if ($status >= 400) {
            throw new AaPanelException(
                sprintf('aaPanel answered HTTP %d: %s', $status, substr(trim(strip_tags($body)), 0, 200)),
                -1,
                $body
            );
        }

        return $body;
    }
}
