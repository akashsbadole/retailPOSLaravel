<?php

namespace App\Livewire\Order;

use App\Models\Product;
use Livewire\Component;
use Livewire\Attributes\On; 

class CartItem extends Component
{
    public $cartItem;

    public $currency_symbol;

    public $quantity;

    public $discountPercentage;

    public $orderId;


    public function mount($cartItem, $orderId)
    {
        $this->orderId = $orderId;
        $this->cartItem = $cartItem;
        $this->quantity = $cartItem->quantity;
        $this->discountPercentage = $cartItem->discount_percentage ?? 0;
    }

    #[On('cartUpdated')]
    public function cartUpdated(){
        $this->quantity = $this->cartItem->quantity;
        $this->discountPercentage = $this->cartItem->discount_percentage ?? 0;
    }


    public function removeFromCart()
    {
        $product = Product::find( $this->cartItem->product_id );
        
        if ($product) {
            // Restore stock when item is removed from cart
            $product->addStock($this->quantity);
            
            $this->dispatch('showNotification',
                "{$product->name} removed from cart and stock restored!",
                'info'
            );
        }
        
        $this->quantity = 0;
        $this->cartItem->delete();
        $this->dispatch('cartUpdatedFromItem');
    }


    public function updatedQuantity(){
        if ($this->quantity > 0) {
            $product = Product::find( $this->cartItem->product_id );
            
            if (!$product) {
                $this->quantity = $this->cartItem->quantity;
                $this->dispatch('cartUpdatedFromItem');
                return;
            }

            // Calculate the difference in quantity
            $quantityDifference = $this->quantity - $this->cartItem->quantity;
            
            // Check if new quantity is available in stock
            if ($quantityDifference > 0) {
                // User wants to increase quantity
                $availableStock = $product->quantity;
                if ($quantityDifference > $availableStock) {
                    $this->quantity = $this->cartItem->quantity + $availableStock;
                    $this->dispatch('showNotification',
                        "Only {$availableStock} additional items available in stock!",
                        'error'
                    );
                    $this->dispatch('cartUpdatedFromItem');
                    return;
                }
            }

            // Update cart item quantity
            $this->cartItem->quantity = $this->quantity;
            $this->cartItem->save();

            // Update stock based on quantity difference
            if ($quantityDifference > 0) {
                // Deduct additional stock
                $product->deductStock($quantityDifference);
            } else {
                // Restore stock (quantity difference is negative)
                $product->addStock(abs($quantityDifference));
            }
        }
        if ( is_numeric($this->quantity) && $this->quantity <= 0){
            $this->quantity = 1;
        }
        $this->dispatch('cartUpdatedFromItem');
    }

    public function updatedDiscountPercentage(){
        // Validate discount percentage
        if ($this->discountPercentage < 0) {
            $this->discountPercentage = 0;
            $this->dispatch('showNotification', 'Discount cannot be negative!', 'error');
        } elseif ($this->discountPercentage > 100) {
            $this->discountPercentage = 100;
            $this->dispatch('showNotification', 'Discount cannot exceed 100%!', 'error');
        }

        // Update cart item discount
        $this->cartItem->discount_percentage = $this->discountPercentage;
        $this->cartItem->save();
        
        $this->dispatch('cartUpdatedFromItem');
    }

    public function render()
    {      
        return view('livewire.order.cart-item');
    }
}
