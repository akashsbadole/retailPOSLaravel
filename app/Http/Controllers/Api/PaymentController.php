<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\StorePayment;
use App\Models\Order;
use App\Models\StoreOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PaymentController extends Controller
{
    /**
     * Store a newly created payment.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required|integer',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|in:cash,card,upi,bank_transfer,cheque',
            'notes' => 'nullable|string|max:500',
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

            // Find the order
            if ($storeId) {
                $order = StoreOrder::forStore($storeId)->find($request->order_id);
                $paymentClass = StorePayment::class;
                $orderIdField = 'store_order_id';
            } else {
                $order = Order::find($request->order_id);
                $paymentClass = Payment::class;
                $orderIdField = 'order_id';
            }

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found'
                ], 404);
            }

            // Check if payment amount exceeds remaining amount
            $totalPaid = $order->total_paid ?? $order->receivedAmount();
            $remainingAmount = $order->total_price - $totalPaid;

            if ($request->amount > $remainingAmount) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment amount exceeds remaining amount',
                    'data' => [
                        'remaining_amount' => $remainingAmount,
                        'requested_amount' => $request->amount,
                    ]
                ], 400);
            }

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

            // Create payment
            $paymentData = [
                $orderIdField => $order->id,
                'user_id' => $user->id,
                'amount' => $request->amount,
                'payment_method' => $request->payment_method,
                'notes' => $request->notes,
                'cashier_shift_id' => $currentShift->id,
            ];

            if ($storeId) {
                $paymentData['store_id'] = $storeId;
            }

            $payment = $paymentClass::create($paymentData);

            // Check if order is fully paid
            $newTotalPaid = $totalPaid + $request->amount;
            if ($newTotalPaid >= $order->total_price) {
                $order->update(['status' => 'completed']);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Payment processed successfully',
                'data' => [
                    'id' => $payment->id,
                    'order_id' => $order->id,
                    'amount' => $payment->amount,
                    'payment_method' => $payment->payment_method,
                    'notes' => $payment->notes,
                    'created_at' => $payment->created_at,
                    'order_status' => $order->status,
                    'total_paid' => $newTotalPaid,
                    'remaining_amount' => $order->total_price - $newTotalPaid,
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to process payment: ' . $e->getMessage()
            ], 400);
        }
    }

    /**
     * Display a listing of payments.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'nullable|integer',
            'payment_method' => 'nullable|in:cash,card,upi,bank_transfer,cheque',
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

        if ($storeId) {
            $query = StorePayment::with(['storeOrder.customer', 'user', 'store'])
                ->forStore($storeId);
        } else {
            $query = Payment::with(['order.customer', 'user']);
        }

        // Apply filters
        if ($request->has('order_id')) {
            $query->where($storeId ? 'store_order_id' : 'order_id', $request->order_id);
        }

        if ($request->has('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $perPage = $request->per_page ?? 15;
        $payments = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'payments' => $payments->items()->map(function ($payment) use ($storeId) {
                    $paymentData = [
                        'id' => $payment->id,
                        'amount' => $payment->amount,
                        'payment_method' => $payment->payment_method,
                        'notes' => $payment->notes,
                        'created_at' => $payment->created_at,
                        'user' => [
                            'id' => $payment->user->id,
                            'name' => $payment->user->name,
                        ],
                    ];

                    if ($storeId) {
                        $paymentData['order'] = [
                            'id' => $payment->storeOrder->id,
                            'customer' => $payment->storeOrder->customer ? [
                                'id' => $payment->storeOrder->customer->id,
                                'name' => $payment->storeOrder->customer->first_name . ' ' . $payment->storeOrder->customer->last_name,
                            ] : null,
                        ];
                        $paymentData['store'] = [
                            'id' => $payment->store->id,
                            'name' => $payment->store->name,
                        ];
                    } else {
                        $paymentData['order'] = [
                            'id' => $payment->order->id,
                            'customer' => $payment->order->customer ? [
                                'id' => $payment->order->customer->id,
                                'name' => $payment->order->customer->first_name . ' ' . $payment->order->customer->last_name,
                            ] : null,
                        ];
                    }

                    return $paymentData;
                }),
                'pagination' => [
                    'current_page' => $payments->currentPage(),
                    'last_page' => $payments->lastPage(),
                    'per_page' => $payments->perPage(),
                    'total' => $payments->total(),
                    'from' => $payments->firstItem(),
                    'to' => $payments->lastItem(),
                ]
            ]
        ]);
    }

    /**
     * Display the specified payment.
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
            $payment = StorePayment::with(['storeOrder.customer', 'storeOrder.items', 'user', 'store'])
                ->forStore($storeId)
                ->find($id);
        } else {
            $payment = Payment::with(['order.customer', 'order.items', 'user'])
                ->find($id);
        }

        if (!$payment) {
            return response()->json([
                'success' => false,
                'message' => 'Payment not found'
            ], 404);
        }

        $paymentData = [
            'id' => $payment->id,
            'amount' => $payment->amount,
            'payment_method' => $payment->payment_method,
            'notes' => $payment->notes,
            'created_at' => $payment->created_at,
            'updated_at' => $payment->updated_at,
            'user' => [
                'id' => $payment->user->id,
                'name' => $payment->user->name,
                'email' => $payment->user->email,
            ],
        ];

        if ($storeId) {
            $paymentData['order'] = [
                'id' => $payment->storeOrder->id,
                'total_price' => $payment->storeOrder->total_price,
                'status' => $payment->storeOrder->status,
                'customer' => $payment->storeOrder->customer ? [
                    'id' => $payment->storeOrder->customer->id,
                    'name' => $payment->storeOrder->customer->first_name . ' ' . $payment->storeOrder->customer->last_name,
                    'email' => $payment->storeOrder->customer->email,
                    'phone' => $payment->storeOrder->customer->phone,
                ] : null,
                'items' => $payment->storeOrder->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'name' => $item->name,
                        'quantity' => $item->quantity,
                        'price' => $item->price,
                    ];
                }),
            ];
            $paymentData['store'] = [
                'id' => $payment->store->id,
                'name' => $payment->store->name,
                'code' => $payment->store->code,
            ];
        } else {
            $paymentData['order'] = [
                'id' => $payment->order->id,
                'total_price' => $payment->order->total_price,
                'status' => $payment->order->status,
                'customer' => $payment->order->customer ? [
                    'id' => $payment->order->customer->id,
                    'name' => $payment->order->customer->first_name . ' ' . $payment->order->customer->last_name,
                    'email' => $payment->order->customer->email,
                    'phone' => $payment->order->customer->phone,
                ] : null,
                'items' => $payment->order->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'name' => $item->name,
                        'quantity' => $item->quantity,
                        'price' => $item->price,
                    ];
                }),
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $paymentData
        ]);
    }

    /**
     * Get payment methods summary for a date range.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function summary(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
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
            $query = StorePayment::forStore($storeId);
        } else {
            $query = Payment::where('user_id', $user->id);
        }

        // Apply date filters
        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $payments = $query->get();

        $summary = $payments->groupBy('payment_method')->map(function ($payments, $method) {
            return [
                'payment_method' => $method,
                'count' => $payments->count(),
                'total_amount' => $payments->sum('amount'),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => $summary,
                'total_payments' => $payments->count(),
                'total_amount' => $payments->sum('amount'),
            ]
        ]);
    }
}