<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Whitelists exactly two fields (FR-004): never internal details.
 *
 * @property-read array{status: string, checkedAt: string} $resource
 */
final class PublicHealthResource extends JsonResource
{
    /** @var string|null */
    public static $wrap = null;

    /** @return array{status: string, checkedAt: string} */
    public function toArray(Request $request): array
    {
        return [
            'status' => $this->resource['status'],
            'checkedAt' => $this->resource['checkedAt'],
        ];
    }
}
