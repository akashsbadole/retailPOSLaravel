<?php

namespace App\Livewire\Order;

use Livewire\Component;
use App\Models\Cart as CartModel;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Setting;
use Livewire\Attributes\On;

class Cart extends Component
{
    public $cartItems = [];

    public $billDiscount = 0;

    public $paymentMethod = 'cash';

    private $currency_symbol;

    public $orderId;

    public function mount($orderId)
    {
        $this->orderId = $orderId;

        $this->cartItems = OrderItem::where('order_id', $orderId)
                            ->orderBy('id', 'DESC')
                            ->get();
        
        $order = Order::find($orderId);
        $this->billDiscount = $order->discount_amount ?? 0;
        $this->currency_symbol = config('settings.currency_symbol');
        
        // Set default payment method to first enabled method
        if (!config('settings.payment_cash_enabled', '1') && $this->paymentMethod === 'cash') {
            $this->paymentMethod = config('settings.payment_card_enabled', '1') ? 'card' : 'upi';
        } elseif (!config('settings.payment_card_enabled', '1') && $this->paymentMethod === 'card') {
            $this->paymentMethod = config('settings.payment_cash_enabled', '1') ? 'cash' : 'upi';
        } elseif (!config('settings.payment_upi_enabled', '1') && $this->paymentMethod === 'upi') {
            $this->paymentMethod = config('settings.payment_cash_enabled', '1') ? 'cash' : 'card';
        }
    }

    
    public function render()
    {
        $this->currency_symbol = config('settings.currency_symbol');
        return view('livewire.order.cart', ['cartItems' => $this->cartItems, 'currency_symbol' => $this->currency_symbol]);
    }


    #[On('cartUpdated')]
    public function updateCart()
    {
        $this->cartItems = OrderItem::where('order_id', $this->orderId)
                                        ->orderBy('id', 'DESC')
                                        ->get();

        $order = Order::find($this->orderId);
        
        $total_price = 0;
        $total_tax = 0;
        $taxEnabled = config('settings.tax_enabled', '1') === '1';
        
        foreach($this->cartItems as $item){
            $itemTotal = $item->quantity * $item->price;
            $discountAmount = ($itemTotal * $item->discount_percentage) / 100;
            $itemTotalAfterDiscount = $itemTotal - $discountAmount;
            
            // Calculate tax if enabled
            if ($taxEnabled) {
                $taxRate = $item->tax ?? config('settings.default_tax_rate', 0);
                $taxAmount = ($itemTotalAfterDiscount * $taxRate) / 100;
                $total_tax += $taxAmount;
            }
            
            $total_price += $itemTotalAfterDiscount;
        }
        
        $order->total_price = $total_price;
        $order->discount_amount = $this->billDiscount;
        $order->save();

    }


    #[On('cartUpdatedFromItem')]
    public function cartUpdatedFromItem()
    {
        $this->cartItems = OrderItem::where('order_id', $this->orderId)
                                        ->orderBy('id', 'DESC')
                                        ->get();

        $order = Order::find($this->orderId);

        $total_price = 0;
        $total_tax = 0;
        $taxEnabled = config('settings.tax_enabled', '1') === '1';
        
        foreach($this->cartItems as $item){
            $itemTotal = $item->quantity * $item->price;
            $discountAmount = ($itemTotal * $item->discount_percentage) / 100;
            $itemTotalAfterDiscount = $itemTotal - $discountAmount;
            
            // Calculate tax if enabled
            if ($taxEnabled) {
                $taxRate = $item->tax ?? config('settings.default_tax_rate', 0);
                $taxAmount = ($itemTotalAfterDiscount * $taxRate) / 100;
                $total_tax += $taxAmount;
            }
            
            $total_price += $itemTotalAfterDiscount;
        }
        
        $order->total_price = $total_price;
        $order->discount_amount = $this->billDiscount;
        $order->save();
                                
    }

    public function updatedBillDiscount()
    {
        // Validate bill discount
        if ($this->billDiscount < 0) {
            $this->billDiscount = 0;
            $this->dispatch('showNotification', 'Discount cannot be negative!', 'error');
        }

        // Update order with bill discount
        $order = Order::find($this->orderId);
        $order->discount_amount = $this->billDiscount;
        
        // Calculate total price with item discounts
        $total_price = 0;
        $total_tax = 0;
        $taxEnabled = config('settings.tax_enabled', '1') === '1';
        
        foreach($this->cartItems as $item){
            $itemTotal = $item->quantity * $item->price;
            $discountAmount = ($itemTotal * $item->discount_percentage) / 100;
            $itemTotalAfterDiscount = $itemTotal - $discountAmount;
            
            // Calculate tax if enabled
            if ($taxEnabled) {
                $taxRate = $item->tax ?? config('settings.default_tax_rate', 0);
                $taxAmount = ($itemTotalAfterDiscount * $taxRate) / 100;
                $total_tax += $taxAmount;
            }
            
            $total_price += $itemTotalAfterDiscount;
        }
        
        // Ensure final price doesn't go below zero
        $finalTotal = $total_price - $this->billDiscount;
        if ($finalTotal < 0) {
            $this->billDiscount = $total_price;
            $order->discount_amount = $this->billDiscount;
            $this->dispatch('showNotification', 'Discount cannot exceed total amount!', 'error');
        }
        
        $order->total_price = $total_price;
        $order->save();
    }


    public function checkout(){
        // Validate payment method is enabled
        if ($this->paymentMethod === 'cash' && !config('settings.payment_cash_enabled', '1')) {
            $this->dispatch('showNotification', 'Cash payments are disabled!', 'error');
            return;
        }
        if ($this->paymentMethod === 'card' && !config('settings.payment_card_enabled', '1')) {
            $this->dispatch('showNotification', 'Card payments are disabled!', 'error');
            return;
        }
        if ($this->paymentMethod === 'upi' && !config('settings.payment_upi_enabled', '1')) {
            $this->dispatch('showNotification', 'UPI payments are disabled!', 'error');
            return;
        }
        
        // Calculate final total
        $total_price = 0;
        $total_tax = 0;
        $taxEnabled = config('settings.tax_enabled', '1') === '1';
        
        foreach($this->cartItems as $item){
            $itemTotal = $item->quantity * $item->price;
            $discountAmount = ($itemTotal * $item->discount_percentage) / 100;
            $itemTotalAfterDiscount = $itemTotal - $discountAmount;
            
            // Calculate tax if enabled
            if ($taxEnabled) {
                $taxRate = $item->tax ?? config('settings.default_tax_rate', 0);
                $taxAmount = ($itemTotalAfterDiscount * $taxRate) / 100;
                $total_tax += $taxAmount;
            }
            
            $total_price += $itemTotalAfterDiscount;
        }
        
        $final_total = $total_price + $total_tax - $this->billDiscount;
        
        if($final_total <= 0) {
            $this->dispatch('showNotification', 'Order total must be greater than zero!', 'error');
            return;
        }
        
        // Create payment record
        Payment::create([
            'order_id' => $this->orderId,
            'user_id' => auth()->user()->id,
            'amount' => $final_total,
            'payment_method' => $this->paymentMethod,
        ]);
        
        // Update order status to completed
        $order = Order::find($this->orderId);
        $order->status = 'completed';
        $order->save();
        
        $this->dispatch('showNotification', 'Order completed successfully!', 'success');
        return $this->redirect( url('admin/orders') );
    }

}
