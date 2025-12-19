<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Purchase extends Model
{
    protected $fillable = [
        'supplier_id',
        'purchase_date',
        'total_amount',
        'discount_amount',
        'tax_amount',
        'final_amount',
        'status',
        'payment_status',
        'notes',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'total_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'final_amount' => 'decimal:2',
    ];

    /**
     * Get the supplier for the purchase.
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Get the items for the purchase.
     */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    /**
     * Get the payments for the purchase.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class);
    }

    /**
     * Get the total amount paid for this purchase.
     */
    public function getTotalPaidAttribute(): float
    {
        return $this->payments()->sum('amount');
    }

    /**
     * Get the remaining amount to be paid for this purchase.
     */
    public function getRemainingAmountAttribute(): float
    {
        return $this->final_amount - $this->total_paid;
    }

    /**
     * Get formatted total amount.
     */
    public function getFormattedTotalAmountAttribute(): string
    {
        return '₹' . number_format($this->total_amount, 2);
    }

    /**
     * Get formatted discount amount.
     */
    public function getFormattedDiscountAmountAttribute(): string
    {
        return '₹' . number_format($this->discount_amount, 2);
    }

    /**
     * Get formatted tax amount.
     */
    public function getFormattedTaxAmountAttribute(): string
    {
        return '₹' . number_format($this->tax_amount, 2);
    }

    /**
     * Get formatted final amount.
     */
    public function getFormattedFinalAmountAttribute(): string
    {
        return '₹' . number_format($this->final_amount, 2);
    }

    /**
     * Get formatted total paid amount.
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
     * Get status badge color.
     */
    public function getStatusBadgeColorAttribute(): string
    {
        return match($this->status) {
            'pending' => 'warning',
            'received' => 'success',
            'cancelled' => 'danger',
            default => 'secondary',
        };
    }

    /**
     * Get payment status badge color.
     */
    public function getPaymentStatusBadgeColorAttribute(): string
    {
        return match($this->payment_status) {
            'unpaid' => 'danger',
            'partial' => 'warning',
            'paid' => 'success',
            default => 'secondary',
        };
    }

    /**
     * Scope to get pending purchases.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope to get received purchases.
     */
    public function scopeReceived($query)
    {
        return $query->where('status', 'received');
    }

    /**
     * Scope to get cancelled purchases.
     */
    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    /**
     * Scope to get unpaid purchases.
     */
    public function scopeUnpaid($query)
    {
        return $query->where('payment_status', 'unpaid');
    }

    /**
     * Scope to get partially paid purchases.
     */
    public function scopePartiallyPaid($query)
    {
        return $query->where('payment_status', 'partial');
    }

    /**
     * Scope to get fully paid purchases.
     */
    public function scopePaid($query)
    {
        return $query->where('payment_status', 'paid');
    }

    /**
     * Check if purchase is fully paid.
     */
    public function isFullyPaid(): bool
    {
        return $this->payment_status === 'paid' || $this->remaining_amount <= 0;
    }

    /**
     * Check if purchase is partially paid.
     */
    public function isPartiallyPaid(): bool
    {
        return $this->payment_status === 'partial' || ($this->total_paid > 0 && $this->remaining_amount > 0);
    }

    /**
     * Check if purchase is unpaid.
     */
    public function isUnpaid(): bool
    {
        return $this->payment_status === 'unpaid' || $this->total_paid <= 0;
    }

    /**
     * Update payment status based on payments.
     */
    public function updatePaymentStatus(): void
    {
        $totalPaid = $this->payments()->sum('amount');
        
        if ($totalPaid <= 0) {
            $this->payment_status = 'unpaid';
        } elseif ($totalPaid >= $this->final_amount) {
            $this->payment_status = 'paid';
        } else {
            $this->payment_status = 'partial';
        }
        
        $this->save();
    }
}