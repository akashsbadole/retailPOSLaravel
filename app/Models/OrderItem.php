<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $fillable =[
        'name',
        'price',
        'tax',
        'quantity',
        'product_id',
        'order_id',
        'discount_percentage'
    ];

    protected $casts = [
        'price' => 'decimal:4',
        'tax' => 'decimal:2',
        'discount_percentage' => 'decimal:2'
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function salesReturnItems()
    {
        return $this->hasMany(SalesReturnItem::class);
    }
}
