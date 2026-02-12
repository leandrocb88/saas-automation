<?php

namespace App\Services;

use OpenAI\Laravel\Facades\OpenAI;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http; // Added this import for the new summarize method

class OpenAIService
{
    public function summarize(string $transcript, string $language = 'en'): array
    {
        // Truncate transcript to reasonable length (approx 100k chars ~ 25k tokens) to fit context
        $transcriptSnippet = substr($transcript, 0, 100000);

        $prompt = "
        You are an expert video analyzer. Analyze the provided transcript and generate a structured JSON response.
        
        Output format (JSON):
        {
            \"summary\": \"A concise 2-3 sentence summary of the video.\",
            \"key_points\": [\"Key point 1\", \"Key point 2\", \"Key point 3\"],
            \"detailed_summary\": \"A comprehensive summary formatted in Markdown (headers, bullet points).\",
            \"sentiment\": \"Positive\" | \"Neutral\" | \"Negative\"
        }
        
        Transcript:
        $transcriptSnippet
        ";

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . config('services.openai.api_key'),
                'Content-Type' => 'application/json',
            ])->timeout(120)->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-5-nano', 
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a helpful AI assistant that analyzes video transcripts and outputs JSON.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 1,
                'response_format' => ['type' => 'json_object'],
            ]);

            if ($response->failed()) {
                Log::error('OpenAI API Error', ['status' => $response->status(), 'body' => $response->body()]);
                throw new \Exception('Failed to communicate with OpenAI API.');
            }

            $content = $response->json('choices.0.message.content');
            $data = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('OpenAI JSON Parse Error', ['content' => $content]);
                throw new \Exception('Failed to parse AI response.');
            }

            return [
                'summary' => $data['summary'] ?? 'Summary not available.',
                'key_points' => $data['key_points'] ?? [],
                'detailed_summary' => $data['detailed_summary'] ?? $data['summary'] ?? '',
                'sentiment' => $data['sentiment'] ?? 'Neutral',
                'raw_response' => $data // Optional: store raw for debug if needed
            ];
            
        } catch (\Exception $e) {
            Log::error('OpenAI Service Exception: ' . $e->getMessage());
            // Fallback or rethrow? Rethrow so controller handles specific error.
            throw $e;
        }
    }

    public function addToPool(\Illuminate\Http\Client\Pool $pool, string $key, string $transcript, string $type = 'detailed')
    {
        $transcriptSnippet = substr($transcript, 0, 100000);

        $prompt = "
        You are an expert video analyzer. Analyze the provided transcript and generate a structured JSON response.
        
        Output format (JSON):
        {
            \"summary\": \"A concise 2-3 sentence summary of the video.\",
            \"key_points\": [\"Key point 1\", \"Key point 2\", \"Key point 3\"],
            \"detailed_summary\": \"A comprehensive summary formatted in Markdown (headers, bullet points).\",
            \"sentiment\": \"Positive\" | \"Neutral\" | \"Negative\"
        }
        
        Transcript:
        $transcriptSnippet
        ";

        return $pool->as($key)->withHeaders([
                'Authorization' => 'Bearer ' . config('services.openai.api_key'),
                'Content-Type' => 'application/json',
            ])->timeout(120)->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-5-nano', 
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a helpful AI assistant that analyzes video transcripts and outputs JSON.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 1,
                'response_format' => ['type' => 'json_object'],
            ]);
    }

    public function parseResponse(\Illuminate\Http\Client\Response $response)
    {
        if ($response->failed()) {
            Log::error('OpenAI Async Error', ['status' => $response->status(), 'body' => $response->body()]);
            return null;
        }

        $content = $response->json('choices.0.message.content');
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        // Format Output
        $output = "### Summary\n" . ($data['summary'] ?? '') . "\n\n";
        
        if (!empty($data['key_points'])) {
            $output .= "### Key Takeaways\n";
            foreach ($data['key_points'] as $point) {
                $output .= "- " . $point . "\n";
            }
            $output .= "\n";
        }

        $output .= "### Detailed Analysis\n" . ($data['detailed_summary'] ?? $data['summary'] ?? '') . "\n\n";
        $output .= "**Sentiment:** " . ($data['sentiment'] ?? 'Neutral');

        return $output;
    }

    public function generateSummary(string $transcript, string $type = 'detailed'): ?string
    {
        if (empty($transcript)) {
            return null;
        }

        try {
            $data = $this->summarize($transcript);

            if ($type === 'short') {
                return $data['summary'];
            }

            // Format as Markdown for detailed
            $output = "### Summary\n" . $data['summary'] . "\n\n";
            
            if (!empty($data['key_points'])) {
                $output .= "### Key Takeaways\n";
                foreach ($data['key_points'] as $point) {
                    $output .= "- " . $point . "\n";
                }
                $output .= "\n";
            }

            $output .= "### Detailed Analysis\n" . $data['detailed_summary'] . "\n\n";
            $output .= "**Sentiment:** " . $data['sentiment'];

            return $output;

        } catch (\Exception $e) {
            Log::error("OpenAI generateSummary failed: " . $e->getMessage());
            // Return concise error or null
            return null; // Controller logs it too
        }
    }
}
