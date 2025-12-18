<?php

namespace App\Livewire\Order;

use App\Models\Product;
use App\Models\Cart;
use App\Models\OrderItem;
use Livewire\Component;

class BarcodeScan extends Component
{
    public $query = '';

    public $error = '';

    public $orderId;

    public function mount($orderId)
    {
        $this->orderId = $orderId;
    }

    public function render()
    {
        return view('livewire.order.barcode-scan');
    }


    public function addToCart(){

        if( empty($this->query) ){
            return;
        }
        
        $this->error = '';
        $quantity = 1;
        $product = Product::where('barcode', $this->query)->first();
        
        if( !$product ){
            $this->error = 'The '. $this->query . ' ' . ' - Product which does not exist!';
            $this->query = '';
            return;
        }

        // Check if product is in stock
        if (!$product->isInStock()) {
            $this->error = 'Product is out of stock!';
            $this->query = '';
            return;
        }

        // Use product tax or default tax rate from settings
        $taxRate = $product->tax ?? config('settings.default_tax_rate', 0);
        
        $cartItem  = OrderItem::firstOrCreate(
            [
                'order_id' => $this->orderId, 'product_id' => $product->id
            ],[
                'name' => $product->name,
                'quantity' => 0,
                'price' => $product->price,
                'tax' => $taxRate,
                'discount_percentage' => 0
            ]
        );

        // Check if adding this quantity would exceed available stock
        $totalQuantity = $cartItem->quantity + $quantity;
        if (!$product->hasStockFor($totalQuantity)) {
            $this->error = "Cannot add {$quantity} more items. Only {$product->quantity} available in stock!";
            if( $cartItem->quantity < 1 ){
                $cartItem->delete();
            }
            $this->query = '';
            return;
        }
        
        // Update cart item quantity
        $cartItem->update([
            'quantity' => $totalQuantity
        ]);

        // Deduct stock using the model method
        if (!$product->deductStock($quantity)) {
            $this->error = 'Failed to update stock!';
            $this->query = '';
            return;
        }

        $this->query = '';

        $this->dispatch('cartUpdated');
        $this->dispatch('showNotification',
            "{$product->name} added to cart!",
            'success'
        );
    }

    public function closeErrorModal(){
        $this->error = '';
    }
}
