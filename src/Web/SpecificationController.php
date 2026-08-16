<?php

declare(strict_types=1);

namespace App\Web;

use App\Exception\NotFoundException;
use App\Http\Response;

/**
 * Serves docs/openapi.yaml to Swagger UI.
 *
 * The specification is the binding contract and lives with the rest of the
 * documentation, outside the docroot. Copying it into public/ would create a second
 * version of the contract that could quietly drift from the first, so it is read
 * from its one location instead.
 */
final class SpecificationController
{
    public function __construct(private readonly string $file)
    {
    }

    public function show(): Response
    {
        $specification = @file_get_contents($this->file);

        if ($specification === false) {
            throw new NotFoundException('The API specification is not available.');
        }

        return Response::yaml($specification);
    }
}
