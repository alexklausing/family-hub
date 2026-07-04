<?php

namespace App\Services\Calendar;

use App\Models\Calendar;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Sabre\DAV\Client;
use Sabre\HTTP\Request;
use Sabre\VObject;
use Sabre\Xml\Service;

class AppleCalendarService
{
    protected string $email;

    protected string $password;

    protected Client $client;

    protected string $baseUrl = 'https://caldav.icloud.com/';

    public function __construct()
    {
        // Default to environment variables if not specified in method calls
        $this->email = config('services.apple.email') ?? '';
        $this->password = config('services.apple.password') ?? '';

        $this->initClient($this->email, $this->password);
    }

    protected function initClient(?string $email, ?string $password): void
    {
        $this->email = $email ?? config('services.apple.email') ?? '';
        $this->password = $password ?? config('services.apple.password') ?? '';

        $this->client = new Client([
            'baseUri' => $this->baseUrl,
            'userName' => $this->email,
            'password' => $this->password,
        ]);
    }

    /**
     * Test the CalDAV connection synchronously.
     */
    public function testConnection(string $email, string $password): bool
    {
        $this->initClient($email, $password);

        try {
            $response = $this->client->propFind('', ['{DAV:}current-user-principal'], 0);

            return ! empty($response['{DAV:}current-user-principal']);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Fetch all events from the user's iCloud calendars.
     */
    public function getEvents(?string $email = null, ?string $password = null, ?string $calendarPath = null): array
    {
        if ($email || $password) {
            $this->initClient($email, $password);
        }

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
                $newBase = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? 'caldav.icloud.com').(isset($parts['port']) ? ':'.$parts['port'] : '').'/';

                $this->client = new Client([
                    'baseUri' => $newBase,
                    'userName' => $this->email,
                    'password' => $this->password,
                ]);

                // Make the path relative to the new base
                $calendarHomeSet = ltrim($parts['path'] ?? $calendarHomeSet, '/');
            }

            if ($calendarPath) {
                return $this->fetchEventsFromCalendar($calendarPath);
            }

            // 3. List calendars and fetch events from them
            return $this->fetchEventsFromCalendars($calendarHomeSet);
        } catch (\Exception $e) {
            \Log::error('iCloud Sync Error ('.get_class($e).'): '.$e->getMessage());

            return [];
        }
    }

    /**
     * Fetch a list of calendars available on the iCloud account.
     */
    public function getCalendars(string $email, string $password): array
    {
        $this->initClient($email, $password);

        try {
            $principalUrl = $this->getPrincipalUrl();
            $calendarHomeSet = $this->getCalendarHomeSet($principalUrl);

            if (str_starts_with($calendarHomeSet, 'http')) {
                $parts = parse_url($calendarHomeSet);
                $newBase = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? 'caldav.icloud.com').(isset($parts['port']) ? ':'.$parts['port'] : '').'/';
                $this->client = new Client([
                    'baseUri' => $newBase,
                    'userName' => $this->email,
                    'password' => $this->password,
                ]);
                $calendarHomeSet = ltrim($parts['path'] ?? $calendarHomeSet, '/');
            }

            $calendars = $this->client->propFind($calendarHomeSet, [
                '{DAV:}displayname',
                '{urn:ietf:params:xml:ns:caldav}calendar-description',
            ], 1);

            $result = [];
            foreach ($calendars as $path => $props) {
                // Skip the parent calendar-home-set container itself
                if (rtrim($path, '/') === rtrim($calendarHomeSet, '/')) {
                    continue;
                }

                $basename = basename(rtrim($path, '/'));
                // Skip internal CalDAV collections
                if (in_array(strtolower($basename), ['inbox', 'outbox', 'notification'])) {
                    continue;
                }

                $name = null;
                if (isset($props['{DAV:}displayname'])) {
                    $name = is_array($props['{DAV:}displayname']) && isset($props['{DAV:}displayname'][0]['value'])
                        ? $props['{DAV:}displayname'][0]['value']
                        : $props['{DAV:}displayname'];
                }

                if (empty($name) && isset($props['{urn:ietf:params:xml:ns:caldav}calendar-description'])) {
                    $desc = is_array($props['{urn:ietf:params:xml:ns:caldav}calendar-description']) && isset($props['{urn:ietf:params:xml:ns:caldav}calendar-description'][0]['value'])
                        ? $props['{urn:ietf:params:xml:ns:caldav}calendar-description'][0]['value']
                        : $props['{urn:ietf:params:xml:ns:caldav}calendar-description'];
                    if (is_string($desc) && !empty($desc)) {
                        $name = $desc;
                    }
                }

                if (empty($name)) {
                    $name = ucfirst($basename); // Fallback to folder name
                }

                if (is_string($name) && ! empty($name)) {
                    $result[] = [
                        'name' => $name,
                        'path' => $path,
                    ];
                }
            }

            return $result;
        } catch (\Exception $e) {
            return [];
        }
    }

    public function createEvent(string $email, string $password, string $calendarPath, array $eventDetails): bool
    {
        $this->initClient($email, $password);

        try {
            $principalUrl = $this->getPrincipalUrl();
            $calendarHomeSet = $this->getCalendarHomeSet($principalUrl);

            if (str_starts_with($calendarHomeSet, 'http')) {
                $parts = parse_url($calendarHomeSet);
                $newBase = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? 'caldav.icloud.com').(isset($parts['port']) ? ':'.$parts['port'] : '').'/';
                $this->client = new Client([
                    'baseUri' => $newBase,
                    'userName' => $this->email,
                    'password' => $this->password,
                ]);
            }

            $uuid = Str::uuid()->toString();
            $now = gmdate("Ymd\THis\Z");

            $isAllDay = filter_var($eventDetails['all_day'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $tz = $eventDetails['timezone'] ?? 'UTC';
            $title = $eventDetails['title'] ?? 'New Event';
            $description = $eventDetails['description'] ?? '';

            if ($isAllDay) {
                $start = Carbon::parse($eventDetails['start'])->format('Ymd');
                // All-day end dates in CalDAV must be exclusive (+1 day) if it's a 1-day event
                // Usually the frontend sends the exact same day for start/end if it's 1 day
                $end = Carbon::parse($eventDetails['end']);
                if (Carbon::parse($eventDetails['start'])->isSameDay($end)) {
                    $end->addDay();
                }
                $endStr = $end->format('Ymd');

                $dtStart = "DTSTART;VALUE=DATE:{$start}";
                $dtEnd = "DTEND;VALUE=DATE:{$endStr}";
            } else {
                $startObj = Carbon::parse($eventDetails['start'], $tz)->setTimezone('UTC');
                $endObj = Carbon::parse($eventDetails['end'], $tz)->setTimezone('UTC');

                $start = $startObj->format('Ymd\THis\Z');
                $end = $endObj->format('Ymd\THis\Z');

                $dtStart = "DTSTART:{$start}";
                $dtEnd = "DTEND:{$end}";
            }

            $vcal = "BEGIN:VCALENDAR\r\n";
            $vcal .= "VERSION:2.0\r\n";
            $vcal .= "PRODID:-//FamilyHub//EN\r\n";
            $vcal .= "BEGIN:VEVENT\r\n";
            $vcal .= "UID:{$uuid}\r\n";
            $vcal .= "DTSTAMP:{$now}\r\n";
            $vcal .= "{$dtStart}\r\n";
            $vcal .= "{$dtEnd}\r\n";
            $vcal .= "SUMMARY:{$title}\r\n";
            if (! empty($description)) {
                // Escape newlines for VCALENDAR
                $escapedDesc = str_replace(["\r\n", "\n", "\r"], '\\n', $description);
                $vcal .= "DESCRIPTION:{$escapedDesc}\r\n";
            }
            $vcal .= "END:VEVENT\r\n";
            $vcal .= "END:VCALENDAR\r\n";

            $url = $this->client->getAbsoluteUrl(rtrim($calendarPath, '/').'/'.$uuid.'.ics');

            $request = new Request('PUT', $url, [
                'Content-Type' => 'text/calendar; charset=utf-8',
                'If-None-Match' => '*',
            ], $vcal);

            $response = $this->client->send($request);

            if ($response->getStatus() >= 200 && $response->getStatus() < 300) {
                return true;
            }

            \Log::error('iCloud Create Event failed: '.$response->getStatus().' - '.$response->getBodyAsString());

            return false;
        } catch (\Exception $e) {
            \Log::error('iCloud Sync Error ('.get_class($e).'): '.$e->getMessage());

            return false;
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

        $request = new Request('REPORT', $url, [
            'Content-Type' => 'application/xml',
            'Depth' => '1',
        ], $xml);

        $response = $this->client->send($request);

        if ($response->getStatus() >= 400) {
            // Log error only for non-reminders/non-collections (which often 403/500)
            if ($response->getStatus() !== 403 && $response->getStatus() !== 500) {
                \Log::error('iCloud REPORT failed: '.$response->getStatus());
            }

            return [];
        }

        $xmlService = new Service;
        $data = $xmlService->parse($response->getBodyAsString());

        $events = [];
        if (! is_array($data)) {
            return [];
        }

        foreach ($data as $resource) {
            if ($resource['name'] !== '{DAV:}response') {
                continue;
            }

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

                        $isAllDay = ! $vevent->DTSTART->hasTime();

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
