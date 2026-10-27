<?php

/**
 * List PHP projects on the panel.
 *
 * The v2 API signs every request with request_time + request_token in the query
 * string, so the only thing a caller has to provide is the panel URL and key.
 */

require __DIR__ . '/../vendor/autoload.php';

use MythicalSystems\AaPanelApi\AaPanel;
use MythicalSystems\AaPanelApi\AaPanelException;

$aapanel = new AaPanel('http://192.168.2.12:7800', 'z6jcyJFMRgJCWNdyHelgi5ilrCbsHO19');

// Self-signed panel certificate? Uncomment:
// $aapanel->setVerifySsl(false);

try {
    $result = $aapanel->siteList(10, 1);

    foreach ($result['data'] as $site) {
        printf("%-4d %-30s %-28s %s\n", $site['id'], $site['name'], $site['path'], $site['status'] === '1' ? 'running' : 'stopped');
    }

    echo PHP_EOL . 'Total: ' . count($result['data']) . PHP_EOL;
} catch (AaPanelException $e) {
    fwrite(STDERR, 'aaPanel error: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
