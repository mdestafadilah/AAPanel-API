<?php

/**
 * Database workflow: list, create, inspect, back up and delete a MySQL database.
 *
 * Run it against a test panel only - step 3 creates a real database.
 */

require __DIR__ . '/../vendor/autoload.php';

use MythicalSystems\AaPanelApi\AaPanel;
use MythicalSystems\AaPanelApi\AaPanelException;

$aapanel = new AaPanel('http://192.168.2.12:7800', 'z6jcyJFMRgJCWNdyHelgi5ilrCbsHO19');

try {
    // 1. MySQL must be running before anything else makes sense.
    $mysql = $aapanel->mysqlStatus();
    echo 'MySQL ' . ($mysql['version'] ?? '?') . ' is ' . (($mysql['status'] ?? false) ? 'running' : 'stopped') . PHP_EOL;

    // 2. Existing databases (page 1, 20 rows).
    $list = $aapanel->databaseList(1, 20);
    echo 'Databases: ' . count($list['data']) . PHP_EOL;

    // 3. Create one.
    $aapanel->addDatabase('demo_db', 'demo_db', 'S3cret-Passw0rd', '127.0.0.1', 'utf8mb4', 0, 'created by the PHP client');
    echo 'Created demo_db' . PHP_EOL;

    // 4. Read its tables and access rules.
    $info = $aapanel->databaseInfo('demo_db');
    echo 'Tables: ' . count($info['tables']) . ', size: ' . $info['data_size'] . PHP_EOL;
    echo 'Access: ' . ($aapanel->getDatabaseAccess('demo_db')['permission'] ?? 'unknown') . PHP_EOL;

    // 5. Back up, then list the backup files of that database.
    $list = $aapanel->databaseList(1, 20, 'demo_db');
    $id = (int) $list['data'][0]['id'];

    $aapanel->backupDatabase($id);
    foreach ($aapanel->backupList($id)['data'] as $backup) {
        echo 'Backup: ' . $backup['name'] . ' (' . $backup['addtime'] . ')' . PHP_EOL;
    }

    // 6. Delete. With the recycle bin enabled this only moves the database there.
    $aapanel->deleteDatabase($id, 'demo_db');
    echo 'Deleted demo_db' . PHP_EOL;
} catch (AaPanelException $e) {
    fwrite(STDERR, 'aaPanel error: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
