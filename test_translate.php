<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$transcript = [
    ['text' => 'Hello world', 'start' => 0, 'duration' => 2],
    ['text' => 'This is a test transcript.', 'start' => 2, 'duration' => 3],
];

echo "Testing OpenAI...\n";
try {
    $openai = app(\App\Services\OpenAIService::class);
    $res1 = $openai->translateTranscript($transcript, 'es');
    print_r($res1);
} catch (\Exception $e) {
    echo "OpenAI Exception: " . $e->getMessage() . "\n";
}

echo "\nTesting Gemini...\n";
try {
    $gemini = app(\App\Services\GeminiService::class);
    $res2 = $gemini->translateTranscript($transcript, 'es');
    print_r($res2);
} catch (\Exception $e) {
    echo "Gemini Exception: " . $e->getMessage() . "\n";
}
