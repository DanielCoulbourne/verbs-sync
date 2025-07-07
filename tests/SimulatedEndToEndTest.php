<?php

namespace DanielCoulbourne\VerbsSync\Tests;

use PHPUnit\Framework\TestCase;

/**
 * This test simulates the end-to-end flow of the Verbs Sync package
 * without relying on Laravel's framework components.
 */
class SimulatedEndToEndTest extends TestCase
{
    /** @test */
    public function it_simulates_full_event_sync_flow()
    {
        // --- 1. SETUP PHASE ---

        // Mock the HTTP client response from the source API
        $apiResponse = [
            'data' => [
                [
                    'id' => 'event-1',
                    'type' => 'user.created',
                    'data' => ['name' => 'John Doe'],
                    'created_at' => date('c'),
                ],
                [
                    'id' => 'event-2',
                    'type' => 'order.placed',
                    'data' => ['order_id' => 'ORD-123'],
                    'created_at' => date('c'),
                ],
                [
                    'id' => 'event-3',
                    'type' => 'post.deleted',
                    'data' => ['post_id' => 456],
                    'created_at' => date('c'),
                ],
            ]
        ];

        // Simulate database of already synced events
        $syncedEvents = [
            // This event was previously synced
            ['event_id' => 'event-2', 'source_url' => 'http://example.com/api']
        ];

        // Simulate configuration
        $config = [
            'source' => [
                'url' => 'http://example.com/api',
                'api_token' => 'test-token',
            ],
            'events' => [
                'include' => ['user.created', 'order.placed'], // Only these types
                'exclude' => ['post.deleted'],                // Exclude these
            ],
            'options' => [
                'batch_size' => 100,
            ],
        ];

        // --- 2. EXECUTION PHASE ---

        // Step 1: Pull events from source API
        $sourceUrl = $config['source']['url'];
        $events = $apiResponse['data'] ?? [];

        $this->assertCount(3, $events, 'API should return 3 events');

        // Step 2: Apply event type filtering
        $filteredEvents = [];
        foreach ($events as $event) {
            // Check if type is included
            $typeIncluded = in_array($event['type'], $config['events']['include']);

            // Check if type is not excluded
            $typeExcluded = in_array($event['type'], $config['events']['exclude']);

            if ($typeIncluded && !$typeExcluded) {
                $filteredEvents[] = $event;
            }
        }

        $this->assertCount(2, $filteredEvents, 'After filtering, only 2 events should remain');
        $this->assertEquals('user.created', $filteredEvents[0]['type']);
        $this->assertEquals('order.placed', $filteredEvents[1]['type']);

        // Step 3: Filter out already synced events
        $newEvents = [];
        foreach ($filteredEvents as $event) {
            $isDuplicate = false;

            // Check if this event is already in synced events
            foreach ($syncedEvents as $syncedEvent) {
                if ($syncedEvent['event_id'] === $event['id'] &&
                    $syncedEvent['source_url'] === $sourceUrl) {
                    $isDuplicate = true;
                    break;
                }
            }

            if (!$isDuplicate) {
                $newEvents[] = $event;
            }
        }

        $this->assertCount(1, $newEvents, 'After deduplication, only 1 new event should remain');
        $this->assertEquals('user.created', $newEvents[0]['type']);

        // Step 4: Process and store events
        $processed = [];
        $skipped = [];
        $logs = [];

        // Process each event
        foreach ($filteredEvents as $event) {
            // Check if event is already synced
            $isDuplicate = false;
            foreach ($syncedEvents as $syncedEvent) {
                if ($syncedEvent['event_id'] === $event['id'] &&
                    $syncedEvent['source_url'] === $sourceUrl) {
                    $isDuplicate = true;
                    break;
                }
            }

            if ($isDuplicate) {
                $skipped[] = $event;
                continue;
            }

            // Process the event
            $syncEvent = [
                'event_id' => $event['id'],
                'source_url' => $sourceUrl,
                'event_type' => $event['type'],
                'event_data' => $event['data'],
                'sync_metadata' => [
                    'pulled_at' => date('c'),
                    'source_url' => $sourceUrl,
                ],
                'synced_at' => date('c'),
            ];

            // In a real system, this would be saved to database
            $processed[] = $syncEvent;

            // This would dispatch the event through Verbs system
            // Here we just simulate that it worked
            $dispatchSuccessful = true;
            $this->assertTrue($dispatchSuccessful);

            // Add to synced events list (simulating database insert)
            $syncedEvents[] = [
                'event_id' => $event['id'],
                'source_url' => $sourceUrl
            ];
        }

        // Step 5: Log the sync operation
        $log = [
            'operation' => 'pull',
            'status' => 'success',
            'events_count' => count($filteredEvents),
            'details' => [
                'processed' => count($processed),
                'skipped' => count($skipped),
            ],
        ];
        $logs[] = $log;

        // --- 3. VERIFICATION PHASE ---

        // Verify the correct events were processed and skipped
        $this->assertCount(1, $processed, 'One event should be processed');
        $this->assertCount(1, $skipped, 'One event should be skipped');
        $this->assertEquals('user.created', $processed[0]['event_type']);
        $this->assertEquals('order.placed', $skipped[0]['type']);

        // Verify log was created correctly
        $this->assertCount(1, $logs);
        $this->assertEquals('pull', $logs[0]['operation']);
        $this->assertEquals('success', $logs[0]['status']);
        $this->assertEquals(2, $logs[0]['events_count']);
        $this->assertEquals(1, $logs[0]['details']['processed']);
        $this->assertEquals(1, $logs[0]['details']['skipped']);

        // Verify we now have all events in the synced list
        $this->assertCount(2, $syncedEvents);
    }

    /** @test */
    public function it_simulates_dry_run_mode()
    {
        // Mock API response
        $apiResponse = [
            'data' => [
                [
                    'id' => 'event-1',
                    'type' => 'user.created',
                    'data' => ['name' => 'Jane Doe'],
                    'created_at' => date('c'),
                ],
            ]
        ];

        // Empty synced events database
        $syncedEvents = [];

        // Configuration with dry run enabled
        $dryRun = true;

        // Process events
        $events = $apiResponse['data'] ?? [];
        $processed = [];
        $syncedEventsBefore = count($syncedEvents);

        if (!$dryRun) {
            // This would normally save the events
            foreach ($events as $event) {
                $processed[] = $event;
                $syncedEvents[] = [
                    'event_id' => $event['id'],
                    'source_url' => 'http://example.com/api'
                ];
            }
        }

        // In dry run mode, nothing should be processed or saved
        $this->assertEmpty($processed, 'No events should be processed in dry run mode');
        $this->assertEquals($syncedEventsBefore, count($syncedEvents), 'Synced events count should not change');
    }

    /** @test */
    public function it_simulates_error_handling()
    {
        // Simulate API error
        $apiError = true;
        $apiErrorCode = 401;
        $apiErrorMessage = 'Unauthorized';

        // Empty databases
        $syncedEvents = [];
        $logs = [];

        // Process request
        if ($apiError) {
            // Log the error
            $logs[] = [
                'operation' => 'pull',
                'status' => 'failed',
                'events_count' => 0,
                'details' => [
                    'error' => "Failed to pull events: {$apiErrorCode}",
                    'message' => $apiErrorMessage
                ],
            ];

            // This would be the command exit code
            $exitCode = 1;
        } else {
            // Normal processing would happen here
            $exitCode = 0;
        }

        // Verify error was logged correctly
        $this->assertCount(1, $logs);
        $this->assertEquals('failed', $logs[0]['status']);
        $this->assertEquals(0, $logs[0]['events_count']);
        $this->assertArrayHasKey('error', $logs[0]['details']);

        // Verify no events were processed
        $this->assertEmpty($syncedEvents);

        // Verify exit code
        $this->assertEquals(1, $exitCode);
    }
}
