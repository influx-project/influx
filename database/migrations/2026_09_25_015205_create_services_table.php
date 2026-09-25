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
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('location')->nullable();
            $table->string('type')->index();
            $table->string('host');
            $table->unsignedInteger('port')->nullable();
            $table->boolean('use_ssl')->default(false);
            $table->string('importance')->default('normal')->index();
            $table->unsignedInteger('check_interval')->default(60);
            $table->unsignedInteger('timeout')->default(10);
            $table->boolean('collect_metrics')->default(true);
            $table->boolean('stream_metrics')->default(true);
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
