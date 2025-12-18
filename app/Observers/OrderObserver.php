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
     * This is a safety measure - stock should already be deducted when items were added to cart
     */
    private function deductStockForOrder(Order $order): void
    {
        $orderItems = OrderItem::where('order_id', $order->id)->get();
        
        foreach ($orderItems as $item) {
            $product = Product::find($item->product_id);
            if ($product) {
                // Only deduct if stock hasn't been deducted yet
                // This prevents double deduction
                $currentCartStock = OrderItem::where('product_id', $item->product_id)
                    ->whereHas('order', function($query) {
                        $query->whereNotIn('status', ['completed', 'cancelled', 'refunded']);
                    })
                    ->sum('quantity');
                
                $productStockInOrders = OrderItem::where('product_id', $item->product_id)
                    ->whereHas('order', function($query) {
                        $query->whereIn('status', ['completed']);
                    })
                    ->sum('quantity');
                
                $expectedStock = $product->quantity + $currentCartStock + $productStockInOrders;
                
                if ($expectedStock > 0) {
                    $product->deductStock($item->quantity);
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
            }
        }
    }
}