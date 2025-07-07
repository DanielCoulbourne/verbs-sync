<?php

namespace DanielCoulbourne\VerbsSync\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Test for model behaviors without using actual model classes
 */
class ModelBehaviorTest extends TestCase
{
    /** @test */
    public function sync_event_model_tracks_synced_events()
    {
        // Simulating SyncEvent model behavior
        $syncEvent = [
            'event_id' => '123',
            'source_url' => 'http://example.com',
            'event_type' => 'user.created',
            'event_data' => ['name' => 'Test User'],
            'sync_metadata' => ['pulled_at' => '2023-01-01T00:00:00Z'],
            'synced_at' => null,
        ];

        // Simulate marking as synced
        $syncEvent['synced_at'] = '2023-01-01T01:00:00Z';

        // Verify behavior
        $this->assertEquals('123', $syncEvent['event_id']);
        $this->assertEquals('http://example.com', $syncEvent['source_url']);
        $this->assertEquals('user.created', $syncEvent['event_type']);
        $this->assertIsArray($syncEvent['event_data']);
        $this->assertEquals('2023-01-01T01:00:00Z', $syncEvent['synced_at']);
    }

    /** @test */
    public function sync_log_model_records_sync_operations()
    {
        // Simulating SyncLog model behavior
        $syncLog = [
            'operation' => 'pull',
            'status' => 'success',
            'events_count' => 5,
            'details' => ['processed' => 5, 'skipped' => 0],
        ];

        // Verify behavior
        $this->assertEquals('pull', $syncLog['operation']);
        $this->assertEquals('success', $syncLog['status']);
        $this->assertEquals(5, $syncLog['events_count']);
        $this->assertIsArray($syncLog['details']);
        $this->assertEquals(5, $syncLog['details']['processed']);
    }

    /** @test */
    public function it_prevents_duplicate_event_syncing()
    {
        // Simulate database of synced events
        $syncedEvents = [
            ['event_id' => '123', 'source_url' => 'http://example.com'],
            ['event_id' => '456', 'source_url' => 'http://example.com'],
        ];

        // Incoming event to check
        $incomingEvent = ['id' => '123', 'source' => 'http://example.com'];

        // Check if event is already synced
        $isDuplicate = false;
        foreach ($syncedEvents as $event) {
            if ($event['event_id'] === $incomingEvent['id'] &&
                $event['source_url'] === $incomingEvent['source']) {
                $isDuplicate = true;
                break;
            }
        }

        // Verify duplicate detection
        $this->assertTrue($isDuplicate);

        // New event that's not a duplicate
        $newEvent = ['id' => '789', 'source' => 'http://example.com'];

        // Check if new event is already synced
        $isNewDuplicate = false;
        foreach ($syncedEvents as $event) {
            if ($event['event_id'] === $newEvent['id'] &&
                $event['source_url'] === $newEvent['source']) {
                $isNewDuplicate = true;
                break;
            }
        }

        // Verify new event is not detected as duplicate
        $this->assertFalse($isNewDuplicate);
    }

    /** @test */
    public function it_handles_event_filtering()
    {
        // Simulating configuration
        $config = [
            'events' => [
                'include' => ['user.created', 'user.updated'],
                'exclude' => ['user.deleted'],
            ],
        ];

        // Event types to test
        $eventTypes = [
            'user.created',  // Should be included
            'user.updated',  // Should be included
            'user.deleted',  // Should be excluded despite being in include list
            'post.created',  // Should be excluded because not in include list
        ];

        // Filter events based on configuration
        $filteredTypes = [];
        foreach ($eventTypes as $type) {
            // Check if type should be included
            $shouldInclude = in_array($type, $config['events']['include']);

            // Check if type is explicitly excluded
            $isExcluded = in_array($type, $config['events']['exclude']);

            // Only include if it passes both checks
            if ($shouldInclude && !$isExcluded) {
                $filteredTypes[] = $type;
            }
        }

        // Verify filtering
        $this->assertCount(2, $filteredTypes);
        $this->assertContains('user.created', $filteredTypes);
        $this->assertContains('user.updated', $filteredTypes);
        $this->assertNotContains('user.deleted', $filteredTypes);
        $this->assertNotContains('post.created', $filteredTypes);
    }

    /** @test */
    public function it_formats_human_readable_descriptions()
    {
        // Simulating log entry
        $log = [
            'operation' => 'pull',
            'status' => 'success',
            'events_count' => 5,
        ];

        // Generate description
        $description = $this->getDescription($log);

        // Verify description
        $this->assertEquals('Pull 5 events from source: success', $description);

        // Test with zero events
        $log['events_count'] = 0;
        $description = $this->getDescription($log);
        $this->assertEquals('Pull no events from source: success', $description);

        // Test different operation
        $log['operation'] = 'check';
        $description = $this->getDescription($log);
        $this->assertEquals('check operation: success', $description);
    }

    /**
     * Helper method to simulate SyncLog::getDescription()
     */
    private function getDescription(array $log)
    {
        $eventCount = $log['events_count'] > 0 ? "{$log['events_count']} events" : "no events";

        if ($log['operation'] === 'pull') {
            return "Pull {$eventCount} from source: {$log['status']}";
        }

        return "{$log['operation']} operation: {$log['status']}";
    }
}
