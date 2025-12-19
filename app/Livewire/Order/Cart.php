<?php

namespace App\Livewire\Order;

use Livewire\Component;
use App\Models\Cart as CartModel;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Setting;
use App\Models\CashierShift;
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
        
        // Get or create current shift
        $currentShift = CashierShift::getCurrentShift();
        if (!$currentShift && config('settings.require_shift_opening', '1')) {
            $this->dispatch('showNotification', 'No active shift found! Please open a shift first.', 'error');
            return;
        }
        
        // If no shift exists and shift opening is not required, create one automatically
        if (!$currentShift && !config('settings.require_shift_opening', '1')) {
            $currentShift = CashierShift::openShift(auth()->user(), 0);
        }
        
        // Create payment record with shift association
        $payment = Payment::create([
            'order_id' => $this->orderId,
            'user_id' => auth()->user()->id,
            'amount' => $final_total,
            'payment_method' => $this->paymentMethod,
            'cashier_shift_id' => $currentShift?->id,
        ]);
        
        // Update order with shift information
        $order = Order::find($this->orderId);
        $order->status = 'completed';
        $order->cashier_shift_id = $currentShift?->id;
        $order->save();
        
        // Update shift totals if shift exists
        if ($currentShift) {
            $currentShift->updateTotalsFromOrder($order);
        }
        
        // Payment reconciliation check
        $this->performPaymentReconciliation($order, $payment, $final_total);
        
        $this->dispatch('showNotification', 'Order completed successfully!', 'success');
        return $this->redirect( url('admin/orders') );
    }
    
    /**
     * Perform payment reconciliation checks
     */
    private function performPaymentReconciliation(Order $order, Payment $payment, float $expectedTotal): void
    {
        // Check if payment amount matches expected total
        if (abs($payment->amount - $expectedTotal) > 0.01) {
            \Log::warning('Payment reconciliation mismatch', [
                'order_id' => $order->id,
                'expected_total' => $expectedTotal,
                'payment_amount' => $payment->amount,
                'difference' => abs($payment->amount - $expectedTotal)
            ]);
        }
        
        // Check if order total matches sum of all payments
        $totalPayments = $order->payments()->sum('amount');
        if (abs($totalPayments - $order->total_price) > 0.01) {
            \Log::warning('Order payment total mismatch', [
                'order_id' => $order->id,
                'order_total' => $order->total_price,
                'total_payments' => $totalPayments,
                'difference' => abs($totalPayments - $order->total_price)
            ]);
        }
        
        // Log successful reconciliation
        \Log::info('Payment reconciliation completed', [
            'order_id' => $order->id,
            'payment_method' => $payment->payment_method,
            'amount' => $payment->amount,
            'shift_id' => $payment->cashier_shift_id
        ]);
    }

}
