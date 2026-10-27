<?php

declare(strict_types=1);

namespace MythicalSystems\AaPanelApi\Traits;

/**
 * SSL endpoints (website-php.json).
 *
 * v2 replaced the v1 "apply certificate then SetSSL" flow with a certificate
 * store: upload the pair once (uploadCertificate), get a hash back, then deploy
 * that hash to one or more domains (deployCertificate).
 */
trait SslTrait
{
    /**
     * SSL state of a website: /v2/site?action=GetSSL
     *
     * @return mixed
     */
    public function siteSsl(string $siteName)
    {
        return $this->request('site', ['action' => 'GetSSL'], [
            'siteName' => $siteName,
        ]);
    }

    /**
     * Turn SSL off for a website: /v2/site?action=CloseSSLConf
     *
     * @return mixed
     */
    public function closeSsl(string $siteName, int $updateOf = 1)
    {
        return $this->request('site', ['action' => 'CloseSSLConf'], [
            'siteName' => $siteName,
            'updateOf' => $updateOf,
        ]);
    }

    /**
     * Store a certificate pair: /v2/ssl_domain?action=upload_cert
     *
     * @param string $key  Private key content
     * @param string $cert Certificate content (CRT or PEM, chain included)
     *
     * @return mixed message with the certificate hash needed by deployCertificate()
     */
    public function uploadCertificate(string $key, string $cert)
    {
        return $this->request('ssl_domain', ['action' => 'upload_cert'], [
            'key' => $key,
            'cert' => $cert,
        ]);
    }

    /**
     * Deploy a stored certificate: /v2/ssl_domain?action=cert_deploy_sites
     *
     * @param string             $hash    Certificate hash returned by uploadCertificate()
     * @param array<int, string> $domains Domains to deploy to
     * @param string             $append  Panel specific extra flag, usually empty
     *
     * @return mixed
     */
    public function deployCertificate(string $hash, array $domains, string $append = '')
    {
        return $this->request('ssl_domain', ['action' => 'cert_deploy_sites'], [
            'hash' => $hash,
            'domains' => array_values($domains),
            'append' => $append,
        ]);
    }

    /**
     * Upload and deploy in one call.
     *
     * @param string             $siteName Website the certificate belongs to
     * @param array<int, string> $domains  Defaults to the website name
     *
     * @return mixed The deploy response
     */
    public function installCertificate(string $siteName, string $key, string $cert, array $domains = [])
    {
        $uploaded = $this->uploadCertificate($key, $cert);
        $hash = is_array($uploaded) ? ($uploaded['hash'] ?? null) : null;

        if (!is_string($hash) || $hash === '') {
            return $uploaded;
        }

        return $this->deployCertificate($hash, $domains !== [] ? $domains : [$siteName]);
    }

    /**
     * All certificates known to the panel: /v2/ssl_domain?action=list_ssl_info
     *
     * The page parameter is capitalised in the specification ("P"), so it is
     * sent that way.
     *
     * @return mixed
     */
    public function certificates(int $page = 1, int $limit = 99999)
    {
        return $this->request('ssl_domain', ['action' => 'list_ssl_info'], [
            'P' => $page,
            'limit' => $limit,
        ]);
    }

    /**
     * Force HTTPS on a website: /v2/site?action=HttpToHttps
     *
     * Not part of the provided v2 specification (it documents set_ssl and
     * force_ssl on AddSite instead), kept because the panel action still exists.
     *
     * @deprecated Prefer addSite(..., $forceSsl: true) or the panel UI.
     *
     * @return mixed
     */
    public function forceHttps(string $siteName)
    {
        return $this->request('site', ['action' => 'HttpToHttps'], [
            'siteName' => $siteName,
        ]);
    }
}
