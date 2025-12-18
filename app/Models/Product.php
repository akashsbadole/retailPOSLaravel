<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    protected $fillable = [
        'name',
        'description',
        'image',
        'barcode',
        'sku',
        'price',
        'cost_price',
        'tax',
        'quantity',
        'minimum_stock',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
        'price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'tax' => 'decimal:2'
    ];

    public function getImageUrl()
    {
        if ($this->image) {
            /** @phpstan-ignore-next-line */
            return Storage::disk('public_uploads')->url($this->image);
        }
        return asset('img/img-placeholder.jpg');
    }

    /**
     * Check if product is in stock
     */
    public function isInStock(): bool
    {
        return $this->quantity > 0;
    }

    /**
     * Check if product has low stock (at or below minimum stock)
     */
    public function isLowStock(): bool
    {
        return $this->quantity > 0 && $this->quantity <= $this->minimum_stock;
    }

    /**
     * Validate if requested quantity is available
     */
    public function hasStockFor(int $quantity): bool
    {
        return $this->quantity >= $quantity;
    }

    /**
     * Get stock status text
     */
    public function getStockStatusAttribute(): string
    {
        if (!$this->isInStock()) {
            return 'Out of Stock';
        }
        
        if ($this->isLowStock()) {
            return 'Low Stock (' . $this->quantity . ')';
        }
        
        return 'In Stock (' . $this->quantity . ')';
    }

    /**
     * Deduct stock quantity
     */
    public function deductStock(int $quantity): bool
    {
        if (!$this->hasStockFor($quantity)) {
            return false;
        }

        $this->quantity -= $quantity;
        return $this->save();
    }

    /**
     * Add stock quantity
     */
    public function addStock(int $quantity): bool
    {
        $this->quantity += $quantity;
        return $this->save();
    }

    /**
     * Scope to get low stock products
     */
    public function scopeLowStock($query)
    {
        return $query->where('quantity', '>', 0)
                    ->where('quantity', '<=', \DB::raw('minimum_stock'));
    }

    /**
     * Scope to get out of stock products
     */
    public function scopeOutOfStock($query)
    {
        return $query->where('quantity', '<=', 0);
    }

}
