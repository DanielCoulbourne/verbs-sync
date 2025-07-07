<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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
