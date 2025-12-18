<?php

namespace App\Livewire\Order;

use Livewire\Component;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Cart;
use App\Models\OrderItem;
use Livewire\Attributes\On; 

class ProductSearch extends Component
{
    public $query = '';

    public $orderId;
    public $products;

    public function mount($orderId)
    {
        $this->orderId = $orderId;
        $this->products = Product::where('name', 'like', '%' . $this->query . '%')
                                ->orWhere('sku', 'like', '%' . $this->query . '%')
                                ->orWhere('barcode', 'like', '%' . $this->query . '%')
                                ->limit(10)->get();

    }

    public function render()
    { 
        $currency_symbol = Setting::select('value')->where('key', 'currency_symbol')->first();
        $currency_symbol = $currency_symbol ? $currency_symbol->value : '';
        return view('livewire.order.product-search', compact('currency_symbol'));
    }

    #[On('checkout-completed')]
    public function checkoutCompleted(){
        $this->query = '';
    }

    #[On('cartUpdated')]
    public function updateCart()
    { 
    }

    public function updated(){
        $this->products = Product::where('name', 'like', '%' . $this->query . '%')
                                ->orWhere('sku', 'like', '%' . $this->query . '%')
                                ->orWhere('barcode', 'like', '%' . $this->query . '%')
                                ->limit(10)->get();
    }

    public function addToCart( $product_id, $quantity = 1 ){

        $product = Product::find( $product_id );
        
        // Check if product exists
        if (!$product) {
            $this->dispatch('showNotification', 'Product not found!', 'error');
            return;
        }

        // Check if product is in stock
        if (!$product->isInStock()) {
            $this->dispatch('showNotification', 'Product is out of stock!', 'error');
            return;
        }

        // Check if requested quantity is available
        if (!$product->hasStockFor($quantity)) {
            $this->dispatch('showNotification',
                "Only {$product->quantity} items available in stock!",
                'error'
            );
            return;
        }

        // Show warning for low stock products
        if ($product->isLowStock()) {
            $this->dispatch('showNotification',
                "Warning: {$product->name} is running low on stock ({$product->quantity} remaining)!",
                'warning'
            );
        }

        // Use product tax or default tax rate from settings
        $taxRate = $product->tax ?? config('settings.default_tax_rate', 0);
        
        $cartItem = OrderItem::firstOrCreate(
            [
                'order_id' => $this->orderId, 'product_id' => $product_id
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
            $this->dispatch('showNotification',
                "Cannot add {$quantity} more items. Only {$product->quantity} available in stock!",
                'error'
            );
            return;
        }
        
        // Update cart item quantity
        $cartItem->update([
            'quantity' => $totalQuantity
        ]);

        // Deduct stock using the model method
        if (!$product->deductStock($quantity)) {
            $this->dispatch('showNotification', 'Failed to update stock!', 'error');
            return;
        }

        $this->dispatch('cartUpdated');
        $this->dispatch('showNotification',
            "{$product->name} added to cart!",
            'success'
        );
    }

}
