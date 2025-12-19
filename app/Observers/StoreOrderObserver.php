<?php

namespace App\Observers;

use App\Models\StoreOrder;
use App\Models\StoreStock;
use Illuminate\Support\Facades\Log;

class StoreOrderObserver
{
    /**
     * Handle the StoreOrder "created" event.
     */
    public function created(StoreOrder $storeOrder): void
    {
        Log::info('Store order created', [
            'store_order_id' => $storeOrder->id,
            'store_id' => $storeOrder->store_id,
            'customer_id' => $storeOrder->customer_id,
            'total_price' => $storeOrder->total_price,
            'status' => $storeOrder->status,
        ]);
    }

    /**
     * Handle the StoreOrder "updated" event.
     */
    public function updated(StoreOrder $storeOrder): void
    {
        Log::info('Store order updated', [
            'store_order_id' => $storeOrder->id,
            'store_id' => $storeOrder->store_id,
            'changes' => $storeOrder->getDirty(),
        ]);
    }

    /**
     * Handle the StoreOrder "deleted" event.
     */
    public function deleted(StoreOrder $storeOrder): void
    {
        Log::warning('Store order deleted', [
            'store_order_id' => $storeOrder->id,
            'store_id' => $storeOrder->store_id,
            'total_price' => $storeOrder->total_price,
        ]);
    }
}