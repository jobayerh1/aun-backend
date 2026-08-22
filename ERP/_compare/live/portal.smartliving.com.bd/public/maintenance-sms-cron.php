<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$result = app(\App\Services\MaintenanceSmsService::class)->processDueSchedules(50);

header('Content-Type: application/json');
echo json_encode($result);
