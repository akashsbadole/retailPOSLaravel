<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'amount',
        'order_id',
        'user_id',
        'payment_method',
        'cashier_shift_id',
    ];

    protected $casts = [
        'payment_method' => 'string',
    ];

    /**
     * Get the cashier shift that owns the payment.
     */
    public function cashierShift()
    {
        return $this->belongsTo(CashierShift::class);
    }
}
