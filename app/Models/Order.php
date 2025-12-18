<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'customer_id',
        'total_price',
        'discount_amount',
        'status',
        'discount_price'
    ];

    // Add a boot method to ensure required fields are always set
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($order) {
            // Ensure default values for required fields
            if (!$order->discount_amount) {
                $order->discount_amount = 0.00;
            }
            if (!$order->status) {
                $order->status = 'pending';
            }
            if (!$order->total_price) {
                $order->total_price = 0.00;
            }
        });
    }

    protected $casts = [
        'total_price' => 'decimal:2',
        'discount_amount' => 'decimal:2'
    ];

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function getCustomerName()
    {
        if($this->customer) {
            return $this->customer->first_name . ' ' . $this->customer->last_name;
        }
        return __('customer.working');
    }

    public function total()
    {
        return $this->items->map(function ($i){
            return $i->price;
        })->sum();
    }

    public function formattedTotal()
    {
        return number_format($this->total(), 2);
    }

    public function receivedAmount()
    {
        return $this->payments->map(function ($i){
            return $i->amount;
        })->sum();
    }

    public function formattedReceivedAmount()
    {
        return number_format($this->receivedAmount(), 2);
    }
}
