<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StoreCashierShift extends Model
{
    protected $fillable = [
        'store_id',
        'user_id',
        'start_time',
        'end_time',
        'opening_cash',
        'closing_cash',
        'total_sales',
        'cash_collected',
        'card_sales',
        'upi_sales',
        'status',
        'notes',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'opening_cash' => 'decimal:2',
        'closing_cash' => 'decimal:2',
        'total_sales' => 'decimal:2',
        'cash_collected' => 'decimal:2',
        'card_sales' => 'decimal:2',
        'upi_sales' => 'decimal:2',
    ];

    /**
     * Get the store that owns the shift.
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Get the user (cashier) that owns the shift.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the orders for the shift.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(StoreOrder::class);
    }

    /**
     * Get the payments for the shift.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(StorePayment::class);
    }

    /**
     * Get the duration of the shift in hours.
     */
    public function getDurationAttribute(): float
    {
        if ($this->end_time) {
            return $this->start_time->diffInHours($this->end_time);
        }
        return $this->start_time->diffInHours(now());
    }

    /**
     * Get the expected cash amount (opening_cash + cash_sales).
     */
    public function getExpectedCashAttribute(): float
    {
        return $this->opening_cash + $this->cash_sales;
    }

    /**
     * Get the cash difference (closing_cash - expected_cash).
     */
    public function getCashDifferenceAttribute(): float
    {
        if ($this->closing_cash) {
            return $this->closing_cash - $this->expected_cash;
        }
        return 0;
    }

    /**
     * Get the total payment methods sales.
     */
    public function getTotalPaymentMethodsSalesAttribute(): float
    {
        return $this->cash_sales + $this->card_sales + $this->upi_sales;
    }

    /**
     * Get the number of orders in the shift.
     */
    public function getOrderCountAttribute(): int
    {
        return $this->orders()->count();
    }

    /**
     * Check if the shift is active.
     */
    public function getIsActiveAttribute(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if the shift is closed.
     */
    public function getIsClosedAttribute(): bool
    {
        return $this->status === 'closed';
    }

    /**
     * Get formatted opening cash.
     */
    public function getFormattedOpeningCashAttribute(): string
    {
        return '₹' . number_format($this->opening_cash, 2);
    }

    /**
     * Get formatted closing cash.
     */
    public function getFormattedClosingCashAttribute(): string
    {
        return '₹' . number_format($this->closing_cash, 2);
    }

    /**
     * Get formatted total sales.
     */
    public function getFormattedTotalSalesAttribute(): string
    {
        return '₹' . number_format($this->total_sales, 2);
    }

    /**
     * Get formatted cash collected.
     */
    public function getFormattedCashCollectedAttribute(): string
    {
        return '₹' . number_format($this->cash_collected, 2);
    }

    /**
     * Get formatted card sales.
     */
    public function getFormattedCardSalesAttribute(): string
    {
        return '₹' . number_format($this->card_sales, 2);
    }

    /**
     * Get formatted UPI sales.
     */
    public function getFormattedUpiSalesAttribute(): string
    {
        return '₹' . number_format($this->upi_sales, 2);
    }

    /**
     * Get formatted expected cash.
     */
    public function getFormattedExpectedCashAttribute(): string
    {
        return '₹' . number_format($this->expected_cash, 2);
    }

    /**
     * Get formatted cash difference.
     */
    public function getFormattedCashDifferenceAttribute(): string
    {
        $diff = $this->cash_difference;
        $prefix = $diff >= 0 ? '+' : '';
        return $prefix . '₹' . number_format($diff, 2);
    }

    /**
     * Get the status badge color.
     */
    public function getStatusBadgeColorAttribute(): string
    {
        return match ($this->status) {
            'active' => 'success',
            'closed' => 'info',
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
            'active' => 'Active',
            'closed' => 'Closed',
            'cancelled' => 'Cancelled',
            default => ucfirst($this->status),
        };
    }

    /**
     * Scope to get only active shifts.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to get only closed shifts.
     */
    public function scopeClosed($query)
    {
        return $query->where('status', 'closed');
    }

    /**
     * Scope to get shifts for a specific store.
     */
    public function scopeForStore($query, $storeId)
    {
        return $query->where('store_id', $storeId);
    }

    /**
     * Scope to get shifts for a specific user.
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to get shifts for a specific date range.
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Scope to get today's shifts.
     */
    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    /**
     * Scope to get this month's shifts.
     */
    public function scopeThisMonth($query)
    {
        return $query->whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year);
    }

    /**
     * Close the shift.
     */
    public function close(float $closingCash, string $notes = null): void
    {
        $this->update([
            'end_time' => now(),
            'closing_cash' => $closingCash,
            'status' => 'closed',
            'notes' => $notes,
        ]);
    }

    /**
     * Cancel the shift.
     */
    public function cancel(string $notes = null): void
    {
        $this->update([
            'end_time' => now(),
            'status' => 'cancelled',
            'notes' => $notes,
        ]);
    }
}