<?php

namespace App\Services;

use App\Models\Order;
use App\Models\StoreOrder;
use App\Models\Product;
use App\Models\StoreStock;
use App\Models\Customer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Event;

class RealtimeSyncService
{
    /**
     * Broadcast order created event
     *
     * @param mixed $order
     * @return void
     */
    public function broadcastOrderCreated($order)
    {
        try {
            $channel = $order instanceof StoreOrder ? 
                "store.{$order->store_id}.orders" : 
                'orders';
                
            $event = 'order.created';
            
            $data = [
                'event' => $event,
                'data' => [
                    'order_id' => $order->id,
                    'customer_id' => $order->customer_id,
                    'total_price' => $order->total_price,
                    'status' => $order->status,
                    'created_at' => $order->created_at->toISOString(),
                    'items_count' => $order->items->count(),
                    'store_id' => $order->store_id ?? null,
                ],
                'timestamp' => now()->toISOString(),
            ];

            $this->broadcastToChannel($channel, $event, $data);
            
        } catch (\Exception $e) {
            Log::error('Failed to broadcast order created event', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Broadcast order updated event
     *
     * @param mixed $order
     * @return void
     */
    public function broadcastOrderUpdated($order)
    {
        try {
            $channel = $order instanceof StoreOrder ? 
                "store.{$order->store_id}.orders" : 
                'orders';
                
            $event = 'order.updated';
            
            $data = [
                'event' => $event,
                'data' => [
                    'order_id' => $order->id,
                    'status' => $order->status,
                    'updated_at' => $order->updated_at->toISOString(),
                    'store_id' => $order->store_id ?? null,
                ],
                'timestamp' => now()->toISOString(),
            ];

            $this->broadcastToChannel($channel, $event, $data);
            
        } catch (\Exception $e) {
            Log::error('Failed to broadcast order updated event', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Broadcast payment processed event
     *
     * @param mixed $payment
     * @param mixed $order
     * @return void
     */
    public function broadcastPaymentProcessed($payment, $order)
    {
        try {
            $channel = $order instanceof StoreOrder ? 
                "store.{$order->store_id}.payments" : 
                'payments';
                
            $event = 'payment.processed';
            
            $data = [
                'event' => $event,
                'data' => [
                    'payment_id' => $payment->id,
                    'order_id' => $order->id,
                    'amount' => $payment->amount,
                    'payment_method' => $payment->payment_method,
                    'created_at' => $payment->created_at->toISOString(),
                    'store_id' => $order->store_id ?? null,
                ],
                'timestamp' => now()->toISOString(),
            ];

            $this->broadcastToChannel($channel, $event, $data);
            
        } catch (\Exception $e) {
            Log::error('Failed to broadcast payment processed event', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Broadcast stock updated event
     *
     * @param mixed $stock
     * @return void
     */
    public function broadcastStockUpdated($stock)
    {
        try {
            $channel = "store.{$stock->store_id}.stock";
            $event = 'stock.updated';
            
            $data = [
                'event' => $event,
                'data' => [
                    'stock_id' => $stock->id,
                    'product_id' => $stock->product_id,
                    'store_id' => $stock->store_id,
                    'quantity' => $stock->quantity,
                    'minimum_stock' => $stock->minimum_stock,
                    'location' => $stock->location,
                    'is_low_stock' => $stock->is_low_stock,
                    'is_out_of_stock' => $stock->is_out_of_stock,
                    'updated_at' => $stock->updated_at->toISOString(),
                ],
                'timestamp' => now()->toISOString(),
            ];

            $this->broadcastToChannel($channel, $event, $data);
            
        } catch (\Exception $e) {
            Log::error('Failed to broadcast stock updated event', [
                'stock_id' => $stock->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Broadcast customer created event
     *
     * @param mixed $customer
     * @return void
     */
    public function broadcastCustomerCreated($customer)
    {
        try {
            $channel = 'customers';
            $event = 'customer.created';
            
            $data = [
                'event' => $event,
                'data' => [
                    'customer_id' => $customer->id,
                    'first_name' => $customer->first_name,
                    'last_name' => $customer->last_name,
                    'email' => $customer->email,
                    'phone' => $customer->phone,
                    'created_at' => $customer->created_at->toISOString(),
                ],
                'timestamp' => now()->toISOString(),
            ];

            $this->broadcastToChannel($channel, $event, $data);
            
        } catch (\Exception $e) {
            Log::error('Failed to broadcast customer created event', [
                'customer_id' => $customer->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Broadcast shift started event
     *
     * @param mixed $shift
     * @return void
     */
    public function broadcastShiftStarted($shift)
    {
        try {
            $channel = $shift instanceof \App\Models\StoreCashierShift ? 
                "store.{$shift->store_id}.shifts" : 
                'shifts';
                
            $event = 'shift.started';
            
            $data = [
                'event' => $event,
                'data' => [
                    'shift_id' => $shift->id,
                    'user_id' => $shift->user_id,
                    'store_id' => $shift->store_id ?? null,
                    'status' => $shift->status,
                    'starting_cash' => $shift->starting_cash,
                    'started_at' => $shift->created_at->toISOString(),
                ],
                'timestamp' => now()->toISOString(),
            ];

            $this->broadcastToChannel($channel, $event, $data);
            
        } catch (\Exception $e) {
            Log::error('Failed to broadcast shift started event', [
                'shift_id' => $shift->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Broadcast shift ended event
     *
     * @param mixed $shift
     * @return void
     */
    public function broadcastShiftEnded($shift)
    {
        try {
            $channel = $shift instanceof \App\Models\StoreCashierShift ? 
                "store.{$shift->store_id}.shifts" : 
                'shifts';
                
            $event = 'shift.ended';
            
            $data = [
                'event' => $event,
                'data' => [
                    'shift_id' => $shift->id,
                    'user_id' => $shift->user_id,
                    'store_id' => $shift->store_id ?? null,
                    'status' => $shift->status,
                    'starting_cash' => $shift->starting_cash,
                    'ending_cash' => $shift->ending_cash,
                    'ended_at' => $shift->updated_at->toISOString(),
                ],
                'timestamp' => now()->toISOString(),
            ];

            $this->broadcastToChannel($channel, $event, $data);
            
        } catch (\Exception $e) {
            Log::error('Failed to broadcast shift ended event', [
                'shift_id' => $shift->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Broadcast to channel
     *
     * @param string $channel
     * @param string $event
     * @param array $data
     * @return void
     */
    private function broadcastToChannel($channel, $event, $data)
    {
        // This would integrate with Laravel Echo and Pusher/Soketi
        // For now, we'll log the broadcast
        Log::info('Broadcasting to channel', [
            'channel' => $channel,
            'event' => $event,
            'data' => $data,
        ]);

        // In a real implementation, you would use:
        // broadcast(new \App\Events\RealtimeEvent($channel, $event, $data))->toOthers();
        
        // Or directly with Pusher:
        // pusher()->trigger($channel, $event, $data);
    }

    /**
     * Get channel name for user
     *
     * @param int $userId
     * @return string
     */
    public function getUserChannel($userId)
    {
        return "user.{$userId}";
    }

    /**
     * Get channel name for store
     *
     * @param int $storeId
     * @return string
     */
    public function getStoreChannel($storeId)
    {
        return "store.{$storeId}";
    }

    /**
     * Get channel name for orders
     *
     * @param int|null $storeId
     * @return string
     */
    public function getOrdersChannel($storeId = null)
    {
        return $storeId ? "store.{$storeId}.orders" : 'orders';
    }

    /**
     * Get channel name for payments
     *
     * @param int|null $storeId
     * @return string
     */
    public function getPaymentsChannel($storeId = null)
    {
        return $storeId ? "store.{$storeId}.payments" : 'payments';
    }

    /**
     * Get channel name for stock
     *
     * @param int $storeId
     * @return string
     */
    public function getStockChannel($storeId)
    {
        return "store.{$storeId}.stock";
    }

    /**
     * Get channel name for shifts
     *
     * @param int|null $storeId
     * @return string
     */
    public function getShiftsChannel($storeId = null)
    {
        return $storeId ? "store.{$storeId}.shifts" : 'shifts';
    }
}