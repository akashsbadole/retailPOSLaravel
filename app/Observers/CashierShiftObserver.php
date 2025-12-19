<?php

namespace App\Observers;

use App\Models\Order;
use App\Models\CashierShift;
use App\Models\Payment;
use Illuminate\Support\Facades\Auth;

class CashierShiftObserver
{
    /**
     * Handle the Order "creating" event.
     */
    public function creating($event): void
    {
        // Only handle Order events, not Payment events
        if (!$event instanceof Order) {
            return;
        }
        
        // Automatically link order to current user's open shift
        if (Auth::check() && !$event->cashier_shift_id) {
            $currentShift = CashierShift::getCurrentShift();
            if ($currentShift) {
                $event->cashier_shift_id = $currentShift->id;
            }
        }
    }

    /**
     * Handle the Order "created" event.
     */
    public function created($event): void
    {
        // Only handle Order events, not Payment events
        if (!$event instanceof Order) {
            return;
        }
        
        // Update shift totals when order is created
        if ($event->cashier_shift_id) {
            $shift = $event->cashierShift;
            if ($shift && $shift->isOpen()) {
                $shift->updateTotalsFromOrder($event);
            }
        }
    }

    /**
     * Handle the Payment "created" event.
     */
    public function paymentCreated(Payment $payment): void
    {
        // Update shift totals when payment is created
        if ($payment->order && $payment->order->cashier_shift_id) {
            $shift = $payment->order->cashierShift;
            if ($shift && $shift->isOpen()) {
                // Recalculate shift totals from all payments
                $this->recalculateShiftTotals($shift);
            }
        }
    }

    /**
     * Handle the Payment "updated" event.
     */
    public function paymentUpdated(Payment $payment): void
    {
        // Update shift totals when payment is updated
        if ($payment->order && $payment->order->cashier_shift_id) {
            $shift = $payment->order->cashierShift;
            if ($shift && $shift->isOpen()) {
                // Recalculate shift totals from all payments
                $this->recalculateShiftTotals($shift);
            }
        }
    }

    /**
     * Handle the Payment "deleted" event.
     */
    public function paymentDeleted(Payment $payment): void
    {
        // Update shift totals when payment is deleted
        if ($payment->order && $payment->order->cashier_shift_id) {
            $shift = $payment->order->cashierShift;
            if ($shift && $shift->isOpen()) {
                // Recalculate shift totals from all payments
                $this->recalculateShiftTotals($shift);
            }
        }
    }

    /**
     * Recalculate shift totals from all orders and payments
     */
    private function recalculateShiftTotals(CashierShift $shift): void
    {
        if ($shift->isClosed()) {
            return;
        }

        // Get all orders for this shift
        $orders = $shift->orders()->with('payments')->get();

        // Reset totals
        $totalSales = 0;
        $cashCollected = 0;
        $cardSales = 0;
        $upiSales = 0;

        foreach ($orders as $order) {
            $totalSales += $order->total_price;

            foreach ($order->payments as $payment) {
                switch ($payment->payment_method) {
                    case 'cash':
                        $cashCollected += $payment->amount;
                        break;
                    case 'card':
                        $cardSales += $payment->amount;
                        break;
                    case 'upi':
                        $upiSales += $payment->amount;
                        break;
                }
            }
        }

        // Update shift totals
        $shift->update([
            'total_sales' => $totalSales,
            'cash_collected' => $cashCollected,
            'card_sales' => $cardSales,
            'upi_sales' => $upiSales,
        ]);
    }

    /**
     * Register the observers for the models.
     */
    public static function register(): void
    {
        Order::observe(static::class);
        Payment::observe(static::class);
    }
}