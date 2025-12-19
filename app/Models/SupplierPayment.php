<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierPayment extends Model
{
    protected $fillable = [
        'supplier_id',
        'purchase_id',
        'amount',
        'payment_date',
        'payment_method',
        'reference_number',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'date',
    ];

    /**
     * Get the supplier for the payment.
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Get the purchase for the payment.
     */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    /**
     * Get formatted amount.
     */
    public function getFormattedAmountAttribute(): string
    {
        return '₹' . number_format($this->amount, 2);
    }

    /**
     * Get payment method label.
     */
    public function getPaymentMethodLabelAttribute(): string
    {
        return match($this->payment_method) {
            'cash' => 'Cash',
            'bank_transfer' => 'Bank Transfer',
            'cheque' => 'Cheque',
            'credit_card' => 'Credit Card',
            'other' => 'Other',
            default => ucfirst($this->payment_method),
        };
    }

    /**
     * Get payment method icon.
     */
    public function getPaymentMethodIconAttribute(): string
    {
        return match($this->payment_method) {
            'cash' => 'heroicon-o-banknotes',
            'bank_transfer' => 'heroicon-o-credit-card',
            'cheque' => 'heroicon-o-document-text',
            'credit_card' => 'heroicon-o-credit-card',
            'other' => 'heroicon-o-question-mark-circle',
            default => 'heroicon-o-question-mark-circle',
        };
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::created(function ($payment) {
            // Update purchase payment status when payment is created
            if ($payment->purchase) {
                $payment->purchase->updatePaymentStatus();
            }
        });

        static::deleted(function ($payment) {
            // Update purchase payment status when payment is deleted
            if ($payment->purchase) {
                $payment->purchase->updatePaymentStatus();
            }
        });
    }
}