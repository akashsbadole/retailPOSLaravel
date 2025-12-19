<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreStock extends Model
{
    protected $fillable = [
        'store_id',
        'product_id',
        'quantity',
        'minimum_stock',
        'location',
    ];

    /**
     * Get the store that owns the stock.
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Get the product that owns the stock.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the stock value.
     */
    public function getStockValueAttribute(): float
    {
        return $this->quantity * $this->product->cost_price;
    }

    /**
     * Get formatted stock value.
     */
    public function getFormattedStockValueAttribute(): string
    {
        return '₹' . number_format($this->stock_value, 2);
    }

    /**
     * Check if stock is low.
     */
    public function getIsLowStockAttribute(): bool
    {
        return $this->quantity <= $this->minimum_stock && $this->quantity > 0;
    }

    /**
     * Check if stock is out of stock.
     */
    public function getIsOutOfStockAttribute(): bool
    {
        return $this->quantity == 0;
    }

    /**
     * Get the stock status badge color.
     */
    public function getStockStatusBadgeColorAttribute(): string
    {
        if ($this->is_out_of_stock) {
            return 'danger';
        } elseif ($this->is_low_stock) {
            return 'warning';
        } else {
            return 'success';
        }
    }

    /**
     * Get the stock status text.
     */
    public function getStockStatusTextAttribute(): string
    {
        if ($this->is_out_of_stock) {
            return 'Out of Stock';
        } elseif ($this->is_low_stock) {
            return 'Low Stock';
        } else {
            return 'In Stock';
        }
    }

    /**
     * Scope to get only low stock items.
     */
    public function scopeLowStock($query)
    {
        return $query->whereColumn('quantity', '<=', 'minimum_stock')
                    ->where('quantity', '>', 0);
    }

    /**
     * Scope to get only out of stock items.
     */
    public function scopeOutOfStock($query)
    {
        return $query->where('quantity', '=', 0);
    }

    /**
     * Scope to get only in stock items.
     */
    public function scopeInStock($query)
    {
        return $query->whereColumn('quantity', '>', 'minimum_stock');
    }

    /**
     * Increase stock quantity.
     */
    public function increaseStock(int $quantity): void
    {
        $this->increment('quantity', $quantity);
    }

    /**
     * Decrease stock quantity.
     */
    public function decreaseStock(int $quantity): void
    {
        $this->decrement('quantity', $quantity);
    }

    /**
     * Transfer stock to another store.
     */
    public function transferTo(Store $destinationStore, int $quantity): StoreStock
    {
        // Decrease from current store
        $this->decreaseStock($quantity);

        // Find or create stock in destination store
        $destinationStock = StoreStock::firstOrCreate(
            [
                'store_id' => $destinationStore->id,
                'product_id' => $this->product_id,
            ],
            [
                'quantity' => 0,
                'minimum_stock' => $this->minimum_stock,
                'location' => $this->location,
            ]
        );

        // Increase in destination store
        $destinationStock->increaseStock($quantity);

        return $destinationStock;
    }
}