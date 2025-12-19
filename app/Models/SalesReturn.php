<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class SalesReturn extends Model
{
    protected $fillable = [
        'order_id',
        'user_id',
        'return_date',
        'reason',
        'notes',
        'status',
        'total_refund_amount',
        'refund_method',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'return_date' => 'date',
        'total_refund_amount' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    /**
     * Get the order that owns the sales return.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the user who created the sales return.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the user who approved the sales return.
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the items for the sales return.
     */
    public function items(): HasMany
    {
        return $this->hasMany(SalesReturnItem::class);
    }

    /**
     * Scope to get only pending returns.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope to get only approved returns.
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope to get only rejected returns.
     */
    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('status', 'rejected');
    }

    /**
     * Scope to get only processed returns.
     */
    public function scopeProcessed(Builder $query): Builder
    {
        return $query->where('status', 'processed');
    }

    /**
     * Scope to get only completed returns.
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope to get returns by date range.
     */
    public function scopeDateRange(Builder $query, string $startDate, string $endDate): Builder
    {
        return $query->whereDate('return_date', '>=', $startDate)
                    ->whereDate('return_date', '<=', $endDate);
    }

    /**
     * Get the reason as a formatted string.
     */
    public function getFormattedReasonAttribute(): string
    {
        return match($this->reason) {
            'damaged' => 'Damaged',
            'defective' => 'Defective',
            'wrong_item' => 'Wrong Item',
            'not_needed' => 'Not Needed',
            'customer_dissatisfaction' => 'Customer Dissatisfaction',
            'expired' => 'Expired',
            'other' => 'Other',
            default => ucfirst($this->reason),
        };
    }

    /**
     * Get the status as a formatted string.
     */
    public function getFormattedStatusAttribute(): string
    {
        return match($this->status) {
            'pending' => 'Pending',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'processed' => 'Processed',
            'completed' => 'Completed',
            default => ucfirst($this->status),
        };
    }

    /**
     * Get the refund method as a formatted string.
     */
    public function getFormattedRefundMethodAttribute(): string
    {
        return match($this->refund_method) {
            'cash' => 'Cash',
            'card' => 'Card',
            'bank_transfer' => 'Bank Transfer',
            'store_credit' => 'Store Credit',
            'original_payment' => 'Original Payment Method',
            default => ucfirst($this->refund_method),
        };
    }

    /**
     * Check if the return is pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if the return is approved.
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * Check if the return is rejected.
     */
    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    /**
     * Check if the return is processed.
     */
    public function isProcessed(): bool
    {
        return $this->status === 'processed';
    }

    /**
     * Check if the return is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Approve the return.
     */
    public function approve(?User $user = null): bool
    {
        $this->status = 'approved';
        $this->approved_by = $user?->id ?? auth()->id();
        $this->approved_at = now();
        
        return $this->save();
    }

    /**
     * Reject the return.
     */
    public function reject(?User $user = null): bool
    {
        $this->status = 'rejected';
        $this->approved_by = $user?->id ?? auth()->id();
        $this->approved_at = now();
        
        return $this->save();
    }

    /**
     * Process the return.
     */
    public function process(): bool
    {
        if (!$this->isApproved()) {
            return false;
        }

        $this->status = 'processed';
        return $this->save();
    }

    /**
     * Complete the return.
     */
    public function complete(): bool
    {
        if (!$this->isProcessed()) {
            return false;
        }

        $this->status = 'completed';
        return $this->save();
    }

    /**
     * Calculate the total refund amount from items.
     */
    public function calculateTotalRefundAmount(): float
    {
        return $this->items->sum(function ($item) {
            return $item->refund_amount;
        });
    }

    /**
     * Update the total refund amount.
     */
    public function updateTotalRefundAmount(): bool
    {
        $this->total_refund_amount = $this->calculateTotalRefundAmount();
        return $this->save();
    }

    /**
     * Check if the return can be processed.
     */
    public function canBeProcessed(): bool
    {
        return $this->isApproved() && !$this->isProcessed() && !$this->isCompleted();
    }

    /**
     * Check if the return can be approved.
     */
    public function canBeApproved(): bool
    {
        return $this->isPending() && !$this->isRejected();
    }

    /**
     * Check if the return can be rejected.
     */
    public function canBeRejected(): bool
    {
        return $this->isPending() && !$this->isApproved();
    }

    /**
     * Get the total quantity of items returned.
     */
    public function getTotalQuantityAttribute(): int
    {
        return $this->items->sum('quantity');
    }

    /**
     * Get the total number of items returned.
     */
    public function getTotalItemsAttribute(): int
    {
        return $this->items->count();
    }

    /**
     * Check if all items are restocked.
     */
    public function areAllItemsRestocked(): bool
    {
        return $this->items->every('restocked');
    }

    /**
     * Get the number of items that are not restocked.
     */
    public function getUnrestockedItemsCountAttribute(): int
    {
        return $this->items->where('restocked', false)->count();
    }
}