<?php

namespace App\Observers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;

class OrderObserver
{
    /**
     * Handle the Order "created" event.
     */
    public function created(Order $order): void
    {
        // No stock deduction on order creation
        // Stock is deducted when items are added to cart
    }

    /**
     * Handle the Order "updated" event.
     */
    public function updated(Order $order): void
    {
        // Check if order status changed to completed
        if ($order->wasChanged('status') && $order->status === 'completed') {
            $this->deductStockForOrder($order);
        }
        
        // Check if order status changed to cancelled or refunded
        if ($order->wasChanged('status') && in_array($order->status, ['cancelled', 'refunded'])) {
            $this->restoreStockForOrder($order);
        }
    }

    /**
     * Handle the Order "deleted" event.
     */
    public function deleted(Order $order): void
    {
        // Restore stock when order is deleted
        $this->restoreStockForOrder($order);
    }

    /**
     * Deduct stock for all items in the order
     * This ensures stock is deducted when order is completed
     */
    private function deductStockForOrder(Order $order): void
    {
        $orderItems = OrderItem::where('order_id', $order->id)->get();
        
        foreach ($orderItems as $item) {
            $product = Product::find($item->product_id);
            if ($product) {
                // Check if stock was already deducted for this item
                // We use a simple approach: deduct stock if product has enough quantity
                if ($product->hasStockFor($item->quantity)) {
                    $product->deductStock($item->quantity);
                    
                    // Log the stock deduction for audit purposes
                    \Log::info('Stock deducted for order completion', [
                        'order_id' => $order->id,
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'quantity_deducted' => $item->quantity,
                        'remaining_stock' => $product->quantity
                    ]);
                } else {
                    // Log warning for insufficient stock
                    \Log::warning('Insufficient stock for order completion', [
                        'order_id' => $order->id,
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'requested_quantity' => $item->quantity,
                        'available_stock' => $product->quantity
                    ]);
                }
            }
        }
    }

    /**
     * Restore stock for all items in the order
     */
    private function restoreStockForOrder(Order $order): void
    {
        $orderItems = OrderItem::where('order_id', $order->id)->get();
        
        foreach ($orderItems as $item) {
            $product = Product::find($item->product_id);
            if ($product) {
                $product->addStock($item->quantity);
                
                // Log the stock restoration for audit purposes
                \Log::info('Stock restored for order cancellation/refund', [
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'quantity_restored' => $item->quantity,
                    'new_stock' => $product->quantity
                ]);
            }
        }
    }
}