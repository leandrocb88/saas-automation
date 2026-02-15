<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = User::first();
if (!$user) {
    echo "No user found.\n";
    exit(1);
}

echo "Testing Manual Digest Trigger for User: {$user->email}\n";
echo "Params: Limit=5, DaysBack=30, Sort=newest\n";

// Run Command
try {
    Artisan::call('app:process-daily-digests', [
        '--force' => true,
        '--user' => $user->id,
        '--limit' => 5,
        '--days-back' => 30,
        '--sort' => 'newest'
    ]);
    echo Artisan::output();
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
