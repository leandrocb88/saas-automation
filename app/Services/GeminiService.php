<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    protected string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models';

    public function addToPool(\Illuminate\Http\Client\Pool $pool, string $key, string $transcript, string $type = 'detailed')
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

        return $pool->as($key)->retry(3, 2000, function ($exception, $request) {
                return $exception instanceof \Illuminate\Http\Client\ConnectionException ||
                       ($exception instanceof \Illuminate\Http\Client\RequestException && 
                        ($exception->response->status() === 429 || $exception->response->status() >= 500));
            })->withHeaders([
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/gemini-2.0-flash:generateContent?key={$apiKey}", [
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

    public function generateSummary(string $transcript, string $type = 'detailed'): ?string
    {
        // Reuse logic? Or keep separate to avoid breaking robust error handling in generateSummary?
        // Keeping separate for now to minimize risk.
        if (empty($transcript)) {
            return null;
        }
// ... existing code ...

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

        try {
            $response = Http::retry(3, 2000, function ($exception, $request) {
                return $exception instanceof \Illuminate\Http\Client\ConnectionException ||
                       ($exception instanceof \Illuminate\Http\Client\RequestException && 
                        ($exception->response->status() === 429 || $exception->response->status() >= 500));
            })->withHeaders([
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/gemini-2.0-flash:generateContent?key={$apiKey}", [
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
}
