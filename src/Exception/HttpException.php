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
    ) {
        parent::__construct($detail);
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
