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
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
                    'Accept' => 'text/calendar,text/plain,application/octet-stream',
                    'Accept-Language' => 'en-US,en;q=0.9',
                    'Cache-Control' => 'no-cache',
                    'Pragma' => 'no-cache',
                ])->timeout(45)->get($url);

                if ($response->status() === 429) {
                    Log::warning("Rate limited (429) while fetching iCal feed: {$url}. Skipping for now.");

                    continue;
                }

                if ($response->failed()) {
                    Log::error("Failed to fetch iCal feed from: {$url}. Status: ".$response->status());

                    continue;
                }

                $body = $response->body();
                if (empty($body) || ! str_contains($body, 'BEGIN:VCALENDAR')) {
                    Log::error("Invalid iCal response from: {$url}. Response length: ".strlen($body));

                    continue;
                }

                $vcal = VObject\Reader::read($body);

                if (isset($vcal->VEVENT)) {
                    foreach ($vcal->VEVENT as $vevent) {
                        $isAllDay = ! $vevent->DTSTART->hasTime();
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
                }
            } catch (\Exception $e) {
                Log::error("iCal Parse Error for {$url}: ".$e->getMessage());
            }
        }

        return $allEvents;
    }
}
