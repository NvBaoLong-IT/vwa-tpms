<?php

namespace App\Http\Controllers;

use App\Services\WebScheduleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class WebScheduleTickController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(WebScheduleService $service): JsonResponse
    {
        $result = Cache::lock('web-schedule:tick', 7200)->get(function () use ($service): JsonResponse {
            if (! Cache::add('web-schedule:last-tick', true, 60)) {
                return response()->json(['status' => 'skipped']);
            }
            $service->tick();

            return response()->json(['status' => 'checked']);
        });

        return $result ?: response()->json(['status' => 'skipped']);
    }
}
