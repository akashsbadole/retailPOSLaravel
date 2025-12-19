<?php

namespace App\Observers;

use App\Models\StockAdjustment;
use App\Models\Product;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class StockAdjustmentObserver
{
    /**
     * Handle the StockAdjustment "created" event.
     */
    public function created(StockAdjustment $stockAdjustment): void
    {
        // Log the creation of stock adjustment
        Log::info('Stock Adjustment Created', [
            'adjustment_id' => $stockAdjustment->id,
            'product_id' => $stockAdjustment->product_id,
            'product_name' => $stockAdjustment->product->name ?? 'Unknown',
            'quantity' => $stockAdjustment->quantity,
            'adjustment_type' => $stockAdjustment->adjustment_type,
            'reason' => $stockAdjustment->reason,
            'previous_quantity' => $stockAdjustment->previous_quantity,
            'new_quantity' => $stockAdjustment->new_quantity,
            'user_id' => $stockAdjustment->user_id,
            'user_name' => $stockAdjustment->user->name ?? 'Unknown',
            'approved' => $stockAdjustment->approved,
            'notes' => $stockAdjustment->notes,
            'created_at' => $stockAdjustment->created_at,
        ]);

        // If auto-approved, log the stock change
        if ($stockAdjustment->approved) {
            $this->logStockChange($stockAdjustment, 'auto_approved');
        }
    }

    /**
     * Handle the StockAdjustment "updated" event.
     */
    public function updated(StockAdjustment $stockAdjustment): void
    {
        // Check if approval status changed
        if ($stockAdjustment->wasChanged('approved') && $stockAdjustment->approved) {
            Log::info('Stock Adjustment Approved', [
                'adjustment_id' => $stockAdjustment->id,
                'product_id' => $stockAdjustment->product_id,
                'product_name' => $stockAdjustment->product->name ?? 'Unknown',
                'approved_by' => $stockAdjustment->approved_by,
                'approved_by_name' => $stockAdjustment->approvedBy->name ?? 'Unknown',
                'approved_at' => $stockAdjustment->approved_at,
            ]);

            // Apply the adjustment to product stock
            $this->logStockChange($stockAdjustment, 'approved');
        }

        // Log any other changes
        $changes = $stockAdjustment->getChanges();
        if (!empty($changes) && !$stockAdjustment->wasChanged('approved')) {
            Log::info('Stock Adjustment Updated', [
                'adjustment_id' => $stockAdjustment->id,
                'product_id' => $stockAdjustment->product_id,
                'changes' => $changes,
                'updated_by' => Auth::id(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Handle the StockAdjustment "deleted" event.
     */
    public function deleted(StockAdjustment $stockAdjustment): void
    {
        Log::warning('Stock Adjustment Deleted', [
            'adjustment_id' => $stockAdjustment->id,
            'product_id' => $stockAdjustment->product_id,
            'product_name' => $stockAdjustment->product->name ?? 'Unknown',
            'quantity' => $stockAdjustment->quantity,
            'adjustment_type' => $stockAdjustment->adjustment_type,
            'reason' => $stockAdjustment->reason,
            'deleted_by' => Auth::id(),
            'deleted_at' => now(),
        ]);
    }

    /**
     * Handle the StockAdjustment "restored" event.
     */
    public function restored(StockAdjustment $stockAdjustment): void
    {
        Log::info('Stock Adjustment Restored', [
            'adjustment_id' => $stockAdjustment->id,
            'product_id' => $stockAdjustment->product_id,
            'restored_by' => Auth::id(),
            'restored_at' => now(),
        ]);
    }

    /**
     * Handle the StockAdjustment "force deleted" event.
     */
    public function forceDeleted(StockAdjustment $stockAdjustment): void
    {
        Log::alert('Stock Adjustment Force Deleted', [
            'adjustment_id' => $stockAdjustment->id,
            'product_id' => $stockAdjustment->product_id,
            'deleted_by' => Auth::id(),
            'deleted_at' => now(),
        ]);
    }

    /**
     * Log stock changes to the audit trail
     */
    private function logStockChange(StockAdjustment $stockAdjustment, string $event): void
    {
        $product = $stockAdjustment->product;
        if (!$product) {
            return;
        }

        Log::info('Stock Change Applied', [
            'event' => $event,
            'adjustment_id' => $stockAdjustment->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'previous_quantity' => $stockAdjustment->previous_quantity,
            'new_quantity' => $stockAdjustment->new_quantity,
            'quantity_change' => $stockAdjustment->quantity,
            'adjustment_type' => $stockAdjustment->adjustment_type,
            'reason' => $stockAdjustment->reason,
            'user_id' => $stockAdjustment->user_id,
            'user_name' => $stockAdjustment->user->name ?? 'Unknown',
            'approved_by' => $stockAdjustment->approved_by,
            'approved_by_name' => $stockAdjustment->approvedBy->name ?? 'System',
            'timestamp' => now(),
        ]);

        // Create a detailed audit log entry
        $this->createAuditLog($stockAdjustment, $event);
    }

    /**
     * Create a detailed audit log entry
     */
    private function createAuditLog(StockAdjustment $stockAdjustment, string $event): void
    {
        $product = $stockAdjustment->product;
        if (!$product) {
            return;
        }

        $auditData = [
            'model_type' => StockAdjustment::class,
            'model_id' => $stockAdjustment->id,
            'event' => $event,
            'old_values' => [
                'product_quantity' => $stockAdjustment->previous_quantity,
            ],
            'new_values' => [
                'product_quantity' => $stockAdjustment->new_quantity,
            ],
            'user_id' => $stockAdjustment->approved_by ?? $stockAdjustment->user_id,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'metadata' => [
                'adjustment_type' => $stockAdjustment->adjustment_type,
                'reason' => $stockAdjustment->reason,
                'quantity' => $stockAdjustment->quantity,
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_sku' => $product->sku,
                'notes' => $stockAdjustment->notes,
            ],
            'created_at' => now(),
            'updated_at' => now(),
        ];

        // Store in audit_logs table if it exists
        try {
            if (\Schema::hasTable('audit_logs')) {
                \DB::table('audit_logs')->insert($auditData);
            }
        } catch (\Exception $e) {
            Log::error('Failed to create audit log', [
                'error' => $e->getMessage(),
                'audit_data' => $auditData,
            ]);
        }
    }

    /**
     * Log when product stock is manually updated outside of adjustments
     */
    public function productStockUpdated(Product $product, int $oldQuantity, int $newQuantity): void
    {
        $difference = $newQuantity - $oldQuantity;
        
        Log::warning('Product Stock Updated Manually', [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'old_quantity' => $oldQuantity,
            'new_quantity' => $newQuantity,
            'difference' => $difference,
            'user_id' => Auth::id(),
            'user_name' => Auth::user()->name ?? 'Unknown',
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'timestamp' => now(),
        ]);
    }
}