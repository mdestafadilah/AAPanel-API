<?php

declare(strict_types=1);

namespace MythicalSystems\AaPanelApi;

use InvalidArgumentException;

/**
 * Init scripts, commands and paths documented on
 * https://www.aapanel.com/docs/faq/Service_Manage_Commands.html
 *
 * This class never talks to the panel. It answers "what is the documented
 * command / path for service X", which is what you need when the API cannot be
 * used at all (SSH session, cron script, runbook) and when you want to log
 * where a service keeps its configuration.
 *
 * To *act* on a service through the API, use the serviceStart() /
 * serviceRestart() family from ServiceTrait — the panel then runs exactly the
 * init script listed here.
 *
 * The panel only manages software it installed itself. Nginx, MySQL, PHP and
 * Redis installed with apt/yum live somewhere else and are not covered by
 * these paths.
 */
final class ServiceCatalog
{
    /** Placeholder replaced by the PHP version, e.g. 82. */
    private const VERSION = '{version}';

    /**
     * Services with a fixed name. "actions" holds the commands the official
     * page documents for that service — nothing is inferred.
     *
     * @var array<string, array<string, mixed>>
     */
    private const SERVICES = [
        'nginx' => [
            'label' => 'Nginx',
            'init' => '/etc/init.d/nginx',
            'actions' => ['start', 'stop', 'restart', 'reload', 'status', 'configtest'],
            'vhost' => '/www/server/panel/vhost/nginx',
            'config_dir' => '/www/server/nginx/conf/',
            'config_file' => '/www/server/nginx/conf/nginx.conf',
            'program' => '/www/server/nginx',
            'log' => '/www/wwwlogs/access.log',
            'error_log' => '/www/wwwlogs/nginx_error.log',
        ],
        'mysqld' => [
            'label' => 'MySQL / MariaDB',
            'init' => '/etc/init.d/mysqld',
            'actions' => ['start', 'stop', 'restart', 'status'],
            'config_file' => '/etc/my.cnf',
            'program' => '/www/server/mysql',
            'data_dir' => '/www/server/data',
            'log' => '/www/server/data/*.err',
            'log_command' => 'cat /www/server/data/*.err|grep -i -C 10 error',
        ],
        'redis' => [
            'label' => 'Redis',
            'init' => '/etc/init.d/redis',
            'actions' => ['start', 'stop', 'restart', 'reload', 'status'],
            'config_file' => '/www/server/redis/redis.conf',
            'program' => '/www/server/redis',
            'log' => '/www/server/redis/redis.log',
        ],
        'httpd' => [
            'label' => 'Apache',
            'init' => '/etc/init.d/httpd',
            'actions' => ['start', 'stop', 'restart', 'status'],
            'vhost' => '/www/server/panel/vhost/apache',
            'config_dir' => '/www/server/apache/conf/',
            'program' => '/www/server/apache',
            'log' => '/www/wwwlogs/access_log',
            'error_log' => '/www/wwwlogs/error_log',
        ],
        'openlitespeed' => [
            'label' => 'OpenLiteSpeed',
            'init' => '/usr/local/lsws/bin/lswsctrl',
            'actions' => ['start', 'stop', 'restart', 'status'],
            'vhost' => '/www/server/panel/vhost/openlitespeed',
            'config_dir' => '/usr/local/lsws/conf/',
            'program' => '/usr/local/lsws',
        ],
    ];

    /**
     * Services whose name carries the PHP version. {version} is the version
     * without dots, as the panel names them: 82, not 8.2.
     *
     * @var array<string, array<string, mixed>>
     */
    private const TEMPLATES = [
        'php-fpm' => [
            'label' => 'PHP-FPM',
            'init' => '/etc/init.d/php-fpm-{version}',
            'actions' => ['start', 'stop', 'restart', 'reload', 'status', 'configtest'],
            'config_dir' => '/www/server/php/{version}/etc/',
            'program' => '/www/server/php/{version}/',
            'log' => '/www/server/php/{version}/var/log/php-fpm.log',
        ],
        'lsphp' => [
            'label' => 'LiteSpeed PHP (lsphp)',
            'actions' => [],
            'config_dir' => '/usr/local/lsws/lsphp{version}/etc',
            'program' => '/usr/local/lsws/lsphp{version}/',
        ],
    ];

    /**
     * Other names the same service is known by, in the docs and in the panel.
     *
     * @var array<string, string>
     */
    private const ALIASES = [
        'openresty' => 'nginx',
        'tengine' => 'nginx',
        'apache' => 'httpd',
        'mysql' => 'mysqld',
        'mariadb' => 'mysqld',
        'mariadbd' => 'mysqld',
    ];

    /**
     * Base names, as documented. php-fpm and lsphp need a version suffix
     * ("php-fpm-82", "lsphp74") and are therefore not listed here.
     *
     * @return array<int, string>
     */
    public static function names(): array
    {
        return array_keys(self::SERVICES);
    }

    /**
     * Documented actions of one service, e.g. ["start", "stop", "restart"].
     *
     * MySQL and Apache have no documented reload; lsphp has no documented
     * command at all (it is managed through OpenLiteSpeed).
     *
     * @return array<int, string>
     */
    public static function actions(string $service): array
    {
        $entry = self::get($service);

        return $entry['actions'];
    }

    /**
     * The documented command for one action:
     * ServiceCatalog::command('nginx', 'reload') === '/etc/init.d/nginx reload'.
     *
     * @throws InvalidArgumentException when the service is unknown or the action
     *                                  is not documented for it
     */
    public static function command(string $service, string $action = 'restart'): string
    {
        $entry = self::get($service);
        $action = strtolower(trim($action));

        if (!isset($entry['init'])) {
            throw new InvalidArgumentException(sprintf(
                '%s has no documented init script; the page only lists its program and config directory.',
                $entry['label']
            ));
        }

        if (!in_array($action, $entry['actions'], true)) {
            throw new InvalidArgumentException(sprintf(
                '%s has no documented "%s" command. Documented: %s.',
                $entry['label'],
                $action,
                implode(', ', $entry['actions'])
            ));
        }

        return $entry['init'] . ' ' . $action;
    }

    /**
     * One documented path of a service: "config_file", "config_dir", "vhost",
     * "program", "log", "error_log", "data_dir", "log_command".
     */
    public static function path(string $service, string $key): ?string
    {
        $entry = self::get($service);

        if (!isset($entry[$key]) || !is_string($entry[$key])) {
            return null;
        }

        return $entry[$key];
    }

    /**
     * Everything documented about one service.
     *
     * @return array<string, mixed> "service" is the name the panel API expects
     *
     * @throws InvalidArgumentException
     */
    public static function get(string $service): array
    {
        $name = strtolower(trim($service));

        if ($name === '') {
            throw new InvalidArgumentException('Service name is empty.');
        }

        if (isset(self::ALIASES[$name])) {
            $name = self::ALIASES[$name];
        }

        if (isset(self::SERVICES[$name])) {
            return ['service' => $name] + self::SERVICES[$name];
        }

        // php-fpm-82, php-fpm-8.2, php82, php-8.2
        if (preg_match('/^php(?:-fpm)?-?(\d+(?:\.\d+)?)$/', $name, $matches) === 1) {
            return self::fromTemplate('php-fpm', $matches[1]);
        }

        // lsphp74, lsphp-74, lsphp-7.4
        if (preg_match('/^lsphp-?(\d+(?:\.\d+)?)$/', $name, $matches) === 1) {
            return self::fromTemplate('lsphp', $matches[1]);
        }

        if ($name === 'php' || $name === 'php-fpm') {
            throw new InvalidArgumentException(
                'PHP needs a version: use "php-fpm-82" (no dot in the middle, as the panel names it).'
            );
        }

        throw new InvalidArgumentException(sprintf(
            'Unknown service "%s". Known: %s, php-fpm-<version>, lsphp<version>.',
            $service,
            implode(', ', self::names())
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private static function fromTemplate(string $template, string $version): array
    {
        $version = str_replace('.', '', $version);
        $entry = self::withVersion(self::TEMPLATES[$template], $version);

        $entry['service'] = $template === 'php-fpm' ? 'php-fpm-' . $version : 'lsphp' . $version;
        $entry['php_version'] = $version;

        return $entry;
    }

    /**
     * @param array<string, mixed> $entry
     *
     * @return array<string, mixed>
     */
    private static function withVersion(array $entry, string $version): array
    {
        foreach ($entry as $key => $value) {
            if (is_string($value)) {
                $entry[$key] = str_replace(self::VERSION, $version, $value);
            }
        }

        return $entry;
    }
}
