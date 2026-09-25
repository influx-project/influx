<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('daemons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('token');
            $table->json('agent')->nullable();
            $table->string('stream_id')->nullable();
            $table->unsignedBigInteger('last_seq')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });

        Schema::create('daemon_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->timestamp('minute');
            $table->unsignedInteger('samples');
            $table->unsignedInteger('seconds');
            $table->float('cpu_percent');
            $table->float('cpu_percent_max');
            $table->float('load_1');
            $table->unsignedBigInteger('memory_used_bytes');
            $table->unsignedBigInteger('memory_total_bytes');
            $table->unsignedBigInteger('swap_used_bytes');
            $table->float('disk_used_percent')->nullable();
            $table->unsignedBigInteger('disk_read_bytes');
            $table->unsignedBigInteger('disk_write_bytes');
            $table->unsignedBigInteger('network_rx_bytes');
            $table->unsignedBigInteger('network_tx_bytes');
            $table->unsignedInteger('containers_total')->nullable();
            $table->unsignedInteger('containers_running')->nullable();
            $table->unsignedInteger('containers_unhealthy')->nullable();

            $table->unique(['service_id', 'minute']);
            $table->index('minute');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daemon_metrics');
        Schema::dropIfExists('daemons');
    }
};
