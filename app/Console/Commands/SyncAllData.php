<?php

namespace App\Console\Commands;

use App\Jobs\SyncPaprikaRecipes;
use App\Jobs\SyncPaprikaShoppingList;
use App\Models\Calendar;
use App\Services\Calendar\CalendarManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncAllData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:all';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manually trigger a full background sync of all data sources';

    /**
     * Execute the console command.
     */
    public function handle(CalendarManager $calendarManager)
    {
        $this->info('Dispatching background sync jobs...');
        
        // 1. Paprika
        SyncPaprikaRecipes::dispatch();
        SyncPaprikaShoppingList::dispatch();
        $this->line('- Paprika Recipes & Shopping List dispatched.');

        // 2. Calendars
        $calendars = Calendar::all();
        foreach ($calendars as $calendar) {
            // We can dispatch these too or run them here if they are fast, 
            // but jobs are safer for memory.
            // For now, let's just trigger the manager's sync which we'll optimize.
            $calendarManager->syncCalendar($calendar);
            $this->line("- Calendar: {$calendar->provider} ({$calendar->id}) synced.");
        }

        $this->info('All sync tasks initiated.');
    }
}
