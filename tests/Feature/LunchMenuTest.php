<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

function lunchWeekFixture(): array
{
    return [
        'start_date' => '2026-08-10',
        'days' => [
            [
                'date' => '2026-08-10',
                'menu_items' => [
                    ['text' => 'No School', 'is_holiday' => true],
                ],
            ],
            [
                'date' => '2026-08-11',
                'menu_items' => [
                    ['text' => 'Hot Meal:', 'is_section_title' => true],
                    ['text' => '', 'is_section_title' => false, 'food' => ['name' => 'Chicken and Waffles E']],
                    ['text' => 'Fruit of the Day:', 'is_section_title' => true],
                    ['text' => '', 'is_section_title' => false, 'food' => ['name' => 'Red Apple Slices']],
                ],
            ],
            [
                'date' => '2026-08-12',
                'menu_items' => [],
            ],
        ],
    ];
}

test('returns the weekly lunch menu for the configured school', function () {
    Http::fake([
        'https://polk-fl.api.nutrislice.com/menu/api/weeks/school/lakeland-montessori/menu-type/lunch/2026/08/12/' => Http::response(lunchWeekFixture()),
    ]);

    $response = $this->getJson('/api/lunch-menu?date=2026-08-12');

    $response->assertStatus(200)
        ->assertJsonPath('school', 'Lakeland Montessori')
        ->assertJsonCount(3, 'days')
        ->assertJsonPath('days.0.has_school', false)
        ->assertJsonPath('days.1.sections.0.name', 'Hot Meal:')
        ->assertJsonPath('days.1.sections.0.items.0', 'Chicken and Waffles E')
        ->assertJsonPath('days.1.sections.1.items.0', 'Red Apple Slices')
        ->assertJsonPath('days.2.sections', []);
});

test('caches the menu response for 6 hours', function () {
    Http::fake([
        'https://polk-fl.api.nutrislice.com/menu/api/weeks/school/lakeland-montessori/menu-type/lunch/2026/08/12/' => Http::response(lunchWeekFixture()),
    ]);

    $this->getJson('/api/lunch-menu?date=2026-08-12')->assertStatus(200);
    $this->getJson('/api/lunch-menu?date=2026-08-12')->assertStatus(200);

    Http::assertSentCount(1);
    expect(Cache::has('lunch_menu_polk-fl_lakeland-montessori_2026-08-12'))->toBeTrue();
});

test('returns a 502 when the Nutrislice API fails', function () {
    Http::fake([
        'https://polk-fl.api.nutrislice.com/menu/api/weeks/school/lakeland-montessori/menu-type/lunch/2026/08/12/' => Http::response([], 500),
    ]);

    $this->getJson('/api/lunch-menu?date=2026-08-12')
        ->assertStatus(502)
        ->assertJsonPath('error', 'Unable to load lunch menu');
});
