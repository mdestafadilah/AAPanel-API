<?php

declare(strict_types=1);

namespace MythicalSystems\AaPanelApi\Traits;

/**
 * MySQL endpoints (databases-mysql.json).
 *
 * Note the parameter split the specification uses for /v2/data?action=getData:
 * the database list takes table/type in the query string, while the backup list
 * takes table/search/type in the body. AaPanel merges $_REQUEST, so the helpers
 * below follow the documented shape per endpoint.
 */
trait DatabaseTrait
{
    /**
     * MySQL database list: /v2/data?action=getData&table=databases&type=MySQL
     *
     * @return mixed message.where / page / data
     */
    public function databaseList(int $page = 1, int $limit = 20, string $search = '')
    {
        return $this->getData('databases', [], [
            'type' => 'MySQL',
            'p' => $page,
            'limit' => $limit,
            'search' => $search,
        ]);
    }

    /**
     * Create a database and its user: /v2/database?action=AddDatabase
     *
     * @param string $access 127.0.0.1 (local), % (everyone) or a specific IP
     * @param int    $siteId Website ID to bind the database to, 0 for none
     *
     * @return mixed
     */
    public function addDatabase(
        string $name,
        string $user,
        string $password,
        string $access = '127.0.0.1',
        string $encoding = 'utf8',
        int $siteId = 0,
        string $ps = '',
        bool $active = false,
        string $type = 'MySQL'
    ) {
        return $this->request('database', ['action' => 'AddDatabase'], [
            'name' => $name,
            'codeing' => $encoding,
            'db_user' => $user,
            'password' => $password,
            'dataAccess' => $access,
            'sid' => $siteId,
            'active' => $active,
            'address' => $access,
            'ps' => $ps,
            'ssl' => '',
            'dtype' => $type,
        ]);
    }

    /**
     * Inspect what a database deletion would remove: /v2/database?action=check_del_data
     *
     * @param array<int, int|string> $ids Database IDs
     *
     * @return mixed
     */
    public function checkDeleteDatabase(array $ids)
    {
        return $this->request('database', ['action' => 'check_del_data'], [
            'ids' => array_values($ids),
        ]);
    }

    /**
     * Delete a database: /v2/database?action=DeleteDatabase
     *
     * Moves the database to the recycle bin when that feature is enabled.
     *
     * @return mixed
     */
    public function deleteDatabase(int $id, string $name)
    {
        return $this->request('database', ['action' => 'DeleteDatabase'], [
            'id' => $id,
            'name' => $name,
        ]);
    }

    /**
     * Change a database password: /v2/database?action=ResDatabasePassword
     *
     * @return mixed
     */
    public function setDatabasePassword(int $id, string $password, ?string $name = null)
    {
        return $this->request('database', ['action' => 'ResDatabasePassword'], [
            'id' => $id,
            'password' => $password,
            'name' => $name,
        ]);
    }

    /**
     * Read access permissions: /v2/database?action=GetDatabaseAccess
     *
     * @return mixed message.permission / ssl
     */
    public function getDatabaseAccess(string $name)
    {
        return $this->request('database', ['action' => 'GetDatabaseAccess'], [
            'name' => $name,
        ]);
    }

    /**
     * Set access permissions: /v2/database?action=SetDatabaseAccess
     *
     * @param string $access 127.0.0.1, % or an IP list such as "1.1.1.1,127.0.0.1"
     *
     * @return mixed
     */
    public function setDatabaseAccess(string $name, string $access)
    {
        return $this->request('database', ['action' => 'SetDatabaseAccess'], [
            'name' => $name,
            'dataAccess' => $access,
            'access' => $access,
            'ssl' => '',
        ]);
    }

    /**
     * Tables and sizes of a database: /v2/database?action=GetInfo
     *
     * @return mixed message.data_size / tables
     */
    public function databaseInfo(string $dbName)
    {
        return $this->request('database', ['action' => 'GetInfo'], [
            'db_name' => $dbName,
        ]);
    }

    /**
     * Optimise tables: /v2/database?action=OpTable
     *
     * @param array<int, string> $tables Table names
     *
     * @return mixed
     */
    public function optimizeTables(string $dbName, array $tables)
    {
        return $this->request('database', ['action' => 'OpTable'], [
            'db_name' => $dbName,
            'tables' => array_values($tables),
        ]);
    }

    /**
     * Repair tables: /v2/database?action=ReTable
     *
     * @param array<int, string> $tables Table names
     *
     * @return mixed
     */
    public function repairTables(string $dbName, array $tables)
    {
        return $this->request('database', ['action' => 'ReTable'], [
            'db_name' => $dbName,
            'tables' => array_values($tables),
        ]);
    }

    /**
     * Trigger a backup: /v2/database?action=ToBackup
     *
     * @return mixed
     */
    public function backupDatabase(int $id)
    {
        return $this->request('database', ['action' => 'ToBackup'], [
            'id' => $id,
        ]);
    }

    /**
     * Backup files of one database: /v2/data?action=getData (table=backup)
     *
     * @return mixed message.data rows with filename / size / addtime
     */
    public function backupList(int $databaseId, int $page = 1, int $limit = 10)
    {
        return $this->getData('backup', [
            'p' => $page,
            'limit' => $limit,
            'search' => $databaseId,
            'type' => 1,
        ]);
    }

    /**
     * Delete one backup file: /v2/database?action=DelBackup
     *
     * @return mixed
     */
    public function deleteBackup(int $id)
    {
        return $this->request('database', ['action' => 'DelBackup'], [
            'id' => $id,
        ]);
    }

    /**
     * Import (restore) a SQL file: /v2/database?action=InputSql
     *
     * @param string $file Absolute path of the .sql or .sql.gz file on the server
     *
     * @return mixed
     */
    public function importSql(string $file, string $dbName)
    {
        return $this->request('database', ['action' => 'InputSql'], [
            'file' => $file,
            'name' => $dbName,
        ]);
    }

    /**
     * Set a quota on a database: /v2/project/quota/modify_database_quota
     *
     * @param int $sizeMb Quota in megabytes
     *
     * @return mixed
     */
    public function databaseQuota(string $dbName, int $sizeMb)
    {
        return $this->request('project/quota/modify_database_quota', [], [
            'data' => (string) json_encode([
                'size' => (string) $sizeMb,
                'db_name' => $dbName,
            ], JSON_UNESCAPED_SLASHES),
        ]);
    }

    /**
     * MySQL service state: /v2/panel/public/get_soft_status?name=mysql
     *
     * @return mixed status, version, setup, ...
     */
    public function mysqlStatus()
    {
        return $this->request('panel/public/get_soft_status', ['name' => 'mysql']);
    }

    /**
     * MySQL root password: /v2/data?action=getKey (table=config, key=mysql_root)
     *
     * This endpoint answers with a bare JSON string, not the usual envelope.
     *
     * @return string|null
     */
    public function mysqlRootPassword()
    {
        $result = $this->getKey('config', 'mysql_root', 1);

        if (is_array($result)) {
            return isset($result['result']) ? (string) $result['result'] : null;
        }

        return is_scalar($result) ? (string) $result : null;
    }

    /**
     * Reset the MySQL root password: /v2/database?action=SetupPassword
     *
     * @return mixed
     */
    public function setMysqlRootPassword(string $password)
    {
        return $this->request('database', ['action' => 'SetupPassword'], [
            'password' => $password,
        ]);
    }

    /**
     * Push panel databases into MySQL: /v2/database?action=SyncToDatabases&type=0
     *
     * @param array<int, int|string> $ids Database IDs, empty for all
     *
     * @return mixed
     */
    public function syncToMysql(array $ids = [])
    {
        return $this->request('database', ['action' => 'SyncToDatabases', 'type' => 0], [
            'ids' => array_values($ids),
        ]);
    }

    /**
     * Pull databases created outside the panel (phpMyAdmin) into it:
     * /v2/database?action=SyncGetDatabases
     *
     * @return mixed
     */
    public function syncFromMysql(int $siteId = 0)
    {
        return $this->request('database', ['action' => 'SyncGetDatabases'], [
            'sid' => $siteId,
        ]);
    }

    /**
     * Database recycle bin: /v2/files?action=Get_Recycle_bin
     *
     * @return mixed message.dirs rows (rname, name, time, size)
     */
    public function recycleBin()
    {
        return $this->request('files', ['action' => 'Get_Recycle_bin']);
    }

    /**
     * Restore a database from the recycle bin: /v2/files?action=Re_Recycle_bin
     *
     * @param string $rname The "rname" value from recycleBin(), e.g. BTDB_test234_t_1757493439
     *
     * @return mixed
     */
    public function restoreFromRecycleBin(string $rname)
    {
        return $this->request('files', ['action' => 'Re_Recycle_bin'], [
            'path' => $rname,
        ]);
    }
}
