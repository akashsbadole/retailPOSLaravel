<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\StoreOrder;
use App\Models\StoreOrderItem;
use App\Models\Product;
use App\Models\StoreStock;
use App\Models\Customer;
use App\Models\StoreCashierShift;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    /**
     * Display a listing of orders.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'nullable|in:pending,completed,cancelled',
            'customer_id' => 'nullable|integer|exists:customers,id',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'store_id' => 'nullable|integer|exists:stores,id',
            'per_page' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();
        $storeId = $request->store_id ?? $user->store_id;

        // Use StoreOrder if store_id is provided or user has store_id
        if ($storeId) {
            $query = StoreOrder::with(['customer', 'items.product', 'payments', 'store', 'cashierShift.user'])
                ->forStore($storeId);
        } else {
            $query = Order::with(['customer', 'items.product', 'payments', 'cashierShift.user']);
        }

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $perPage = $request->per_page ?? 15;
        $orders = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'orders' => collect($orders->items())->map(function ($order) use ($storeId) {
                    $orderData = [
                        'id' => $order->id,
                        'customer' => $order->customer ? [
                            'id' => $order->customer->id,
                            'name' => $order->customer->first_name . ' ' . $order->customer->last_name,
                            'email' => $order->customer->email,
                            'phone' => $order->customer->phone,
                        ] : null,
                        'total_price' => $order->total_price,
                        'discount_amount' => $order->discount_amount,
                        'status' => $order->status,
                        'notes' => $order->notes ?? null,
                        'items_count' => $order->items->count(),
                        'total_paid' => $order->total_paid ?? $order->receivedAmount(),
                        'remaining_amount' => $order->remaining_amount ?? ($order->total_price - $order->receivedAmount()),
                        'created_at' => $order->created_at,
                        'updated_at' => $order->updated_at,
                    ];

                    if ($storeId) {
                        $orderData['store'] = [
                            'id' => $order->store->id,
                            'name' => $order->store->name,
                            'code' => $order->store->code,
                        ];
                    }

                    return $orderData;
                }),
                'pagination' => [
                    'current_page' => $orders->currentPage(),
                    'last_page' => $orders->lastPage(),
                    'per_page' => $orders->perPage(),
                    'total' => $orders->total(),
                    'from' => $orders->firstItem(),
                    'to' => $orders->lastItem(),
                ]
            ]
        ]);
    }

    /**
     * Store a newly created order.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => 'nullable|integer|exists:customers,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.discount_percentage' => 'nullable|numeric|min:0|max:100',
            'discount_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
            'store_id' => 'nullable|integer|exists:stores,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();
        $storeId = $request->store_id ?? $user->store_id;

        try {
            DB::beginTransaction();

            // Get current shift
            $currentShift = $storeId ? 
                $user->currentStoreShift() : 
                $user->currentShift();

            if (!$currentShift) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active shift found. Please start a shift first.'
                ], 400);
            }

            // Calculate total price
            $totalPrice = 0;
            foreach ($request->items as $item) {
                $totalPrice += ($item['price'] * $item['quantity']) * (1 - ($item['discount_percentage'] ?? 0) / 100);
            }
            $totalPrice -= $request->discount_amount ?? 0;

            // Create order
            $orderData = [
                'customer_id' => $request->customer_id,
                'total_price' => $totalPrice,
                'discount_amount' => $request->discount_amount ?? 0,
                'status' => 'pending',
                'notes' => $request->notes,
            ];

            if ($storeId) {
                $orderData['store_id'] = $storeId;
                $orderData['cashier_shift_id'] = $currentShift->id;
                $order = StoreOrder::create($orderData);
            } else {
                $orderData['cashier_shift_id'] = $currentShift->id;
                $order = Order::create($orderData);
            }

            // Create order items
            foreach ($request->items as $item) {
                $product = Product::find($item['product_id']);
                
                if (!$product) {
                    throw new \Exception("Product with ID {$item['product_id']} not found");
                }

                // Check stock availability
                if ($storeId) {
                    $storeStock = StoreStock::where('store_id', $storeId)
                        ->where('product_id', $item['product_id'])
                        ->first();

                    if (!$storeStock || $storeStock->quantity < $item['quantity']) {
                        throw new \Exception("Insufficient stock for product: {$product->name}");
                    }

                    // Deduct stock
                    $storeStock->decreaseStock($item['quantity']);
                } else {
                    if (!$product->hasStockFor($item['quantity'])) {
                        throw new \Exception("Insufficient stock for product: {$product->name}");
                    }

                    // Deduct stock
                    $product->deductStock($item['quantity']);
                }

                $itemData = [
                    'name' => $product->name,
                    'price' => $item['price'],
                    'tax' => $product->tax,
                    'quantity' => $item['quantity'],
                    'discount_percentage' => $item['discount_percentage'] ?? 0,
                    'product_id' => $item['product_id'],
                ];

                if ($storeId) {
                    $itemData['store_order_id'] = $order->id;
                    StoreOrderItem::create($itemData);
                } else {
                    $itemData['order_id'] = $order->id;
                    OrderItem::create($itemData);
                }
            }

            DB::commit();

            // Load relationships for response
            if ($storeId) {
                $order->load(['customer', 'items.product', 'store', 'cashierShift.user']);
            } else {
                $order->load(['customer', 'items.product', 'cashierShift.user']);
            }

            return response()->json([
                'success' => true,
                'message' => 'Order created successfully',
                'data' => [
                    'id' => $order->id,
                    'customer' => $order->customer ? [
                        'id' => $order->customer->id,
                        'name' => $order->customer->first_name . ' ' . $order->customer->last_name,
                        'email' => $order->customer->email,
                        'phone' => $order->customer->phone,
                    ] : null,
                    'total_price' => $order->total_price,
                    'discount_amount' => $order->discount_amount,
                    'status' => $order->status,
                    'notes' => $order->notes,
                    'items' => collect($order->items)->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'product' => [
                                'id' => $item->product->id,
                                'name' => $item->product->name,
                                'barcode' => $item->product->barcode,
                                'sku' => $item->product->sku,
                            ],
                            'quantity' => $item->quantity,
                            'price' => $item->price,
                            'tax' => $item->tax,
                            'discount_percentage' => $item->discount_percentage,
                            'subtotal' => ($item->price * $item->quantity) * (1 - $item->discount_percentage / 100),
                        ];
                    }),
                    'total_paid' => 0,
                    'remaining_amount' => $order->total_price,
                    'created_at' => $order->created_at,
                    'updated_at' => $order->updated_at,
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create order: ' . $e->getMessage()
            ], 400);
        }
    }

    /**
     * Display the specified order.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();
        $storeId = $request->query('store_id', $user->store_id);

        if ($storeId) {
            $order = StoreOrder::with(['customer', 'items.product', 'payments', 'store', 'cashierShift.user'])
                ->forStore($storeId)
                ->find($id);
        } else {
            $order = Order::with(['customer', 'items.product', 'payments', 'cashierShift.user'])
                ->find($id);
        }

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        }

        $orderData = [
            'id' => $order->id,
            'customer' => $order->customer ? [
                'id' => $order->customer->id,
                'name' => $order->customer->first_name . ' ' . $order->customer->last_name,
                'email' => $order->customer->email,
                'phone' => $order->customer->phone,
                'address' => $order->customer->address,
            ] : null,
            'total_price' => $order->total_price,
            'discount_amount' => $order->discount_amount,
            'status' => $order->status,
            'notes' => $order->notes,
            'items' => collect($order->items)->map(function ($item) {
                return [
                    'id' => $item->id,
                    'product' => [
                        'id' => $item->product->id,
                        'name' => $item->product->name,
                        'barcode' => $item->product->barcode,
                        'sku' => $item->product->sku,
                    ],
                    'quantity' => $item->quantity,
                    'price' => $item->price,
                    'tax' => $item->tax,
                    'discount_percentage' => $item->discount_percentage,
                    'subtotal' => ($item->price * $item->quantity) * (1 - $item->discount_percentage / 100),
                ];
            }),
            'payments' => collect($order->payments)->map(function ($payment) {
                return [
                    'id' => $payment->id,
                    'amount' => $payment->amount,
                    'payment_method' => $payment->payment_method,
                    'notes' => $payment->notes ?? null,
                    'created_at' => $payment->created_at,
                ];
            }),
            'total_paid' => $order->total_paid ?? $order->receivedAmount(),
            'remaining_amount' => $order->remaining_amount ?? ($order->total_price - $order->receivedAmount()),
            'created_at' => $order->created_at,
            'updated_at' => $order->updated_at,
        ];

        if ($storeId) {
            $orderData['store'] = [
                'id' => $order->store->id,
                'name' => $order->store->name,
                'code' => $order->store->code,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $orderData
        ]);
    }

    /**
     * Update the specified order.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pending,completed,cancelled',
            'notes' => 'nullable|string|max:1000',
            'store_id' => 'nullable|integer|exists:stores,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();
        $storeId = $request->store_id ?? $user->store_id;

        if ($storeId) {
            $order = StoreOrder::forStore($storeId)->find($id);
        } else {
            $order = Order::find($id);
        }

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        }

        $order->update([
            'status' => $request->status,
            'notes' => $request->notes ?? $order->notes,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Order updated successfully',
            'data' => [
                'id' => $order->id,
                'status' => $order->status,
                'notes' => $order->notes,
                'updated_at' => $order->updated_at,
            ]
        ]);
    }
}