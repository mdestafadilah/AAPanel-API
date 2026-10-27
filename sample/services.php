<?php

/**
 * Service management: restart a service through the API, and print the
 * documented commands/paths for the services this panel runs.
 *
 * Background: https://www.aapanel.com/docs/faq/Service_Manage_Commands.html
 *
 * The API call and the shell command hit the same init script. Use the API when
 * you only have the panel URL + key; use ServiceCatalog when you are on the box
 * (SSH, cron, runbook) and need the exact path.
 */

require __DIR__ . '/../vendor/autoload.php';

use MythicalSystems\AaPanelApi\AaPanel;
use MythicalSystems\AaPanelApi\AaPanelException;
use MythicalSystems\AaPanelApi\ServiceCatalog;

$aapanel = new AaPanel('http://192.168.2.12:7800', 'z6jcyJFMRgJCWNdyHelgi5ilrCbsHO19');

// What the panel will run, before running it:
echo ServiceCatalog::command('nginx', 'restart') . PHP_EOL;          // /etc/init.d/nginx restart
echo ServiceCatalog::command('php-fpm-8.2', 'reload') . PHP_EOL;     // /etc/init.d/php-fpm-82 reload

try {
    // Same as "/etc/init.d/nginx restart", executed by the panel.
    $aapanel->restartService('nginx');
    echo 'nginx restarted' . PHP_EOL;

    // PHP-FPM: the panel names it php-fpm-82, "8.2" is accepted here.
    $aapanel->reloadService($aapanel->phpFpmService('8.2'));

    // MySQL has no reload target; restart is the documented way.
    $aapanel->restartService('mysqld');

    // State, not an action.
    $status = $aapanel->serviceStatus('nginx');
    echo 'nginx status: ' . json_encode($status) . PHP_EOL;
} catch (AaPanelException $e) {
    // A broken nginx config lands here with the "nginx -t" output attached.
    fwrite(STDERR, 'aaPanel error: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

// Where each service keeps its files, as documented:
foreach (ServiceCatalog::names() as $name) {
    printf(
        "%-14s %-34s %s\n",
        $name,
        ServiceCatalog::path($name, 'program') ?? '-',
        implode('|', ServiceCatalog::actions($name))
    );
}
