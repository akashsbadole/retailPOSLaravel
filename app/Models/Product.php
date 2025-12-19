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

    /**
     * Get the stock adjustments for the product.
     */
    public function stockAdjustments()
    {
        return $this->hasMany(StockAdjustment::class);
    }

    /**
     * Get approved stock adjustments for the product.
     */
    public function approvedStockAdjustments()
    {
        return $this->stockAdjustments()->approved();
    }

    /**
     * Get pending stock adjustments for the product.
     */
    public function pendingStockAdjustments()
    {
        return $this->stockAdjustments()->pending();
    }

    /**
     * Get stock adjustment history with optional filtering
     */
    public function getStockAdjustmentHistory(array $filters = [])
    {
        $query = $this->stockAdjustments()->with(['user', 'approvedBy']);

        // Apply filters
        if (isset($filters['adjustment_type'])) {
            $query->where('adjustment_type', $filters['adjustment_type']);
        }

        if (isset($filters['reason'])) {
            $query->where('reason', $filters['reason']);
        }

        if (isset($filters['approved'])) {
            $query->where('approved', $filters['approved']);
        }

        if (isset($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        return $query->latest()->get();
    }

    /**
     * Apply a stock adjustment to this product
     */
    public function applyStockAdjustment(int $quantity, string $adjustmentType, string $reason, ?string $notes = null, ?User $user = null): StockAdjustment
    {
        return StockAdjustment::createAdjustment(
            $this,
            $quantity,
            $adjustmentType,
            $reason,
            $notes,
            $user
        );
    }

    /**
     * Get total stock increased from adjustments
     */
    public function getTotalStockIncreaseAttribute(): int
    {
        return $this->approvedStockAdjustments()->increase()->sum('quantity');
    }

    /**
     * Get total stock decreased from adjustments
     */
    public function getTotalStockDecreaseAttribute(): int
    {
        return abs($this->approvedStockAdjustments()->decrease()->sum('quantity'));
    }

    /**
     * Get net stock change from adjustments
     */
    public function getNetStockChangeAttribute(): int
    {
        return $this->total_stock_increase - $this->total_stock_decrease;
    }

    /**
     * Check if product has any pending adjustments
     */
    public function hasPendingAdjustments(): bool
    {
        return $this->pendingStockAdjustments()->exists();
    }

    /**
     * Get the most recent stock adjustment
     */
    public function getLatestStockAdjustmentAttribute()
    {
        return $this->stockAdjustments()->latest()->first();
    }

    /**
     * Get the sales return items for the product.
     */
    public function salesReturnItems()
    {
        return $this->hasManyThrough(
            SalesReturnItem::class,
            OrderItem::class,
            'product_id',
            'order_item_id',
            'id',
            'id'
        );
    }

    /**
     * Get the total quantity returned for this product.
     */
    public function getTotalReturnedQuantityAttribute(): int
    {
        return $this->salesReturnItems()->sum('quantity');
    }

    /**
     * Get the total refund amount for this product.
     */
    public function getTotalRefundAmountAttribute(): float
    {
        return $this->salesReturnItems()->sum('refund_amount');
    }

    /**
     * Get the most recent sales return item for this product.
     */
    public function getLatestSalesReturnItemAttribute()
    {
        return $this->salesReturnItems()->latest()->first();
    }

}
