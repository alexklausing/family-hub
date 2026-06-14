<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class WeatherController extends Controller
{
    public function index()
    {
        $lat = config('services.openweathermap.lat');
        $lon = config('services.openweathermap.lon');
        $apiKey = config('services.openweathermap.key');

        if (!$lat || !$lon || !$apiKey) {
            return response()->json(['error' => 'Weather configuration missing'], 500);
        }

        // Fetch Weather from OpenWeatherMap (One Call 3.0 or similar)
        // We'll cache it for 15 minutes to avoid rate limits
        $weather = Cache::remember('weather_data', 900, function () use ($lat, $lon, $apiKey) {
            $response = Http::get('https://api.openweathermap.org/data/3.0/onecall', [
                'lat' => $lat,
                'lon' => $lon,
                'appid' => $apiKey,
                'units' => 'imperial',
                'exclude' => 'minutely',
            ]);
            
            if ($response->failed()) {
                // Try fallback to standard 5-day if OneCall 3.0 is not subscribed
                $currentResponse = Http::get('https://api.openweathermap.org/data/2.5/weather', [
                    'lat' => $lat,
                    'lon' => $lon,
                    'appid' => $apiKey,
                    'units' => 'imperial',
                ]);

                $forecastResponse = Http::get('https://api.openweathermap.org/data/2.5/forecast', [
                    'lat' => $lat,
                    'lon' => $lon,
                    'appid' => $apiKey,
                    'units' => 'imperial',
                ]);

                if ($currentResponse->failed()) {
                    return null;
                }

                $current = $currentResponse->json();
                $forecast = $forecastResponse->json();

                // Map current weather to OneCall format
                $mappedCurrent = [
                    'dt' => $current['dt'] ?? time(),
                    'temp' => $current['main']['temp'] ?? 0,
                    'feels_like' => $current['main']['feels_like'] ?? 0,
                    'pressure' => $current['main']['pressure'] ?? 0,
                    'humidity' => $current['main']['humidity'] ?? 0,
                    'clouds' => $current['clouds']['all'] ?? 0,
                    'visibility' => $current['visibility'] ?? 0,
                    'wind_speed' => $current['wind']['speed'] ?? 0,
                    'wind_deg' => $current['wind']['deg'] ?? 0,
                    'weather' => $current['weather'] ?? [],
                ];

                // Map forecast to OneCall hourly format
                $mappedHourly = array_map(function ($item) {
                    return [
                        'dt' => $item['dt'],
                        'temp' => $item['main']['temp'],
                        'feels_like' => $item['main']['feels_like'],
                        'pressure' => $item['main']['pressure'],
                        'humidity' => $item['main']['humidity'],
                        'clouds' => $item['clouds']['all'] ?? 0,
                        'visibility' => $item['visibility'] ?? 0,
                        'wind_speed' => $item['wind']['speed'],
                        'wind_deg' => $item['wind']['deg'],
                        'weather' => $item['weather'],
                        'pop' => $item['pop'] ?? 0,
                    ];
                }, $forecast['list'] ?? []);

                // Generate basic daily outlook from 5-day forecast
                $mappedDaily = [];
                $dailyGroups = [];
                foreach ($forecast['list'] ?? [] as $item) {
                    $date = date('Y-m-d', $item['dt']);
                    $dailyGroups[$date][] = $item;
                }

                foreach ($dailyGroups as $date => $items) {
                    $temps = array_column(array_column($items, 'main'), 'temp');
                    $midIndex = (int) floor(count($items) / 2);
                    $mappedDaily[] = [
                        'dt' => $items[0]['dt'],
                        'temp' => [
                            'min' => min($temps),
                            'max' => max($temps),
                            'day' => $items[$midIndex]['main']['temp'],
                        ],
                        'weather' => $items[$midIndex]['weather'],
                    ];
                }

                return [
                    'current' => $mappedCurrent,
                    'hourly' => $mappedHourly,
                    'daily' => $mappedDaily,
                ];
            }

            return $response->json();
        });

        // Fetch Alerts from NWS (US only)
        $alerts = Cache::remember('weather_alerts', 300, function () use ($lat, $lon) {
            $response = Http::withHeaders(['User-Agent' => 'FamilyHub/1.0'])
                ->get("https://api.weather.gov/alerts/active", [
                    'point' => "{$lat},{$lon}"
                ]);
            
            return $response->json()['features'] ?? [];
        });

        return response()->json([
            'weather' => $weather,
            'alerts' => $alerts,
            'location' => [
                'lat' => $lat,
                'lon' => $lon
            ],
            'apiKey' => $apiKey
        ]);
    }
}
