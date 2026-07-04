<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class RefreshKiosk extends Command
{
    protected $signature = 'kiosk:refresh';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sends a signal to refresh all physical kiosks';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if (\Illuminate\Support\Facades\Cache::has('kiosk_version')) {
            \Illuminate\Support\Facades\Cache::increment('kiosk_version');
        } else {
            \Illuminate\Support\Facades\Cache::put('kiosk_version', 2);
        }
        
        $this->info('Kiosk refresh signal sent successfully.');
    }
}
