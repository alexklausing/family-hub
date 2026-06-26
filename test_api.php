<?php
$month = date('n');
$day = date('j');
$wikipediaUrl = "https://en.wikipedia.org/api/rest_v1/feed/onthisday/all/".sprintf("%02d", $month)."/".sprintf("%02d", $day);

$ch = curl_init($wikipediaUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERAGENT, "FamilyHubDashboard/1.0");
$json = curl_exec($ch);
curl_close($ch);

$data = json_decode($json, true);
$holiday = "National Fun Day";
if (!empty($data['holidays'])) {
    foreach ($data['holidays'] as $h) {
        $text = $h['text'] ?? '';
        if (stripos($text, 'feast day') === false && stripos($text, 'remembrance') === false) {
            $holiday = trim(preg_replace('/\([^)]+\)/', '', $text)); // remove parens
            break;
        }
    }
}
echo "Holiday: $holiday\n";

$word = json_decode(file_get_contents("https://random-word-api.herokuapp.com/word?length=5"))[0] ?? "Hello";
$word = ucfirst($word);
$es = json_decode(file_get_contents("https://api.mymemory.translated.net/get?q=$word&langpair=en|es"), true)['responseData']['translatedText'] ?? "Hola";
$fr = json_decode(file_get_contents("https://api.mymemory.translated.net/get?q=$word&langpair=en|fr"), true)['responseData']['translatedText'] ?? "Bonjour";

echo "Word: $word / $es / $fr\n";
