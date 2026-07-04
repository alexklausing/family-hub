<?php
// Mock SabreDAV response array format
$calendarHomeSet = '/path/to/home/';
$calendars = [
    '/path/to/home/' => ['{DAV:}displayname' => 'Personal'],
    '/path/to/home/cal1/' => ['{DAV:}displayname' => 'Work'],
    '/path/to/home/cal2/' => ['{urn:ietf:params:xml:ns:caldav}calendar-description' => 'A&S Family Calendar'],
    '/path/to/home/inbox/' => [],
];

$result = [];
foreach ($calendars as $path => $props) {
    if (rtrim($path, '/') === rtrim($calendarHomeSet, '/')) {
        continue;
    }
    $basename = basename(rtrim($path, '/'));
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
        $name = ucfirst($basename); // Fallback
    }

    if (is_string($name) && ! empty($name)) {
        $result[] = [
            'name' => $name,
            'path' => $path,
        ];
    }
}
print_r($result);
