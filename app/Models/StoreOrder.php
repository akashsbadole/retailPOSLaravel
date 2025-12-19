<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StoreOrder extends Model
{
    protected $fillable = [
        'store_id',
        'customer_id',
        'cashier_shift_id',
        'total_price',
        'discount_amount',
        'status',
        'notes',
    ];

    protected $casts = [
        'total_price' => 'decimal:2',
        'discount_amount' => 'decimal:2',
    ];

    /**
     * Get the store that owns the order.
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Get the customer that owns the order.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the cashier shift that owns the order.
     */
    public function cashierShift(): BelongsTo
    {
        return $this->belongsTo(StoreCashierShift::class);
    }

    /**
     * Get the items for the order.
     */
    public function items(): HasMany
    {
        return $this->hasMany(StoreOrderItem::class);
    }

    /**
     * Get the payments for the order.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(StorePayment::class);
    }

    /**
     * Get the total amount paid for the order.
     */
    public function getTotalPaidAttribute(): float
    {
        return $this->payments()->sum('amount');
    }

    /**
     * Get the remaining amount to be paid for the order.
     */
    public function getRemainingAmountAttribute(): float
    {
        return $this->total_price - $this->total_paid;
    }

    /**
     * Get the total tax amount for the order.
     */
    public function getTotalTaxAttribute(): float
    {
        return $this->items()->sum('tax');
    }

    /**
     * Get the total discount amount for the order.
     */
    public function getTotalDiscountAttribute(): float
    {
        $itemDiscounts = $this->items()->sum(function ($item) {
            return ($item->price * $item->quantity) * ($item->discount_percentage / 100);
        });
        
        return $itemDiscounts + $this->discount_amount;
    }

    /**
     * Get the subtotal for the order (total before tax and discount).
     */
    public function getSubtotalAttribute(): float
    {
        return $this->items()->sum(function ($item) {
            return $item->price * $item->quantity;
        });
    }

    /**
     * Check if the order is fully paid.
     */
    public function getIsFullyPaidAttribute(): bool
    {
        return $this->total_paid >= $this->total_price;
    }

    /**
     * Check if the order is pending.
     */
    public function getIsPendingAttribute(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if the order is completed.
     */
    public function getIsCompletedAttribute(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if the order is cancelled.
     */
    public function getIsCancelledAttribute(): bool
    {
        return $this->status === 'cancelled';
    }

    /**
     * Get formatted total price.
     */
    public function getFormattedTotalPriceAttribute(): string
    {
        return '₹' . number_format($this->total_price, 2);
    }

    /**
     * Get formatted total paid.
     */
    public function getFormattedTotalPaidAttribute(): string
    {
        return '₹' . number_format($this->total_paid, 2);
    }

    /**
     * Get formatted remaining amount.
     */
    public function getFormattedRemainingAmountAttribute(): string
    {
        return '₹' . number_format($this->remaining_amount, 2);
    }

    /**
     * Get formatted total tax.
     */
    public function getFormattedTotalTaxAttribute(): string
    {
        return '₹' . number_format($this->total_tax, 2);
    }

    /**
     * Get formatted total discount.
     */
    public function getFormattedTotalDiscountAttribute(): string
    {
        return '₹' . number_format($this->total_discount, 2);
    }

    /**
     * Get formatted subtotal.
     */
    public function getFormattedSubtotalAttribute(): string
    {
        return '₹' . number_format($this->subtotal, 2);
    }

    /**
     * Get the status badge color.
     */
    public function getStatusBadgeColorAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'warning',
            'completed' => 'success',
            'cancelled' => 'danger',
            default => 'secondary',
        };
    }

    /**
     * Get the status text.
     */
    public function getStatusTextAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'Pending',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            default => ucfirst($this->status),
        };
    }

    /**
     * Scope to get only pending orders.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope to get only completed orders.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope to get only cancelled orders.
     */
    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    /**
     * Scope to get orders for a specific store.
     */
    public function scopeForStore($query, $storeId)
    {
        return $query->where('store_id', $storeId);
    }

    /**
     * Scope to get orders for a specific date range.
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Complete the order.
     */
    public function complete(): void
    {
        $this->update(['status' => 'completed']);
    }

    /**
     * Cancel the order.
     */
    public function cancel(): void
    {
        $this->update(['status' => 'cancelled']);
    }
}