<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\PublicHealthResource;
use App\Services\HealthService;
use Illuminate\Http\JsonResponse;

final class HealthController
{
    public function __invoke(HealthService $health): JsonResponse
    {
        return (new PublicHealthResource($health->publicStatus()))->response();
    }
}
