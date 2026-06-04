<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * POST /api/v1/callback/test
 * Endpoint untuk testing integrasi callback dari source app.
 */
class CallbackTestController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $client  = $request->attributes->get('api_client');
        $payload = $request->all();

        Log::info('CallbackTest received', [
            'client_key'   => $client?->client_key,
            'ip'           => $request->ip(),
            'payload_keys' => array_keys($payload), // log struktur saja, bukan isi
        ]);

        return response()->json([
            'success'    => true,
            'message'    => 'Callback test diterima.',
            'client_key' => $client?->client_key,
            'timestamp'  => now()->toIso8601String(),
        ]);
    }
}
