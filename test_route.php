<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    echo "Host: " . request()->getHost() . "\n";
    echo "URL: " . route('youtube.digest.show', 'test') . "\n";
    echo "URL2: " . route('youtube.show', collect(['id' => 1])) . "\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
