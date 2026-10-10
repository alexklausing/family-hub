<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Label;
use App\Models\Profile;
use Illuminate\Http\Request;

class MobileBootstrapController extends Controller
{
    /**
     * Initial payload for the mobile app: device identity, profiles, labels.
     */
    public function index(Request $request)
    {
        $device = $request->user();
        $device->forceFill(['last_seen_at' => now()])->save();

        return response()->json([
            'device' => [
                'id' => $device->id,
                'name' => $device->name,
            ],
            'profiles' => Profile::orderBy('order')->orderBy('name')->pluck('name')->values(),
            'labels' => Label::with('bonusReward')->orderBy('name')->get(),
        ]);
    }
}
