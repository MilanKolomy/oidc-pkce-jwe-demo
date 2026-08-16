<?php

declare(strict_types=1);

namespace App\Exception;

use RuntimeException;

/**
 * Base for failures that map to a documented HTTP status.
 *
 * Each subclass fixes the status, the problem type and the title, so the values
 * cannot drift from docs/openapi.yaml at individual throw sites. Only the detail
 * varies per occurrence.
 */
abstract class HttpException extends RuntimeException
{
    public function __construct(
        private readonly int $status,
        private readonly string $problemType,
        private readonly string $title,
        string $detail = '',
        private readonly ?string $logReason = null,
    ) {
        parent::__construct($detail);
    }

    /**
     * What actually happened, for the log only.
     *
     * Some refusals are deliberately uninformative to the caller: every way of
     * failing to present a valid token is the same 401, because the difference
     * between "expired" and "not decryptable" says something about the application
     * and nothing the caller can act on. That difference still has to be diagnosable,
     * so it goes here and the front controller writes it to the log.
     */
    public function logReason(): ?string
    {
        return $this->logReason;
    }

    public function status(): int
    {
        return $this->status;
    }

    /**
     * Slug appended to the problem type base URI, for example "not-found".
     */
    public function problemType(): string
    {
        return $this->problemType;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function detail(): ?string
    {
        return $this->getMessage() === '' ? null : $this->getMessage();
    }
}
