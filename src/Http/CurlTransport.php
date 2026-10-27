<?php

declare(strict_types=1);

namespace MythicalSystems\AaPanelApi\Http;

use MythicalSystems\AaPanelApi\AaPanelException;

/**
 * Default transport: plain cURL, no third party dependency.
 *
 * Deliberately does not use a cookie jar. API key authentication is stateless,
 * and the previous implementation wrote a .cookie file into the current working
 * directory for every panel URL it talked to.
 */
class CurlTransport implements TransportInterface
{
    public function post(string $url, array $fields, float $timeout = 30.0, bool $verifySsl = true): string
    {
        if (!function_exists('curl_init')) {
            throw new AaPanelException('The cURL extension is required. Install ext-curl or use GuzzleTransport.');
        }

        $ch = curl_init();
        if ($ch === false) {
            throw new AaPanelException('Unable to initialise cURL.');
        }

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            // An array body makes cURL send multipart/form-data, which is what
            // every documented aaPanel endpoint expects.
            CURLOPT_POSTFIELDS => $fields,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT_MS => (int) round($timeout * 1000),
            CURLOPT_CONNECTTIMEOUT => max(1, (int) ceil($timeout)),
            CURLOPT_SSL_VERIFYPEER => $verifySsl,
            CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);

        $body = curl_exec($ch);

        if ($body === false) {
            $error = curl_error($ch);
            $number = curl_errno($ch);
            curl_close($ch);

            throw new AaPanelException(sprintf('cURL error %d: %s', $number, $error));
        }

        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status >= 400) {
            throw new AaPanelException(
                sprintf('aaPanel answered HTTP %d: %s', $status, substr(trim(strip_tags($body)), 0, 200)),
                -1,
                $body
            );
        }

        return (string) $body;
    }
}
