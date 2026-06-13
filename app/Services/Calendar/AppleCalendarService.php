<?php

namespace App\Services\Calendar;

use App\Models\Calendar;
use Sabre\Dav\Client;
use Sabre\VObject;

class AppleCalendarService
{
    protected string $email;

    protected string $password;

    protected Client $client;

    protected string $baseUrl = 'https://caldav.icloud.com/';

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
            
            // If the home set is a full URL, update the client
            if (str_starts_with($calendarHomeSet, 'http')) {
                $parts = parse_url($calendarHomeSet);
                $newBase = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? 'caldav.icloud.com') . (isset($parts['port']) ? ':' . $parts['port'] : '') . '/';
                
                $this->client = new Client([
                    'baseUri' => $newBase,
                    'userName' => $this->email,
                    'password' => $this->password,
                ]);

                // Make the path relative to the new base
                $calendarHomeSet = ltrim($parts['path'] ?? $calendarHomeSet, '/');
            }

            // 3. List calendars and fetch events from them
            return $this->fetchEventsFromCalendars($calendarHomeSet);
        } catch (\Exception $e) {
            \Log::error('iCloud Sync Error (' . get_class($e) . '): '.$e->getMessage());

            return [];
        }
    }

    protected function getPrincipalUrl(): string
    {
        $response = $this->client->propFind('', [
            '{DAV:}current-user-principal',
        ], 0);

        $principal = $response['{DAV:}current-user-principal'] ?? '';

        if (is_array($principal)) {
            // Handle SabreDav's collection of properties (e.g. {DAV:}href)
            if (isset($principal[0]['value'])) {
                return (string) $principal[0]['value'];
            }
            return (string) reset($principal);
        }

        return (string) $principal;
    }

    protected function getCalendarHomeSet(string $principalUrl): string
    {
        $response = $this->client->propFind($principalUrl, [
            '{urn:ietf:params:xml:ns:caldav}calendar-home-set',
        ], 0);

        $homeSet = $response['{urn:ietf:params:xml:ns:caldav}calendar-home-set'] ?? '';

        if (is_array($homeSet)) {
            if (isset($homeSet[0]['value'])) {
                return (string) $homeSet[0]['value'];
            }
            return (string) reset($homeSet);
        }

        return (string) $homeSet;
    }

    protected function fetchEventsFromCalendars(string $calendarHomeSet): array
    {
        $calendars = $this->client->propFind($calendarHomeSet, [
            '{DAV:}displayname',
            '{urn:ietf:params:xml:ns:caldav}calendar-description',
        ], 1);

        $allEvents = [];

        foreach ($calendars as $path => $props) {
            if (isset($props['{DAV:}displayname'])) {
                $events = $this->fetchEventsFromCalendar($path);
                $allEvents = array_merge($allEvents, $events);
            }
        }

        return $allEvents;
    }

    protected function fetchEventsFromCalendar(string $calendarPath): array
    {
        $xml = '<?xml version="1.0" encoding="utf-8" ?>
<c:calendar-query xmlns:d="DAV:" xmlns:c="urn:ietf:params:xml:ns:caldav">
    <d:prop>
        <d:getetag />
        <c:calendar-data />
    </d:prop>
    <c:filter>
        <c:comp-filter name="VCALENDAR">
            <c:comp-filter name="VEVENT" />
        </c:comp-filter>
    </c:filter>
</c:calendar-query>';

        $url = $this->client->getAbsoluteUrl($calendarPath);

        $request = new \Sabre\HTTP\Request('REPORT', $url, [
            'Content-Type' => 'application/xml',
            'Depth' => '1',
        ], $xml);

        $response = $this->client->send($request);

        if ($response->getStatus() >= 400) {
            // Log error only for non-reminders/non-collections (which often 403/500)
            if ($response->getStatus() !== 403 && $response->getStatus() !== 500) {
                \Log::error("iCloud REPORT failed: " . $response->getStatus());
            }
            return [];
        }

        $xmlService = new \Sabre\Xml\Service();
        $data = $xmlService->parse($response->getBodyAsString());

        $events = [];
        if (!is_array($data)) return [];

        foreach ($data as $resource) {
            if ($resource['name'] !== '{DAV:}response') continue;

            $props = [];
            foreach ($resource['value'] as $val) {
                if ($val['name'] === '{DAV:}propstat') {
                    foreach ($val['value'] as $propstatVal) {
                        if ($propstatVal['name'] === '{DAV:}prop') {
                            foreach ($propstatVal['value'] as $prop) {
                                $props[$prop['name']] = $prop['value'];
                            }
                        }
                    }
                }
            }

            if (isset($props['{urn:ietf:params:xml:ns:caldav}calendar-data'])) {
                $vcal = VObject\Reader::read($props['{urn:ietf:params:xml:ns:caldav}calendar-data']);
                if (isset($vcal->VEVENT)) {
                    foreach ($vcal->VEVENT as $vevent) {
                        $start = $vevent->DTSTART->getDateTime();
                        $end = $vevent->DTEND ? $vevent->DTEND->getDateTime() : $start;
                        
                        $isAllDay = !isset($vevent->DTSTART['VALUE']) || (string)$vevent->DTSTART['VALUE'] === 'DATE';

                        $events[] = [
                            'id' => (string) $vevent->UID,
                            'title' => (string) $vevent->SUMMARY,
                            'start' => $isAllDay ? $start->format('Y-m-d') : $start->format(\DateTime::ISO8601),
                            'end' => $isAllDay ? $end->format('Y-m-d') : $end->format(\DateTime::ISO8601),
                            'all_day' => $isAllDay,
                            'provider' => 'apple',
                        ];
                    }
                }
            }
        }

        return $events;
    }
}
