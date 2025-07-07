# Verbs Sync

A Laravel package that allows applications using [Verbs](https://verbs.thunk.dev) to sync events from a remote source.

## Overview

Verbs Sync is a simple CLI tool that enables a Laravel application using the Verbs package to pull events from another Verbs-powered application. This tool focuses on a straightforward pull-based syncing model where your application acts as the destination, pulling events from a remote source.

## Features

- Pull Verbs events from a remote source application
- API token authentication for secure communication
- Selective event filtering
- Event deduplication to prevent duplicate processing
- Comprehensive logging
- Simple command-line interface

## Installation

You can install the package via composer:

```bash
composer require danielcoulbourne/verbs-sync
```

After installing, publish the configuration file:

```bash
php artisan vendor:publish --provider="DanielCoulbourne\VerbsSync\VerbsSyncServiceProvider"
```

Run the migrations:

```bash
php artisan migrate
```

## Quick Setup

1. Install the package
2. Publish the configuration
3. Set your source URL and API token in your `.env` file
4. Run the sync command

## Configuration

### Configuration File

The package's configuration file (`config/verbs-sync.php`) contains the following settings:

- `source`: Configuration for the remote source (URL and API token)
- `events`: Configuration for which events to sync (include/exclude)
- `options`: Syncing behavior options (batch size, retries)

### Environment Variables

Configure the package using these environment variables:

```
VERBS_SYNC_SOURCE_URL=https://source-app.example.com
VERBS_SYNC_API_TOKEN=your-secure-token
VERBS_SYNC_INCLUDE_EVENTS=*
VERBS_SYNC_EXCLUDE_EVENTS=
VERBS_SYNC_BATCH_SIZE=100
VERBS_SYNC_RETRY_ATTEMPTS=3
```

## Usage

To sync events from the source application:

```bash
php artisan verbs:sync
```

### Options

- `--since=TIMESTAMP`: Only pull events since this timestamp (ISO-8601 format)
- `--types=type1,type2`: Comma-separated list of event types to pull
- `--limit=100`: Maximum number of events to pull per batch
- `--dry-run`: Show what would be synced without actually syncing

### Examples

Sync all events:
```bash
php artisan verbs:sync
```

Sync only specific event types:
```bash
php artisan verbs:sync --types=user.created,user.updated
```

Sync events since a specific time:
```bash
php artisan verbs:sync --since="2023-06-01T00:00:00Z"
```

Preview what would be synced:
```bash
php artisan verbs:sync --dry-run
```

## How It Works

1. The `verbs:sync` command makes a request to the source application's Verbs API
2. It fetches events based on your filters (event types, time range, etc.)
3. For each event, it:
   - Checks if the event has already been synced (to avoid duplicates)
   - Stores a record of the event in the local database
   - Processes the event through your local Verbs system
4. Events are marked with `synced: true` metadata to prevent infinite loops

## Database Tables

The package creates two tables:

1. `verbs_sync_events`: Tracks synced events with their source, type, and status
2. `verbs_sync_logs`: Records sync operations for monitoring and troubleshooting

## Integrating with Scheduled Tasks

For regular syncing, you can add the command to your Laravel scheduler:

```php
// In app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    // Sync events every hour
    $schedule->command('verbs:sync')->hourly();
    
    // Or with options
    $schedule->command('verbs:sync --types=user.created,order.placed --limit=200')
        ->dailyAt('02:00');
}
```

## Testing

This package includes a comprehensive test suite. To run the tests:

```bash
composer test
```

### Test Coverage

The test suite covers:

- Behavior tests for core functionality
- Command option processing
- Event filtering and deduplication
- Error handling scenarios
- End-to-end flow simulation

## Troubleshooting

If you encounter issues with event syncing:

1. Use the `--dry-run` option to see what would be synced
2. Check your Laravel logs for errors
3. Ensure your API token is configured correctly
4. Verify network connectivity to the source application
5. Check that event types are not excluded in configuration

## License

This package is open-sourced software licensed under the MIT license.

This package is open-sourced software licensed under the MIT license.