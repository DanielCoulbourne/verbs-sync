<?php

namespace DanielCoulbourne\VerbsSync\Tests;

use PHPUnit\Framework\TestCase;
use DanielCoulbourne\VerbsSync\VerbsSync;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;

/**
 * A test class that directly tests VerbsSync without Laravel dependencies
 */
class DirectVerbsSyncTest extends TestCase
{
    /** @test */
    public function it_can_be_instantiated()
    {
        // Mock application
        $app = $this->createMock(\Illuminate\Contracts\Foundation\Application::class);

        $verbsSync = new VerbsSync($app);
        $this->assertInstanceOf(VerbsSync::class, $verbsSync);
    }

    /** @test */
    public function it_handles_missing_source_url()
    {
        // Create app mock that returns null for config
        $app = $this->createMock(\Illuminate\Contracts\Foundation\Application::class);
        $app->method('make')->willReturn(null);

        // Create config mock
        $config = $this->createMock(\Illuminate\Contracts\Config\Repository::class);
        $config->method('get')->willReturn(null);

        // Bind the config to the app
        $app->method('make')->with('config')->willReturn($config);

        $verbsSync = new VerbsSync($app);

        // Create a reflection method to access protected method
        $processEvents = new \ReflectionMethod(VerbsSync::class, 'processIncomingEvents');
        $processEvents->setAccessible(true);

        // Call processIncomingEvents directly
        $result = $processEvents->invoke($verbsSync, new Collection([]), 'http://example.com');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }
}
