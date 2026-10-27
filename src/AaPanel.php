<?php

declare(strict_types=1);

namespace MythicalSystems\AaPanelApi;

use MythicalSystems\AaPanelApi\Http\CurlTransport;
use MythicalSystems\AaPanelApi\Http\TransportInterface;
use MythicalSystems\AaPanelApi\Traits\DatabaseTrait;
use MythicalSystems\AaPanelApi\Traits\DnsTrait;
use MythicalSystems\AaPanelApi\Traits\FileTrait;
use MythicalSystems\AaPanelApi\Traits\ServiceTrait;
use MythicalSystems\AaPanelApi\Traits\SslTrait;
use MythicalSystems\AaPanelApi\Traits\WebsiteTrait;

/**
 * aaPanel API client, aligned with the panel API v2 specification.
 *
 * What v2 changed compared to the v1 calls this library used to make:
 *
 *  1. Every endpoint moved under /v2 (e.g. /site?action=AddSite is now
 *     /v2/site?action=AddSite).
 *  2. request_time and request_token are *query string* parameters. v1 read
 *     them from the POST body.
 *  3. Answers are wrapped in {"status":0,"timestamp":...,"message":...}.
 *     status 0 is success; the payload lives in "message".
 *  4. Several contracts changed: AddSite gained is_create_default_file,
 *     DeleteSite dropped its ftp/database/path flags, SaveFileBody requires
 *     encoding, database access uses dataAccess + access.
 *
 * The token is md5(request_time . md5(api_key)). Both values must come from the
 * same timestamp, otherwise the panel rejects the call, so they are generated
 * together in sign().
 *
 * @author MythicalSystems
 */
class AaPanel
{
    use WebsiteTrait;
    use DatabaseTrait;
    use SslTrait;
    use FileTrait;
    use DnsTrait;
    use ServiceTrait;

    /** Prefix shared by every documented v2 endpoint. */
    public const API_PREFIX = '/v2';

    /** @var string|null Panel base URL, e.g. http://192.168.2.12:7800 */
    public ?string $url = null;

    /** @var string|null API key from Panel settings -> API */
    public ?string $key = null;

    /** @var float Request timeout in seconds */
    protected float $timeout = 30.0;

    /** @var bool Verify the panel TLS certificate; set false only for self-signed panels */
    protected bool $verifySsl = true;

    protected TransportInterface $transport;

    public function __construct(?string $url = null, ?string $key = null, ?TransportInterface $transport = null)
    {
        if ($url !== null) {
            $this->url = rtrim($url, '/');
        }
        if ($key !== null) {
            $this->key = $key;
        }

        $this->transport = $transport ?? new CurlTransport();
    }

    public function setUrl(string $url): self
    {
        $this->url = rtrim($url, '/');

        return $this;
    }

    public function setKey(string $key): self
    {
        $this->key = $key;

        return $this;
    }

    public function setTimeout(float $seconds): self
    {
        $this->timeout = $seconds;

        return $this;
    }

    /**
     * Self-signed panels need this off. Default is on, which is the safe value.
     */
    public function setVerifySsl(bool $verify): self
    {
        $this->verifySsl = $verify;

        return $this;
    }

    public function setTransport(TransportInterface $transport): self
    {
        $this->transport = $transport;

        return $this;
    }

    /**
     * Call any panel endpoint, including ones this library does not wrap yet.
     *
     * @param string               $endpoint    Path after /v2, e.g. "site" or "database"
     * @param array<string, mixed> $query       Extra query parameters (action, table, ...)
     * @param array<string, mixed> $body        Form fields; arrays are JSON encoded, bools become "1"/"0"
     * @param bool                 $rawEnvelope Return the full status/message envelope instead of "message"
     *
     * @return mixed
     *
     * @throws AaPanelException
     */
    public function request(string $endpoint, array $query = [], array $body = [], bool $rawEnvelope = false)
    {
        if ($this->url === null || $this->url === '') {
            throw new AaPanelException('aaPanel URL is not configured.');
        }
        if ($this->key === null || $this->key === '') {
            throw new AaPanelException('aaPanel API key is not configured.');
        }

        $url = rtrim($this->url, '/')
            . self::API_PREFIX
            . '/'
            . ltrim($endpoint, '/')
            . '?'
            . http_build_query($this->sign($query), '', '&', PHP_QUERY_RFC3986);

        $response = $this->transport->post($url, $this->normaliseFields($body), $this->timeout, $this->verifySsl);

        return $this->decodeResponse($response, $rawEnvelope);
    }

    /**
     * Build the signed query string parameters.
     *
     * The timestamp is captured once on purpose: computing time() twice (as the
     * previous implementation did) can straddle a second boundary and produce a
     * token that does not match request_time.
     *
     * @param array<string, mixed> $query
     *
     * @return array<string, string>
     */
    protected function sign(array $query): array
    {
        $requestTime = time();

        $query['request_time'] = (string) $requestTime;
        $query['request_token'] = md5($requestTime . md5((string) $this->key));

        return $query;
    }

    /**
     * Reduce the body to the flat string map a multipart request needs.
     *
     * Arrays (ids, tables, domains) are JSON encoded because the panel parses
     * them with json_decode, not as PHP array syntax.
     *
     * @param array<string, mixed> $body
     *
     * @return array<string, string>
     */
    protected function normaliseFields(array $body): array
    {
        $fields = [];

        foreach ($body as $name => $value) {
            if ($value === null) {
                continue;
            }

            if (is_bool($value)) {
                $value = $value ? '1' : '0';
            } elseif (is_array($value)) {
                $value = (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }

            $fields[(string) $name] = (string) $value;
        }

        return $fields;
    }

    /**
     * @return mixed
     *
     * @throws AaPanelException
     */
    protected function decodeResponse(string $response, bool $rawEnvelope)
    {
        $response = trim($response);

        if ($response === '') {
            throw new AaPanelException('aaPanel returned an empty response.');
        }

        $decoded = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new AaPanelException(
                'aaPanel returned a non-JSON response. Check the URL, that the API is enabled and that this IP is whitelisted. Body: '
                . substr(trim(strip_tags($response)), 0, 200),
                -1,
                $response
            );
        }

        // A few endpoints answer with a bare JSON value instead of the usual
        // envelope (for example /v2/data?action=getKey returns the raw value).
        if (!is_array($decoded) || !array_key_exists('status', $decoded)) {
            return $decoded;
        }

        $status = (int) $decoded['status'];

        if ($status !== 0) {
            throw new AaPanelException($this->describeError($decoded), $status, $response);
        }

        return $rawEnvelope ? $decoded : ($decoded['message'] ?? null);
    }

    /**
     * @param array<string, mixed> $decoded
     */
    protected function describeError(array $decoded): string
    {
        $message = $decoded['message'] ?? null;

        if (is_array($message)) {
            foreach (['result', 'msg', 'error', 'message'] as $candidate) {
                if (isset($message[$candidate]) && is_scalar($message[$candidate])) {
                    return (string) $message[$candidate];
                }
            }

            $encoded = json_encode($message, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            return $encoded !== false ? $encoded : 'aaPanel reported an error.';
        }

        if (is_scalar($message)) {
            return (string) $message;
        }

        return 'aaPanel reported an error.';
    }

    /**
     * Generic reader for /v2/data?action=getData.
     *
     * The specification puts "table" in the query string for some tables and in
     * the body for others; the panel merges $_REQUEST, so sending it in both
     * places keeps every documented variant working.
     *
     * @param array<string, mixed> $params Body fields (p, limit, search, type, ...)
     * @param array<string, mixed> $query  Extra query fields
     *
     * @return mixed
     */
    public function getData(string $table, array $params = [], array $query = [])
    {
        return $this->request(
            'data',
            array_merge(['action' => 'getData', 'table' => $table], $query),
            array_merge(['table' => $table], $params)
        );
    }

    /**
     * Read a single column of one row: /v2/data?action=getKey.
     *
     * @param string|int|null $id
     *
     * @return mixed The "result" wrapper, or a bare scalar for config keys
     */
    public function getKey(string $table, string $key, $id = null)
    {
        $body = ['table' => $table, 'key' => $key];

        if ($id !== null) {
            $body['id'] = $id;
        }

        return $this->request('data', ['action' => 'getKey'], $body);
    }

    /**
     * Panel operation log.
     *
     * @return mixed
     */
    public function logs(int $limit = 10, int $page = 1)
    {
        return $this->getData('logs', ['limit' => $limit, 'p' => $page]);
    }
}
