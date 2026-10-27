<?php

declare(strict_types=1);

namespace MythicalSystems\AaPanelApi\Traits;

/**
 * Website / PHP project endpoints (website-php.json).
 *
 * Covers: project list, groups, create, delete, domains, PHP version,
 * run path, .user.ini, URL rewrite and the site stop/start toggles.
 */
trait WebsiteTrait
{
    /**
     * List PHP project websites: /v2/data?action=getData (table=sites).
     *
     * @return mixed Decoded "message": where, page and data rows
     */
    public function siteList(int $limit = 10, int $page = 1, string $search = '', int $type = -1)
    {
        return $this->getData('sites', [
            'p' => $page,
            'limit' => $limit,
            'search' => $search,
            'type' => $type,
        ]);
    }

    /**
     * Website groups: /v2/site?action=get_site_types
     *
     * @return mixed List of {id, name}
     */
    public function siteGroups()
    {
        return $this->request('site', ['action' => 'get_site_types']);
    }

    /**
     * Create a PHP website: /v2/site?action=AddSite
     *
     * An FTP account is created when $ftpUsername is given, a MySQL database
     * when $dbUser is given (the v1 signature used separate $ftp / $sql flags
     * for this).
     *
     * @param string $path Absolute path, or a folder name that gets placed under /www/wwwroot
     *
     * @return mixed
     */
    public function addSite(
        string $domain,
        string $path,
        string $description = '',
        int $groupId = 0,
        string $type = 'PHP',
        string $phpVersion = '74',
        int $port = 80,
        ?string $ftpUsername = null,
        ?string $ftpPassword = null,
        ?string $dbUser = null,
        ?string $dbPassword = null,
        string $dbEncoding = 'utf8',
        bool $createDefaultFile = true,
        bool $applySsl = false,
        bool $forceSsl = false
    ) {
        $body = [
            'webname' => (string) json_encode([
                'domain' => $domain,
                'domainlist' => [],
                'count' => 0,
            ], JSON_UNESCAPED_SLASHES),
            'type' => $type,
            'port' => $port,
            'ps' => $description,
            'path' => $this->resolveSitePath($path),
            'type_id' => $groupId,
            'version' => $phpVersion,
            'ftp' => $ftpUsername !== null,
            'sql' => $dbUser !== null,
            'set_ssl' => $applySsl ? 1 : 0,
            'force_ssl' => $forceSsl ? 1 : 0,
            'is_create_default_file' => $createDefaultFile,
        ];

        if ($ftpUsername !== null) {
            $body['ftp_username'] = $ftpUsername;
            $body['ftp_password'] = (string) $ftpPassword;
        }

        if ($dbUser !== null) {
            $body['datauser'] = $dbUser;
            $body['datapassword'] = (string) $dbPassword;
            $body['codeing'] = $dbEncoding;
        }

        return $this->request('site', ['action' => 'AddSite'], $body);
    }

    /**
     * Inspect what a deletion would remove: /v2/site?action=check_del_data
     *
     * v2 moved this out of DeleteSite: you ask first, then delete.
     *
     * @param array<int, int|string> $ids Website IDs
     *
     * @return mixed data rows plus file_size / db_size
     */
    public function checkDeleteSite(array $ids)
    {
        return $this->request('site', ['action' => 'check_del_data'], [
            'ids' => array_values($ids),
        ]);
    }

    /**
     * Delete a website: /v2/site?action=DeleteSite
     *
     * The v1 ftp/database/path flags are not part of the v2 contract; pass them
     * through $extra if your panel build still honours them.
     *
     * @param array<string, mixed> $extra Additional form fields (ftp, database, path, ...)
     *
     * @return mixed
     */
    public function deleteSite(int $id, string $webname, array $extra = [])
    {
        return $this->request('site', ['action' => 'DeleteSite'], array_merge([
            'id' => $id,
            'webname' => $webname,
        ], $extra));
    }

    /**
     * Domains bound to one website: /v2/data?action=getData&table=domain
     *
     * @return mixed List of {id, pid, name, port, addtime}
     */
    public function siteDomains(int $siteId, bool $list = true)
    {
        return $this->request(
            'data',
            ['action' => 'getData', 'table' => 'domain'],
            ['list' => $list, 'search' => $siteId]
        );
    }

    /**
     * Add a domain to a website: /v2/site?action=AddDomain
     *
     * @return mixed
     */
    public function addDomain(string $domain, string $webname, int $id)
    {
        return $this->request('site', ['action' => 'AddDomain'], [
            'domain' => $domain,
            'webname' => $webname,
            'id' => $id,
        ]);
    }

    /**
     * Remove a domain from a website: /v2/site?action=DelDomain
     *
     * @return mixed
     */
    public function deleteDomain(int $id, string $webname, string $domain, int $port = 80)
    {
        return $this->request('site', ['action' => 'DelDomain'], [
            'id' => $id,
            'webname' => $webname,
            'domain' => $domain,
            'port' => $port,
        ]);
    }

    /**
     * Read .user.ini protection and run directory state: /v2/site?action=GetDirUserINI
     *
     * @return mixed logs / userini / runPath / pass
     */
    public function siteUserIni(string $path, int $id)
    {
        return $this->request('site', ['action' => 'GetDirUserINI'], [
            'path' => $path,
            'id' => $id,
        ]);
    }

    /**
     * Toggle .user.ini protection: /v2/site?action=SetDirUserINI
     *
     * @return mixed
     */
    public function toggleSiteUserIni(string $path, int $id)
    {
        return $this->request('site', ['action' => 'SetDirUserINI'], [
            'path' => $path,
            'id' => $id,
        ]);
    }

    /**
     * Root directory of a website: /v2/data?action=getKey (table=sites, key=path)
     *
     * @return string|null
     */
    public function sitePath(int $id)
    {
        $result = $this->getKey('sites', 'path', $id);

        if (is_array($result)) {
            return isset($result['result']) ? (string) $result['result'] : null;
        }

        return is_scalar($result) ? (string) $result : null;
    }

    /**
     * Change the run directory: /v2/site?action=SetSiteRunPath
     *
     * @param string $runPath Relative to the site root, e.g. "/public"
     *
     * @return mixed
     */
    public function setSiteRunPath(int $id, string $runPath)
    {
        return $this->request('site', ['action' => 'SetSiteRunPath'], [
            'runPath' => $runPath,
            'id' => $id,
        ]);
    }

    /**
     * PHP version in use by a website: /v2/site?action=GetSitePHPVersion
     *
     * @return mixed phpversion, tomcat, nodejsversion, php_other
     */
    public function sitePhpVersion(string $siteName)
    {
        return $this->request('site', ['action' => 'GetSitePHPVersion'], [
            'siteName' => $siteName,
        ]);
    }

    /**
     * Installed PHP versions: /v2/site?action=GetPHPVersion
     *
     * @return mixed List of {version, name}
     */
    public function phpVersions()
    {
        return $this->request('site', ['action' => 'GetPHPVersion']);
    }

    /**
     * Switch the PHP version of a website: /v2/site?action=SetPHPVersion
     *
     * @param string $version Short version, e.g. "81" for PHP-81
     *
     * @return mixed
     */
    public function setSitePhpVersion(string $siteName, string $version, string $other = '')
    {
        return $this->request('site', ['action' => 'SetPHPVersion'], [
            'siteName' => $siteName,
            'version' => $version,
            'other' => $other,
        ]);
    }

    /**
     * Available URL rewrite templates: /v2/site?action=GetRewriteList
     *
     * @return mixed message.rewrite (array of template names)
     */
    public function rewriteTemplates(string $siteName)
    {
        return $this->request('site', ['action' => 'GetRewriteList'], [
            'siteName' => $siteName,
        ]);
    }

    /**
     * Stop a website.
     *
     * Not covered by the provided v2 specification; kept on the v1 action name
     * because no v2 replacement is documented.
     *
     * @return mixed
     */
    public function disableSite(int $id, string $domain, string $projectType = 'PHP')
    {
        return $this->setSiteState('SiteStop', $id, $domain, $projectType);
    }

    /**
     * Start a website. See disableSite() for the spec caveat.
     *
     * @return mixed
     */
    public function enableSite(int $id, string $domain, string $projectType = 'PHP')
    {
        return $this->setSiteState('SiteStart', $id, $domain, $projectType);
    }

    /**
     * @return mixed
     */
    private function setSiteState(string $action, int $id, string $domain, string $projectType)
    {
        if (strcasecmp($projectType, 'node') === 0) {
            $endpoint = $action === 'SiteStop' ? 'project/nodejs/stop_project' : 'project/nodejs/start_project';

            return $this->request($endpoint, [], [
                'data' => (string) json_encode(['project_name' => $domain]),
            ]);
        }

        return $this->request('site', ['action' => $action], [
            'id' => $id,
            'name' => $domain,
        ]);
    }

    /**
     * Node.js projects. Not covered by the provided v2 specification.
     *
     * @return mixed
     */
    public function nodejsProjects()
    {
        return $this->request('project/nodejs/get_project_list');
    }

    /**
     * PM2 projects. Not covered by the provided v2 specification.
     *
     * @return mixed
     */
    public function pm2Projects()
    {
        return $this->request('plugin', ['action' => 'a', 's' => 'List', 'name' => 'pm2']);
    }

    /**
     * Keep relative paths inside the default web root, as v1 did.
     */
    private function resolveSitePath(string $path): string
    {
        if ($path !== '' && $path[0] === '/') {
            return $path;
        }

        return '/www/wwwroot/' . $path;
    }
}
