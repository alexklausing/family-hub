<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AuraFramesService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AuraFramesController extends Controller
{
    public function index(AuraFramesService $service)
    {
        // Cache the photos list for 1 hour to avoid hitting the API too frequently
        $photos = Cache::remember('aura_photos', 3600, function () use ($service) {
            return $service->getImages();
        });

        return response()->json([
            'photos' => $photos,
        ]);
    }
}
