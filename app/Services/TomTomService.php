<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TomTomService
{
    protected string $baseUrl = 'https://api.tomtom.com';

    protected function apiKey(): ?string
    {
        return config('services.tomtom.key');
    }

    /**
     * Calculate a traffic-aware route between two points.
     *
     * @return array{route: ?array, traffic_sections: array|array{}}
     */
    public function getRoute(float $originLat, float $originLon, float $destLat, float $destLon): array
    {
        $apiKey = $this->apiKey();
        if (! $apiKey) {
            Log::error('TomTom API key not configured.');

            return ['route' => null, 'traffic_sections' => []];
        }

        try {
            $response = Http::timeout(10)
                ->get("{$this->baseUrl}/routing/1/calculateRoute/{$originLat},{$originLon}:{$destLat},{$destLon}/json", [
                    'key' => $apiKey,
                    'traffic' => 'true',
                    'routeType' => 'fastest',
                    'computeTravelTimeFor' => 'all',
                    'sectionType' => 'traffic',
                    'language' => 'en-US',
                ]);

            if (! $response->successful()) {
                Log::error('TomTom route failed: '.$response->status().' '.$response->body());

                return ['route' => null, 'traffic_sections' => []];
            }

            $data = $response->json();
            $route = $data['routes'][0] ?? null;
            if (! $route) {
                return ['route' => null, 'traffic_sections' => []];
            }

            $summary = $route['summary'] ?? [];

            return [
                'route' => [
                    'travel_time_seconds' => $summary['travelTimeInSeconds'] ?? null,
                    'traffic_delay_seconds' => $summary['trafficDelayInSeconds'] ?? 0,
                    'no_traffic_time_seconds' => $summary['noTrafficTravelTimeInSeconds'] ?? $summary['travelTimeInSeconds'] ?? null,
                    'historic_traffic_time_seconds' => $summary['historicTrafficTravelTimeInSeconds'] ?? null,
                    'distance_meters' => $summary['lengthInMeters'] ?? null,
                    'polyline' => $route['legs'][0]['points'] ?? [],
                ],
                'traffic_sections' => $this->extractTrafficSections($route['sections'] ?? []),
            ];
        } catch (\Exception $e) {
            Log::error('TomTom route exception: '.$e->getMessage());

            return ['route' => null, 'traffic_sections' => []];
        }
    }

    /**
     * Geocode an address to lat/lon coordinates.
     *
     * @return array{lat: ?float, lon: ?float}|null
     */
    public function geocode(string $address): ?array
    {
        $apiKey = $this->apiKey();
        if (! $apiKey || empty(trim($address))) {
            return null;
        }

        try {
            $response = Http::timeout(10)
                ->get($this->baseUrl.'/search/2/geocode/'.rawurlencode($address).'.json', [
                    'key' => $apiKey,
                    'language' => 'en-US',
                ]);

            if (! $response->successful()) {
                Log::error('TomTom geocode failed: '.$response->status().' '.$response->body());

                return null;
            }

            $result = $response->json('results.0');
            if (! $result) {
                return null;
            }

            return [
                'lat' => $result['position']['lat'] ?? null,
                'lon' => $result['position']['lon'] ?? null,
            ];
        } catch (\Exception $e) {
            Log::error('TomTom geocode exception: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Extract traffic incident sections from a route response.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function extractTrafficSections(array $sections): array
    {
        $traffic = [];
        foreach ($sections as $section) {
            if (($section['type'] ?? $section['sectionType'] ?? '') !== 'TRAFFIC') {
                continue;
            }

            $traffic[] = [
                'start_point_index' => $section['startPointIndex'] ?? null,
                'end_point_index' => $section['endPointIndex'] ?? null,
                'category' => $section['simpleCategory'] ?? 'OTHER',
                'effective_speed_kmh' => $section['effectiveSpeedInKmh'] ?? null,
                'delay_seconds' => $section['delayInSeconds'] ?? 0,
                'magnitude' => $section['magnitudeOfDelay'] ?? 0,
            ];
        }

        return $traffic;
    }
}
