<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class StockAdjustment extends Model
{
    protected $fillable = [
        'product_id',
        'store_id',
        'quantity',
        'adjustment_type',
        'reason',
        'notes',
        'user_id',
        'previous_quantity',
        'new_quantity',
        'approved',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'approved' => 'boolean',
        'approved_at' => 'datetime',
        'quantity' => 'integer',
        'previous_quantity' => 'integer',
        'new_quantity' => 'integer',
    ];

    /**
     * Get the product that owns the stock adjustment.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the store that owns the stock adjustment.
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Get the user who created the stock adjustment.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the user who approved the stock adjustment.
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Scope to get only approved adjustments.
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('approved', true);
    }

    /**
     * Scope to get only pending adjustments.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('approved', false);
    }

    /**
     * Scope to get only increase adjustments.
     */
    public function scopeIncrease(Builder $query): Builder
    {
        return $query->where('adjustment_type', 'increase');
    }

    /**
     * Scope to get only decrease adjustments.
     */
    public function scopeDecrease(Builder $query): Builder
    {
        return $query->where('adjustment_type', 'decrease');
    }

    /**
     * Scope to get adjustments by reason.
     */
    public function scopeByReason(Builder $query, string $reason): Builder
    {
        return $query->where('reason', $reason);
    }

    /**
     * Get the absolute quantity value (always positive).
     */
    public function getAbsoluteQuantityAttribute(): int
    {
        return abs($this->quantity);
    }

    /**
     * Get the adjustment type as a formatted string.
     */
    public function getFormattedAdjustmentTypeAttribute(): string
    {
        return ucfirst($this->adjustment_type);
    }

    /**
     * Get the reason as a formatted string.
     */
    public function getFormattedReasonAttribute(): string
    {
        return match($this->reason) {
            'damage' => 'Damage',
            'theft' => 'Theft',
            'manual_correction' => 'Manual Correction',
            'return' => 'Return',
            'other' => 'Other',
            default => ucfirst($this->reason),
        };
    }

    /**
     * Get the quantity with sign indicator.
     */
    public function getQuantityWithSignAttribute(): string
    {
        $prefix = $this->adjustment_type === 'decrease' ? '-' : '+';
        return $prefix . $this->absolute_quantity;
    }

    /**
     * Check if this is a decrease adjustment.
     */
    public function isDecrease(): bool
    {
        return $this->adjustment_type === 'decrease';
    }

    /**
     * Check if this is an increase adjustment.
     */
    public function isIncrease(): bool
    {
        return $this->adjustment_type === 'increase';
    }

    /**
     * Approve the adjustment.
     */
    public function approve(?User $user = null): bool
    {
        $this->approved = true;
        $this->approved_by = $user?->id ?? auth()->id();
        $this->approved_at = now();
        
        return $this->save();
    }

    /**
     * Create a stock adjustment record.
     */
    public static function createAdjustment(
        Product $product,
        int $quantity,
        string $adjustmentType,
        string $reason,
        ?string $notes = null,
        ?User $user = null
    ): self {
        $previousQuantity = $product->quantity;
        
        // Calculate new quantity based on adjustment type
        $newQuantity = $adjustmentType === 'increase' 
            ? $previousQuantity + $quantity 
            : $previousQuantity - $quantity;

        $adjustment = static::create([
            'product_id' => $product->id,
            'store_id' => request()->get('store_id') ?: auth()->user()->store_id,
            'quantity' => $adjustmentType === 'decrease' ? -$quantity : $quantity,
            'adjustment_type' => $adjustmentType,
            'reason' => $reason,
            'notes' => $notes,
            'user_id' => $user?->id ?? auth()->id() ?? 1, // Default to admin user (ID 1) if no auth
            'previous_quantity' => $previousQuantity,
            'new_quantity' => $newQuantity,
            'approved' => !config('settings.stock_adjustment_require_approval', false),
        ]);

        // Auto-approve if not required
        if ($adjustment->approved) {
            $adjustment->approved_by = $user?->id ?? auth()->id() ?? 1; // Default to admin user (ID 1) if no auth
            $adjustment->approved_at = now();
            $adjustment->save();
            
            // Apply the adjustment to product
            $product->quantity = $newQuantity;
            $product->save();
        }

        return $adjustment;
    }

    /**
     * Apply the adjustment to the product stock.
     */
    public function applyToProduct(): bool
    {
        if (!$this->approved) {
            return false;
        }

        $product = $this->product;
        if (!$product) {
            return false;
        }

        $product->quantity = $this->new_quantity;
        return $product->save();
    }
}