<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Cart as CartModel;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use Livewire\Attributes\On; 

class Cart extends Component
{
    public $cartItems = [];

    public $discountPercentage = 0;

    public $discountAmount = 0;

    private $currency_symbol;

    public function mount()
    {
        $this->cartItems = CartModel::with('product')
                            ->where('user_id', auth()->user()->id)
                            ->orderBy('id', 'DESC')
                            ->get();    

        $this->currency_symbol = config('settings.currency_symbol');
    }

    
    public function render()
    {
        return view('livewire.cart', ['cartItems' => $this->cartItems, 'currency_symbol' => $this->currency_symbol]);
    }


    #[On('cartUpdated')] 
    public function updateCart()
    {
        $this->cartItems = CartModel::with('product')
                            ->where('user_id', auth()->user()->id)
                            ->orderBy('id', 'DESC')
                            ->get();

        $this->currency_symbol = config('settings.currency_symbol');

    }



    #[On('cartUpdatedFromItem')] 
    public function cartUpdatedFromItem()
    {
        $this->cartItems = CartModel::with('product')
                            ->where('user_id', auth()->user()->id)
                            ->orderBy('id', 'DESC')
                            ->get();

        $this->currency_symbol = config('settings.currency_symbol');

    }


    public function checkout(){
        
        $total_price = 0;
        $customerId =  session('customer_id');

        if( empty($customerId) ){
            return $this->dispatch('error', error: 'Please select customer!');
        }

        $items = $this->cartItems;

        if( ! is_countable( $items ) || count( $items ) < 1){
            return;
        }

        // Calculate discount based on user permissions
        $discountAmount = $this->calculateDiscountAmount($total_price);

        $order = Order::create([
            'customer_id' => $customerId,
            'total_price' => $total_price,
            'discount_amount' => $discountAmount,
            'status' => 'pending'
        ]);

        

        foreach ($items as $item) {
            $product = Product::find( $item->product_id );

            $order->items()->create([
                'name' => $item->name,
                'price' => $item->price,
                'tax' => $item->tax,
                'quantity' => $item->quantity,
                'discount_percentage' => $this->discountPercentage,
                'product_id' => $item->product_id,
            ]);
            $total_price += $item->quantity * $item->price;
            $product->quantity = $product->quantity - $item->quantity;
            $product->save();
        }
        
        $order->total_price = $total_price;
        $order->save();

        $this->cartItems = CartModel::where('user_id', auth()->user()->id)
                            ->delete();  

        $this->dispatch('checkout-completed');

        redirect( url('/admin/orders/'. $order->id .'/edit') );

    }

    public function canApplyDiscount(): bool
    {
        $user = auth()->user();
        
        if ($user->hasPermissionTo('discount.full')) {
            return true;
        }
        
        if ($user->hasPermissionTo('discount.limited')) {
            return $this->discountPercentage <= 10; // Limited to 10% discount
        }
        
        return false; // No discount access
    }

    public function getMaxDiscountPercentage(): int
    {
        $user = auth()->user();
        
        if ($user->hasPermissionTo('discount.full')) {
            return 50; // Max 50% for full access
        }
        
        if ($user->hasPermissionTo('discount.limited')) {
            return 10; // Max 10% for limited access
        }
        
        return 0; // No discount allowed
    }

    public function calculateDiscountAmount($totalPrice): float
    {
        if (!$this->canApplyDiscount()) {
            return 0;
        }
        
        $maxDiscount = $this->getMaxDiscountPercentage();
        $actualDiscount = min($this->discountPercentage, $maxDiscount);
        
        return ($totalPrice * $actualDiscount) / 100;
    }

    public function updatedDiscountPercentage()
    {
        if ($this->discountPercentage < 0) {
            $this->discountPercentage = 0;
        }
        
        $maxDiscount = $this->getMaxDiscountPercentage();
        if ($this->discountPercentage > $maxDiscount) {
            $this->discountPercentage = $maxDiscount;
        }
    }

}
