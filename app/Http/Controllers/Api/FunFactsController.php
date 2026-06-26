<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class FunFactsController extends Controller
{
    public function index()
    {
        $tz = 'America/New_York';
        $now = now()->timezone($tz);
        $month = $now->format('n');
        $day = $now->format('j');
        $cacheKey = "fun_facts_v4_{$month}_{$day}";

        $data = Cache::remember($cacheKey, now()->addHours(24), function () use ($month, $day, $now) {
            
            $nationalDay = "National Fun Day";
            $historyFact = "Today is a great day to learn something new!";
            $historyYear = null;
            
            // 1. Get Holiday from date.nager.at API (User requested)
            $nationalDay = null;
            try {
                // Fetch holidays for the current year in the US
                $year = $now->format('Y');
                $response = Http::timeout(5)->get("https://date.nager.at/api/v3/PublicHolidays/{$year}/US");
                
                if ($response->successful()) {
                    $holidays = $response->json();
                    $currentDateString = $now->format('Y-m-d');
                    
                    foreach ($holidays as $h) {
                        if (isset($h['date']) && $h['date'] === $currentDateString) {
                            $nationalDay = $h['name'] ?? $h['localName'];
                            break;
                        }
                    }
                }
            } catch (\Exception $e) {
                // Ignore and keep null
            }

            // 2. Get Fun Fact from RapidAPI
            $funFact = "Today is a great day to learn something new!";
            try {
                $response = Http::withHeaders([
                    'x-rapidapi-key' => '33fe6a113fmsh821c04223ddb263p1eef51jsn2556c9a041ac',
                    'x-rapidapi-host' => 'world-fun-facts-all-languages-support.p.rapidapi.com'
                ])->timeout(5)->get("https://world-fun-facts-all-languages-support.p.rapidapi.com/fact.php?lang=en&uuid=df6dae1dba517a24b85a3dccca293eaa");
                
                if ($response->successful()) {
                    $factData = $response->json();
                    if (isset($factData['text'])) {
                        $funFact = $factData['text'];
                    }
                }
            } catch (\Exception $e) {
                // Ignore and use fallback
            }

            // 3. Get Word of the Day
            $wordOfTheDay = ["en" => "Hello", "fr" => "Bonjour", "es" => "Hola"];
            // Adjust path for Laravel 11's default storage/app/private vs storage/app
            $dictPath = storage_path('app/words_of_the_day.json');
            if (file_exists($dictPath)) {
                $wordsData = json_decode(file_get_contents($dictPath), true);
                if (isset($wordsData[(string)$month][(string)$day])) {
                    $wordOfTheDay = $wordsData[(string)$month][(string)$day];
                }
            }

            return [
                'national_day' => $nationalDay,
                'fun_fact' => $funFact,
                'word_of_the_day' => $wordOfTheDay,
                'date_formatted' => $now->format('F jS')
            ];
        });

        return response()->json($data);
    }
}
