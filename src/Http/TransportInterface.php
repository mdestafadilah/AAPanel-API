<?php

declare(strict_types=1);

namespace MythicalSystems\AaPanelApi\Http;

use MythicalSystems\AaPanelApi\AaPanelException;

/**
 * Minimal HTTP contract used by the client.
 *
 * aaPanel expects POST requests whose body is multipart/form-data, so the
 * transport receives a flat array of string fields and is responsible for
 * sending it as form data (not as urlencoded, not as JSON).
 */
interface TransportInterface
{
    /**
     * Perform the POST and return the raw response body.
     *
     * @param string                $url     Absolute URL including the signed query string
     * @param array<string, string> $fields  Flat form fields, already normalised to strings
     * @param float                 $timeout Timeout in seconds
     * @param bool                  $verifySsl Verify the panel certificate (false for self-signed panels)
     *
     * @throws AaPanelException On connection failure or HTTP status >= 400
     */
    public function post(string $url, array $fields, float $timeout = 30.0, bool $verifySsl = true): string;
}
