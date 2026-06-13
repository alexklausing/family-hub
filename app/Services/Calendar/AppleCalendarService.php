<?php

namespace App\Services\Calendar;

use Sabre\Dav\Client;
use Sabre\VObject;

class AppleCalendarService
{
    protected string $email;

    protected string $password;

    protected Client $client;

    protected string $baseUrl = 'https://caldav.icloud.com';

    public function __construct()
    {
        $this->email = config('services.apple.email') ?? '';
        $this->password = config('services.apple.password') ?? '';

        $this->client = new Client([
            'baseUri' => $this->baseUrl,
            'userName' => $this->email,
            'password' => $this->password,
        ]);
    }

    /**
     * Fetch all events from the user's iCloud calendars.
     */
    public function getEvents(): array
    {
        if (empty($this->email) || empty($this->password)) {
            return [];
        }

        try {
            // 1. Discover the principal URL
            $principalUrl = $this->getPrincipalUrl();

            // 2. Discover the calendar home set
            $calendarHomeSet = $this->getCalendarHomeSet($principalUrl);

            // 3. List calendars and fetch events from them
            return $this->fetchEventsFromCalendars($calendarHomeSet);
        } catch (\Exception $e) {
            \Log::error('iCloud Sync Error: '.$e->getMessage());

            return [];
        }
    }

    protected function getPrincipalUrl(): string
    {
        $response = $this->client->propFind('', [
            '{DAV:}current-user-principal',
        ], 0);

        return $response['{DAV:}current-user-principal'] ?? '';
    }

    protected function getCalendarHomeSet(string $principalUrl): string
    {
        $response = $this->client->propFind($principalUrl, [
            '{urn:ietf:params:xml:ns:caldav}calendar-home-set',
        ], 0);

        return $response['{urn:ietf:params:xml:ns:caldav}calendar-home-set'] ?? '';
    }

    protected function fetchEventsFromCalendars(string $calendarHomeSet): array
    {
        $calendars = $this->client->propFind($calendarHomeSet, [
            '{DAV:}displayname',
            '{urn:ietf:params:xml:ns:caldav}calendar-description',
        ], 1);

        $allEvents = [];

        foreach ($calendars as $path => $props) {
            // Filter for actual calendars (they usually have a displayname)
            if (isset($props['{DAV:}displayname'])) {
                $events = $this->fetchEventsFromCalendar($path);
                $allEvents = array_merge($allEvents, $events);
            }
        }

        return $allEvents;
    }

    protected function fetchEventsFromCalendar(string $calendarPath): array
    {
        // Simple report to get events
        $response = $this->client->report($calendarPath, [
            '{urn:ietf:params:xml:ns:caldav}calendar-query' => [
                'xmlns:d' => 'DAV:',
                'xmlns:c' => 'urn:ietf:params:xml:ns:caldav',
                'c:prop' => [
                    'd:getetag' => '',
                    'c:calendar-data' => '',
                ],
                'c:filter' => [
                    'c:comp-filter' => [
                        'name' => 'VCALENDAR',
                        'c:comp-filter' => [
                            'name' => 'VEVENT',
                        ],
                    ],
                ],
            ],
        ]);

        $events = [];
        foreach ($response as $path => $props) {
            if (isset($props['{urn:ietf:params:xml:ns:caldav}calendar-data'])) {
                $vcal = VObject\Reader::read($props['{urn:ietf:params:xml:ns:caldav}calendar-data']);
                foreach ($vcal->VEVENT as $vevent) {
                    $events[] = [
                        'id' => (string) $vevent->UID,
                        'title' => (string) $vevent->SUMMARY,
                        'start' => $vevent->DTSTART->getDateTime()->format(\DateTime::ISO8601),
                        'end' => $vevent->DTEND->getDateTime()->format(\DateTime::ISO8601),
                        'provider' => 'apple',
                    ];
                }
            }
        }

        return $events;
    }
}
