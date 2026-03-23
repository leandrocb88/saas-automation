<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$video = \App\Models\Video::firstWhere('transcript', '!=', null);
if (!$video) {
    die("No video with transcript found\n");
}

$request = \Illuminate\Http\Request::create('/api/youtube/translate/' . $video->id, 'POST', ['language' => 'es']);
// Mock user or IP
$request->server->set('REMOTE_ADDR', '127.0.0.1');

$controller = app(\App\Http\Controllers\YouTubeController::class);
$response = $controller->translate($request, $video);

echo "Response Status: " . $response->getStatusCode() . "\n";
echo "Response Content: " . $response->getContent() . "\n";
