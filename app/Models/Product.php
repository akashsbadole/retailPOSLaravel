<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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

    /**
     * Boot the model
     */
    protected static function booted()
    {
        static::creating(function ($product) {
            // Auto-generate barcode if enabled and barcode is empty
            if (config('settings.auto_generate_barcodes', true) && empty($product->barcode)) {
                $barcodeService = app(\App\Services\BarcodeService::class);
                $prefix = config('settings.barcode_prefix', 'PROD');
                $product->barcode = $barcodeService->generateUniqueBarcode($prefix);
            }
        });

        static::updating(function ($product) {
            // Validate barcode uniqueness if enabled
            if (config('settings.require_unique_barcodes', true) && $product->isDirty('barcode')) {
                $existingProduct = static::where('barcode', $product->barcode)
                    ->where('id', '!=', $product->id)
                    ->first();
                
                if ($existingProduct) {
                    throw new \Exception("Barcode '{$product->barcode}' already exists for another product.");
                }
            }
        });
    }

    /**
     * Get barcode URL for display
     */
    public function getBarcodeUrlAttribute()
    {
        if (!$this->barcode) {
            return null;
        }

        $barcodeService = app(\App\Services\BarcodeService::class);
        return 'data:image/png;base64,' . base64_encode($barcodeService->generatePng($this->barcode));
    }

    /**
     * Get barcode HTML for display
     */
    public function getBarcodeHtmlAttribute()
    {
        if (!$this->barcode) {
            return null;
        }

        $barcodeService = app(\App\Services\BarcodeService::class);
        return $barcodeService->generateHtml($this->barcode);
    }

    /**
     * Check if product has a valid barcode
     */
    public function hasValidBarcode(): bool
    {
        if (empty($this->barcode)) {
            return false;
        }

        $barcodeService = app(\App\Services\BarcodeService::class);
        return $barcodeService->validateBarcode($this->barcode);
    }

    /**
     * Scope to find products by barcode or SKU
     */
    public function scopeByBarcodeOrSku($query, $barcode)
    {
        return $query->where(function ($q) use ($barcode) {
            $q->where('barcode', $barcode)
              ->orWhere('sku', $barcode);
        });
    }

    /**
     * Scope to get products without barcodes
     */
    public function scopeWithoutBarcode($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('barcode')
              ->orWhere('barcode', '');
        });
    }

    /**
     * Scope to get products with barcodes
     */
    public function scopeWithBarcode($query)
    {
        return $query->whereNotNull('barcode')
                    ->where('barcode', '!=', '');
    }

    /**
     * Generate barcode for this product
     */
    public function generateBarcode(string $prefix = null): string
    {
        $barcodeService = app(\App\Services\BarcodeService::class);
        $prefix = $prefix ?? config('settings.barcode_prefix', 'PROD');
        
        $barcode = $barcodeService->generateUniqueBarcode($prefix);
        $this->update(['barcode' => $barcode]);
        
        return $barcode;
    }

    /**
     * Regenerate barcode for this product
     */
    public function regenerateBarcode(string $prefix = null): string
    {
        return $this->generateBarcode($prefix);
    }

    /**
     * Get barcode label data for printing
     */
    public function getBarcodeLabelData(): array
    {
        $barcodeService = app(\App\Services\BarcodeService::class);
        
        return [
            'product' => $this,
            'barcode_html' => $barcodeService->generateHtml($this->barcode),
            'product_name' => $this->name,
            'price' => number_format($this->price, 2),
            'barcode' => $this->barcode,
            'sku' => $this->sku,
        ];
    }

    /**
     * Get the stores that have this product in stock.
     */
    public function stores(): BelongsToMany
    {
        return $this->belongsToMany(Store::class, 'store_stocks')
            ->withPivot('quantity', 'minimum_stock', 'location')
            ->withTimestamps();
    }

    /**
     * Get the store stocks for this product.
     */
    public function storeStocks()
    {
        return $this->hasMany(StoreStock::class);
    }

    /**
     * Get stock quantity for a specific store.
     */
    public function getStockForStore(int $storeId): int
    {
        $storeStock = $this->storeStocks()->where('store_id', $storeId)->first();
        return $storeStock ? $storeStock->quantity : 0;
    }

    /**
     * Check if product is in stock for a specific store.
     */
    public function isInStockForStore(int $storeId): bool
    {
        return $this->getStockForStore($storeId) > 0;
    }

    /**
     * Check if product has low stock for a specific store.
     */
    public function isLowStockForStore(int $storeId): bool
    {
        $storeStock = $this->storeStocks()->where('store_id', $storeId)->first();
        if (!$storeStock) {
            return true; // No stock means it's "low"
        }
        
        return $storeStock->quantity > 0 && $storeStock->quantity <= $storeStock->minimum_stock;
    }

    /**
     * Get total stock across all stores.
     */
    public function getTotalStockAttribute(): int
    {
        return $this->storeStocks()->sum('quantity');
    }

    /**
     * Get total stock value across all stores.
     */
    public function getTotalStockValueAttribute(): float
    {
        return $this->storeStocks()->get()->sum(function ($storeStock) {
            return $storeStock->quantity * $this->cost_price;
        });
    }
}
