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
        Schema::create('backups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type'); // full, selective, incremental, structure
            $table->string('status'); // pending, processing, completed, failed
            $table->string('format')->default('sql'); // sql, json
            $table->string('file_path')->nullable();
            $table->string('disk')->default('local');
            $table->unsignedBigInteger('file_size')->nullable();
            $table->integer('total_records')->default(0);
            $table->integer('processed_records')->default(0);
            $table->integer('failed_records')->default(0);
            $table->json('tables')->nullable(); // List of tables included in backup
            $table->json('options')->nullable(); // Backup options and settings
            $table->json('errors')->nullable(); // Error messages if backup failed
            $table->text('description')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('store_id')->nullable()->constrained()->onDelete('cascade');
            $table->timestamps();
            
            // Indexes for better performance
            $table->index(['type', 'status']);
            $table->index(['user_id', 'created_at']);
            $table->index(['store_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('backups');
    }
};