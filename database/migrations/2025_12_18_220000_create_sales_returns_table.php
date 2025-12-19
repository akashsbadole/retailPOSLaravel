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
        Schema::create('sales_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->date('return_date');
            $table->enum('reason', [
                'damaged', 
                'defective', 
                'wrong_item', 
                'not_needed', 
                'customer_dissatisfaction', 
                'expired', 
                'other'
            ]);
            $table->text('notes')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'processed', 'completed'])->default('pending');
            $table->decimal('total_refund_amount', 8, 2);
            $table->enum('refund_method', ['cash', 'card', 'bank_transfer', 'store_credit', 'original_payment'])->default('original_payment');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            
            $table->index(['order_id', 'status']);
            $table->index(['user_id', 'return_date']);
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_returns');
    }
};