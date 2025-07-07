<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVerbsSyncTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('verbs_sync_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_id')->index();
            $table->string('source_url')->nullable();
            $table->string('event_type');
            $table->json('event_data');
            $table->json('sync_metadata')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index(['event_type', 'synced_at']);
            $table->unique(['event_id', 'source_url'], 'unique_event_source');
        });

        Schema::create('verbs_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->string('operation')->index();
            $table->string('status');
            $table->json('details')->nullable();
            $table->integer('events_count')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('verbs_sync_logs');
        Schema::dropIfExists('verbs_sync_events');
    }
}
