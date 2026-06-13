<?php

test('the dashboard returns a successful response', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
});

test('api returns weather data placeholder', function () {
    // This is a placeholder for a future weather API test
    $response = $this->getJson('/api/weather');

    // We haven't created the route yet, so this will fail initially
    // I'll create the route after this
    $response->assertStatus(200)
        ->assertJsonStructure(['current', 'hourly', 'daily']);
})->todo();
