<?php

declare(strict_types=1);

namespace MythicalSystems\AaPanelApi;

use RuntimeException;
use Throwable;

/**
 * Raised for every failure of the aaPanel API: transport errors (DNS, timeout,
 * TLS), non-JSON answers and panel level errors (status != 0).
 *
 * The original panel payload is kept on the exception so callers can log it or
 * inspect extra fields (for example the "result" string of a failed action).
 */
class AaPanelException extends RuntimeException
{
    /** @var int Panel status field, or -1 when the failure happened before a JSON answer arrived */
    private int $panelStatus;

    /** @var string|null Raw response body as received from the panel */
    private ?string $responseBody;

    public function __construct(
        string $message,
        int $panelStatus = -1,
        ?string $responseBody = null,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $panelStatus, $previous);
        $this->panelStatus = $panelStatus;
        $this->responseBody = $responseBody;
    }

    /**
     * Status value returned by the panel. 0 means success, so a real error is
     * always either -1 (no JSON answer) or a positive number.
     */
    public function getPanelStatus(): int
    {
        return $this->panelStatus;
    }

    public function getResponseBody(): ?string
    {
        return $this->responseBody;
    }
}
