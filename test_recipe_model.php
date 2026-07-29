<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$recipe = \App\Models\Recipe::where('uuid', '52DB1422-F33E-4422-9DFE-BD667B033FC4')->first();
var_dump($recipe);
