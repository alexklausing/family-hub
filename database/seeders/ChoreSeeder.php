<?php

namespace Database\Seeders;

use App\Models\Chore;
use App\Models\Label;
use Illuminate\Database\Seeder;

class ChoreSeeder extends Seeder
{
    /**
     * All days of the week (Sun–Sat = 0–6)
     */
    private const EVERY_DAY = [0, 1, 2, 3, 4, 5, 6];

    private const WEEKDAYS = [1, 2, 3, 4, 5];

    private const WEEKENDS = [0, 6];

    private const MON_WED_FRI = [1, 3, 5];

    private const TUE_THU = [2, 4];

    private const SATURDAY = [6];

    private const SUNDAY = [0];

    public function run(): void
    {
        // ── Clear existing demo data ──────────────────────────────────────────
        Chore::query()->delete();
        Label::query()->delete();

        // ── 1. LABELS (with group rewards) ───────────────────────────────────

        $morningRoutine = Label::create([
            'name' => 'Morning Routine',
            'reward' => '$0.50',
        ]);

        $bedtimeRoutine = Label::create([
            'name' => 'Bedtime Routine',
            'reward' => 'Stay up 15 min late on weekends',
        ]);

        $screenTimeList = Label::create([
            'name' => 'Screen Time List',
            'reward' => '30 min screen time',
        ]);

        $weekendDeepClean = Label::create([
            'name' => 'Weekend Deep Clean',
            'reward' => '$1.00',
        ]);

        $homework = Label::create([
            'name' => 'Homework Block',
            'reward' => 'Pick dessert tonight',
        ]);

        // ── 2. FAMILY CHORES (shared household tasks) ─────────────────────────

        $order = 0;

        $familyChores = [
            // Weekend Deep Clean group
            ['title' => 'Vacuum living room',    'time' => '11:00', 'days' => self::SATURDAY,    'label_id' => $weekendDeepClean->id, 'reward' => null],
            ['title' => 'Mop kitchen floor',     'time' => '11:30', 'days' => self::SATURDAY,    'label_id' => $weekendDeepClean->id, 'reward' => null],
            ['title' => 'Clean bathrooms',       'time' => '12:00', 'days' => self::SATURDAY,    'label_id' => $weekendDeepClean->id, 'reward' => null],
            ['title' => 'Take out recycling',    'time' => null,    'days' => self::SUNDAY,      'label_id' => null,                  'reward' => null],
            // Weekday shared
            ['title' => 'Run dishwasher',        'time' => '19:30', 'days' => self::EVERY_DAY,   'label_id' => null,                  'reward' => null],
            ['title' => 'Wipe kitchen counters', 'time' => '20:00', 'days' => self::WEEKDAYS,    'label_id' => null,                  'reward' => null],
        ];

        foreach ($familyChores as $chore) {
            Chore::create([
                'profile' => 'Family',
                'title' => $chore['title'],
                'time' => $chore['time'],
                'days' => $chore['days'],
                'label_id' => $chore['label_id'],
                'reward' => $chore['reward'],
                'order' => $order++,
            ]);
        }

        // ── 3. EMILY'S CHORES ────────────────────────────────────────────────

        $order = 0;

        $emilyChores = [
            // Morning Routine group (reward: $0.50)
            ['title' => 'Make bed',              'time' => '07:30', 'days' => self::EVERY_DAY,   'label_id' => $morningRoutine->id, 'reward' => null],
            ['title' => 'Get dressed',           'time' => '07:45', 'days' => self::EVERY_DAY,   'label_id' => $morningRoutine->id, 'reward' => null],
            ['title' => 'Brush teeth (morning)', 'time' => '07:50', 'days' => self::EVERY_DAY,   'label_id' => $morningRoutine->id, 'reward' => null],
            ['title' => 'Eat breakfast',         'time' => '08:00', 'days' => self::EVERY_DAY,   'label_id' => $morningRoutine->id, 'reward' => null],

            // Screen Time List group (reward: 30 min screen time)
            ['title' => 'Feed the cat',          'time' => '16:00', 'days' => self::EVERY_DAY,   'label_id' => $screenTimeList->id, 'reward' => null],
            ['title' => 'Empty lunch box',       'time' => '15:30', 'days' => self::WEEKDAYS,    'label_id' => $screenTimeList->id, 'reward' => null],
            ['title' => 'Read for 20 minutes',   'time' => '16:30', 'days' => self::EVERY_DAY,   'label_id' => $screenTimeList->id, 'reward' => null],

            // Homework Block group (reward: Pick dessert)
            ['title' => 'Do homework',           'time' => '17:00', 'days' => self::WEEKDAYS,    'label_id' => $homework->id,       'reward' => null],
            ['title' => 'Pack backpack',         'time' => '20:00', 'days' => self::WEEKDAYS,    'label_id' => $homework->id,       'reward' => null],

            // Bedtime Routine group
            ['title' => 'Brush teeth (night)',   'time' => '20:30', 'days' => self::EVERY_DAY,   'label_id' => $bedtimeRoutine->id, 'reward' => null],
            ['title' => 'Lay out tomorrow\'s clothes', 'time' => '20:45', 'days' => self::WEEKDAYS, 'label_id' => $bedtimeRoutine->id, 'reward' => null],
            ['title' => 'Lights out',            'time' => '21:00', 'days' => self::EVERY_DAY,   'label_id' => $bedtimeRoutine->id, 'reward' => null],

            // Individual chores with individual rewards
            ['title' => 'Clean room',            'time' => '14:00', 'days' => self::SATURDAY,    'label_id' => null,                'reward' => '$0.25'],
            ['title' => 'Help set dinner table', 'time' => '17:45', 'days' => self::EVERY_DAY,   'label_id' => null,                'reward' => '$0.10'],
        ];

        foreach ($emilyChores as $chore) {
            Chore::create([
                'profile' => 'Emily',
                'title' => $chore['title'],
                'time' => $chore['time'],
                'days' => $chore['days'],
                'label_id' => $chore['label_id'],
                'reward' => $chore['reward'],
                'order' => $order++,
            ]);
        }

        // ── 4. HENRY'S CHORES ─────────────────────────────────────────────────

        $order = 0;

        $henryChores = [
            // Morning Routine group
            ['title' => 'Make bed',              'time' => '07:30', 'days' => self::EVERY_DAY,   'label_id' => $morningRoutine->id, 'reward' => null],
            ['title' => 'Get dressed',           'time' => '07:45', 'days' => self::EVERY_DAY,   'label_id' => $morningRoutine->id, 'reward' => null],
            ['title' => 'Brush teeth (morning)', 'time' => '07:50', 'days' => self::EVERY_DAY,   'label_id' => $morningRoutine->id, 'reward' => null],

            // Screen Time List group
            ['title' => 'Unload the dishwasher', 'time' => '15:30', 'days' => self::MON_WED_FRI, 'label_id' => $screenTimeList->id, 'reward' => null],
            ['title' => 'Take out trash (kitchen)', 'time' => '16:00', 'days' => self::TUE_THU, 'label_id' => $screenTimeList->id, 'reward' => null],
            ['title' => 'Read for 20 minutes',   'time' => '16:30', 'days' => self::EVERY_DAY,   'label_id' => $screenTimeList->id, 'reward' => null],

            // Homework Block group
            ['title' => 'Do homework',           'time' => '17:00', 'days' => self::WEEKDAYS,    'label_id' => $homework->id,       'reward' => null],
            ['title' => 'Pack backpack',         'time' => '20:00', 'days' => self::WEEKDAYS,    'label_id' => $homework->id,       'reward' => null],

            // Bedtime Routine group
            ['title' => 'Brush teeth (night)',   'time' => '20:30', 'days' => self::EVERY_DAY,   'label_id' => $bedtimeRoutine->id, 'reward' => null],
            ['title' => 'Lights out',            'time' => '21:00', 'days' => self::EVERY_DAY,   'label_id' => $bedtimeRoutine->id, 'reward' => null],

            // Individual chores
            ['title' => 'Feed the dog',          'time' => '07:30', 'days' => self::EVERY_DAY,   'label_id' => null,                'reward' => '$0.25'],
            ['title' => 'Walk the dog',          'time' => '16:00', 'days' => self::EVERY_DAY,   'label_id' => null,                'reward' => '$0.25'],
            ['title' => 'Clean room',            'time' => '14:00', 'days' => self::SATURDAY,    'label_id' => null,                'reward' => '$0.25'],
            ['title' => 'Mow the lawn',          'time' => '10:00', 'days' => self::SATURDAY,    'label_id' => $weekendDeepClean->id, 'reward' => '$2.00'],
        ];

        foreach ($henryChores as $chore) {
            Chore::create([
                'profile' => 'Henry',
                'title' => $chore['title'],
                'time' => $chore['time'],
                'days' => $chore['days'],
                'label_id' => $chore['label_id'],
                'reward' => $chore['reward'],
                'order' => $order++,
            ]);
        }

        // ── 5. ALEX'S CHORES ──────────────────────────────────────────────────

        $order = 0;

        $alexChores = [
            ['title' => 'Morning workout',        'time' => '06:00', 'days' => self::MON_WED_FRI, 'label_id' => null,                'reward' => null],
            ['title' => 'Pack lunches',           'time' => '07:15', 'days' => self::WEEKDAYS,    'label_id' => null,                'reward' => null],
            ['title' => 'Check & respond to emails', 'time' => '08:30', 'days' => self::WEEKDAYS, 'label_id' => null,                'reward' => null],
            ['title' => 'Pay bills / finances review', 'time' => '10:00', 'days' => self::SUNDAY, 'label_id' => null,                'reward' => null],
            ['title' => 'Grocery shopping',       'time' => '10:00', 'days' => self::SATURDAY,   'label_id' => null,                'reward' => null],
            ['title' => 'Start dinner',           'time' => '17:30', 'days' => self::WEEKENDS,   'label_id' => null,                'reward' => null],
        ];

        foreach ($alexChores as $chore) {
            Chore::create([
                'profile' => 'Alex',
                'title' => $chore['title'],
                'time' => $chore['time'],
                'days' => $chore['days'],
                'label_id' => $chore['label_id'],
                'reward' => $chore['reward'],
                'order' => $order++,
            ]);
        }

        // ── 6. SARAH'S CHORES ─────────────────────────────────────────────────

        $order = 0;

        $sarahChores = [
            ['title' => 'Morning yoga / stretch', 'time' => '06:30', 'days' => self::TUE_THU,   'label_id' => null,                'reward' => null],
            ['title' => 'Schedule weekly appointments', 'time' => '09:00', 'days' => self::SUNDAY, 'label_id' => null,             'reward' => null],
            ['title' => 'Water plants',           'time' => null,    'days' => self::MON_WED_FRI, 'label_id' => null,               'reward' => null],
            ['title' => 'Start laundry',          'time' => '09:00', 'days' => self::SATURDAY,   'label_id' => null,                'reward' => null],
            ['title' => 'Fold & put away laundry', 'time' => '14:00', 'days' => self::SATURDAY,   'label_id' => null,                'reward' => null],
            ['title' => 'Meal plan for the week', 'time' => '11:00', 'days' => self::SUNDAY,     'label_id' => null,                'reward' => null],
            ['title' => 'Start dinner',           'time' => '17:30', 'days' => self::WEEKDAYS,   'label_id' => null,                'reward' => null],
        ];

        foreach ($sarahChores as $chore) {
            Chore::create([
                'profile' => 'Sarah',
                'title' => $chore['title'],
                'time' => $chore['time'],
                'days' => $chore['days'],
                'label_id' => $chore['label_id'],
                'reward' => $chore['reward'],
                'order' => $order++,
            ]);
        }

        $this->command->info('✅ ChoreSeeder complete! Created '.Chore::count().' chores across '.Label::count().' labels.');
    }
}
