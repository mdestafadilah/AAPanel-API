<?php

declare(strict_types=1);

namespace MythicalSystems\AaPanelApi\Traits;

/**
 * DNS Manager plugin endpoints.
 *
 * None of these appear in the provided v2 specification (website-php.json and
 * databases-mysql.json only cover the website and MySQL controllers), so they
 * are carried over from v1 unchanged apart from the /v2 prefix. Verify them
 * against your panel build before relying on them in production.
 */
trait DnsTrait
{
    /**
     * @return array<string, string>
     */
    private function dnsQuery(string $action): array
    {
        return [
            'action' => 'a',
            'name' => 'dns_manager',
            's' => $action,
        ];
    }

    /**
     * Add an A record: /v2/plugin?action=a&name=dns_manager&s=act_resolve
     *
     * @return mixed
     */
    public function addSubDomain(string $subdomain, string $mainDomain, string $ipTarget)
    {
        return $this->request('plugin', $this->dnsQuery('act_resolve'), [
            'host' => $subdomain,
            'value' => $ipTarget,
            'domain' => $mainDomain,
            'ttl' => '600',
            'type' => 'A',
            'act' => 'add',
        ]);
    }

    /**
     * Delete an A record.
     *
     * @return mixed
     */
    public function deleteSubDomain(string $subdomain, string $mainDomain, string $ipTarget)
    {
        return $this->request('plugin', $this->dnsQuery('act_resolve'), [
            'host' => $subdomain,
            'value' => $ipTarget,
            'domain' => $mainDomain,
            'ttl' => '600',
            'type' => 'A',
            'act' => 'delete',
        ]);
    }

    /**
     * Update an A record.
     *
     * @param string|int $id Record ID from subDomainList()
     *
     * @return mixed
     */
    public function modifySubDomain(string $subdomain, string $mainDomain, string $ipTarget, $id)
    {
        return $this->request('plugin', $this->dnsQuery('act_resolve'), [
            'host' => $subdomain,
            'value' => $ipTarget,
            'domain' => $mainDomain,
            'ttl' => '600',
            'type' => 'A',
            'act' => 'modify',
            'id' => $id,
        ]);
    }

    /**
     * List records of a zone: /v2/plugin?action=a&name=dns_manager&s=get_resolve
     *
     * @return mixed A single record when $host is given, otherwise the whole zone
     */
    public function subDomainList(string $domain, ?string $host = null)
    {
        $result = $this->request('plugin', $this->dnsQuery('get_resolve'), [
            'domain' => $domain,
        ]);

        if ($host === null || !is_array($result)) {
            return $result;
        }

        foreach ($result as $index => $record) {
            if (is_array($record) && isset($record['host']) && $record['host'] === $host) {
                $record['id'] = $record['id'] ?? $index;

                return $record;
            }
        }

        return null;
    }
}
