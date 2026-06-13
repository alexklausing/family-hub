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
     * Fetch events from all configured iCal/Webcal URLs.
     */
    public function getEvents(): array
    {
        $allEvents = [];

        foreach ($this->urls as $url) {
            $url = trim($url);
            // Convert webcal:// to https://
            $url = str_replace('webcal://', 'https://', $url);

            try {
                $response = Http::get($url);

                if ($response->failed()) {
                    Log::error("Failed to fetch iCal feed from: {$url}");

                    continue;
                }

                $vcal = VObject\Reader::read($response->body());

                foreach ($vcal->VEVENT as $vevent) {
                    $allEvents[] = [
                        'id' => (string) ($vevent->UID ?? uniqid()),
                        'title' => (string) $vevent->SUMMARY,
                        'start' => $vevent->DTSTART->getDateTime()->format(\DateTime::ISO8601),
                        'end' => isset($vevent->DTEND)
                            ? $vevent->DTEND->getDateTime()->format(\DateTime::ISO8601)
                            : $vevent->DTSTART->getDateTime()->modify('+1 hour')->format(\DateTime::ISO8601),
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
