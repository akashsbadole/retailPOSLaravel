<?php

namespace App\Observers;

use App\Models\StoreStock;
use Illuminate\Support\Facades\Log;

class StoreStockObserver
{
    /**
     * Handle the StoreStock "created" event.
     */
    public function created(StoreStock $storeStock): void
    {
        Log::info('Store stock created', [
            'store_id' => $storeStock->store_id,
            'product_id' => $storeStock->product_id,
            'quantity' => $storeStock->quantity,
            'minimum_stock' => $storeStock->minimum_stock,
        ]);
    }

    /**
     * Handle the StoreStock "updated" event.
     */
    public function updated(StoreStock $storeStock): void
    {
        // Check if quantity changed
        if ($storeStock->wasChanged('quantity')) {
            $previousQuantity = $storeStock->getOriginal('quantity');
            $newQuantity = $storeStock->quantity;
            
            Log::info('Store stock quantity updated', [
                'store_id' => $storeStock->store_id,
                'product_id' => $storeStock->product_id,
                'previous_quantity' => $previousQuantity,
                'new_quantity' => $newQuantity,
                'change' => $newQuantity - $previousQuantity,
            ]);
        }
        
        // Check if minimum stock changed
        if ($storeStock->wasChanged('minimum_stock')) {
            Log::info('Store minimum stock updated', [
                'store_id' => $storeStock->store_id,
                'product_id' => $storeStock->product_id,
                'previous_minimum' => $storeStock->getOriginal('minimum_stock'),
                'new_minimum' => $storeStock->minimum_stock,
            ]);
        }
    }

    /**
     * Handle the StoreStock "deleted" event.
     */
    public function deleted(StoreStock $storeStock): void
    {
        Log::warning('Store stock deleted', [
            'store_id' => $storeStock->store_id,
            'product_id' => $storeStock->product_id,
            'quantity' => $storeStock->quantity,
        ]);
    }
}