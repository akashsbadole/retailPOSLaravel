<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreOrderItem extends Model
{
    protected $fillable = [
        'store_order_id',
        'product_id',
        'name',
        'price',
        'quantity',
        'tax',
        'discount_percentage',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'tax' => 'decimal:2',
        'discount_percentage' => 'decimal:2',
    ];

    /**
     * Get the store order that owns the item.
     */
    public function storeOrder(): BelongsTo
    {
        return $this->belongsTo(StoreOrder::class);
    }

    /**
     * Get the product that owns the item.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the subtotal for the item (price * quantity).
     */
    public function getSubtotalAttribute(): float
    {
        return $this->price * $this->quantity;
    }

    /**
     * Get the discount amount for the item.
     */
    public function getDiscountAmountAttribute(): float
    {
        return $this->subtotal * ($this->discount_percentage / 100);
    }

    /**
     * Get the total for the item (subtotal - discount + tax).
     */
    public function getTotalAttribute(): float
    {
        return $this->subtotal - $this->discount_amount + $this->tax;
    }

    /**
     * Get the profit amount for the item.
     */
    public function getProfitAttribute(): float
    {
        if ($this->product) {
            $costPrice = $this->product->cost_price;
            return ($this->price - $costPrice) * $this->quantity - $this->discount_amount;
        }
        return 0;
    }

    /**
     * Get the profit percentage for the item.
     */
    public function getProfitPercentageAttribute(): float
    {
        if ($this->product && $this->product->cost_price > 0) {
            $costPrice = $this->product->cost_price;
            return (($this->price - $costPrice) / $costPrice) * 100;
        }
        return 0;
    }

    /**
     * Get formatted subtotal.
     */
    public function getFormattedSubtotalAttribute(): string
    {
        return '₹' . number_format($this->subtotal, 2);
    }

    /**
     * Get formatted discount amount.
     */
    public function getFormattedDiscountAmountAttribute(): string
    {
        return '₹' . number_format($this->discount_amount, 2);
    }

    /**
     * Get formatted total.
     */
    public function getFormattedTotalAttribute(): string
    {
        return '₹' . number_format($this->total, 2);
    }

    /**
     * Get formatted profit.
     */
    public function getFormattedProfitAttribute(): string
    {
        return '₹' . number_format($this->profit, 2);
    }

    /**
     * Get formatted profit percentage.
     */
    public function getFormattedProfitPercentageAttribute(): string
    {
        return number_format($this->profit_percentage, 2) . '%';
    }

    /**
     * Get formatted price.
     */
    public function getFormattedPriceAttribute(): string
    {
        return '₹' . number_format($this->price, 2);
    }

    /**
     * Get formatted tax.
     */
    public function getFormattedTaxAttribute(): string
    {
        return '₹' . number_format($this->tax, 2);
    }

    /**
     * Get formatted discount percentage.
     */
    public function getFormattedDiscountPercentageAttribute(): string
    {
        return number_format($this->discount_percentage, 2) . '%';
    }

    /**
     * Scope to get items for a specific store order.
     */
    public function scopeForStoreOrder($query, $storeOrderId)
    {
        return $query->where('store_order_id', $storeOrderId);
    }

    /**
     * Scope to get items for a specific product.
     */
    public function scopeForProduct($query, $productId)
    {
        return $query->where('product_id', $productId);
    }

    /**
     * Scope to get items with discount.
     */
    public function scopeWithDiscount($query)
    {
        return $query->where('discount_percentage', '>', 0);
    }

    /**
     * Scope to get items without discount.
     */
    public function scopeWithoutDiscount($query)
    {
        return $query->where('discount_percentage', '=', 0);
    }
}