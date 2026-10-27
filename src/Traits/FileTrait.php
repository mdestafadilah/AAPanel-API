<?php

declare(strict_types=1);

namespace MythicalSystems\AaPanelApi\Traits;

/**
 * File endpoints used by the website workflow (website-php.json).
 */
trait FileTrait
{
    /**
     * Read a file from the server: /v2/files?action=GetFileBody
     *
     * @return mixed message.data (content), size, encoding, only_read
     */
    public function getFileBody(string $path)
    {
        return $this->request('files', ['action' => 'GetFileBody'], [
            'path' => $path,
        ]);
    }

    /**
     * Write a file on the server: /v2/files?action=SaveFileBody
     *
     * v1 called this safeFileBody() and sent the arguments in the opposite
     * order; encoding is now required by the specification.
     *
     * @return mixed
     */
    public function saveFileBody(string $path, string $data, string $encoding = 'utf-8')
    {
        return $this->request('files', ['action' => 'SaveFileBody'], [
            'path' => $path,
            'data' => $data,
            'encoding' => $encoding,
        ]);
    }

    /**
     * Replace the URL rewrite rules of a website.
     *
     * The template content comes from rewriteTemplates() + getFileBody(); this
     * helper only handles the documented destination path (nginx builds).
     *
     * @param string $siteName Website domain, e.g. test31.com
     * @param string $rules    Full rewrite file content
     *
     * @return mixed
     */
    public function setRewrite(string $siteName, string $rules)
    {
        return $this->saveFileBody('/www/server/panel/vhost/rewrite/' . $siteName . '.conf', $rules);
    }

    /**
     * Extract an archive: /v2/files?action=UnZip
     *
     * Not part of the provided v2 specification; kept from v1.
     *
     * @param string|null $password Archive password, null when not encrypted
     *
     * @return mixed
     */
    public function unzip(string $sourceFile, string $destinationFile, ?string $password = null)
    {
        return $this->request('files', ['action' => 'UnZip'], [
            'sfile' => $sourceFile,
            'dfile' => $destinationFile,
            'type' => 'zip',
            'coding' => 'UTF-8',
            'password' => $password,
        ]);
    }
}
