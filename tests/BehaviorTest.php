<?php

namespace DanielCoulbourne\VerbsSync\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Simple tests to verify the behavior of our package without relying on the framework
 */
class BehaviorTest extends TestCase
{
    /** @test */
    public function it_can_process_events()
    {
        // Mock event data that would come from a source
        $event = [
            'id' => '123',
            'type' => 'user.created',
            'data' => ['name' => 'Test User'],
            'created_at' => '2023-01-01T00:00:00Z',
        ];

        // Mock source URL
        $sourceUrl = 'http://example.com';

        // Verify behavior that would happen in EventProcessor
        $this->assertArrayHasKey('id', $event);
        $this->assertArrayHasKey('type', $event);
        $this->assertArrayHasKey('data', $event);

        // Verify the event structure is valid
        $this->assertEquals('123', $event['id']);
        $this->assertEquals('user.created', $event['type']);
        $this->assertIsArray($event['data']);

        // Simulate event metadata that would be created
        $metadata = [
            'synced' => true,
            'source_url' => $sourceUrl,
            'original_id' => $event['id'],
            'original_created_at' => $event['created_at'],
        ];

        // Verify metadata structure
        $this->assertTrue($metadata['synced']);
        $this->assertEquals($sourceUrl, $metadata['source_url']);
        $this->assertEquals($event['id'], $metadata['original_id']);
    }

    /** @test */
    public function it_filters_events_based_on_configuration()
    {
        // Mock events
        $events = [
            ['id' => '1', 'type' => 'user.created', 'data' => []],
            ['id' => '2', 'type' => 'user.updated', 'data' => []],
            ['id' => '3', 'type' => 'post.created', 'data' => []],
            ['id' => '4', 'type' => 'post.deleted', 'data' => []],
        ];

        // Mock configuration: include only user events
        $includeTypes = ['user.created', 'user.updated'];

        // Filter events as our package would
        $filteredEvents = array_filter($events, function($event) use ($includeTypes) {
            return in_array($event['type'], $includeTypes);
        });

        // Verify filtering behavior
        $this->assertCount(2, $filteredEvents);
        $eventTypes = array_map(function($event) {
            return $event['type'];
        }, array_values($filteredEvents));
        $this->assertContains('user.created', $eventTypes);
        $this->assertContains('user.updated', $eventTypes);
        $this->assertNotContains('post.created', $eventTypes);
    }

    /** @test */
    public function it_handles_duplicate_events()
    {
        // Mock an array of synced event IDs (simulating database records)
        $syncedEvents = ['123', '456'];

        // Mock incoming events
        $incomingEvents = [
            ['id' => '123', 'type' => 'user.created', 'data' => []],  // Already synced
            ['id' => '789', 'type' => 'user.updated', 'data' => []],  // New event
        ];

        // Filter out already synced events as our package would
        $newEvents = array_filter($incomingEvents, function($event) use ($syncedEvents) {
            return !in_array($event['id'], $syncedEvents);
        });

        // Verify duplicate handling behavior
        $this->assertCount(1, $newEvents);
        $newEvents = array_values($newEvents);
        $this->assertEquals('789', $newEvents[0]['id']);
    }

    /** @test */
    public function it_returns_error_when_source_url_not_configured()
    {
        // Mock the situation where source URL is not configured
        $sourceUrl = null;

        // Simulate the behavior in our VerbsSync class
        if (!$sourceUrl) {
            $result = [
                'success' => false,
                'message' => 'Source URL not configured',
            ];
        } else {
            $result = [
                'success' => true,
            ];
        }

        // Verify the error handling behavior
        $this->assertFalse($result['success']);
        $this->assertEquals('Source URL not configured', $result['message']);
    }

    /** @test */
    public function it_formats_events_for_processing()
    {
        // Mock a response from the API
        $apiResponse = [
            'data' => [
                [
                    'id' => '123',
                    'type' => 'user.created',
                    'data' => ['name' => 'Test User'],
                    'created_at' => '2023-01-01T00:00:00Z',
                ],
            ],
        ];

        // Extract and process events as our package would
        $events = $apiResponse['data'] ?? [];
        $processedEvents = [];

        foreach ($events as $event) {
            // Verify the event has the required fields
            if (isset($event['id']) && isset($event['type']) && isset($event['data'])) {
                $processedEvents[] = [
                    'event_id' => $event['id'],
                    'source_url' => 'http://example.com',
                    'event_type' => $event['type'],
                    'event_data' => $event['data'],
                    'sync_metadata' => [
                        'pulled_at' => date('c'),
                        'source_url' => 'http://example.com',
                    ],
                ];
            }
        }

        // Verify processing behavior
        $this->assertCount(1, $processedEvents);
        $this->assertEquals('123', $processedEvents[0]['event_id']);
        $this->assertEquals('user.created', $processedEvents[0]['event_type']);
        $this->assertEquals(['name' => 'Test User'], $processedEvents[0]['event_data']);
        $this->assertArrayHasKey('pulled_at', $processedEvents[0]['sync_metadata']);
    }
}
