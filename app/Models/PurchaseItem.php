<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseItem extends Model
{
    protected $fillable = [
        'purchase_id',
        'product_id',
        'quantity',
        'unit_price',
        'total_price',
        'discount_percentage',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'discount_percentage' => 'decimal:2',
    ];

    /**
     * Get the purchase for the purchase item.
     */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    /**
     * Get the product for the purchase item.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get formatted unit price.
     */
    public function getFormattedUnitPriceAttribute(): string
    {
        return '₹' . number_format($this->unit_price, 2);
    }

    /**
     * Get formatted total price.
     */
    public function getFormattedTotalPriceAttribute(): string
    {
        return '₹' . number_format($this->total_price, 2);
    }

    /**
     * Get formatted discount percentage.
     */
    public function getFormattedDiscountPercentageAttribute(): string
    {
        return number_format($this->discount_percentage, 2) . '%';
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($purchaseItem) {
            // Calculate total price if not set
            if (!$purchaseItem->total_price) {
                $discountMultiplier = (100 - $purchaseItem->discount_percentage) / 100;
                $purchaseItem->total_price = $purchaseItem->quantity * $purchaseItem->unit_price * $discountMultiplier;
            }
        });
    }
}