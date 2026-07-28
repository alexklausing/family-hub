<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

class AutoMonitor extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'monitor:auto';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically turn the monitor on or off based on user settings';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $settings = Cache::get('monitor_settings', [
            'enabled' => true,
            'off' => '22:00',
            'on' => '07:00'
        ]);

        if (!$settings['enabled']) {
            $this->info("Auto monitor control is disabled.");
            return 0;
        }

        // Check if the current time matches the specific hour and minute
        $currentTime = now()->format('H:i');

        if ($currentTime === $settings['off']) {
            $this->info("Time matched OFF setting ({$settings['off']}). Turning off.");
            Artisan::call('monitor:control', ['state' => 'off']);
        } elseif ($currentTime === $settings['on']) {
            $this->info("Time matched ON setting ({$settings['on']}). Turning on.");
            Artisan::call('monitor:control', ['state' => 'on']);
        } else {
            $this->info("Current time ($currentTime) does not match scheduled times.");
        }

        return 0;
    }
}
