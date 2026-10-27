# AAPanel-PHP

A small PHP client for the **aaPanel panel API v2**: websites (PHP projects), domains, SSL certificates, files, MySQL databases and system services (Nginx, MySQL, PHP-FPM, Redis, Apache).

No framework, no required third-party HTTP library — plain cURL by default, Guzzle optional.

> **Fork.** This is a maintained fork of [AAPanel-PHP](#original-source--credits), the aaPanel client started by [skolastikarhea](https://github.com/skolastikarhea) in 2022 and later developed by [NaysKutzu](https://github.com/nayskutzu) / MythicalSystems. Version 2.0.0 rewrites the request layer for panel API v2. See [credits](#original-source--credits) for the full lineage and licence holders.

```php
use MythicalSystems\AaPanelApi\AaPanel;

$aapanel = new AaPanel('http://1.2.3.4:7800', 'YOUR_API_KEY');

foreach ($aapanel->siteList()['data'] as $site) {
    echo $site['name'], PHP_EOL;
}
```

---

## Requirements

- PHP **7.4+** with `ext-curl`
- aaPanel with the API enabled
- The IP you call from added to the panel API whitelist

## Install

```bash
composer require mdestafadilah/aapanelapi
```

Optional, if you prefer Guzzle over the bundled cURL transport:

```bash
composer require guzzlehttp/guzzle
```

## Enabling the API in aaPanel

1. **Panel settings → API** → enable
2. Generate an API key
3. Add your public IP to the whitelist
4. Save

## Quick start

```php
use MythicalSystems\AaPanelApi\AaPanel;

$aapanel = new AaPanel('http://1.2.3.4:7800', 'YOUR_API_KEY');

// Self-signed panel certificate? Turn verification off explicitly.
$aapanel->setVerifySsl(false);

// Or configure step by step
$aapanel->setUrl('http://1.2.3.4:7800')
        ->setKey('YOUR_API_KEY')
        ->setTimeout(60);

// Swap the transport if the project already ships Guzzle
$aapanel->setTransport(new \MythicalSystems\AaPanelApi\Http\GuzzleTransport());
```

Every method returns the panel's `message` payload, or throws `AaPanelException`.

---

## What changed in aaPanel API v2

The v1 calls this library used to make no longer match the panel. Four things moved:

| | v1 (old library) | v2 (this version) |
|---|---|---|
| **Base path** | `/site?action=AddSite` | `/v2/site?action=AddSite` |
| **Auth** | `request_time` + `request_token` in the POST body | both in the **query string** |
| **Response** | raw payload | `{"status":0,"timestamp":…,"message":…}` |
| **Session** | cookie jar written to the working directory | stateless, no cookies |

The token formula is unchanged — `md5(request_time . md5(api_key))` — but both values must come from the *same* timestamp. The old code called `time()` twice, which could straddle a second boundary and produce a token the panel rejects. `AaPanel::sign()` now captures the timestamp once.

Per-endpoint contract changes worth knowing:

- **AddSite** — `type` is `PHP`, and `is_create_default_file` is a new required field. `ftp` and `sql` are booleans.
- **DeleteSite** — only `id` + `webname`; the old `ftp` / `database` / `path` flags are gone. Use `checkDeleteSite()` first to see what a deletion would remove.
- **SaveFileBody** — `encoding` is required.
- **AddDatabase** — access rules are sent as `dataAccess` *and* `address`.
- **SSL** — `apply_cert_api` + `SetSSL` were replaced by a certificate store: `uploadCertificate()` returns a hash, `deployCertificate()` pushes it to domains.
- **getData** — the spec puts `table` in the query for some tables (`domain`, `databases`) and in the body for others (`sites`, `backup`). The panel merges `$_REQUEST`, so the helpers send it in both places.

## Migrating from 1.x

```diff
- use mythicalsystems\aapanel\aapanel;
+ use MythicalSystems\AaPanelApi\AaPanel;

- $aapanel = new aapanel;
- $aapanel->key = 'YOUR_API_KEY';
- $aapanel->url = 'http://1.2.3.4:7800';
+ $aapanel = new AaPanel('http://1.2.3.4:7800', 'YOUR_API_KEY');
```

Renamed or changed methods:

| 1.x | 2.x |
|---|---|
| `siteList($limit, $page, $projectType, $search)` | `siteList($limit, $page, $search, $type)` — PHP projects only; `nodejsProjects()` and `pm2Projects()` are separate |
| `deleteSite($webname, $id)` | `deleteSite($id, $webname)` |
| `safeFileBody($data, $path)` | `saveFileBody($path, $data, $encoding = 'utf-8')` |
| `importDbase($file, $dbasename)` | `importSql($file, $dbName)` |
| `applySSL($domain, $id)` | `installCertificate($siteName, $key, $cert)` |
| `forceHTTPS($siteName)` | `forceHttps($siteName)` (deprecated) |
| `addSite(..., $ftp, $ftpUser, $ftpPass, $sql, $dbUser, $dbPass, ...)` | `addSite(...)` — FTP/database are created when you pass the credentials |

Return values are now the `message` payload instead of the whole envelope. Pass `true` as the fourth argument of `request()` when you want the envelope back.

---

## Method reference

### Client

| Method | Endpoint |
|---|---|
| `request($endpoint, $query, $body, $rawEnvelope)` | any `/v2/…` endpoint |
| `getData($table, $params, $query)` | `/v2/data?action=getData` |
| `getKey($table, $key, $id)` | `/v2/data?action=getKey` |
| `logs($limit, $page)` | `/v2/data?action=getData` (`table=logs`) |

### Websites

| Method | Endpoint |
|---|---|
| `siteList($limit, $page, $search, $type)` | `/v2/data?action=getData` (`table=sites`) |
| `siteGroups()` | `/v2/site?action=get_site_types` |
| `addSite(...)` | `/v2/site?action=AddSite` |
| `checkDeleteSite($ids)` | `/v2/site?action=check_del_data` |
| `deleteSite($id, $webname, $extra)` | `/v2/site?action=DeleteSite` |
| `siteDomains($siteId)` | `/v2/data?action=getData&table=domain` |
| `addDomain($domain, $webname, $id)` | `/v2/site?action=AddDomain` |
| `deleteDomain($id, $webname, $domain, $port)` | `/v2/site?action=DelDomain` |
| `siteUserIni($path, $id)` | `/v2/site?action=GetDirUserINI` |
| `toggleSiteUserIni($path, $id)` | `/v2/site?action=SetDirUserINI` |
| `sitePath($id)` | `/v2/data?action=getKey` (`sites.path`) |
| `setSiteRunPath($id, $runPath)` | `/v2/site?action=SetSiteRunPath` |
| `sitePhpVersion($siteName)` | `/v2/site?action=GetSitePHPVersion` |
| `phpVersions()` | `/v2/site?action=GetPHPVersion` |
| `setSitePhpVersion($siteName, $version)` | `/v2/site?action=SetPHPVersion` |
| `rewriteTemplates($siteName)` | `/v2/site?action=GetRewriteList` |
| `disableSite($id, $domain)` / `enableSite(...)` | `/v2/site?action=SiteStop` / `SiteStart` |
| `nodejsProjects()` / `pm2Projects()` | `/v2/project/nodejs/get_project_list`, `/v2/plugin?action=a&s=List&name=pm2` |

### SSL

| Method | Endpoint |
|---|---|
| `siteSsl($siteName)` | `/v2/site?action=GetSSL` |
| `closeSsl($siteName, $updateOf)` | `/v2/site?action=CloseSSLConf` |
| `uploadCertificate($key, $cert)` | `/v2/ssl_domain?action=upload_cert` |
| `deployCertificate($hash, $domains, $append)` | `/v2/ssl_domain?action=cert_deploy_sites` |
| `installCertificate($siteName, $key, $cert, $domains)` | upload + deploy |
| `certificates($page, $limit)` | `/v2/ssl_domain?action=list_ssl_info` |

### Databases (MySQL)

| Method | Endpoint |
|---|---|
| `databaseList($page, $limit, $search)` | `/v2/data?action=getData&table=databases&type=MySQL` |
| `addDatabase($name, $user, $password, $access, …)` | `/v2/database?action=AddDatabase` |
| `checkDeleteDatabase($ids)` | `/v2/database?action=check_del_data` |
| `deleteDatabase($id, $name)` | `/v2/database?action=DeleteDatabase` |
| `setDatabasePassword($id, $password, $name)` | `/v2/database?action=ResDatabasePassword` |
| `getDatabaseAccess($name)` | `/v2/database?action=GetDatabaseAccess` |
| `setDatabaseAccess($name, $access)` | `/v2/database?action=SetDatabaseAccess` |
| `databaseInfo($dbName)` | `/v2/database?action=GetInfo` |
| `optimizeTables($dbName, $tables)` | `/v2/database?action=OpTable` |
| `repairTables($dbName, $tables)` | `/v2/database?action=ReTable` |
| `backupDatabase($id)` | `/v2/database?action=ToBackup` |
| `backupList($databaseId, $page, $limit)` | `/v2/data?action=getData` (`table=backup`) |
| `deleteBackup($id)` | `/v2/database?action=DelBackup` |
| `importSql($file, $dbName)` | `/v2/database?action=InputSql` |
| `databaseQuota($dbName, $sizeMb)` | `/v2/project/quota/modify_database_quota` |
| `mysqlStatus()` | `/v2/panel/public/get_soft_status?name=mysql` |
| `mysqlRootPassword()` / `setMysqlRootPassword($password)` | `/v2/data?action=getKey`, `/v2/database?action=SetupPassword` |
| `syncToMysql($ids)` / `syncFromMysql($siteId)` | `SyncToDatabases` / `SyncGetDatabases` |
| `recycleBin()` / `restoreFromRecycleBin($rname)` | `/v2/files?action=Get_Recycle_bin` / `Re_Recycle_bin` |

### Files and DNS

| Method | Endpoint |
|---|---|
| `getFileBody($path)` / `saveFileBody($path, $data, $encoding)` | `/v2/files?action=GetFileBody` / `SaveFileBody` |
| `setRewrite($siteName, $rules)` | `SaveFileBody` on `/www/server/panel/vhost/rewrite/{site}.conf` |
| `unzip($source, $destination, $password)` | `/v2/files?action=UnZip` |
| `addSubDomain()` / `deleteSubDomain()` / `modifySubDomain()` / `subDomainList()` | `/v2/plugin?action=a&name=dns_manager&…` |

### Services

Start, stop, restart and reload the software aaPanel installed itself — Nginx, MySQL/MariaDB, PHP-FPM, Redis, Apache and OpenLiteSpeed. The panel runs `/etc/init.d/<service> <action>` for you, which is exactly what [Service management commands](https://www.aapanel.com/docs/faq/Service_Manage_Commands.html) documents.

| Method | Endpoint |
|---|---|
| `serviceAction($service, $action)` | `/v2/system?action=ServiceAdmin` |
| `startService($service)` / `stopService($service)` | same, `type=start` / `stop` |
| `restartService($service)` / `reloadService($service)` | same, `type=restart` / `reload` |
| `serviceConfigTest($service)` | same, `type=test` (the page calls it `configtest`) |
| `serviceStatus($name)` | `/v2/panel/public/get_soft_status?name=…` |
| `phpFpmService($version)` | `8.2` → `php-fpm-82` |

```php
$aapanel->restartService('nginx');
$aapanel->reloadService($aapanel->phpFpmService('8.2'));   // php-fpm-82
$aapanel->restartService('mysqld');                        // MySQL has no reload
```

`reloadService()` and `serviceConfigTest()` refuse a service the official page does not document that action for, instead of letting the panel run an unknown init target and still answer "success": MySQL and Apache have no reload, OpenLiteSpeed turns any unknown action into a restart, and only Nginx and PHP-FPM document a config test. `serviceAction()` stays raw for everything else.

A broken web server configuration is caught by the panel before it starts anything — the exception carries the `nginx -t` / `apachectl -t` output.

**Service catalog.** `ServiceCatalog` holds the documented init scripts, commands and paths. It makes no API call, so it is also useful over SSH, in cron and in runbooks:

```php
use MythicalSystems\AaPanelApi\ServiceCatalog;

ServiceCatalog::command('nginx', 'reload');            // /etc/init.d/nginx reload
ServiceCatalog::command('php-fpm-8.2', 'configtest');  // /etc/init.d/php-fpm-82 configtest
ServiceCatalog::path('nginx', 'config_file');          // /www/server/nginx/conf/nginx.conf
ServiceCatalog::path('php-fpm-82', 'log');             // /www/server/php/82/var/log/php-fpm.log
ServiceCatalog::actions('redis');                      // start, stop, restart, reload, status
```

Names are the ones the panel uses (`mysqld`, `httpd`, `php-fpm-82`, `lsphp74`). The aliases `mysql`, `apache`, `openresty` and `tengine` resolve to the same entries, and PHP versions are accepted with or without the dot (`8.2`, `82`, `php-fpm-8.2`).

## Error handling

```php
use MythicalSystems\AaPanelApi\AaPanelException;

try {
    $aapanel->addSite('test31.com', 'test31.com');
} catch (AaPanelException $e) {
    $e->getMessage();        // panel message, e.g. "Site already exists"
    $e->getPanelStatus();    // panel status field, -1 for transport failures
    $e->getResponseBody();   // raw response for logging
}
```

`status != 0` from the panel, HTTP errors, non-JSON answers (wrong URL, API disabled, IP not whitelisted) and connection failures all raise `AaPanelException`.

## Unverified endpoints

The provided v2 specification covers the website and MySQL controllers only. These methods are carried over from v1 and are **not** covered by it — verify them against your panel build before relying on them:

`disableSite` / `enableSite`, `nodejsProjects`, `pm2Projects`, `unzip`, `forceHttps`, and everything in the DNS manager group.

Service management is not in that specification either. `serviceAction` and the `startService` / `stopService` / `restartService` / `reloadService` / `serviceConfigTest` wrappers call `/v2/system?action=ServiceAdmin`, which is verified against the panel source (`class/system.py`: it executes `/etc/init.d/<name> <type>`) but is not documented as an API. `serviceStatus` reuses the `get_soft_status` endpoint `mysqlStatus` already relies on; only `name=mysql` is covered by the specification, so other software names are a best guess.

## Tests

```bash
composer test
```

`tests/smoke.php` runs 91 checks against a fake transport: request signing, the `/v2` prefix, field names and types, the service catalog, and envelope/error handling. No panel needed.

## Releasing

Bumping the `version` file and pushing a tag is the whole manual part:

```bash
git tag -a v2.1.0 -m "2.1.0 - service management"
git push origin v2.1.0
```

`.github/workflows/release.yml` then:

1. runs `composer validate --strict`, the linter and the smoke test on PHP 7.4 (the floor in `composer.json`) and 8.3;
2. refuses to continue when the tag and the `version` file disagree;
3. creates the GitHub release, with notes generated from the commits since the previous tag — a tag containing a suffix (`v2.2.0-rc1`) is marked as a prerelease;
4. pings Packagist when a `PACKAGIST_TOKEN` secret is configured. Without it Packagist still updates from its own GitHub webhook, just not immediately.

## Original source & credits

This library was not written from scratch. It descends from a third-party aaPanel client that changed hands twice before the v2 rewrite:

| Year | Author | Where |
|---|---|---|
| 2022 | **skolastikarhea** | [skolastikarhea/api_aapanel](https://github.com/skolastikarhea/api_aapanel) — the original "3rd party api aapanel" |
| 2022–2023 | **mdestafadilah** | this repository |
| 2023 | **NaysKutzu / MythicalSystems** | published as [`mythicalsystems/aapanelapi`](https://packagist.org/packages/mythicalsystems/aapanelapi); its GitHub repository (`MythicalLTD/AAPanel-API`) is no longer available |
| 2026 | **mdestafadilah** | v2 rewrite of the request layer, published as `mdestafadilah/aapanelapi` |

The original package is marked **abandoned** on Packagist, so this fork continues it under a new vendor name instead of trying to take over the old one.

**Kept from the original work:** the overall shape of the client (one class, one method per panel action), the public method names wherever they still map to a live endpoint, and the MIT licence.

**Replaced in 2.0.0:** the transport (no more cookie jar, TLS verification on by default), request signing, response handling, and every endpoint path — see [What changed in aaPanel API v2](#what-changed-in-aapanel-api-v2).

If you are one of the original authors and want this notice worded differently, open an issue.

## License

MIT. Copyright (c) 2022 skolastikarhea, 2022–2023 mdestafadilah, 2023 MythicalSystems / NaysKutzu — see [LICENSE](LICENSE).
