<?php

use App\Models\Calendar;

foreach (Calendar::where('provider', 'apple')->get() as $cal) {
    echo $cal->credentials['path']."\n";
}
