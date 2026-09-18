<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Destination;
use App\Services\TomTomService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CommuteController extends Controller
{
    public function __construct(
        protected TomTomService $tomTom,
    ) {}

    public function destinations()
    {
        return response()->json(Destination::ordered()->get());
    }

    public function storeDestination(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'required|string|max:500',
            'lat' => 'nullable|numeric|between:-90,90',
            'lon' => 'nullable|numeric|between:-180,180',
            'icon' => 'nullable|string|max:50',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['is_active'] = $validated['is_active'] ?? true;

        $lat = $validated['lat'] ?? null;
        $lon = $validated['lon'] ?? null;

        if (is_null($lat) || is_null($lon)) {
            $coords = $this->tomTom->geocode($validated['address']);
            if ($coords) {
                $validated['lat'] = $coords['lat'];
                $validated['lon'] = $coords['lon'];
            }
        }

        $validated['sort_order'] = $validated['sort_order'] ?? Destination::max('sort_order') + 1;

        $destination = Destination::create($validated);

        return response()->json($destination, 201);
    }

    public function updateDestination(Request $request, Destination $destination)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'address' => 'sometimes|required|string|max:500',
            'lat' => 'nullable|numeric|between:-90,90',
            'lon' => 'nullable|numeric|between:-180,180',
            'icon' => 'nullable|string|max:50',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $lat = $validated['lat'] ?? null;
        $lon = $validated['lon'] ?? null;

        if (isset($validated['address']) && (is_null($lat) || is_null($lon))) {
            $coords = $this->tomTom->geocode($validated['address']);
            if ($coords) {
                $validated['lat'] = $coords['lat'];
                $validated['lon'] = $coords['lon'];
            }
        }

        $destination->update($validated);

        return response()->json($destination);
    }

    public function deleteDestination(Destination $destination)
    {
        $destination->delete();

        return response()->json(null, 204);
    }

    public function etas(Request $request)
    {
        $homeLat = $request->query('home_lat');
        $homeLon = $request->query('home_lon');

        if (! $homeLat || ! $homeLon) {
            return response()->json(['error' => 'Home coordinates are required (home_lat, home_lon)'], 422);
        }

        $homeLat = (float) $homeLat;
        $homeLon = (float) $homeLon;
        $cacheKey = "commute_etas_{$homeLat}_{$homeLon}";
        $force = $request->boolean('force');

        $data = Cache::get($cacheKey);

        if ($data !== null && ! $force) {
            return response()->json($data);
        }

        $destinations = Destination::ordered()->active()->get();
        $results = [];

        foreach ($destinations as $dest) {
            $routeCache = "commute_route_{$homeLat}_{$homeLon}_{$dest->id}";
            $routeData = Cache::get($routeCache);

            if ($routeData === null || $force) {
                $routeData = $this->tomTom->getRoute($homeLat, $homeLon, $dest->lat, $dest->lon);
                Cache::put($routeCache, $routeData, now()->addMinutes(15));
            }

            $route = $routeData['route'];
            $results[] = [
                'destination' => [
                    'id' => $dest->id,
                    'name' => $dest->name,
                    'address' => $dest->address,
                    'lat' => $dest->lat,
                    'lon' => $dest->lon,
                    'icon' => $dest->icon,
                ],
                'route' => $route ? [
                    'travel_time_seconds' => $route['travel_time_seconds'],
                    'travel_time_minutes' => $route['travel_time_seconds'] ? (int) ceil($route['travel_time_seconds'] / 60) : null,
                    'traffic_delay_seconds' => $route['traffic_delay_seconds'],
                    'traffic_delay_minutes' => (int) ceil($route['traffic_delay_seconds'] / 60),
                    'no_traffic_time_seconds' => $route['no_traffic_time_seconds'],
                    'no_traffic_time_minutes' => $route['no_traffic_time_seconds'] ? (int) ceil($route['no_traffic_time_seconds'] / 60) : null,
                    'distance_meters' => $route['distance_meters'],
                    'distance_miles' => $route['distance_meters'] ? round($route['distance_meters'] / 1609.344, 1) : null,
                    'polyline' => $route['polyline'],
                ] : null,
                'traffic_sections' => $routeData['traffic_sections'] ?? [],
                'fetched_at' => now()->toISOString(),
            ];
        }

        Cache::put($cacheKey, $results, now()->addMinutes(15));

        return response()->json($results);
    }

    public function refresh(Request $request)
    {
        return $this->etas($request->merge(['force' => true]));
    }

    public function geocode(Request $request)
    {
        $validated = $request->validate([
            'address' => 'required|string|max:500',
        ]);

        $coords = $this->tomTom->geocode($validated['address']);

        if (! $coords) {
            return response()->json(['error' => 'Could not geocode address'], 404);
        }

        return response()->json($coords);
    }
}
