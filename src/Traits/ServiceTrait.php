<?php

declare(strict_types=1);

namespace MythicalSystems\AaPanelApi\Traits;

use InvalidArgumentException;
use MythicalSystems\AaPanelApi\ServiceCatalog;

/**
 * Service management: start, stop, restart, reload and config-test the software
 * aaPanel installed (Nginx, MySQL/MariaDB, PHP-FPM, Redis, Apache, OpenLiteSpeed).
 *
 * The panel exposes this as /v2/system?action=ServiceAdmin with two fields:
 * "name" (the init script name, e.g. nginx or php-fpm-82) and "type" (the
 * action). Internally the panel runs "/etc/init.d/<name> <type>", i.e. exactly
 * the commands listed on
 * https://www.aapanel.com/docs/faq/Service_Manage_Commands.html
 *
 * Consequences worth knowing before you call this:
 *
 *  - Only software installed by aaPanel is managed. Nginx/MySQL/PHP installed
 *    with apt/yum is not covered, and the panel reports "service failed to
 *    start" rather than doing something clever.
 *  - Nginx and Apache run a configuration test first and refuse to start when
 *    the config is broken; the panel then throws with the test output in the
 *    exception message.
 *  - MySQL has no "reload" init target; use restartService().
 *
 * See ServiceCatalog for the documented commands and paths of each service.
 */
trait ServiceTrait
{
    /**
     * Restart a service: /v2/system?action=ServiceAdmin (type=restart)
     *
     * @param string $service Init script name, e.g. nginx, mysqld, php-fpm-82
     *
     * @return mixed
     */
    public function restartService(string $service)
    {
        return $this->serviceAction($service, 'restart');
    }

    /**
     * Start a service. For Nginx and Apache the panel validates the config
     * first and refuses to start when it is broken.
     *
     * @return mixed
     */
    public function startService(string $service)
    {
        return $this->serviceAction($service, 'start');
    }

    /**
     * Stop a service. The panel waits ~2.5s and reports an error if the process
     * is still there.
     *
     * @return mixed
     */
    public function stopService(string $service)
    {
        return $this->serviceAction($service, 'stop');
    }

    /**
     * Reload a service without dropping connections.
     *
     * Not available for MySQL or Apache: their init scripts have no reload
     * target, so the panel would run an unknown command and still report
     * success. Those two — and OpenLiteSpeed, which turns any unknown action
     * into a restart — are rejected here instead.
     *
     * @return mixed
     *
     * @throws InvalidArgumentException when the service has no documented reload
     */
    public function reloadService(string $service)
    {
        $this->assertDocumentedAction($service, 'reload', 'restartService()');

        return $this->serviceAction($service, 'reload');
    }

    /**
     * Configuration test without restarting anything.
     *
     * The documented shell command is "configtest", but the panel's own action
     * name for it is "test" (it runs /etc/init.d/<service> test and skips the
     * operation log entry), which is what this sends.
     *
     * Documented for Nginx and PHP-FPM only; the other services are rejected.
     *
     * @return mixed
     *
     * @throws InvalidArgumentException when the service has no documented config test
     */
    public function serviceConfigTest(string $service)
    {
        $this->assertDocumentedAction($service, 'configtest', 'restartService()');

        return $this->serviceAction($service, 'test');
    }

    /**
     * Service state through the same endpoint mysqlStatus() uses:
     * /v2/panel/public/get_soft_status?name=...
     *
     * @param string $name Software name as the App Store knows it: mysql, nginx,
     *                     redis, php-82. "mysqld" is mapped to "mysql".
     *
     * @return mixed status, version, setup, ...
     */
    public function serviceStatus(string $name)
    {
        $name = strtolower(trim($name));

        if ($name === 'mysqld' || $name === 'mariadb' || $name === 'mariadbd') {
            $name = 'mysql';
        }

        return $this->request('panel/public/get_soft_status', ['name' => $name]);
    }

    /**
     * Panel name of a PHP-FPM service: "8.2", "82" and "php-fpm-8.2" all
     * become "php-fpm-82" (the panel drops the dot).
     *
     * @throws InvalidArgumentException when the version is not numeric
     */
    public function phpFpmService(string $version): string
    {
        $version = strtolower(trim($version));
        $version = (string) preg_replace('/^php(?:-fpm)?-?/', '', $version);
        $version = str_replace('.', '', $version);

        if ($version === '' || !ctype_digit($version)) {
            throw new InvalidArgumentException(
                'PHP version must look like "82", "8.2" or "php-fpm-82"; got an unusable value.'
            );
        }

        return 'php-fpm-' . $version;
    }

    /**
     * Raw call: /v2/system?action=ServiceAdmin
     *
     * "status" runs the init script's status target, but the panel answers with
     * its own "Executed successfully!" and does not forward the output, so it
     * cannot tell a running service from a stopped one. Use serviceStatus()
     * when you need the actual state.
     *
     * @param string $service Init script name, e.g. nginx
     * @param string $action  start, stop, restart, reload, test or status
     *
     * @return mixed
     *
     * @throws InvalidArgumentException for an action name this library does not send
     */
    public function serviceAction(string $service, string $action)
    {
        $service = trim($service);
        $action = strtolower(trim($action));

        if ($service === '') {
            throw new InvalidArgumentException('Service name is empty.');
        }

        if (!in_array($action, $this->serviceActionNames(), true)) {
            throw new InvalidArgumentException(sprintf(
                'Unsupported service action "%s". Use one of: %s.',
                $action,
                implode(', ', $this->serviceActionNames())
            ));
        }

        return $this->request('system', ['action' => 'ServiceAdmin'], [
            'name' => $service,
            'type' => $action,
        ]);
    }

    /**
     * Refuse an action the official page does not document for this service.
     *
     * Unknown names pass through: the catalog only knows the software the page
     * lists, and for anything else the panel is the authority.
     */
    private function assertDocumentedAction(string $service, string $documented, string $alternative): void
    {
        try {
            $entry = ServiceCatalog::get($service);
        } catch (InvalidArgumentException $e) {
            return;
        }

        $actions = $entry['actions'];

        if ($actions === [] || in_array($documented, $actions, true)) {
            return;
        }

        throw new InvalidArgumentException(sprintf(
            '%s has no documented "%s" command (documented: %s); use %s instead.',
            $entry['label'],
            $documented,
            implode(', ', $actions),
            $alternative
        ));
    }

    /**
     * Kept as a method instead of a trait constant: constants in traits are
     * only allowed from PHP 8.2 and this package still supports 7.4.
     *
     * @return array<int, string>
     */
    private function serviceActionNames(): array
    {
        return ['start', 'stop', 'restart', 'reload', 'test', 'status'];
    }
}
