<?php

namespace App\Observers;

use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Product;

class PurchaseObserver
{
    /**
     * Handle the Purchase "created" event.
     */
    public function created(Purchase $purchase): void
    {
        // No stock update on creation
        // Stock will be updated when purchase is marked as received
    }

    /**
     * Handle the Purchase "updated" event.
     */
    public function updated(Purchase $purchase): void
    {
        // Check if status changed to received
        if ($purchase->wasChanged('status') && $purchase->status === 'received') {
            $this->updateStockForReceivedPurchase($purchase);
        }
        
        // Check if status changed from received to something else
        if ($purchase->wasChanged('status') && $purchase->getOriginal('status') === 'received') {
            $this->restoreStockForCancelledPurchase($purchase);
        }
    }

    /**
     * Handle the Purchase "deleted" event.
     */
    public function deleted(Purchase $purchase): void
    {
        // Restore stock if purchase was received
        if ($purchase->status === 'received') {
            $this->restoreStockForCancelledPurchase($purchase);
        }
    }

    /**
     * Update stock when purchase is received.
     */
    private function updateStockForReceivedPurchase(Purchase $purchase): void
    {
        $purchaseItems = PurchaseItem::where('purchase_id', $purchase->id)->get();
        
        foreach ($purchaseItems as $item) {
            $product = Product::find($item->product_id);
            if ($product) {
                $product->addStock($item->quantity);
                
                // Log the stock addition for audit purposes
                \Log::info('Stock added from purchase', [
                    'purchase_id' => $purchase->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'quantity_added' => $item->quantity,
                    'new_stock' => $product->quantity,
                ]);
            }
        }
    }

    /**
     * Restore stock when purchase is cancelled or deleted.
     */
    private function restoreStockForCancelledPurchase(Purchase $purchase): void
    {
        $purchaseItems = PurchaseItem::where('purchase_id', $purchase->id)->get();
        
        foreach ($purchaseItems as $item) {
            $product = Product::find($item->product_id);
            if ($product) {
                // Only restore stock if we have enough stock to deduct
                if ($product->quantity >= $item->quantity) {
                    $product->quantity -= $item->quantity;
                    $product->save();
                    
                    // Log the stock restoration for audit purposes
                    \Log::info('Stock restored from cancelled purchase', [
                        'purchase_id' => $purchase->id,
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'quantity_restored' => $item->quantity,
                        'new_stock' => $product->quantity,
                    ]);
                } else {
                    // Log warning if insufficient stock
                    \Log::warning('Insufficient stock to restore from cancelled purchase', [
                        'purchase_id' => $purchase->id,
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'quantity_to_restore' => $item->quantity,
                        'available_stock' => $product->quantity,
                    ]);
                }
            }
        }
    }
}