<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class LunchMenuController extends Controller
{
    /**
     * Return the weekly lunch menu for the configured school.
     *
     * Accepts optional ?date=YYYY-MM-DD to select the week containing that
     * date (defaults to the current date). The Nutrislice API is cached for
     * 6 hours to be polite to their servers.
     */
    public function index(Request $request)
    {
        $tenant = config('services.nutrislice.tenant');
        $school = config('services.nutrislice.school');

        if (! $tenant || ! $school) {
            return response()->json(['error' => 'Nutrislice configuration missing'], 500);
        }

        $date = $request->query('date')
            ? Carbon::parse($request->query('date'))
            : Carbon::now();

        $cacheKey = "lunch_menu_{$tenant}_{$school}_{$date->format('Y-m-d')}";

        $week = Cache::remember($cacheKey, 21600, function () use ($tenant, $school, $date) {
            $url = "https://{$tenant}.api.nutrislice.com/menu/api/weeks/school/{$school}/menu-type/lunch/{$date->format('Y/m/d')}/";

            $response = Http::timeout(15)->get($url);

            if ($response->failed() || ! is_array($response->json('days'))) {
                return null;
            }

            return $response->json();
        });

        if ($week === null) {
            return response()->json(['error' => 'Unable to load lunch menu'], 502);
        }

        return response()->json([
            'school' => config('services.nutrislice.school_name', $school),
            'start_date' => $week['start_date'] ?? $date->format('Y-m-d'),
            'days' => collect($week['days'] ?? [])->map(function ($day) {
                return $this->normalizeDay($day);
            })->values(),
        ]);
    }

    /**
     * Squash a raw Nutrislice day payload into a lightweight shape:
     * sections (e.g. "Hot Meal:") with the food item names beneath them.
     */
    private function normalizeDay(array $day): array
    {
        $sections = [];
        $current = null;
        $hasSchool = true;

        foreach ($day['menu_items'] ?? [] as $item) {
            if (($item['is_holiday'] ?? false) || ($item['text'] ?? '') === 'No School') {
                $hasSchool = false;

                continue;
            }

            if ($item['is_section_title'] ?? false) {
                $current = $item['text'] ?? 'Menu';
                $sections[$current] = ['name' => $current, 'items' => []];

                continue;
            }

            $foodName = $item['food']['name'] ?? null;
            if (! empty($foodName)) {
                $section = $current ?? 'Menu';
                if (! isset($sections[$section])) {
                    $sections[$section] = ['name' => $section, 'items' => []];
                }
                $sections[$section]['items'][] = $foodName;
            }
        }

        if ($hasSchool) {
            return [
                'date' => $day['date'],
                'sections' => array_values($sections),
            ];
        }

        return [
            'date' => $day['date'],
            'has_school' => false,
            'sections' => [],
        ];
    }
}
