<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Jobs\ProcessDatasetSyncJob;

class YoutubeWebhookController extends Controller
{
    /**
     * Handle YouTube Actor completion webhook (Apify or Railway).
     */
    public function handle(Request $request)
    {
        Log::info('YouTube Webhook Received:', $request->all());

        $actorDatasetId = null;
        $localDatasetId = null;

        // 1. Detect Payload Format (Apify vs Railway)
        $resource = $request->input('resource');
        if ($resource && isset($resource['defaultDatasetId'])) {
            // Apify Format
            $actorDatasetId = $resource['defaultDatasetId'];
            $localDatasetId = $resource['meta']['userData']['dataset_id'] ?? null;
            $status = $resource['status'] ?? 'UNKNOWN';

            if ($status !== 'SUCCEEDED') {
                Log::warning("Apify Webhook: Run status is {$status}. Ignoring.");
                return response()->json(['message' => 'Ignored'], 400);
            }
        } else {
            // Railway/Custom Format
            $status = $request->input('status');
            $runId = $request->input('runId');
            
            // Prioritize query string to avoid shadowing (user pointed out dataset_id/runId confusion)
            $localDatasetId = $request->query('dataset_id') ?? $request->input('dataset_id') ?? $request->input('user_data.dataset_id') ?? $request->input('userData.dataset_id');

            Log::info("Railway Webhook ID extraction:", [
                'query_dataset_id' => $request->query('dataset_id'),
                'input_dataset_id' => $request->input('dataset_id'),
                'final_local_id' => $localDatasetId,
                'run_id' => $runId
            ]);

            if ($status === 'error') {
                $errorMsg = $request->input('error', 'Unknown error from actor.');
                Log::error("Railway Webhook Error for Dataset #{$localDatasetId}: {$errorMsg}", ['runId' => $runId]);
                
                if ($localDatasetId) {
                    \App\Models\Dataset::where('id', $localDatasetId)->update(['status' => 'error']);
                }
                
                return response()->json(['message' => 'Error logged'], 200);
            }

            if ($status !== 'success') {
                Log::warning("Railway Webhook: Unexpected status '{$status}'.");
                return response()->json(['message' => 'Ignored'], 400);
            }

            // In Railway format, the "actorDatasetId" is actually the S3 File ID now, fallback to runId if S3 is disabled
            $actorDatasetId = $request->input('s3FileId') ?? $runId;
        }

        if (!$actorDatasetId || !$localDatasetId) {
            Log::error('YouTube Webhook: Missing required IDs', [
                'actor_dataset_id_or_s3' => $actorDatasetId,
                'local_dataset_id' => $localDatasetId,
                'payload' => $request->all()
            ]);
            return response()->json(['message' => 'Missing data'], 422);
        }

        Log::info("YouTube Webhook (Railway branch): Detected Local ID: {$localDatasetId}, S3 ID: {$actorDatasetId}, Status: {$status}");

        try {
            // 2. Dispatch the background job synchronously
            $isFullSync = $request->query('full_sync') == '1';
            ProcessDatasetSyncJob::dispatchSync($actorDatasetId, (int)$localDatasetId, $isFullSync);
            Log::info("YouTube Webhook: Processed ProcessDatasetSyncJob for local dataset #{$localDatasetId} synchronously.", ['full_sync' => $isFullSync]);
            return response()->json(['message' => 'Webhook processed successfully', 'dataset_id' => $localDatasetId], 200);
        } catch (\Exception $e) {
            Log::error("YouTube Webhook: Critical failure during sync processing: " . $e->getMessage(), [
                'local_dataset_id' => $localDatasetId,
                'actor_source' => $actorDatasetId,
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'Internal processing error',
                'error' => $e->getMessage(),
                'dataset_id' => $localDatasetId
            ], 500);
        }
    }
}
