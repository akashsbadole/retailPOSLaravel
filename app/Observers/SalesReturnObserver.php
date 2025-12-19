<?php

namespace App\Observers;

use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use App\Models\StockAdjustment;
use App\Services\SalesReturnService;
use Illuminate\Support\Facades\Log;

class SalesReturnObserver
{
    protected $salesReturnService;

    public function __construct(SalesReturnService $salesReturnService)
    {
        $this->salesReturnService = $salesReturnService;
    }

    /**
     * Handle the SalesReturn "created" event.
     */
    public function created(SalesReturn $salesReturn): void
    {
        Log::info('Sales Return created', ['return_id' => $salesReturn->id]);
        
        // If approval is not required, auto-approve the return
        if (!$this->salesReturnService->isApprovalRequired()) {
            $salesReturn->approve();
            Log::info('Sales Return auto-approved', ['return_id' => $salesReturn->id]);
        }
    }

    /**
     * Handle the SalesReturn "updated" event.
     */
    public function updated(SalesReturn $salesReturn): void
    {
        Log::info('Sales Return updated', [
            'return_id' => $salesReturn->id,
            'old_status' => $salesReturn->getOriginal('status'),
            'new_status' => $salesReturn->status
        ]);
        
        // If status changed to approved and auto stock adjustment is enabled
        if ($salesReturn->wasChanged('status') && $salesReturn->isApproved()) {
            if ($this->salesReturnService->isAutoStockAdjustmentEnabled()) {
                $this->salesReturnService->processStockAdjustments($salesReturn);
                Log::info('Stock adjustments processed for approved return', ['return_id' => $salesReturn->id]);
            }
        }
        
        // If status changed to processed, trigger any additional processing
        if ($salesReturn->wasChanged('status') && $salesReturn->isProcessed()) {
            Log::info('Sales Return processed', ['return_id' => $salesReturn->id]);
            // Additional processing logic can be added here
        }
        
        // If status changed to completed, log completion
        if ($salesReturn->wasChanged('status') && $salesReturn->isCompleted()) {
            Log::info('Sales Return completed', ['return_id' => $salesReturn->id]);
            // Additional completion logic can be added here
        }
    }

    /**
     * Handle the SalesReturn "deleted" event.
     */
    public function deleted(SalesReturn $salesReturn): void
    {
        Log::info('Sales Return deleted', ['return_id' => $salesReturn->id]);
        
        // If items were restocked, reverse the stock adjustments
        foreach ($salesReturn->items as $item) {
            if ($item->restocked) {
                $product = $item->product;
                if ($product) {
                    StockAdjustment::createAdjustment(
                        $product,
                        $item->quantity,
                        'decrease',
                        'return_reversal',
                        "Return reversal for deleted Return #{$salesReturn->id}",
                        "Return deleted, reversing stock adjustment",
                        null
                    );
                }
            }
        }
    }

    /**
     * Handle the SalesReturn "restored" event.
     */
    public function restored(SalesReturn $salesReturn): void
    {
        Log::info('Sales Return restored', ['return_id' => $salesReturn->id]);
        
        // If items were restocked before deletion, restore the stock adjustments
        foreach ($salesReturn->items as $item) {
            if ($item->restocked) {
                $product = $item->product;
                if ($product) {
                    StockAdjustment::createAdjustment(
                        $product,
                        $item->quantity,
                        'increase',
                        'return_restored',
                        "Return restoration for restored Return #{$salesReturn->id}",
                        "Return restored, restoring stock adjustment",
                        null
                    );
                }
            }
        }
    }
}