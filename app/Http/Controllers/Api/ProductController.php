<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StoreStock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    /**
     * Display a listing of products.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'search' => 'nullable|string|max:255',
            'category' => 'nullable|string|max:255',
            'status' => 'nullable|boolean',
            'in_stock' => 'nullable|boolean',
            'low_stock' => 'nullable|boolean',
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

        $query = Product::with(['storeStocks' => function ($query) use ($storeId) {
            if ($storeId) {
                $query->where('store_id', $storeId);
            }
        }]);

        // Apply filters
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('barcode', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('in_stock') && $request->in_stock) {
            if ($storeId) {
                $query->whereHas('storeStocks', function ($q) use ($storeId) {
                    $q->where('store_id', $storeId)
                      ->where('quantity', '>', 0);
                });
            } else {
                $query->where('quantity', '>', 0);
            }
        }

        if ($request->has('low_stock') && $request->low_stock) {
            if ($storeId) {
                $query->whereHas('storeStocks', function ($q) use ($storeId) {
                    $q->where('store_id', $storeId)
                      ->whereColumn('quantity', '<=', 'minimum_stock')
                      ->where('quantity', '>', 0);
                });
            } else {
                $query->lowStock();
            }
        }

        $perPage = $request->per_page ?? 15;
        $products = $query->orderBy('name')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'products' => collect($products->items())->map(function ($product) use ($storeId) {
                    $storeStock = $storeId ? $product->storeStocks->firstWhere('store_id', $storeId) : null;
                    return [
                        'id' => $product->id,
                        'name' => $product->name,
                        'description' => $product->description,
                        'barcode' => $product->barcode,
                        'sku' => $product->sku,
                        'price' => $product->price,
                        'cost_price' => $product->cost_price,
                        'tax' => $product->tax,
                        'image' => $product->getImageUrl(),
                        'status' => $product->status,
                        'stock' => $storeStock ? [
                            'quantity' => $storeStock->quantity,
                            'minimum_stock' => $storeStock->minimum_stock,
                            'location' => $storeStock->location,
                            'is_low_stock' => $storeStock->is_low_stock,
                            'is_out_of_stock' => $storeStock->is_out_of_stock,
                            'stock_status' => $storeStock->stock_status_text,
                        ] : [
                            'quantity' => $product->quantity,
                            'minimum_stock' => $product->minimum_stock,
                            'is_low_stock' => $product->isLowStock(),
                            'is_out_of_stock' => !$product->isInStock(),
                            'stock_status' => $product->stock_status,
                        ],
                    ];
                }),
                'pagination' => [
                    'current_page' => $products->currentPage(),
                    'last_page' => $products->lastPage(),
                    'per_page' => $products->perPage(),
                    'total' => $products->total(),
                    'from' => $products->firstItem(),
                    'to' => $products->lastItem(),
                ]
            ]
        ]);
    }

    /**
     * Display the specified product.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();
        $storeId = $request->query('store_id', $user->store_id);

        $product = Product::with(['storeStocks' => function ($query) use ($storeId) {
            if ($storeId) {
                $query->where('store_id', $storeId);
            }
        }])->find($id);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found'
            ], 404);
        }

        $storeStock = $storeId ? $product->storeStocks->firstWhere('store_id', $storeId) : null;

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $product->id,
                'name' => $product->name,
                'description' => $product->description,
                'barcode' => $product->barcode,
                'sku' => $product->sku,
                'price' => $product->price,
                'cost_price' => $product->cost_price,
                'tax' => $product->tax,
                'image' => $product->getImageUrl(),
                'status' => $product->status,
                'stock' => $storeStock ? [
                    'quantity' => $storeStock->quantity,
                    'minimum_stock' => $storeStock->minimum_stock,
                    'location' => $storeStock->location,
                    'is_low_stock' => $storeStock->is_low_stock,
                    'is_out_of_stock' => $storeStock->is_out_of_stock,
                    'stock_status' => $storeStock->stock_status_text,
                ] : [
                    'quantity' => $product->quantity,
                    'minimum_stock' => $product->minimum_stock,
                    'is_low_stock' => $product->isLowStock(),
                    'is_out_of_stock' => !$product->isInStock(),
                    'stock_status' => $product->stock_status,
                ],
                'created_at' => $product->created_at,
                'updated_at' => $product->updated_at,
            ]
        ]);
    }

    /**
     * Search products by barcode or SKU.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function barcodeSearch(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'barcode' => 'required|string|max:255',
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

        $product = Product::byBarcodeOrSku($request->barcode)
            ->with(['storeStocks' => function ($query) use ($storeId) {
                if ($storeId) {
                    $query->where('store_id', $storeId);
                }
            }])
            ->first();

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found'
            ], 404);
        }

        $storeStock = $storeId ? $product->storeStocks->firstWhere('store_id', $storeId) : null;

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $product->id,
                'name' => $product->name,
                'description' => $product->description,
                'barcode' => $product->barcode,
                'sku' => $product->sku,
                'price' => $product->price,
                'cost_price' => $product->cost_price,
                'tax' => $product->tax,
                'image' => $product->getImageUrl(),
                'status' => $product->status,
                'stock' => $storeStock ? [
                    'quantity' => $storeStock->quantity,
                    'minimum_stock' => $storeStock->minimum_stock,
                    'location' => $storeStock->location,
                    'is_low_stock' => $storeStock->is_low_stock,
                    'is_out_of_stock' => $storeStock->is_out_of_stock,
                    'stock_status' => $storeStock->stock_status_text,
                ] : [
                    'quantity' => $product->quantity,
                    'minimum_stock' => $product->minimum_stock,
                    'is_low_stock' => $product->isLowStock(),
                    'is_out_of_stock' => !$product->isInStock(),
                    'stock_status' => $product->stock_status,
                ],
            ]
        ]);
    }
}