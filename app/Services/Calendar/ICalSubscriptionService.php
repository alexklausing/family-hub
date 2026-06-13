<?php

namespace App\Services\Calendar;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Sabre\VObject;

class ICalSubscriptionService
{
    protected array $urls;

    public function __construct()
    {
        $urls = config('services.calendar.subscriptions');
        $this->urls = $urls ? explode(',', $urls) : [];
    }

    /**
     * Fetch events from all configured iCal/Webcal URLs, or a specific one.
     */
    public function getEvents(?string $specificUrl = null): array
    {
        $allEvents = [];
        $urlsToFetch = $specificUrl ? [$specificUrl] : $this->urls;

        foreach ($urlsToFetch as $url) {
            $url = trim($url);
            // Convert webcal:// to https://
            $url = str_replace('webcal://', 'https://', $url);

            try {
                $response = Http::withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36'
                ])->get($url);

                if ($response->failed()) {
                    Log::error("Failed to fetch iCal feed from: {$url}. Status: " . $response->status());

                    continue;
                }

                $vcal = VObject\Reader::read($response->body());

                foreach ($vcal->VEVENT as $vevent) {
                    $isAllDay = !isset($vevent->DTSTART['VALUE']) || (string)$vevent->DTSTART['VALUE'] === 'DATE';
                    $start = $vevent->DTSTART->getDateTime();
                    $end = isset($vevent->DTEND) ? $vevent->DTEND->getDateTime() : (clone $start)->modify('+1 hour');

                    $allEvents[] = [
                        'id' => (string) ($vevent->UID ?? uniqid()),
                        'title' => (string) $vevent->SUMMARY,
                        'start' => $isAllDay ? $start->format('Y-m-d') : $start->format(\DateTime::ISO8601),
                        'end' => $isAllDay ? $end->format('Y-m-d') : $end->format(\DateTime::ISO8601),
                        'all_day' => $isAllDay,
                        'provider' => 'ical',
                        'feed_url' => $url,
                    ];
                }
            } catch (\Exception $e) {
                Log::error("iCal Parse Error for {$url}: ".$e->getMessage());
            }
        }

        return $allEvents;
    }
}
