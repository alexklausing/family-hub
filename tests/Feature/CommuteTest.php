<?php

use App\Models\Destination;
use App\Services\TomTomService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->session(['_token' => 'test_token']);
    config(['services.tomtom.key' => 'test-key']);
});

test('destinations can be listed', function () {
    Destination::create([
        'name' => 'Lakeland Montessori',
        'address' => '1124 N. Lake Parker Ave, Lakeland, FL 33805',
        'lat' => 28.0636,
        'lon' => -81.9434,
        'sort_order' => 0,
    ]);

    $response = $this->getJson('/api/commute/destinations');

    $response->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.name', 'Lakeland Montessori');
});

test('destinations are ordered by sort_order then name', function () {
    Destination::create([
        'name' => 'Zoo',
        'address' => '123 Main St',
        'lat' => 28.1,
        'lon' => -81.9,
        'sort_order' => 2,
    ]);
    Destination::create([
        'name' => 'School',
        'address' => '456 Oak Ave',
        'lat' => 28.2,
        'lon' => -81.8,
        'sort_order' => 1,
    ]);
    Destination::create([
        'name' => 'Gym',
        'address' => '789 Pine Rd',
        'lat' => 28.3,
        'lon' => -81.7,
        'sort_order' => 0,
    ]);

    $response = $this->getJson('/api/commute/destinations');

    $response->assertOk()
        ->assertJsonCount(3)
        ->assertJsonPath('0.name', 'Gym')
        ->assertJsonPath('1.name', 'School')
        ->assertJsonPath('2.name', 'Zoo');
});

test('a destination can be created with explicit coordinates', function () {
    $response = $this->postJson('/api/commute/destinations', [
        'name' => 'Florida Southern College',
        'address' => '111 Lake Hollingsworth Dr, Lakeland, FL 33801',
        'lat' => 28.0327,
        'lon' => -81.9502,
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('name', 'Florida Southern College')
        ->assertJsonPath('lat', 28.0327);

    expect(Destination::count())->toBe(1);
});

test('a destination is geocoded when coordinates are omitted', function () {
    Http::fake([
        'api.tomtom.com/search/*' => Http::response([
            'results' => [
                [
                    'position' => ['lat' => 28.0636, 'lon' => -81.9434],
                ],
            ],
        ], 200),
    ]);

    $response = $this->postJson('/api/commute/destinations', [
        'name' => 'Lakeland Montessori',
        'address' => '1124 N. Lake Parker Ave, Lakeland, FL 33805',
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('lat', 28.0636)
        ->assertJsonPath('lon', -81.9434);
});

test('a destination can be updated', function () {
    $dest = Destination::create([
        'name' => 'School',
        'address' => '1124 N. Lake Parker Ave, Lakeland, FL 33805',
        'lat' => 28.0636,
        'lon' => -81.9434,
    ]);

    $response = $this->putJson("/api/commute/destinations/{$dest->id}", [
        'name' => 'Lakeland Montessori Upper Campus',
        'address' => '2200 Reynolds Rd, Lakeland, FL 33801',
        'lat' => 28.0583,
        'lon' => -81.8889,
    ]);

    $response->assertOk()
        ->assertJsonPath('name', 'Lakeland Montessori Upper Campus');

    expect($dest->fresh()->name)->toBe('Lakeland Montessori Upper Campus');
});

test('a destination can be deleted', function () {
    $dest = Destination::create([
        'name' => 'Old Place',
        'address' => '123 Main St',
        'lat' => 28.1,
        'lon' => -81.9,
    ]);

    $response = $this->deleteJson("/api/commute/destinations/{$dest->id}");

    $response->assertStatus(204);
    expect(Destination::count())->toBe(0);
});

test('etas endpoint requires home coordinates', function () {
    $response = $this->getJson('/api/commute/etas');

    $response->assertStatus(422)
        ->assertJsonPath('error', 'Home coordinates are required (home_lat, home_lon)');
});

test('etas returns cached route data without hitting the api again', function () {
    Http::fake([
        'api.tomtom.com/routing/*' => Http::response([
            'routes' => [
                [
                    'summary' => [
                        'travelTimeInSeconds' => 720,
                        'trafficDelayInSeconds' => 60,
                        'noTrafficTravelTimeInSeconds' => 660,
                        'historicTrafficTravelTimeInSeconds' => 690,
                        'lengthInMeters' => 8047,
                    ],
                    'legs' => [
                        ['points' => [
                            ['latitude' => 28.0636, 'longitude' => -81.9434],
                            ['latitude' => 28.0592, 'longitude' => -81.9331],
                        ]],
                    ],
                    'sections' => [],
                ],
            ],
        ], 200),
    ]);

    $dest = Destination::create([
        'name' => 'School',
        'address' => '1124 N. Lake Parker Ave, Lakeland, FL 33805',
        'lat' => 28.0636,
        'lon' => -81.9434,
    ]);

    $response = $this->getJson('/api/commute/etas?home_lat=28.1&home_lon=-81.9');

    $response->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.destination.name', 'School')
        ->assertJsonPath('0.route.travel_time_minutes', 12)
        ->assertJsonPath('0.route.traffic_delay_minutes', 1)
        ->assertJsonPath('0.route.distance_miles', 5);

    Http::assertSentCount(1);

    // Second (cached) request should not hit the API again
    $this->getJson('/api/commute/etas?home_lat=28.1&home_lon=-81.9');
    Http::assertSentCount(1);
});

test('etas refresh bypasses the cache and hits the api again', function () {
    Http::fake([
        'api.tomtom.com/routing/*' => Http::response([
            'routes' => [
                [
                    'summary' => [
                        'travelTimeInSeconds' => 720,
                        'trafficDelayInSeconds' => 0,
                        'noTrafficTravelTimeInSeconds' => 720,
                        'lengthInMeters' => 8047,
                    ],
                    'legs' => [
                        ['points' => [
                            ['latitude' => 28.0636, 'longitude' => -81.9434],
                            ['latitude' => 28.0592, 'longitude' => -81.9331],
                        ]],
                    ],
                    'sections' => [],
                ],
            ],
        ], 200),
    ]);

    Destination::create([
        'name' => 'School',
        'address' => '1124 N. Lake Parker Ave, Lakeland, FL 33805',
        'lat' => 28.0636,
        'lon' => -81.9434,
    ]);

    $this->getJson('/api/commute/etas?home_lat=28.1&home_lon=-81.9');
    Http::assertSentCount(1);

    $this->postJson('/api/commute/refresh?home_lat=28.1&home_lon=-81.9');
    Http::assertSentCount(2);
});

test('geocode endpoint returns coordinates', function () {
    Http::fake([
        'api.tomtom.com/search/*' => Http::response([
            'results' => [
                [
                    'position' => ['lat' => 28.0636, 'lon' => -81.9434],
                ],
            ],
        ], 200),
    ]);

    $response = $this->postJson('/api/commute/geocode', [
        'address' => '1124 N. Lake Parker Ave, Lakeland, FL 33805',
    ]);

    $response->assertOk()
        ->assertJsonPath('lat', 28.0636)
        ->assertJsonPath('lon', -81.9434);

    Http::assertSent(function ($request) {
        $path = parse_url($request->url(), PHP_URL_PATH);

        return $path === '/search/2/geocode/1124%20N.%20Lake%20Parker%20Ave%2C%20Lakeland%2C%20FL%2033805.json';
    });
});

test('geocode endpoint returns 404 when address not found', function () {
    Http::fake([
        'api.tomtom.com/search/*' => Http::response(['results' => []], 200),
    ]);

    $response = $this->postJson('/api/commute/geocode', [
        'address' => 'Nowhere St, Antarctica',
    ]);

    $response->assertStatus(404);
});

test('tomtom service returns null route on api failure', function () {
    Http::fake([
        'api.tomtom.com/routing/*' => Http::response([], 500),
    ]);

    $service = app(TomTomService::class);
    $result = $service->getRoute(28.1, -81.9, 28.06, -81.85);

    expect($result['route'])->toBeNull();
    expect($result['traffic_sections'])->toBe([]);
});

test('tomtom service extracts traffic sections', function () {
    Http::fake([
        'api.tomtom.com/routing/*' => Http::response([
            'routes' => [
                [
                    'summary' => [
                        'travelTimeInSeconds' => 900,
                        'trafficDelayInSeconds' => 180,
                        'noTrafficTravelTimeInSeconds' => 720,
                        'lengthInMeters' => 10000,
                    ],
                    'legs' => [
                        ['points' => [
                            ['latitude' => 28.1, 'longitude' => -81.9],
                            ['latitude' => 28.06, 'longitude' => -81.85],
                        ]],
                    ],
                    'sections' => [
                        [
                            'type' => 'TRAFFIC',
                            'startPointIndex' => 0,
                            'endPointIndex' => 5,
                            'simpleCategory' => 'JAM',
                            'effectiveSpeedInKmh' => 25,
                            'delayInSeconds' => 120,
                            'magnitudeOfDelay' => 3,
                        ],
                    ],
                ],
            ],
        ], 200),
    ]);

    $service = app(TomTomService::class);
    $result = $service->getRoute(28.1, -81.9, 28.06, -81.85);

    expect($result['route']['travel_time_seconds'])->toBe(900);
    expect($result['traffic_sections'])->toHaveCount(1);
    expect($result['traffic_sections'][0]['category'])->toBe('JAM');
    expect($result['traffic_sections'][0]['magnitude'])->toBe(3);
});
