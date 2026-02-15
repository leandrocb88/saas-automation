<?php

use App\Models\User;
use App\Models\Digest;
use App\Models\Channel;
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

$channel = Channel::first();
if (!$channel) {
    echo "No channel found.\n";
    exit(1);
}

// Create Test Digest
$digest = Digest::create([
    'user_id' => $user->id,
    'name' => 'Test Custom Digest ' . time(),
    'frequency' => 'daily',
    'scheduled_at' => '12:00',
    'mode' => 'channels',
    'is_active' => true,
    'custom_prompt' => 'Summarize this video as if you are a pirate. Keep it short.',
]);

$digest->channels()->attach($channel->id);

echo "Created Digest ID: {$digest->id} for User: {$user->email}\n";
echo "Channel attached: {$channel->name}\n";

// Run Command
echo "Running process-custom-digests...\n";
Artisan::call('app:process-custom-digests', [
    '--force' => true,
    '--digest' => $digest->id
]);

echo Artisan::output();

// Cleanup (optional, maybe keep to check DB)
// $digest->delete();
