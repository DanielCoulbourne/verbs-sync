<?php

namespace DanielCoulbourne\VerbsSync\Tests\Feature;

use DanielCoulbourne\VerbsSync\Tests\TestCase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class PullEventsCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createVerbsTables();
        Http::preventStrayRequests();
    }

    /** @test */
    public function it_runs_the_verbs_sync_command()
    {
        // Mock HTTP response
        Http::fake([
            'http://example.com/api*' => Http::response([
                'events' => [
                    [
                        'id' => '123-abc',
                        'type' => 'user.created',
                        'data' => ['name' => 'Test User'],
                        'created_at' => now()->toIso8601String(),
                    ],
                ],
            ], 200),
        ]);

        // Run the command
        $this->artisan('verbs:sync')
            ->expectsOutput('Syncing events from source: http://example.com/api')
            ->assertExitCode(0);

        // Verify event was inserted into verbs_events table
        $this->assertDatabaseHas('verbs_events', [
            'id' => '123-abc',
            'type' => 'user.created',
        ]);

        // Verify event was tracked in verbs_sync_events table
        $this->assertDatabaseHas('verbs_sync_events', [
            'event_id' => '123-abc',
            'event_type' => 'user.created',
            'source_url' => 'http://example.com/api',
        ]);

        // Verify log was created
        $this->assertDatabaseHas('verbs_sync_logs', [
            'operation' => 'pull_events',
            'status' => 'success',
            'events_count' => 1,
        ]);
    }

    /** @test */
    public function it_handles_api_errors()
    {
        // Mock HTTP error response
        Http::fake([
            'http://example.com/api*' => Http::response([
                'message' => 'Unauthorized',
            ], 401),
        ]);

        // Run the command
        $this->artisan('verbs:sync')
            ->expectsOutput('Failed to sync events: Failed to pull events: 401')
            ->assertExitCode(1);

        // Verify no events were inserted
        $this->assertEquals(0, DB::table('verbs_events')->count());

        // Verify error was logged
        $this->assertDatabaseHas('verbs_sync_logs', [
            'operation' => 'pull_events',
            'status' => 'failed',
        ]);
    }

    /** @test */
    public function it_handles_dry_run_mode()
    {
        // Mock HTTP response
        Http::fake([
            'http://example.com/api*' => Http::response([
                'events' => [
                    [
                        'id' => '123-abc',
                        'type' => 'user.created',
                        'data' => ['name' => 'Test User'],
                        'created_at' => now()->toIso8601String(),
                    ],
                ],
            ], 200),
        ]);

        // Run the command with dry-run option
        $this->artisan('verbs:sync --dry-run')
            ->expectsOutput('DRY RUN MODE - No events will be synced')
            ->expectsOutput('Found 1 events that would be synced')
            ->assertExitCode(0);

        // Verify no events were actually inserted
        $this->assertEquals(0, DB::table('verbs_events')->count());
        $this->assertEquals(0, DB::table('verbs_sync_events')->count());
        $this->assertEquals(0, DB::table('verbs_sync_logs')->count());
    }

    /** @test */
    public function it_respects_event_filtering()
    {
        // Mock HTTP response with multiple event types
        Http::fake([
            'http://example.com/api*' => Http::response([
                'events' => [
                    [
                        'id' => '123-abc',
                        'type' => 'user.created',
                        'data' => ['name' => 'Test User'],
                        'created_at' => now()->toIso8601String(),
                    ],
                    [
                        'id' => '456-def',
                        'type' => 'post.created',
                        'data' => ['title' => 'Test Post'],
                        'created_at' => now()->toIso8601String(),
                    ],
                ],
            ], 200),
        ]);

        // Set config to only include user events
        config(['verbs-sync.events.include' => ['user.created']]);

        // Run the command
        $this->artisan('verbs:sync')
            ->expectsOutput('Syncing events from source: http://example.com/api')
            ->assertExitCode(0);

        // Verify only the user.created event was processed
        $this->assertDatabaseHas('verbs_events', [
            'id' => '123-abc',
            'type' => 'user.created',
        ]);

        $this->assertDatabaseMissing('verbs_events', [
            'id' => '456-def',
        ]);
    }

    /** @test */
    public function it_prevents_duplicate_events()
    {
        // Create a pre-existing event
        DB::table('verbs_sync_events')->insert([
            'event_id' => '123-abc',
            'source_url' => 'http://example.com/api',
            'event_type' => 'user.created',
            'event_data' => json_encode(['name' => 'Test User']),
            'sync_metadata' => json_encode(['source' => 'http://example.com/api']),
            'synced_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Mock HTTP response with the same event plus a new one
        Http::fake([
            'http://example.com/api*' => Http::response([
                'events' => [
                    [
                        'id' => '123-abc',
                        'type' => 'user.created',
                        'data' => ['name' => 'Test User'],
                        'created_at' => now()->toIso8601String(),
                    ],
                    [
                        'id' => '456-def',
                        'type' => 'user.updated',
                        'data' => ['name' => 'Updated User'],
                        'created_at' => now()->toIso8601String(),
                    ],
                ],
            ], 200),
        ]);

        // Run the command
        $this->artisan('verbs:sync')
            ->expectsOutput('Syncing events from source: http://example.com/api')
            ->assertExitCode(0);

        // Verify only the new event was added
        $this->assertEquals(1, DB::table('verbs_events')->where('id', '123-abc')->count());
        $this->assertEquals(1, DB::table('verbs_events')->where('id', '456-def')->count());
        $this->assertEquals(2, DB::table('verbs_sync_events')->count());
    }
}
