<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StorePingRequest;
use App\Models\Device;
use App\Services\Geofencing\PingProcessor;
use Illuminate\Http\JsonResponse;

class TelemetryPingController extends Controller
{
    /**
     * POST /api/v1/telemetry/ping
     *
     * Accepts one GPS fix from a mobile app or GPS tag, writes it to the
     * 7-day rolling buffer and runs the two-phase geofencing engine.
     */
    public function __invoke(StorePingRequest $request, PingProcessor $processor): JsonResponse
    {
        /** @var Device $device */
        $device = $request->attributes->get('device');

        $result = $processor->process($device, $request->validatedPayload());

        return response()->json([
            'success' => true,
            'message' => 'موقعیت با موفقیت ثبت شد.',
            'data' => $result->toArray(),
        ], 200);
    }
}
