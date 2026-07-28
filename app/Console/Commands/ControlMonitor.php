<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ControlMonitor extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'monitor:control {state : State to set the monitor (on/off)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Turn the kiosk monitor on or off to save energy';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $state = $this->argument('state');

        if (!in_array($state, ['on', 'off'])) {
            $this->error('State must be "on" or "off"');
            return 1;
        }

        // On Ubuntu with X11, xset can put the screen into DPMS standby (off) or wake it up (on).
        // This effectively cuts power to the backlight and saves maximum energy.
        // We set DISPLAY=:0 which is the default for the primary screen on Ubuntu.
        $cmd = $state === 'off' 
            ? 'DISPLAY=:0 xset dpms force off'
            : 'DISPLAY=:0 xset dpms force on';

        $this->info("Setting monitor state to: $state");

        exec($cmd, $output, $returnVar);

        if ($returnVar !== 0) {
            $this->warn("xset command failed. Trying ddcutil as fallback...");
            
            // Fallback to ddcutil brightness control if xset fails
            $brightnessCmd = $state === 'off'
                ? 'ddcutil setvcp 10 0' // Brightness 0%
                : 'ddcutil setvcp 10 100'; // Brightness 100%
                
            exec($brightnessCmd, $outputDdc, $returnVarDdc);
            
            if ($returnVarDdc !== 0) {
                $this->error("Failed to control monitor. Ensure the cron user has access to X display (e.g. xhost +) or i2c devices for ddcutil.");
                return 1;
            }
        }

        $this->info("Monitor state updated successfully.");
        return 0;
    }
}
