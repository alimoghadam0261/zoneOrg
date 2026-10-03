<?php

namespace App\Http\Middleware;

use App\Models\Device;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bearer-token authentication for GPS tags / mobile apps.
 *
 * The token is a Sanctum personal-access token; `devices.api_token` holds its
 * sha256 digest so a ping never costs more than one indexed lookup.
 */
class AuthenticateDevice
{
    public function handle(Request $request, Closure $next): Response
    {
        $plainText = $request->bearerToken();

        if (! $plainText) {
            return response()->json([
                'message' => 'توکن دستگاه (Bearer Token) ارسال نشده است.',
            ], 401);
        }

        $device = Device::findByToken($plainText);

        if (! $device) {
            return response()->json([
                'message' => 'توکن دستگاه نامعتبر یا باطل‌شده است.',
            ], 401);
        }

        if (! $device->person) {
            return response()->json([
                'message' => 'این دستگاه به پرسنلی متصل نیست.',
            ], 403);
        }

        $request->attributes->set('device', $device);

        return $next($request);
    }
}
