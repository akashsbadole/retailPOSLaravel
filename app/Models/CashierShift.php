<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class CashierShift extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'start_time',
        'end_time',
        'opening_time',
        'closing_time',
        'opening_cash',
        'closing_cash',
        'expected_cash',
        'cash_discrepancy',
        'total_sales',
        'cash_collected',
        'card_sales',
        'upi_sales',
        'total_orders',
        'status',
        'notes',
        'opened_by',
        'closed_by',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'opening_time' => 'datetime',
        'closing_time' => 'datetime',
        'opening_cash' => 'decimal:2',
        'closing_cash' => 'decimal:2',
        'expected_cash' => 'decimal:2',
        'cash_discrepancy' => 'decimal:2',
        'total_sales' => 'decimal:2',
        'cash_collected' => 'decimal:2',
        'card_sales' => 'decimal:2',
        'upi_sales' => 'decimal:2',
        'total_orders' => 'integer',
    ];

    /**
     * Get the user that owns the shift.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the user who opened the shift.
     */
    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    /**
     * Get the user who closed the shift.
     */
    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /**
     * Get the orders for this shift.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Calculate the duration of the shift in hours and minutes.
     */
    public function getDurationAttribute(): string
    {
        if (!$this->end_time) {
            $duration = $this->start_time->diffForHumans(now(), true);
        } else {
            $duration = $this->start_time->diffForHumans($this->end_time, true);
        }
        return $duration;
    }

    /**
     * Calculate the expected cash based on opening cash and cash sales.
     */
    public function getExpectedCashAttribute(): float
    {
        return $this->opening_cash + $this->cash_collected;
    }

    /**
     * Calculate the cash discrepancy.
     */
    public function getCashDiscrepancyAttribute(): float
    {
        if (!$this->closing_cash) {
            return 0;
        }
        return $this->closing_cash - $this->expected_cash;
    }

    /**
     * Get the total payment methods sales.
     */
    public function getTotalPaymentSalesAttribute(): float
    {
        return $this->cash_collected + $this->card_sales + $this->upi_sales;
    }

    /**
     * Check if shift is currently open.
     */
    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    /**
     * Check if shift is closed.
     */
    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    /**
     * Open a new shift.
     */
    public static function openShift(User $user, float $openingCash = 0): self
    {
        // Check if user already has an open shift
        $existingShift = static::getOpenShiftForUser($user);
        if ($existingShift) {
            return $existingShift;
        }

        return static::create([
            'user_id' => $user->id,
            'opened_by' => $user->id,
            'start_time' => now(),
            'opening_time' => now(),
            'opening_cash' => $openingCash,
            'status' => 'open',
        ]);
    }

    /**
     * Close the current shift.
     */
    public function closeShift(float $closingCash, ?string $notes = null, ?User $closedBy = null): bool
    {
        $expectedCash = $this->opening_cash + $this->cash_collected;
        $discrepancy = $closingCash - $expectedCash;

        $this->update([
            'end_time' => now(),
            'closing_time' => now(),
            'closing_cash' => $closingCash,
            'expected_cash' => $expectedCash,
            'cash_discrepancy' => $discrepancy,
            'status' => 'closed',
            'notes' => $notes,
            'closed_by' => $closedBy?->id ?? auth()->id(),
        ]);

        return true;
    }

    /**
     * Update shift totals when an order is created.
     */
    public function updateTotalsFromOrder(Order $order): void
    {
        if ($this->isClosed()) {
            return;
        }

        $this->increment('total_sales', $order->total_price);
        $this->increment('total_orders', 1);

        // Update payment method totals based on order payments
        foreach ($order->payments as $payment) {
            switch ($payment->payment_method) {
                case 'cash':
                    $this->increment('cash_collected', $payment->amount);
                    break;
                case 'card':
                    $this->increment('card_sales', $payment->amount);
                    break;
                case 'upi':
                    $this->increment('upi_sales', $payment->amount);
                    break;
            }
        }

        $this->save();
    }

    /**
     * Get open shift for a user.
     */
    public static function getOpenShiftForUser(User $user): ?self
    {
        return static::where('user_id', $user->id)
            ->where('status', 'open')
            ->first();
    }

    /**
     * Get current open shift for authenticated user.
     */
    public static function getCurrentShift(): ?self
    {
        $user = auth()->user();
        if (!$user) {
            return null;
        }

        return static::getOpenShiftForUser($user);
    }

    /**
     * Scope to get only open shifts.
     */
    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    /**
     * Scope to get only closed shifts.
     */
    public function scopeClosed($query)
    {
        return $query->where('status', 'closed');
    }

    /**
     * Scope to get shifts for a specific user.
     */
    public function scopeForUser($query, User $user)
    {
        return $query->where('user_id', $user->id);
    }

    /**
     * Scope to get shifts for a date range.
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('start_time', [$startDate, $endDate]);
    }

    /**
     * Scope to get shifts for today.
     */
    public function scopeToday($query)
    {
        return $query->whereDate('start_time', today());
    }

    /**
     * Scope to get shifts for this week.
     */
    public function scopeThisWeek($query)
    {
        return $query->whereBetween('start_time', [now()->startOfWeek(), now()->endOfWeek()]);
    }

    /**
     * Scope to get shifts for this month.
     */
    public function scopeThisMonth($query)
    {
        return $query->whereMonth('start_time', now()->month)
            ->whereYear('start_time', now()->year);
    }
}