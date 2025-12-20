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
        Schema::create('import_export_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // import, export, backup
            $table->string('resource_type');
            $table->string('status'); // pending, processing, completed, failed
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('file_path')->nullable();
            $table->integer('total_records')->default(0);
            $table->integer('processed_records')->default(0);
            $table->integer('failed_records')->default(0);
            $table->json('errors')->nullable();
            $table->json('options')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            
            $table->index(['type', 'status']);
            $table->index('resource_type');
            $table->index('user_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('import_export_jobs');
    }
};