<?php

namespace App\Http\Controllers;

use App\Services\TargetApp;
use Illuminate\Http\JsonResponse;

class TargetStatusController extends Controller
{
    /**
     * Proxy the target app's bridge status to the builder UI. Calling it
     * server-side keeps the shared token off the client and sidesteps
     * CORS between the two apps.
     */
    public function __invoke(TargetApp $targetApp): JsonResponse
    {
        return response()->json($targetApp->status());
    }
}
