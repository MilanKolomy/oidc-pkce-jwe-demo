<?php

declare(strict_types=1);

namespace App\Api;

use App\Exception\BadRequestException;
use App\Http\Request;

/**
 * The page and per_page parameters shared by the two collection endpoints, with the
 * bounds from docs/openapi.yaml.
 */
final class Pagination
{
    private const DEFAULT_PER_PAGE = 20;
    private const MAX_PER_PAGE = 100;

    private function __construct(
        public readonly int $page,
        public readonly int $perPage,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        return new self(
            self::positiveInteger($request, 'page', 1, PHP_INT_MAX),
            self::positiveInteger($request, 'per_page', self::DEFAULT_PER_PAGE, self::MAX_PER_PAGE),
        );
    }

    /**
     * @return array{page: int, perPage: int, total: int}
     */
    public function meta(int $total): array
    {
        return ['page' => $this->page, 'perPage' => $this->perPage, 'total' => $total];
    }

    private static function positiveInteger(Request $request, string $name, int $default, int $max): int
    {
        $raw = $request->query[$name] ?? null;

        if ($raw === null || $raw === '') {
            return $default;
        }

        // Rejected rather than clamped: silently answering a different question than
        // the one asked is worse than saying the question was malformed.
        if (preg_match('/^\d+$/', $raw) !== 1) {
            throw new BadRequestException(sprintf('The %s parameter must be a whole number.', $name));
        }

        $value = (int) $raw;

        if ($value < 1 || $value > $max) {
            throw new BadRequestException(sprintf('The %s parameter must be between 1 and %d.', $name, $max));
        }

        return $value;
    }
}
