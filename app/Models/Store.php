<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Store extends Model
{
    protected $fillable = [
        'name',
        'code',
        'address',
        'phone',
        'email',
        'tax_number',
        'status',
        'settings',
    ];

    protected $casts = [
        'status' => 'boolean',
        'settings' => 'array',
    ];

    /**
     * Get the stocks for the store.
     */
    public function stocks(): HasMany
    {
        return $this->hasMany(StoreStock::class);
    }

    /**
     * Get the orders for the store.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(StoreOrder::class);
    }

    /**
     * Get the payments for the store.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(StorePayment::class);
    }

    /**
     * Get the cashier shifts for the store.
     */
    public function cashierShifts(): HasMany
    {
        return $this->hasMany(StoreCashierShift::class);
    }

    /**
     * Get the users assigned to the store.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get the products available in the store.
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'store_stocks')
            ->withPivot('quantity', 'minimum_stock', 'location')
            ->withTimestamps();
    }

    /**
     * Get the total sales amount for the store.
     */
    public function getTotalSalesAttribute(): float
    {
        return $this->orders()->where('status', 'completed')->sum('total_price');
    }

    /**
     * Get the total number of orders for the store.
     */
    public function getTotalOrdersAttribute(): int
    {
        return $this->orders()->count();
    }

    /**
     * Get the total number of products in stock for the store.
     */
    public function getTotalProductsAttribute(): int
    {
        return $this->stocks()->where('quantity', '>', 0)->count();
    }

    /**
     * Get the total stock value for the store.
     */
    public function getTotalStockValueAttribute(): float
    {
        return $this->stocks()->with('product')->get()->sum(function ($stock) {
            return $stock->quantity * $stock->product->cost_price;
        });
    }

    /**
     * Get the low stock items for the store.
     */
    public function getLowStockItemsAttribute()
    {
        return $this->stocks()->with('product')
            ->whereColumn('quantity', '<=', 'minimum_stock')
            ->where('quantity', '>', 0)
            ->get();
    }

    /**
     * Get the out of stock items for the store.
     */
    public function getOutOfStockItemsAttribute()
    {
        return $this->stocks()->with('product')
            ->where('quantity', '=', 0)
            ->get();
    }

    /**
     * Scope to get only active stores.
     */
    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    /**
     * Scope to get only inactive stores.
     */
    public function scopeInactive($query)
    {
        return $query->where('status', false);
    }

    /**
     * Get formatted total sales amount.
     */
    public function getFormattedTotalSalesAttribute(): string
    {
        return '₹' . number_format($this->total_sales, 2);
    }

    /**
     * Get formatted total stock value.
     */
    public function getFormattedTotalStockValueAttribute(): string
    {
        return '₹' . number_format($this->total_stock_value, 2);
    }

    /**
     * Get the status badge color.
     */
    public function getStatusBadgeColorAttribute(): string
    {
        return $this->status ? 'success' : 'danger';
    }

    /**
     * Get the status text.
     */
    public function getStatusTextAttribute(): string
    {
        return $this->status ? 'Active' : 'Inactive';
    }

    /**
     * Get today's sales for the store.
     */
    public function getTodaySalesAttribute(): float
    {
        return $this->orders()
            ->whereDate('created_at', today())
            ->where('status', 'completed')
            ->sum('total_price');
    }

    /**
     * Get formatted today's sales amount.
     */
    public function getFormattedTodaySalesAttribute(): string
    {
        return '₹' . number_format($this->today_sales, 2);
    }

    /**
     * Get this month's sales for the store.
     */
    public function getThisMonthSalesAttribute(): float
    {
        return $this->orders()
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->where('status', 'completed')
            ->sum('total_price');
    }

    /**
     * Get formatted this month's sales amount.
     */
    public function getFormattedThisMonthSalesAttribute(): string
    {
        return '₹' . number_format($this->this_month_sales, 2);
    }
}