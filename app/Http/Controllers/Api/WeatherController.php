<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class WeatherController extends Controller
{
    public function index(\Illuminate\Http\Request $request)
    {
        $lat = config('services.openweathermap.lat');
        $lon = config('services.openweathermap.lon');
        $apiKey = config('services.openweathermap.key');

        if (! $lat || ! $lon || ! $apiKey) {
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
                // Fallback to Open-Meteo for free 7-day, hour-by-hour forecast
                $omResponse = Http::get('https://api.open-meteo.com/v1/forecast', [
                    'latitude' => $lat,
                    'longitude' => $lon,
                    'current' => 'temperature_2m,relative_humidity_2m,apparent_temperature,weather_code,cloud_cover,wind_speed_10m,wind_direction_10m',
                    'hourly' => 'temperature_2m,apparent_temperature,precipitation_probability,weather_code',
                    'daily' => 'weather_code,temperature_2m_max,temperature_2m_min,sunrise,sunset',
                    'temperature_unit' => 'fahrenheit',
                    'wind_speed_unit' => 'mph',
                    'precipitation_unit' => 'inch',
                    'timeformat' => 'unixtime',
                    'timezone' => 'auto',
                ]);

                if ($omResponse->failed()) {
                    return null;
                }

                $om = $omResponse->json();

                $wmoToMain = function ($code) {
                    if ($code == 0) return 'Clear';
                    if (in_array($code, [1, 2, 3, 45, 48])) return 'Clouds';
                    if (in_array($code, [51, 53, 55, 56, 57])) return 'Drizzle';
                    if (in_array($code, [61, 63, 65, 66, 67, 80, 81, 82])) return 'Rain';
                    if (in_array($code, [71, 73, 75, 77, 85, 86])) return 'Snow';
                    if (in_array($code, [95, 96, 99])) return 'Thunderstorm';
                    return 'Clear';
                };

                // Map current
                $mappedCurrent = [
                    'dt' => $om['current']['time'],
                    'sunrise' => $om['daily']['sunrise'][0] ?? null,
                    'sunset' => $om['daily']['sunset'][0] ?? null,
                    'temp' => $om['current']['temperature_2m'],
                    'feels_like' => $om['current']['apparent_temperature'],
                    'humidity' => $om['current']['relative_humidity_2m'],
                    'clouds' => $om['current']['cloud_cover'],
                    'wind_speed' => $om['current']['wind_speed_10m'],
                    'wind_deg' => $om['current']['wind_direction_10m'],
                    'weather' => [
                        ['main' => $wmoToMain($om['current']['weather_code']), 'description' => $wmoToMain($om['current']['weather_code'])]
                    ],
                ];

                // Map hourly
                $mappedHourly = [];
                $now = time();
                foreach ($om['hourly']['time'] as $index => $time) {
                    if ($time >= $now - 3600) {
                        $mappedHourly[] = [
                            'dt' => $time,
                            'temp' => $om['hourly']['temperature_2m'][$index],
                            'feels_like' => $om['hourly']['apparent_temperature'][$index],
                            'pop' => ($om['hourly']['precipitation_probability'][$index] ?? 0) / 100,
                            'weather' => [
                                ['main' => $wmoToMain($om['hourly']['weather_code'][$index]), 'description' => $wmoToMain($om['hourly']['weather_code'][$index])]
                            ],
                        ];
                    }
                }

                // Map daily
                $mappedDaily = [];
                foreach ($om['daily']['time'] as $index => $time) {
                    $mappedDaily[] = [
                        'dt' => $time,
                        'temp' => [
                            'min' => $om['daily']['temperature_2m_min'][$index],
                            'max' => $om['daily']['temperature_2m_max'][$index],
                            'day' => ($om['daily']['temperature_2m_max'][$index] + $om['daily']['temperature_2m_min'][$index]) / 2,
                        ],
                        'weather' => [
                            ['main' => $wmoToMain($om['daily']['weather_code'][$index]), 'description' => $wmoToMain($om['daily']['weather_code'][$index])]
                        ],
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

        if ($request->query('test_alerts') == '1') {
            $alerts = [
                [
                    'id' => 'test-tornado-1',
                    'properties' => [
                        'event' => 'Tornado Warning',
                        'headline' => 'Tornado Warning issued June 22 at 5:00PM EDT until June 22 at 6:00PM EDT by NWS.',
                        'description' => "WUUS52 KTAE 222104\nSVRTAE\nALC031-045-222145-\n/O.NEW.KTAE.SV.W.0166.260622T2104Z-260622T2145Z/\n\nBULLETIN - IMMEDIATE BROADCAST REQUESTED\nSEVERE THUNDERSTORM WARNING\nNATIONAL WEATHER SERVICE TALLAHASSEE FL\n404 PM CDT MON JUN 22 2026\n\nTHE NATIONAL WEATHER SERVICE IN TALLAHASSEE HAS ISSUED A\n\n* SEVERE THUNDERSTORM WARNING FOR...\n  DALE COUNTY IN SOUTHEASTERN ALABAMA...\n  NORTHERN COFFEE COUNTY IN SOUTHEASTERN ALABAMA...\n\n* UNTIL 445 PM CDT.\n\n* AT 404 PM CDT, A SEVERE THUNDERSTORM WAS LOCATED 8 MILES NORTHEAST\n  OF ELBA, MOVING EAST AT 40 MPH.\n\n  HAZARD...60 MPH WIND GUSTS.\n\n  SOURCE...RADAR INDICATED.\n\n  IMPACT...EXPECT DAMAGE TO ROOFS, SIDING, AND TREES.\n\n* LOCATIONS IMPACTED INCLUDE...\n  ELBA, FORT RUCKER, OZARK, ENTERPRISE, NEWTON, NEW BROCKTON, ARITON,\n  LEE, MIXONS CROSSROADS, HUNT FIELD, WATERFORD, CARL FOLSOM A/P,\n  HOOPER STAGE FIELD, CAMP HUMMING HILLS, CLINTONVILLE, EWELL,\n  ROETON, DALE COUNTY LAKE, CAMP ALAFLO BSA, AND PHILLIPS CROSSROADS.",
                        'instruction' => 'TAKE COVER NOW! Move to a basement or an interior room on the lowest floor of a sturdy building.',
                        'severity' => 'Extreme',
                        'urgency' => 'Immediate'
                    ]
                ],
                [
                    'id' => 'test-watch-2',
                    'properties' => [
                        'event' => 'Severe Thunderstorm Watch',
                        'headline' => 'Severe Thunderstorm Watch issued June 22 at 4:30PM EDT until June 22 at 9:00PM EDT.',
                        'description' => "WWUS62 KTAE 222030\nWWA...\nSEVERE THUNDERSTORM WATCH OUTLINE UPDATE FOR WS 225\nNWS STORM PREDICTION CENTER NORMAN OK\n430 PM EDT MON JUN 22 2026\n\nSEVERE THUNDERSTORM WATCH 225 IS IN EFFECT UNTIL 900 PM EDT\nFOR THE FOLLOWING LOCATIONS...",
                        'instruction' => 'Be prepared for severe weather including damaging winds and large hail.',
                        'severity' => 'Severe',
                        'urgency' => 'Expected'
                    ]
                ]
            ];
        } else {
            // Fetch Alerts from NWS (US only)
            $alerts = Cache::remember('weather_alerts', 300, function () use ($lat, $lon) {
                $response = Http::withHeaders(['User-Agent' => 'FamilyHub/1.0'])
                    ->get('https://api.weather.gov/alerts/active', [
                        'point' => "{$lat},{$lon}",
                    ]);

                return $response->json()['features'] ?? [];
            });
        }

        // Fetch Air Quality from Open-Meteo
        $airQuality = Cache::remember('air_quality', 1800, function () use ($lat, $lon) {
            $response = Http::get('https://air-quality-api.open-meteo.com/v1/air-quality', [
                'latitude' => $lat,
                'longitude' => $lon,
                'current' => 'us_aqi',
            ]);

            if ($response->failed()) {
                return null;
            }

            return $response->json()['current']['us_aqi'] ?? null;
        });

        // Fetch ISS Data
        $issData = Cache::remember('iss_data', 60, function () {
            try {
                $response = Http::timeout(3)->get('https://api.wheretheiss.at/v1/satellites/25544');
                if ($response->successful()) {
                    $data = $response->json();
                    return [
                        'latitude' => number_format($data['latitude'], 4, '.', ''),
                        'longitude' => number_format($data['longitude'], 4, '.', ''),
                        'altitude' => round($data['altitude'] * 0.621371),
                        'velocity' => number_format(round($data['velocity'] * 0.621371)),
                        'visibility' => $data['visibility']
                    ];
                }
            } catch (\Exception $e) {
                // If it times out or fails, just return null so it doesn't crash the dashboard
            }
            return null;
        });

        // Fetch Space Coast Launch
        $launchData = Cache::remember('space_launch', 3600, function () {
            try {
                // Location IDs: 12 (Cape Canaveral), 27 (Kennedy Space Center)
                $response = Http::timeout(5)->get('https://ll.thespacedevs.com/2.2.0/launch/upcoming/?limit=1&location__ids=12,27');
                if ($response->successful()) {
                    $data = $response->json();
                    if (!empty($data['results'])) {
                        $launch = $data['results'][0];
                        $netDate = strtotime($launch['net']);
                        return [
                            'name' => $launch['name'],
                            'provider' => $launch['launch_service_provider']['name'] ?? 'Unknown',
                            'pad' => $launch['pad']['name'] ?? 'Unknown Pad',
                            'date' => date('n/j/Y', $netDate),
                            'time' => date('g:i A', $netDate),
                            'status' => $launch['status']['name'] ?? 'Unknown',
                            'image' => $launch['image']
                        ];
                    }
                }
            } catch (\Exception $e) {
                // Ignore timeout
            }
            return null;
        });

        return response()->json([
            'weather' => $weather,
            'alerts' => $alerts,
            'air_quality' => $airQuality,
            'iss' => $issData,
            'launch' => $launchData,
            'location' => [
                'lat' => $lat,
                'lon' => $lon,
            ],
            'apiKey' => $apiKey,
        ]);
    }
}
