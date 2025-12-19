<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesReturnItem extends Model
{
    protected $fillable = [
        'sales_return_id',
        'order_item_id',
        'quantity',
        'refund_amount',
        'reason',
        'restocked',
    ];

    protected $casts = [
        'refund_amount' => 'decimal:2',
        'restocked' => 'boolean',
    ];

    /**
     * Get the sales return that owns the item.
     */
    public function salesReturn(): BelongsTo
    {
        return $this->belongsTo(SalesReturn::class);
    }

    /**
     * Get the order item that owns the sales return item.
     */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    /**
     * Get the product through the order item.
     */
    public function product()
    {
        return $this->hasOneThrough(Product::class, OrderItem::class, 'id', 'id', 'order_item_id', 'product_id');
    }

    /**
     * Get the order through the sales return.
     */
    public function order()
    {
        return $this->hasOneThrough(Order::class, SalesReturn::class, 'id', 'id', 'sales_return_id', 'order_id');
    }

    /**
     * Check if the item is restocked.
     */
    public function isRestocked(): bool
    {
        return $this->restocked;
    }

    /**
     * Mark the item as restocked.
     */
    public function markAsRestocked(): bool
    {
        $this->restocked = true;
        return $this->save();
    }

    /**
     * Mark the item as not restocked.
     */
    public function markAsNotRestocked(): bool
    {
        $this->restocked = false;
        return $this->save();
    }

    /**
     * Get the unit refund amount.
     */
    public function getUnitRefundAmountAttribute(): float
    {
        return $this->quantity > 0 ? $this->refund_amount / $this->quantity : 0;
    }

    /**
     * Get the formatted refund amount.
     */
    public function getFormattedRefundAmountAttribute(): string
    {
        return number_format($this->refund_amount, 2);
    }

    /**
     * Get the formatted unit refund amount.
     */
    public function getFormattedUnitRefundAmountAttribute(): string
    {
        return number_format($this->unit_refund_amount, 2);
    }

    /**
     * Get the original order item price.
     */
    public function getOriginalPriceAttribute(): float
    {
        return $this->orderItem?->price ?? 0;
    }

    /**
     * Get the formatted original price.
     */
    public function getFormattedOriginalPriceAttribute(): string
    {
        return number_format($this->original_price, 2);
    }

    /**
     * Get the original order item quantity.
     */
    public function getOriginalQuantityAttribute(): int
    {
        return $this->orderItem?->quantity ?? 0;
    }

    /**
     * Get the product name.
     */
    public function getProductNameAttribute(): string
    {
        return $this->orderItem?->name ?? $this->product?->name ?? 'Unknown Product';
    }

    /**
     * Get the product SKU.
     */
    public function getProductSkuAttribute(): string
    {
        return $this->product?->sku ?? '';
    }

    /**
     * Get the product barcode.
     */
    public function getProductBarcodeAttribute(): string
    {
        return $this->product?->barcode ?? '';
    }

    /**
     * Check if the return quantity is valid.
     */
    public function isQuantityValid(): bool
    {
        $originalQuantity = $this->original_quantity;
        $alreadyReturned = SalesReturnItem::where('order_item_id', $this->order_item_id)
            ->where('id', '!=', $this->id)
            ->sum('quantity');
        
        return ($this->quantity + $alreadyReturned) <= $originalQuantity;
    }

    /**
     * Get the remaining quantity that can be returned.
     */
    public function getRemainingReturnableQuantityAttribute(): int
    {
        $originalQuantity = $this->original_quantity;
        $alreadyReturned = SalesReturnItem::where('order_item_id', $this->order_item_id)
            ->where('id', '!=', $this->id)
            ->sum('quantity');
        
        return max(0, $originalQuantity - $alreadyReturned);
    }

    /**
     * Calculate the refund amount based on quantity and original price.
     */
    public function calculateRefundAmount(float $restockingFeePercentage = 0): float
    {
        $originalPrice = $this->original_price;
        $refundAmount = $originalPrice * $this->quantity;
        
        if ($restockingFeePercentage > 0) {
            $refundAmount = $refundAmount * (1 - ($restockingFeePercentage / 100));
        }
        
        return round($refundAmount, 2);
    }

    /**
     * Update the refund amount.
     */
    public function updateRefundAmount(float $restockingFeePercentage = 0): bool
    {
        $this->refund_amount = $this->calculateRefundAmount($restockingFeePercentage);
        return $this->save();
    }

    /**
     * Scope to get only restocked items.
     */
    public function scopeRestocked($query)
    {
        return $query->where('restocked', true);
    }

    /**
     * Scope to get only not restocked items.
     */
    public function scopeNotRestocked($query)
    {
        return $query->where('restocked', false);
    }

    /**
     * Scope to get items by product.
     */
    public function scopeByProduct($query, $productId)
    {
        return $query->whereHas('product', function ($q) use ($productId) {
            $q->where('id', $productId);
        });
    }

    /**
     * Scope to get items by order.
     */
    public function scopeByOrder($query, $orderId)
    {
        return $query->whereHas('salesReturn', function ($q) use ($orderId) {
            $q->where('order_id', $orderId);
        });
    }
}