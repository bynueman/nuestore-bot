<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$l = new App\Services\LollipopSmmService();
$services = $l->getServices();
if ($services) {
    print_r($services[0]);
} else {
    echo "No services returned";
}
