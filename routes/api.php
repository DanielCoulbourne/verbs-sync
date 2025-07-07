<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use DanielCoulbourne\VerbsSync\EventProcessor;
use DanielCoulbourne\VerbsSync\EventRepository;

/*
|--------------------------------------------------------------------------
| Verbs Sync API Routes
|--------------------------------------------------------------------------
|
| These routes are loaded by the VerbsSyncServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('api')->prefix('api/verbs-sync')->group(function () {
    // Endpoint to retrieve events (for pull mode)
    Route::get('/', function (Request $request) {
        // Verify API token
        $apiToken = $request->header('Authorization');
        $expectedToken = 'Bearer ' . env('VERBS_SYNC_API_KEY', '');

        if (!$apiToken || $apiToken !== $expectedToken) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Get filters from request
        $limit = $request->input('limit', 10);
        $since = $request->input('since');
        $eventType = $request->input('event_type');

        // Get events from database
        $query = DB::table('verbs_sync_events');

        if ($eventType) {
            $query->where('event_type', $eventType);
        }

        if ($since) {
            $query->where('created_at', '>=', $since);
        }

        $events = $query->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        // Format events for response
        $formattedEvents = $events->map(function ($event) {
            return [
                'id' => $event->event_id,
                'type' => $event->event_type,
                'data' => json_decode($event->event_data, true),
                'created_at' => $event->created_at,
            ];
        });

        return response()->json([
            'success' => true,
            'count' => $events->count(),
            'events' => $formattedEvents,
            'source_url' => config('app.url'),
            'source_name' => env('VERBS_SYNC_APP_NAME', config('app.name')),
        ]);
    });

    // Endpoint to receive events from source applications
    Route::post('/', function (Request $request, EventProcessor $processor, EventRepository $repository) {
        // Verify API key
        $apiKey = $request->header('X-Verbs-Sync-Key');
        $expectedKey = config('verbs-sync.options.api_key', env('VERBS_SYNC_API_KEY'));

        if (!$apiKey || $apiKey !== $expectedKey) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Process incoming events
        $events = $request->input('events', []);
        $sourceUrl = $request->input('source_url');
        $sourceName = $request->input('source_name');

        if (empty($events)) {
            return response()->json(['error' => 'No events provided'], 400);
        }

        $results = [];
        foreach ($events as $event) {
            try {
                $processed = $processor->process($event, $sourceUrl, $sourceName);
                $stored = $repository->store($processed);

                // Just log that we stored the event
                $eventType = $stored['event_type'];
                $eventData = json_decode($stored['event_data'], true);

                Log::info("Imported event: {$eventType}", [
                    'event_id' => $stored['event_id'],
                    'source' => $sourceUrl,
                ]);

                // Note: Events are not fired automatically on import
                // They can be replayed later using Verbs replay functionality

                $results[] = [
                    'event_id' => $stored['event_id'],
                    'status' => 'processed',
                    'message' => 'Event processed successfully'
                ];
            } catch (\Exception $e) {
                $results[] = [
                    'event_id' => $event['id'] ?? 'unknown',
                    'status' => 'error',
                    'message' => $e->getMessage()
                ];
            }
        }

        return response()->json([
            'success' => true,
            'processed' => count($results),
            'results' => $results
        ]);
    });

    // Endpoint to check status
    Route::get('/status', function () {
        return response()->json([
            'status' => 'online',
            'version' => '1.0',
            'app_name' => config('app.name'),
            'sync_type' => env('VERBS_SYNC_TYPE', 'destination')
        ]);
    });
});
