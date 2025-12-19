<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    protected $fillable = [
        'name',
        'contact_person',
        'email',
        'phone',
        'address',
        'gst_number',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    /**
     * Get the purchases for the supplier.
     */
    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    /**
     * Get the payments for the supplier.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class);
    }

    /**
     * Get the total amount due to supplier.
     */
    public function getTotalDueAttribute(): float
    {
        $totalPurchases = $this->purchases()->where('status', 'received')->sum('final_amount');
        $totalPayments = $this->payments()->sum('amount');
        
        return $totalPurchases - $totalPayments;
    }

    /**
     * Get the total amount paid to supplier.
     */
    public function getTotalPaidAttribute(): float
    {
        return $this->payments()->sum('amount');
    }

    /**
     * Get the total purchase amount from supplier.
     */
    public function getTotalPurchasesAttribute(): float
    {
        return $this->purchases()->where('status', 'received')->sum('final_amount');
    }

    /**
     * Scope to get only active suppliers.
     */
    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    /**
     * Scope to get only inactive suppliers.
     */
    public function scopeInactive($query)
    {
        return $query->where('status', false);
    }

    /**
     * Get formatted total due amount.
     */
    public function getFormattedTotalDueAttribute(): string
    {
        return '₹' . number_format($this->total_due, 2);
    }

    /**
     * Get formatted total paid amount.
     */
    public function getFormattedTotalPaidAttribute(): string
    {
        return '₹' . number_format($this->total_paid, 2);
    }

    /**
     * Get formatted total purchases amount.
     */
    public function getFormattedTotalPurchasesAttribute(): string
    {
        return '₹' . number_format($this->total_purchases, 2);
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
}