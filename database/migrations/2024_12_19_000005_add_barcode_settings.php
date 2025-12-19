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
        Schema::table('settings', function (Blueprint $table) {
            $table->string('default_barcode_format')->default('CODE128');
            $table->boolean('auto_generate_barcodes')->default(true);
            $table->string('barcode_prefix')->default('PROD');
            $table->boolean('scanner_sound_enabled')->default(true);
            $table->integer('scanner_timeout')->default(5000);
            $table->boolean('require_unique_barcodes')->default(true);
            $table->boolean('allow_manual_barcode_entry')->default(true);
            $table->boolean('barcode_validation_enabled')->default(true);
            $table->string('default_label_size')->default('standard');
            $table->integer('labels_per_product')->default(1);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn([
                'default_barcode_format',
                'auto_generate_barcodes',
                'barcode_prefix',
                'scanner_sound_enabled',
                'scanner_timeout',
                'require_unique_barcodes',
                'allow_manual_barcode_entry',
                'barcode_validation_enabled',
                'default_label_size',
                'labels_per_product',
            ]);
        });
    }
};