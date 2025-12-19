<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\Setting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SalesReturnService
{
    /**
     * Create a full return for an order
     */
    public function createFullReturn(Order $order, array $data): SalesReturn
    {
        return DB::transaction(function () use ($order, $data) {
            $salesReturn = SalesReturn::create([
                'order_id' => $order->id,
                'user_id' => Auth::id(),
                'return_date' => $data['return_date'] ?? now(),
                'reason' => $data['reason'],
                'notes' => $data['notes'] ?? null,
                'status' => 'pending',
                'total_refund_amount' => 0, // Will be calculated
                'refund_method' => $data['refund_method'] ?? 'original_payment',
            ]);

            // Add all order items to the return
            foreach ($order->items as $orderItem) {
                $alreadyReturned = SalesReturnItem::where('order_item_id', $orderItem->id)
                    ->sum('quantity');
                $availableQuantity = $orderItem->quantity - $alreadyReturned;

                if ($availableQuantity > 0) {
                    $restockingFeePercentage = $this->getRestockingFeePercentage();
                    $refundAmount = $orderItem->price * $availableQuantity * (1 - $restockingFeePercentage / 100);

                    SalesReturnItem::create([
                        'sales_return_id' => $salesReturn->id,
                        'order_item_id' => $orderItem->id,
                        'quantity' => $availableQuantity,
                        'refund_amount' => $refundAmount,
                        'reason' => $data['item_reasons'][$orderItem->id] ?? null,
                        'restocked' => false,
                    ]);
                }
            }

            // Update total refund amount
            $salesReturn->updateTotalRefundAmount();

            return $salesReturn;
        });
    }

    /**
     * Create a partial return for an order
     */
    public function createPartialReturn(Order $order, array $items, array $data): SalesReturn
    {
        return DB::transaction(function () use ($order, $items, $data) {
            $salesReturn = SalesReturn::create([
                'order_id' => $order->id,
                'user_id' => Auth::id(),
                'return_date' => $data['return_date'] ?? now(),
                'reason' => $data['reason'],
                'notes' => $data['notes'] ?? null,
                'status' => 'pending',
                'total_refund_amount' => 0, // Will be calculated
                'refund_method' => $data['refund_method'] ?? 'original_payment',
            ]);

            $restockingFeePercentage = $this->getRestockingFeePercentage();

            foreach ($items as $itemData) {
                $orderItem = OrderItem::find($itemData['order_item_id']);
                if (!$orderItem) continue;

                $alreadyReturned = SalesReturnItem::where('order_item_id', $orderItem->id)
                    ->sum('quantity');
                $availableQuantity = $orderItem->quantity - $alreadyReturned;

                $requestedQuantity = min($itemData['quantity'], $availableQuantity);

                if ($requestedQuantity > 0) {
                    $refundAmount = $orderItem->price * $requestedQuantity * (1 - $restockingFeePercentage / 100);

                    SalesReturnItem::create([
                        'sales_return_id' => $salesReturn->id,
                        'order_item_id' => $orderItem->id,
                        'quantity' => $requestedQuantity,
                        'refund_amount' => $refundAmount,
                        'reason' => $itemData['reason'] ?? null,
                        'restocked' => false,
                    ]);
                }
            }

            // Update total refund amount
            $salesReturn->updateTotalRefundAmount();

            return $salesReturn;
        });
    }

    /**
     * Process a return (approve and handle stock adjustments)
     */
    public function processReturn(SalesReturn $salesReturn, ?User $user = null): bool
    {
        return DB::transaction(function () use ($salesReturn, $user) {
            // Approve the return
            $salesReturn->approve($user);

            // Process stock adjustments if auto-adjustment is enabled
            if ($this->isAutoStockAdjustmentEnabled()) {
                $this->processStockAdjustments($salesReturn);
            }

            // Update the return status to processed
            $salesReturn->process();

            return true;
        });
    }

    /**
     * Process stock adjustments for a return
     */
    public function processStockAdjustments(SalesReturn $salesReturn): void
    {
        foreach ($salesReturn->items as $item) {
            if (!$item->restocked) {
                $product = $item->product;
                if ($product) {
                    // Create stock adjustment
                    StockAdjustment::createAdjustment(
                        $product,
                        $item->quantity,
                        'increase',
                        'return',
                        "Return from Order #{$salesReturn->order_id} - {$item->reason}",
                        null
                    );

                    // Mark item as restocked
                    $item->markAsRestocked();
                }
            }
        }
    }

    /**
     * Check if an order is eligible for return
     */
    public function isOrderEligibleForReturn(Order $order): bool
    {
        $returnTimeLimit = $this->getReturnTimeLimitDays();
        $orderDate = $order->created_at;
        $currentDate = now();

        return $currentDate->diffInDays($orderDate) <= $returnTimeLimit;
    }

    /**
     * Get the return time limit in days
     */
    public function getReturnTimeLimitDays(): int
    {
        $setting = Setting::where('key', 'return_time_limit_days')->first();
        return $setting ? (int) $setting->value : 30;
    }

    /**
     * Get the restocking fee percentage
     */
    public function getRestockingFeePercentage(): float
    {
        $setting = Setting::where('key', 'return_restocking_fee_percentage')->first();
        return $setting ? (float) $setting->value : 0.00;
    }

    /**
     * Check if return requires approval
     */
    public function isApprovalRequired(): bool
    {
        $setting = Setting::where('key', 'return_require_approval')->first();
        return $setting ? (bool) $setting->value : true;
    }

    /**
     * Check if auto stock adjustment is enabled
     */
    public function isAutoStockAdjustmentEnabled(): bool
    {
        $setting = Setting::where('key', 'return_auto_stock_adjustment')->first();
        return $setting ? (bool) $setting->value : true;
    }

    /**
     * Get available return reasons
     */
    public function getReturnReasons(): array
    {
        $setting = Setting::where('key', 'return_reasons')->first();
        return $setting ? json_decode($setting->value, true) : [
            'damaged' => 'Damaged',
            'defective' => 'Defective',
            'wrong_item' => 'Wrong Item',
            'not_needed' => 'Not Needed',
            'customer_dissatisfaction' => 'Customer Dissatisfaction',
            'expired' => 'Expired',
            'other' => 'Other',
        ];
    }

    /**
     * Calculate refund amount for an item
     */
    public function calculateRefundAmount(OrderItem $orderItem, int $quantity): float
    {
        $restockingFeePercentage = $this->getRestockingFeePercentage();
        return $orderItem->price * $quantity * (1 - $restockingFeePercentage / 100);
    }

    /**
     * Get returnable quantity for an order item
     */
    public function getReturnableQuantity(OrderItem $orderItem): int
    {
        $alreadyReturned = SalesReturnItem::where('order_item_id', $orderItem->id)
            ->sum('quantity');
        return $orderItem->quantity - $alreadyReturned;
    }

    /**
     * Get return statistics for a date range
     */
    public function getReturnStatistics(string $startDate, string $endDate): array
    {
        $returns = SalesReturn::whereDate('return_date', '>=', $startDate)
            ->whereDate('return_date', '<=', $endDate)
            ->get();

        return [
            'total_returns' => $returns->count(),
            'pending_returns' => $returns->where('status', 'pending')->count(),
            'approved_returns' => $returns->where('status', 'approved')->count(),
            'rejected_returns' => $returns->where('status', 'rejected')->count(),
            'processed_returns' => $returns->where('status', 'processed')->count(),
            'completed_returns' => $returns->where('status', 'completed')->count(),
            'total_refund_amount' => $returns->sum('total_refund_amount'),
            'average_refund_amount' => $returns->count() > 0 ? $returns->avg('total_refund_amount') : 0,
        ];
    }

    /**
     * Get return reasons statistics
     */
    public function getReturnReasonsStatistics(string $startDate, string $endDate): array
    {
        $returns = SalesReturn::whereDate('return_date', '>=', $startDate)
            ->whereDate('return_date', '<=', $endDate)
            ->get();

        return $returns->groupBy('reason')->map(function ($group) {
            return [
                'count' => $group->count(),
                'total_amount' => $group->sum('total_refund_amount'),
                'percentage' => ($group->count() / $returns->count()) * 100,
            ];
        })->toArray();
    }

    /**
     * Get product return statistics
     */
    public function getProductReturnStatistics(string $startDate, string $endDate): array
    {
        $returnItems = SalesReturnItem::whereHas('salesReturn', function ($query) use ($startDate, $endDate) {
            $query->whereDate('return_date', '>=', $startDate)
                  ->whereDate('return_date', '<=', $endDate);
        })->get();

        return $returnItems->groupBy('product_id')->map(function ($group) {
            $product = $group->first()->product;
            return [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_sku' => $product->sku,
                'total_quantity' => $group->sum('quantity'),
                'total_amount' => $group->sum('refund_amount'),
                'return_count' => $group->count(),
            ];
        })->toArray();
    }
}