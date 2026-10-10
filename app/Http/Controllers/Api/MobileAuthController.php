<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MobileDevice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class MobileAuthController extends Controller
{
    /**
     * Exchange a pairing code for a long-lived device API token.
     */
    public function pair(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string',
            'device_name' => 'nullable|string|max:100',
        ]);

        $expected = (string) config('services.mobile.pairing_code');

        if ($expected === '') {
            return response()->json([
                'message' => 'Mobile pairing is not configured on this hub.',
            ], 503);
        }

        $key = 'mobile-pair:'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 10)) {
            return response()->json([
                'message' => 'Too many attempts. Please try again in a minute.',
            ], 429);
        }

        if (! hash_equals($expected, trim($validated['code']))) {
            RateLimiter::hit($key, 60);

            return response()->json([
                'message' => 'That pairing code is incorrect.',
            ], 422);
        }

        RateLimiter::clear($key);

        $device = MobileDevice::create([
            'name' => $validated['device_name'] ?: 'iPhone',
            'paired_at' => now(),
            'last_seen_at' => now(),
        ]);

        $token = $device->createToken('family-hub-mobile', ['chores'])->plainTextToken;

        return response()->json([
            'token' => $token,
            'device' => [
                'id' => $device->id,
                'name' => $device->name,
            ],
        ], 201);
    }

    /**
     * Return the currently paired device, refreshing its last-seen timestamp.
     */
    public function me(Request $request)
    {
        $device = $request->user();

        return response()->json([
            'id' => $device->id,
            'name' => $device->name,
            'paired_at' => $device->paired_at,
            'last_seen_at' => $device->last_seen_at,
        ]);
    }

    /**
     * Revoke the token used for this request, unpairing the device.
     */
    public function revoke(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Device unpaired.']);
    }
}
