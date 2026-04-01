<?php

namespace App\Services;

use App\Models\Dataset;
use App\Models\Video;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class DatasetService
{
    /**
     * Append new videos to the dataset's knowledge file.
     *
     * @param Dataset $dataset
     * @param array $videos
     * @return void
     */
    public function appendVideosToKnowledge(Dataset $dataset, array $videos): void
    {
        $filePath = $this->getDatasetPath($dataset);
        
        $content = "";
        
        foreach ($videos as $video) {
            // Check if already in dataset_videos to prevent duplicates
            // This is the core requirement for preventing re-indexing of the same video
            if ($dataset->videos()->where('video_id', $video->id)->exists()) {
                Log::info("Skipping duplicate video #{$video->id} for dataset #{$dataset->id}");
                continue;
            }

            $content .= $this->formatVideoAsMarkdown($video);
            
            // Attach to dataset (this updates the pivot table)
            $dataset->videos()->attach($video->id);
            Log::info("Attached video #{$video->id} to dataset #{$dataset->id}");
        }

        if (!empty($content)) {
            if (!Storage::exists($filePath)) {
                $header = "# Knowledge Base: {$dataset->name}\n";
                $header .= "Source: {$dataset->channel_url}\n";
                $header .= "Generated on: " . now()->toDateTimeString() . "\n\n";
                $header .= "---\n\n";
                Storage::put($filePath, $header . $content);
            } else {
                Storage::append($filePath, $content);
            }
            
            $dataset->update(['file_path' => $filePath]);
        }
    }

    /**
     * Get the relative storage path for the dataset file.
     */
    public function getDatasetPath(Dataset $dataset): string
    {
        return "datasets/{$dataset->user_id}/{$dataset->id}/knowledge.md";
    }

    /**
     * Format a single video's transcript as Markdown.
     */
    protected function formatVideoAsMarkdown(Video $video): string
    {
        $transcriptText = "";
        if (is_array($video->transcript)) {
            $transcriptText = collect($video->transcript)->pluck('text')->join(' ');
        }

        $markdown = "## Video: {$video->title}\n";
        $markdown .= "URL: https://www.youtube.com/watch?v={$video->video_id}\n";
        $markdown .= "Published At: " . ($video->published_at ? $video->published_at->toFormattedDateString() : 'Unknown') . "\n\n";
        
        $markdown .= "### Transcript\n";
        $markdown .= trim($transcriptText) . "\n\n";
        $markdown .= "---\n\n";

        return $markdown;
    }
}
