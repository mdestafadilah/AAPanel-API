<?php

declare(strict_types=1);

/**
 * Smoke test: verifies the request shape against the aaPanel v2 specification
 * without touching a real panel. Run with: php tests/smoke.php
 *
 * It covers what actually broke between v1 and v2: the /v2 prefix, the signed
 * query string, the status/message envelope and the field names of the calls
 * this library wraps.
 */

$autoload = __DIR__ . '/../vendor/autoload.php';

if (is_file($autoload)) {
    require $autoload;
} else {
    spl_autoload_register(static function (string $class): void {
        $prefix = 'MythicalSystems\\AaPanelApi\\';
        if (strpos($class, $prefix) !== 0) {
            return;
        }
        $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
        $file = __DIR__ . '/../src/' . $relative . '.php';
        if (is_file($file)) {
            require $file;
        }
    });
}

use MythicalSystems\AaPanelApi\AaPanel;
use MythicalSystems\AaPanelApi\AaPanelException;
use MythicalSystems\AaPanelApi\Http\TransportInterface;
use MythicalSystems\AaPanelApi\ServiceCatalog;

final class FakeTransport implements TransportInterface
{
    public string $url = '';
    /** @var array<string, string> */
    public array $fields = [];
    /** @var array<int, string> */
    public array $queue = [];

    public function post(string $url, array $fields, float $timeout = 30.0, bool $verifySsl = true): string
    {
        $this->url = $url;
        $this->fields = $fields;

        return array_shift($this->queue) ?? '{"status":0,"timestamp":0,"message":[]}';
    }

    /** @return array<string, string> */
    public function query(): array
    {
        $query = [];
        parse_str((string) parse_url($this->url, PHP_URL_QUERY), $query);

        return array_map('strval', $query);
    }

    public function path(): string
    {
        return (string) parse_url($this->url, PHP_URL_PATH);
    }
}

final class Check
{
    public static int $passed = 0;
    /** @var array<int, string> */
    public static array $failures = [];

    public static function that(string $label, bool $condition, string $detail = ''): void
    {
        if ($condition) {
            self::$passed++;
            echo "  ok   {$label}\n";

            return;
        }

        self::$failures[] = $label . ($detail !== '' ? ' -> ' . $detail : '');
        echo "  FAIL {$label}" . ($detail !== '' ? ' -> ' . $detail : '') . "\n";
    }

    public static function same(string $label, $expected, $actual): void
    {
        self::that(
            $label,
            $expected === $actual,
            'expected ' . var_export($expected, true) . ', got ' . var_export($actual, true)
        );
    }
}

const PANEL_URL = 'http://192.168.2.12:7800';
const API_KEY = 'z6jcyJFMRgJCWNdyHelgi5ilrCbsHO19';

function client(FakeTransport $transport): AaPanel
{
    return new AaPanel(PANEL_URL, API_KEY, $transport);
}

echo "aaPanel v2 client smoke test\n\n";

// ---------------------------------------------------------------- signing ---
echo "signing\n";

$transport = new FakeTransport();
client($transport)->siteList(10, 2, 'demo');

$query = $transport->query();
$token = md5($query['request_time'] . md5(API_KEY));

Check::that('request_time is a unix timestamp', ctype_digit($query['request_time']), $query['request_time']);
Check::same('request_token matches md5(request_time . md5(key))', $token, $query['request_token']);
Check::that(
    'auth parameters live in the query string',
    strpos($transport->url, 'request_token=') !== false && !isset($transport->fields['request_token'])
);

// ------------------------------------------------------------------- paths ---
echo "\npaths\n";

Check::same('base URL is prefixed with /v2', '/v2/data', $transport->path());
Check::same('action travels in the query string', 'getData', $query['action']);
Check::same('table travels in the query string', 'sites', $query['table']);
Check::same('table is also mirrored into the body', 'sites', $transport->fields['table']);
Check::same('page is sent as "p"', '2', $transport->fields['p']);
Check::same('limit is sent', '10', $transport->fields['limit']);
Check::same('default type is -1', '-1', $transport->fields['type']);

// ------------------------------------------------------------ body shaping ---
echo "\nbody shaping\n";

$transport = new FakeTransport();
client($transport)->addSite('test31.com', 'test31.com', 'demo', 0, 'PHP', '74', 80, 'ftpuser', 'ftppass', 'dbuser', 'dbpass');

$webname = json_decode($transport->fields['webname'], true);
Check::same('addSite hits /v2/site', '/v2/site', $transport->path());
Check::same('addSite action', 'AddSite', $transport->query()['action']);
Check::same('webname carries the domain', 'test31.com', $webname['domain']);
Check::same('relative path is expanded to /www/wwwroot', '/www/wwwroot/test31.com', $transport->fields['path']);
Check::same('ftp flag is a string boolean', '1', $transport->fields['ftp']);
Check::same('sql flag is a string boolean', '1', $transport->fields['sql']);
Check::same('is_create_default_file is present', '1', $transport->fields['is_create_default_file']);
Check::same('database encoding defaults to utf8', 'utf8', $transport->fields['codeing']);

$transport = new FakeTransport();
client($transport)->addSite('plain.com', '/custom/root');
Check::same('absolute path is kept', '/custom/root', $transport->fields['path']);
Check::same('no ftp account without credentials', '0', $transport->fields['ftp']);
Check::same('no database without credentials', '0', $transport->fields['sql']);
Check::that('ftp_username is omitted when unused', !isset($transport->fields['ftp_username']));

$transport = new FakeTransport();
client($transport)->checkDeleteSite([128, 129]);
Check::same('array ids are JSON encoded', '[128,129]', $transport->fields['ids']);

$transport = new FakeTransport();
client($transport)->deleteSite(128, 'test31.com');
Check::same('deleteSite sends only id and webname', ['id' => '128', 'webname' => 'test31.com'], $transport->fields);

// --------------------------------------------------------------- databases ---
echo "\ndatabases\n";

$transport = new FakeTransport();
client($transport)->databaseList(1, 20, 'demo');
$query = $transport->query();
Check::same('database list path', '/v2/data', $transport->path());
Check::same('database list table', 'databases', $query['table']);
Check::same('database list type', 'MySQL', $query['type']);

$transport = new FakeTransport();
client($transport)->addDatabase('test234', 'test234', 'secret', '%');
Check::same('addDatabase path', '/v2/database', $transport->path());
Check::same('addDatabase action', 'AddDatabase', $transport->query()['action']);
Check::same('access is mirrored into dataAccess', '%', $transport->fields['dataAccess']);
Check::same('access is mirrored into address', '%', $transport->fields['address']);
Check::same('dtype defaults to MySQL', 'MySQL', $transport->fields['dtype']);

$transport = new FakeTransport();
client($transport)->databaseQuota('test234', 12);
Check::same('quota path', '/v2/project/quota/modify_database_quota', $transport->path());
Check::same('quota payload', '{"size":"12","db_name":"test234"}', $transport->fields['data']);

$transport = new FakeTransport();
client($transport)->optimizeTables('test234', ['a', 'b']);
Check::same('optimize action', 'OpTable', $transport->query()['action']);
Check::same('table list is JSON encoded', '["a","b"]', $transport->fields['tables']);

$transport = new FakeTransport();
client($transport)->backupList(107, 1, 10);
Check::same('backup list uses search for the db id', '107', $transport->fields['search']);
Check::same('backup list type is 1', '1', $transport->fields['type']);

$transport = new FakeTransport();
client($transport)->mysqlStatus();
Check::same('soft status path', '/v2/panel/public/get_soft_status', $transport->path());
Check::same('soft status name', 'mysql', $transport->query()['name']);

// --------------------------------------------------------------------- ssl ---
echo "\nssl\n";

$transport = new FakeTransport();
client($transport)->deployCertificate('abc123', ['test31.com']);
Check::same('deploy path', '/v2/ssl_domain', $transport->path());
Check::same('deploy action', 'cert_deploy_sites', $transport->query()['action']);
Check::same('domains are JSON encoded', '["test31.com"]', $transport->fields['domains']);

$transport = new FakeTransport();
client($transport)->certificates();
Check::same('certificate list page parameter is capital P', '1', $transport->fields['P']);

// ------------------------------------------------------------------- files ---
echo "\nfiles\n";

$transport = new FakeTransport();
client($transport)->saveFileBody('/www/server/panel/vhost/rewrite/test31.com.conf', 'location / {}');
Check::same('saveFileBody path', '/v2/files', $transport->path());
Check::same('saveFileBody action', 'SaveFileBody', $transport->query()['action']);
Check::same('encoding is sent explicitly', 'utf-8', $transport->fields['encoding']);

$transport = new FakeTransport();
client($transport)->setRewrite('test31.com', 'location / {}');
Check::same(
    'setRewrite targets the nginx rewrite file',
    '/www/server/panel/vhost/rewrite/test31.com.conf',
    $transport->fields['path']
);

$transport = new FakeTransport();
client($transport)->subDomainList('example.com', 'www');
Check::same('dns manager path', '/v2/plugin', $transport->path());
Check::same('dns manager plugin name', 'dns_manager', $transport->query()['name']);

// ---------------------------------------------------------------- services ---
echo "\nservices\n";

$transport = new FakeTransport();
client($transport)->restartService('nginx');
Check::same('service admin path', '/v2/system', $transport->path());
Check::same('service admin action', 'ServiceAdmin', $transport->query()['action']);
Check::same('service name travels in the body', 'nginx', $transport->fields['name']);
Check::same('service action travels in the body', 'restart', $transport->fields['type']);

$transport = new FakeTransport();
client($transport)->stopService('mysqld');
Check::same('stopService sends type=stop', 'stop', $transport->fields['type']);

$transport = new FakeTransport();
client($transport)->reloadService('redis');
Check::same('reloadService sends type=reload', 'reload', $transport->fields['type']);

$transport = new FakeTransport();
client($transport)->serviceConfigTest('nginx');
Check::same('config test uses the panel action name "test"', 'test', $transport->fields['type']);

Check::same('phpFpmService accepts a dotted version', 'php-fpm-82', client(new FakeTransport())->phpFpmService('8.2'));
Check::same('phpFpmService accepts the panel form', 'php-fpm-82', client(new FakeTransport())->phpFpmService('php-fpm-82'));

$transport = new FakeTransport();
client($transport)->serviceStatus('mysqld');
Check::same('service status path', '/v2/panel/public/get_soft_status', $transport->path());
Check::same('mysqld is mapped to the mysql software name', 'mysql', $transport->query()['name']);

try {
    client(new FakeTransport())->serviceAction('nginx', 'explode');
    Check::that('unknown service action is rejected locally', false);
} catch (InvalidArgumentException $e) {
    Check::that('unknown service action is rejected locally', strpos($e->getMessage(), 'explode') !== false, $e->getMessage());
}

try {
    client(new FakeTransport())->reloadService('mysqld');
    Check::that('mysql reload is rejected before reaching the panel', false);
} catch (InvalidArgumentException $e) {
    Check::that(
        'mysql reload is rejected before reaching the panel',
        strpos($e->getMessage(), 'restartService') !== false,
        $e->getMessage()
    );
}

try {
    client(new FakeTransport())->serviceConfigTest('redis');
    Check::that('config test is rejected for services without one', false);
} catch (InvalidArgumentException $e) {
    Check::that(
        'config test is rejected for services without one',
        strpos($e->getMessage(), 'configtest') !== false,
        $e->getMessage()
    );
}

$transport = new FakeTransport();
client($transport)->reloadService('php-fpm-83');
Check::same('a PHP version the catalog does not list is passed through', 'reload', $transport->fields['type']);

// ----------------------------------------------------------------- catalog ---
echo "\nservice catalog\n";

Check::same('documented reload command', '/etc/init.d/nginx reload', ServiceCatalog::command('nginx', 'reload'));
Check::same('documented config test command', '/etc/init.d/nginx configtest', ServiceCatalog::command('nginx', 'configtest'));
Check::same('restart is the default command', '/etc/init.d/nginx restart', ServiceCatalog::command('nginx'));
Check::same('nginx main config path', '/www/server/nginx/conf/nginx.conf', ServiceCatalog::path('nginx', 'config_file'));
Check::same('openresty is an alias of nginx', '/etc/init.d/nginx', ServiceCatalog::path('openresty', 'init'));
Check::same('apache is an alias of httpd', '/etc/init.d/httpd', ServiceCatalog::path('apache', 'init'));
Check::same('php-fpm version is templated', '/etc/init.d/php-fpm-82 restart', ServiceCatalog::command('php-fpm-8.2', 'restart'));
Check::same('php-fpm config dir is templated', '/www/server/php/82/etc/', ServiceCatalog::path('php-fpm-82', 'config_dir'));
Check::same('lsphp keeps its version without a dash', '/usr/local/lsws/lsphp74/', ServiceCatalog::path('lsphp74', 'program'));
Check::same('mysql data directory', '/www/server/data', ServiceCatalog::path('mysqld', 'data_dir'));
Check::same('canonical service name is exposed', 'php-fpm-74', ServiceCatalog::get('php74')['service']);

try {
    ServiceCatalog::command('mysqld', 'reload');
    Check::that('undocumented mysql reload is rejected', false);
} catch (InvalidArgumentException $e) {
    Check::that('undocumented mysql reload is rejected', strpos($e->getMessage(), 'reload') !== false, $e->getMessage());
}

try {
    ServiceCatalog::command('lsphp74', 'restart');
    Check::that('lsphp has no init script', false);
} catch (InvalidArgumentException $e) {
    Check::that('lsphp has no init script', strpos($e->getMessage(), 'init script') !== false, $e->getMessage());
}

try {
    ServiceCatalog::get('php');
    Check::that('php without a version is rejected', false);
} catch (InvalidArgumentException $e) {
    Check::that('php without a version is rejected', strpos($e->getMessage(), 'version') !== false, $e->getMessage());
}

try {
    ServiceCatalog::get('apache2');
    Check::that('unknown service is rejected', false);
} catch (InvalidArgumentException $e) {
    Check::that('unknown service is rejected', strpos($e->getMessage(), 'Unknown service') !== false, $e->getMessage());
}

// ---------------------------------------------------------------- responses ---
echo "\nresponses\n";

$transport = new FakeTransport();
$transport->queue[] = '{"status":0,"timestamp":1715409141,"message":{"data":[{"id":38}]}}';
$result = client($transport)->siteList();
Check::same('status 0 returns the message payload', 38, $result['data'][0]['id']);

$transport = new FakeTransport();
$transport->queue[] = '{"status":0,"timestamp":1,"message":{"result":"Setup successfully!"}}';
Check::same('message.result is preserved', 'Setup successfully!', client($transport)->addDatabase('a', 'b', 'c')['result']);

$transport = new FakeTransport();
$transport->queue[] = '"98870d899a87303c"';
Check::same('bare JSON scalar is returned as-is', '98870d899a87303c', client($transport)->mysqlRootPassword());

$transport = new FakeTransport();
$transport->queue[] = '{"status":0,"timestamp":1,"message":{"result":"/www/wwwroot/demo"}}';
Check::same('sitePath extracts result', '/www/wwwroot/demo', client($transport)->sitePath(128));

$transport = new FakeTransport();
$transport->queue[] = '{"status":1,"timestamp":1,"message":"API key is invalid"}';
try {
    client($transport)->siteList();
    Check::that('panel error raises an exception', false);
} catch (AaPanelException $e) {
    Check::same('panel error message is surfaced', 'API key is invalid', $e->getMessage());
    Check::same('panel status is kept on the exception', 1, $e->getPanelStatus());
}

$transport = new FakeTransport();
$transport->queue[] = '{"status":1,"timestamp":1,"message":{"result":"Site does not exist"}}';
try {
    client($transport)->deleteSite(1, 'nope');
    Check::that('nested error message is surfaced', false);
} catch (AaPanelException $e) {
    Check::same('nested error message is surfaced', 'Site does not exist', $e->getMessage());
}

$transport = new FakeTransport();
$transport->queue[] = '<html><body>404 Not Found</body></html>';
try {
    client($transport)->siteList();
    Check::that('non-JSON response raises an exception', false);
} catch (AaPanelException $e) {
    Check::that('non-JSON response raises an exception', strpos($e->getMessage(), '404 Not Found') !== false, $e->getMessage());
    Check::same('transport level failures use status -1', -1, $e->getPanelStatus());
}

// ------------------------------------------------------------------ config ---
echo "\nconfig\n";

try {
    (new AaPanel(null, null, new FakeTransport()))->siteList();
    Check::that('missing URL raises an exception', false);
} catch (AaPanelException $e) {
    Check::that('missing URL raises an exception', strpos($e->getMessage(), 'URL') !== false, $e->getMessage());
}

$transport = new FakeTransport();
$panel = (new AaPanel())->setUrl(PANEL_URL . '/')->setKey(API_KEY)->setTransport($transport);
$panel->logs();
Check::same('trailing slash on the URL is normalised', '/v2/data', $transport->path());

echo "\n";

if (Check::$failures !== []) {
    echo count(Check::$failures) . " failed, " . Check::$passed . " passed\n";
    foreach (Check::$failures as $failure) {
        echo "  - {$failure}\n";
    }
    exit(1);
}

echo Check::$passed . " checks passed\n";
exit(0);
