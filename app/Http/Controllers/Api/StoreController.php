<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\StoreStock;
use App\Models\StoreOrder;
use App\Models\StorePayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class StoreController extends Controller
{
    /**
     * Display a listing of stores.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'nullable|boolean',
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

        $query = Store::with(['stocks.product', 'users']);

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $perPage = $request->per_page ?? 15;
        $stores = $query->orderBy('name')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'stores' => collect($stores->items())->map(function ($store) {
                    return [
                        'id' => $store->id,
                        'name' => $store->name,
                        'code' => $store->code,
                        'address' => $store->address,
                        'phone' => $store->phone,
                        'email' => $store->email,
                        'tax_number' => $store->tax_number,
                        'status' => $store->status,
                        'total_sales' => $store->total_sales,
                        'total_orders' => $store->total_orders,
                        'total_products' => $store->total_products,
                        'total_stock_value' => $store->total_stock_value,
                        'today_sales' => $store->today_sales,
                        'this_month_sales' => $store->this_month_sales,
                        'low_stock_items' => $store->low_stock_items->count(),
                        'out_of_stock_items' => $store->out_of_stock_items->count(),
                        'users_count' => $store->users->count(),
                        'created_at' => $store->created_at,
                        'updated_at' => $store->updated_at,
                    ];
                }),
                'pagination' => [
                    'current_page' => $stores->currentPage(),
                    'last_page' => $stores->lastPage(),
                    'per_page' => $stores->perPage(),
                    'total' => $stores->total(),
                    'from' => $stores->firstItem(),
                    'to' => $stores->lastItem(),
                ]
            ]
        ]);
    }

    /**
     * Display the specified store.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $store = Store::with(['stocks.product', 'users', 'cashierShifts' => function ($query) {
            $query->where('status', 'active')->with('user');
        }])->find($id);

        if (!$store) {
            return response()->json([
                'success' => false,
                'message' => 'Store not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $store->id,
                'name' => $store->name,
                'code' => $store->code,
                'address' => $store->address,
                'phone' => $store->phone,
                'email' => $store->email,
                'tax_number' => $store->tax_number,
                'status' => $store->status,
                'settings' => $store->settings,
                'total_sales' => $store->total_sales,
                'total_orders' => $store->total_orders,
                'total_products' => $store->total_products,
                'total_stock_value' => $store->total_stock_value,
                'today_sales' => $store->today_sales,
                'this_month_sales' => $store->this_month_sales,
                'low_stock_items' => collect($store->low_stock_items)->map(function ($stock) {
                    return [
                        'id' => $stock->id,
                        'product' => [
                            'id' => $stock->product->id,
                            'name' => $stock->product->name,
                            'barcode' => $stock->product->barcode,
                            'sku' => $stock->product->sku,
                        ],
                        'quantity' => $stock->quantity,
                        'minimum_stock' => $stock->minimum_stock,
                        'location' => $stock->location,
                        'stock_status' => $stock->stock_status_text,
                    ];
                }),
                'out_of_stock_items' => collect($store->out_of_stock_items)->map(function ($stock) {
                    return [
                        'id' => $stock->id,
                        'product' => [
                            'id' => $stock->product->id,
                            'name' => $stock->product->name,
                            'barcode' => $stock->product->barcode,
                            'sku' => $stock->product->sku,
                        ],
                        'quantity' => $stock->quantity,
                        'minimum_stock' => $stock->minimum_stock,
                        'location' => $stock->location,
                        'stock_status' => $stock->stock_status_text,
                    ];
                }),
                'users' => collect($store->users)->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'roles' => $user->roles->pluck('name'),
                    ];
                }),
                'active_shifts' => collect($store->cashierShifts)->map(function ($shift) {
                    return [
                        'id' => $shift->id,
                        'user' => [
                            'id' => $shift->user->id,
                            'name' => $shift->user->name,
                        ],
                        'started_at' => $shift->created_at,
                    ];
                }),
                'created_at' => $store->created_at,
                'updated_at' => $store->updated_at,
            ]
        ]);
    }

    /**
     * Get store statistics.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function statistics(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $store = Store::find($id);

        if (!$store) {
            return response()->json([
                'success' => false,
                'message' => 'Store not found'
            ], 404);
        }

        $ordersQuery = StoreOrder::forStore($id);
        $paymentsQuery = StorePayment::forStore($id);

        // Apply date filters
        if ($request->has('date_from')) {
            $ordersQuery->whereDate('created_at', '>=', $request->date_from);
            $paymentsQuery->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $ordersQuery->whereDate('created_at', '<=', $request->date_to);
            $paymentsQuery->whereDate('created_at', '<=', $request->date_to);
        }

        $orders = $ordersQuery->get();
        $payments = $paymentsQuery->get();

        $statistics = [
            'total_orders' => $orders->count(),
            'completed_orders' => $orders->where('status', 'completed')->count(),
            'pending_orders' => $orders->where('status', 'pending')->count(),
            'cancelled_orders' => $orders->where('status', 'cancelled')->count(),
            'total_sales' => $orders->where('status', 'completed')->sum('total_price'),
            'total_payments' => $payments->count(),
            'total_amount_paid' => $payments->sum('amount'),
            'payment_methods' => $payments->groupBy('payment_method')->map(function ($payments, $method) {
                return [
                    'method' => $method,
                    'count' => $payments->count(),
                    'amount' => $payments->sum('amount'),
                ];
            })->values(),
        ];

        return response()->json([
            'success' => true,
            'data' => $statistics
        ]);
    }

    /**
     * Get store stock information.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function stock(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'search' => 'nullable|string|max:255',
            'low_stock' => 'nullable|boolean',
            'out_of_stock' => 'nullable|boolean',
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

        $store = Store::find($id);

        if (!$store) {
            return response()->json([
                'success' => false,
                'message' => 'Store not found'
            ], 404);
        }

        $query = StoreStock::with('product')->where('store_id', $id);

        // Apply filters
        if ($request->has('search')) {
            $search = $request->search;
            $query->whereHas('product', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('barcode', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        if ($request->has('low_stock') && $request->low_stock) {
            $query->lowStock();
        }

        if ($request->has('out_of_stock') && $request->out_of_stock) {
            $query->outOfStock();
        }

        $perPage = $request->per_page ?? 15;
        $stocks = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'stocks' => collect($stocks->items())->map(function ($stock) {
                    return [
                        'id' => $stock->id,
                        'product' => [
                            'id' => $stock->product->id,
                            'name' => $stock->product->name,
                            'description' => $stock->product->description,
                            'barcode' => $stock->product->barcode,
                            'sku' => $stock->product->sku,
                            'price' => $stock->product->price,
                            'cost_price' => $stock->product->cost_price,
                            'image' => $stock->product->getImageUrl(),
                        ],
                        'quantity' => $stock->quantity,
                        'minimum_stock' => $stock->minimum_stock,
                        'location' => $stock->location,
                        'stock_value' => $stock->stock_value,
                        'is_low_stock' => $stock->is_low_stock,
                        'is_out_of_stock' => $stock->is_out_of_stock,
                        'stock_status' => $stock->stock_status_text,
                        'created_at' => $stock->created_at,
                        'updated_at' => $stock->updated_at,
                    ];
                }),
                'pagination' => [
                    'current_page' => $stocks->currentPage(),
                    'last_page' => $stocks->lastPage(),
                    'per_page' => $stocks->perPage(),
                    'total' => $stocks->total(),
                    'from' => $stocks->firstItem(),
                    'to' => $stocks->lastItem(),
                ]
            ]
        ]);
    }
}