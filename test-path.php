<?php
foreach (App\Models\Calendar::where('provider', 'apple')->get() as $cal) {
    echo $cal->credentials['path'] . "\n";
}
