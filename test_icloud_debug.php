<?php
require __DIR__ . '/vendor/autoload.php';

use Sabre\DAV\Client;

$email = 'alex.klausing@icloud.com';
$password = 'rpkr-lige-tncy-cdez';

echo "Querying iCloud...\n";

$client = new Client([
    'baseUri' => 'https://caldav.icloud.com/',
    'userName' => $email,
    'password' => $password,
]);

try {
    $props = $client->propFind('/', ['{DAV:}current-user-principal'], 0);
    $principalUrl = $props['{DAV:}current-user-principal'][0]['value'] ?? $props['{DAV:}current-user-principal'];
    echo "Principal URL: $principalUrl\n";

    $homeSetProps = $client->propFind($principalUrl, ['{urn:ietf:params:xml:ns:caldav}calendar-home-set'], 0);
    $calendarHomeSet = $homeSetProps['{urn:ietf:params:xml:ns:caldav}calendar-home-set'][0]['value'] ?? $homeSetProps['{urn:ietf:params:xml:ns:caldav}calendar-home-set'];
    echo "Calendar Home Set: $calendarHomeSet\n";

    if (str_starts_with($calendarHomeSet, 'http')) {
        $parts = parse_url($calendarHomeSet);
        $newBase = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? 'caldav.icloud.com').(isset($parts['port']) ? ':'.$parts['port'] : '').'/';
        $client = new Client([
            'baseUri' => $newBase,
            'userName' => $email,
            'password' => $password,
        ]);
        $calendarHomeSet = ltrim($parts['path'] ?? $calendarHomeSet, '/');
    }

    echo "Querying $calendarHomeSet for calendars...\n";
    $calendars = $client->propFind($calendarHomeSet, [
        '{DAV:}displayname',
        '{urn:ietf:params:xml:ns:caldav}calendar-description',
    ], 1);
    
    echo "Direct Calendars:\n";
    foreach ($calendars as $path => $props) {
        $name = $props['{DAV:}displayname'][0]['value'] ?? $props['{DAV:}displayname'] ?? null;
        if (!$name && isset($props['{urn:ietf:params:xml:ns:caldav}calendar-description'])) {
            $name = $props['{urn:ietf:params:xml:ns:caldav}calendar-description'][0]['value'] ?? $props['{urn:ietf:params:xml:ns:caldav}calendar-description'];
        }
        echo " - PATH: $path | NAME: $name\n";
    }

    echo "\nQuerying proxies...\n";
    $proxyProps = $client->propFind($principalUrl, [
        '{http://calendarserver.org/ns/}calendar-proxy-read-for',
        '{http://calendarserver.org/ns/}calendar-proxy-write-for'
    ], 0);

    print_r($proxyProps);
    
    // Check if there are other collections we can scan at depth 1 on principalUrl?
    echo "\nTrying to propfind principalUrl depth 1 for any proxy lists...\n";
    $allPrincipalProps = $client->propFind($principalUrl, [
        '{DAV:}displayname'
    ], 1);
    foreach ($allPrincipalProps as $path => $props) {
        echo " - PRINCIPAL PATH: $path\n";
    }

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
