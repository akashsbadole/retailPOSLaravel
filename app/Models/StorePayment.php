<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StorePayment extends Model
{
    protected $fillable = [
        'store_order_id',
        'user_id',
        'store_id',
        'amount',
        'payment_method',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    /**
     * Get the store order that owns the payment.
     */
    public function storeOrder(): BelongsTo
    {
        return $this->belongsTo(StoreOrder::class);
    }

    /**
     * Get the user that owns the payment.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the store that owns the payment.
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Get formatted amount.
     */
    public function getFormattedAmountAttribute(): string
    {
        return '₹' . number_format($this->amount, 2);
    }

    /**
     * Get the payment method badge color.
     */
    public function getPaymentMethodBadgeColorAttribute(): string
    {
        return match ($this->payment_method) {
            'cash' => 'success',
            'card' => 'info',
            'upi' => 'warning',
            'bank_transfer' => 'primary',
            'cheque' => 'secondary',
            default => 'gray',
        };
    }

    /**
     * Get the payment method text.
     */
    public function getPaymentMethodTextAttribute(): string
    {
        return match ($this->payment_method) {
            'cash' => 'Cash',
            'card' => 'Card',
            'upi' => 'UPI',
            'bank_transfer' => 'Bank Transfer',
            'cheque' => 'Cheque',
            default => ucfirst($this->payment_method),
        };
    }

    /**
     * Scope to get payments for a specific store order.
     */
    public function scopeForStoreOrder($query, $storeOrderId)
    {
        return $query->where('store_order_id', $storeOrderId);
    }

    /**
     * Scope to get payments for a specific store.
     */
    public function scopeForStore($query, $storeId)
    {
        return $query->where('store_id', $storeId);
    }

    /**
     * Scope to get payments by a specific user.
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to get payments by payment method.
     */
    public function scopeByPaymentMethod($query, $paymentMethod)
    {
        return $query->where('payment_method', $paymentMethod);
    }

    /**
     * Scope to get cash payments.
     */
    public function scopeCash($query)
    {
        return $query->where('payment_method', 'cash');
    }

    /**
     * Scope to get card payments.
     */
    public function scopeCard($query)
    {
        return $query->where('payment_method', 'card');
    }

    /**
     * Scope to get UPI payments.
     */
    public function scopeUpi($query)
    {
        return $query->where('payment_method', 'upi');
    }

    /**
     * Scope to get payments for a specific date range.
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Scope to get today's payments.
     */
    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    /**
     * Scope to get this month's payments.
     */
    public function scopeThisMonth($query)
    {
        return $query->whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year);
    }
}