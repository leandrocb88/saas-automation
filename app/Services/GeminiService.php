<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    protected string $baseUrl = 'https://generativelanguage.googleapis.com';

    public function addToPool(\Illuminate\Http\Client\Pool $pool, string $key, string $transcript, string $type = 'detailed', ?string $customPrompt = null)
    {
        if (empty($transcript)) {
            return null;
        }

        $apiKey = config('services.google.gemini_api_key');
        if (!$apiKey) {
            return null; 
        }

        $limit = 2000000;
        if (strlen($transcript) > $limit) {
            $transcript = substr($transcript, 0, $limit) . "... (truncated)";
        }

        $systemPrompt = $type === 'short' 
            ? 'You are a helpful assistant. Provide a very concise summary (max 3-5 bullet points) focusing only on the main idea. Use Markdown.'
            : 'You are a helpful assistant that summarizes YouTube video transcripts. Provide a summary with key takeaways, deep analysis, and structured sections (Introduction, Key Points, Conclusion). Use Markdown formatting. If the transcript is very short or nonsensical, say so.';
        
        if ($customPrompt) {
            $systemPrompt .= "\n\nSpecific Instruction: " . $customPrompt;
        }

        $model = config('services.google.gemini_summary_model', 'gemini-2.0-flash');
        $version = config('services.google.gemini_version', 'v1beta');

        return $pool->as($key)->retry(3, 2000, function ($exception, $request) {
                return $exception instanceof \Illuminate\Http\Client\ConnectionException ||
                       ($exception instanceof \Illuminate\Http\Client\RequestException && 
                        ($exception->response->status() === 429 || $exception->response->status() >= 500));
            })->withHeaders([
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/{$version}/models/{$model}:generateContent?key={$apiKey}", [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $systemPrompt . "\n\nTranscript:\n" . $transcript]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'temperature' => 0.5,
                    'maxOutputTokens' => 2000,
                ]
            ]);
    }

    public function parseResponse(\Illuminate\Http\Client\Response $response)
    {
        if ($response->failed()) {
            Log::error('Gemini Async Error: ' . $response->body());
            return null;
        }

        $data = $response->json();
        return $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
    }

    public function generateSummary(string $transcript, string $type = 'detailed', ?string $customPrompt = null): ?string
    {
        // Reuse logic? Or keep separate to avoid breaking robust error handling in generateSummary?
        // Keeping separate for now to minimize risk.
        if (empty($transcript)) {
            return null;
        }

        $apiKey = config('services.google.gemini_api_key');

        if (!$apiKey) {
            Log::error('Gemini API Key not configured.');
            throw new \Exception('Gemini API Key not configured.');
        }

        // Gemini Context Window is huge (1M+ tokens), so we can be much more generous here.
        // But for safety/speed, let's limit to ~2M characters (~500k tokens), well within limits.
        $limit = 2000000;
        if (strlen($transcript) > $limit) {
            $transcript = substr($transcript, 0, $limit) . "... (truncated)";
        }

        $systemPrompt = $type === 'short' 
            ? 'You are a helpful assistant. Provide a very concise summary (max 3-5 bullet points) focusing only on the main idea. Use Markdown.'
            : 'You are a helpful assistant that summarizes YouTube video transcripts. Provide a summary with key takeaways, deep analysis, and structured sections (Introduction, Key Points, Conclusion). Use Markdown formatting. If the transcript is very short or nonsensical, say so.';

        if ($customPrompt) {
            $systemPrompt .= "\n\nSpecific Instruction: " . $customPrompt;
        }

        $model = config('services.google.gemini_summary_model', 'gemini-2.0-flash');
        $version = config('services.google.gemini_version', 'v1beta');

        try {
            $response = Http::retry(3, 2000, function ($exception, $request) {
                return $exception instanceof \Illuminate\Http\Client\ConnectionException ||
                       ($exception instanceof \Illuminate\Http\Client\RequestException && 
                        ($exception->response->status() === 429 || $exception->response->status() >= 500));
            })->withHeaders([
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/{$version}/models/{$model}:generateContent?key={$apiKey}", [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $systemPrompt . "\n\nTranscript:\n" . $transcript]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'temperature' => 0.5,
                    'maxOutputTokens' => 2000,
                ]
            ]);

            if ($response->failed()) {
                Log::error('Gemini API Error: ' . $response->body());
                throw new \Exception('Gemini API request failed: ' . $response->status());
            }

            $data = $response->json();
            
            // Extract text from Gemini response structure
            return $data['candidates'][0]['content']['parts'][0]['text'] ?? null;

        } catch (\Exception $e) {
            Log::error('Gemini Summary Generation Failed: ' . $e->getMessage());
            throw $e;
        }
    }

    public function translateTranscript(array $transcript, string $targetLanguage): ?array
    {
        if (empty($transcript)) {
            return null;
        }

        $apiKey = config('services.google.gemini_api_key');
        $model = config('services.google.gemini_translate_model', 'gemini-2.0-flash');
        $version = config('services.google.gemini_version', 'v1beta');

        $systemPrompt = "You are a professional translator. Translate the following video transcript into {$targetLanguage}.
        Return ONLY a JSON array of objects, where each object has 'text', 'start', and 'duration' fields.
        Translate ONLY the 'text' field. Keep 'start' and 'duration' exactly as they are.
        Maintain the same number of items in the array.";

        $transcriptJson = json_encode($transcript);

        try {
            $response = Http::retry(3, 2000)->timeout(300)->post("{$this->baseUrl}/{$version}/models/{$model}:generateContent?key={$apiKey}", [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $systemPrompt . "\n\nTranscript JSON:\n" . $transcriptJson]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'temperature' => 0.2,
                    'responseMimeType' => 'application/json',
                ]
            ]);

            if ($response->failed()) {
                Log::error('Gemini Translation Error: ' . $response->body());
                return null;
            }

            $data = $response->json();
            $translatedText = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;

            if (!$translatedText) {
                file_put_contents('/tmp/gemini_debug.json', json_encode([
                    'status' => $response->status(),
                    'body' => $data,
                    'transcript_size' => strlen($transcriptJson)
                ]));
                Log::error('Gemini Translation Missing Content in Response');
                return null;
            }

            $decoded = json_decode($translatedText, true);

            return $decoded;

        } catch (\Exception $e) {
            file_put_contents('/tmp/gemini_debug.json', 'Exception: ' . $e->getMessage());
            return null;
        }
    }

    public function translateSummary(string $summary, string $targetLanguage): ?string
    {
        if (empty($summary)) {
            return null;
        }

        $apiKey = config('services.google.gemini_api_key');
        $model = config('services.google.gemini_translate_model', 'gemini-2.0-flash');
        $version = config('services.google.gemini_version', 'v1beta');

        $systemPrompt = "You are a professional translator. Translate the following AI-generated video summary into {$targetLanguage}.
        Preserve all Markdown formatting (headers, bold text, bullet points).
        Provide ONLY the translated text.";

        try {
            $response = Http::retry(3, 2000)->timeout(300)->post("{$this->baseUrl}/{$version}/models/{$model}:generateContent?key={$apiKey}", [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $systemPrompt . "\n\nSummary:\n" . $summary]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'temperature' => 0.3,
                ]
            ]);

            if ($response->failed()) {
                Log::error('Gemini Summary Translation Error: ' . $response->body());
                return null;
            }

            $data = $response->json();
            return $data['candidates'][0]['content']['parts'][0]['text'] ?? null;

        } catch (\Exception $e) {
            Log::error('Gemini Summary Translation Failed: ' . $e->getMessage());
            return null;
        }
    }
}
