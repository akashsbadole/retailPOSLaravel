<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\StoreCashierShift;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    /**
     * Display the authenticated user's profile.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function profile(Request $request)
    {
        $user = $request->user()->load(['store', 'roles', 'permissions']);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'store_id' => $user->store_id,
                'store' => $user->store ? [
                    'id' => $user->store->id,
                    'name' => $user->store->name,
                    'code' => $user->store->code,
                    'address' => $user->store->address,
                    'phone' => $user->store->phone,
                ] : null,
                'roles' => $user->roles->pluck('name'),
                'permissions' => $user->getAllPermissions()->pluck('name'),
                'current_shift' => $user->currentStoreShift(),
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ]
        ]);
    }

    /**
     * Update the authenticated user's profile.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateProfile(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . $request->user()->id,
            'current_password' => 'required_with:new_password|string',
            'new_password' => 'nullable|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();

        // Verify current password if changing password
        if ($request->has('new_password') && !Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect'
            ], 400);
        }

        $updateData = [
            'name' => $request->name,
            'email' => $request->email,
        ];

        if ($request->has('new_password')) {
            $updateData['password'] = Hash::make($request->new_password);
        }

        $user->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'updated_at' => $user->updated_at,
            ]
        ]);
    }

    /**
     * Get the authenticated user's current shift.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function currentShift(Request $request)
    {
        $user = $request->user();
        $currentShift = $user->currentStoreShift();

        if (!$currentShift) {
            return response()->json([
                'success' => false,
                'message' => 'No active shift found'
            ], 404);
        }

        $shiftData = [
            'id' => $currentShift->id,
            'user' => [
                'id' => $currentShift->user->id,
                'name' => $currentShift->user->name,
            ],
            'store' => [
                'id' => $currentShift->store->id,
                'name' => $currentShift->store->name,
                'code' => $currentShift->store->code,
            ],
            'status' => $currentShift->status,
            'started_at' => $currentShift->created_at,
            'orders_count' => $currentShift->orders()->count(),
            'payments_count' => $currentShift->payments()->count(),
            'total_sales' => $currentShift->orders()->where('status', 'completed')->sum('total_price'),
            'total_payments' => $currentShift->payments()->sum('amount'),
        ];

        return response()->json([
            'success' => true,
            'data' => $shiftData
        ]);
    }

    /**
     * Start a new shift for the authenticated user.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function startShift(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'store_id' => 'required|integer|exists:stores,id',
            'starting_cash' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();

        // Check if user already has an active shift
        if ($user->currentStoreShift()) {
            return response()->json([
                'success' => false,
                'message' => 'You already have an active shift'
            ], 400);
        }

        // Create new shift
        $shift = StoreCashierShift::create([
            'store_id' => $request->store_id,
            'user_id' => $user->id,
            'starting_cash' => $request->starting_cash ?? 0,
            'status' => 'active',
            'notes' => $request->notes,
        ]);

        $shiftData = [
            'id' => $shift->id,
            'store' => [
                'id' => $shift->store->id,
                'name' => $shift->store->name,
                'code' => $shift->store->code,
            ],
            'status' => $shift->status,
            'starting_cash' => $shift->starting_cash,
            'notes' => $shift->notes,
            'started_at' => $shift->created_at,
        ];

        return response()->json([
            'success' => true,
            'message' => 'Shift started successfully',
            'data' => $shiftData
        ], 201);
    }

    /**
     * End the current shift for the authenticated user.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function endShift(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ending_cash' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();
        $currentShift = $user->currentStoreShift();

        if (!$currentShift) {
            return response()->json([
                'success' => false,
                'message' => 'No active shift found'
            ], 404);
        }

        // Calculate shift summary
        $orders = $currentShift->orders;
        $payments = $currentShift->payments;

        $summary = [
            'orders_count' => $orders->count(),
            'completed_orders' => $orders->where('status', 'completed')->count(),
            'total_sales' => $orders->where('status', 'completed')->sum('total_price'),
            'payments_count' => $payments->count(),
            'total_payments' => $payments->sum('amount'),
            'cash_payments' => $payments->where('payment_method', 'cash')->sum('amount'),
            'card_payments' => $payments->where('payment_method', 'card')->sum('amount'),
            'upi_payments' => $payments->where('payment_method', 'upi')->sum('amount'),
            'other_payments' => $payments->whereNotIn('payment_method', ['cash', 'card', 'upi'])->sum('amount'),
        ];

        // Update shift
        $currentShift->update([
            'status' => 'closed',
            'ending_cash' => $request->ending_cash ?? 0,
            'notes' => $request->notes ?? $currentShift->notes,
        ]);

        $shiftData = [
            'id' => $currentShift->id,
            'store' => [
                'id' => $currentShift->store->id,
                'name' => $currentShift->store->name,
                'code' => $currentShift->store->code,
            ],
            'status' => $currentShift->status,
            'starting_cash' => $currentShift->starting_cash,
            'ending_cash' => $currentShift->ending_cash,
            'notes' => $currentShift->notes,
            'started_at' => $currentShift->created_at,
            'ended_at' => $currentShift->updated_at,
            'summary' => $summary,
        ];

        return response()->json([
            'success' => true,
            'message' => 'Shift ended successfully',
            'data' => $shiftData
        ]);
    }

    /**
     * Get the authenticated user's shift history.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function shiftHistory(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'status' => 'nullable|in:active,closed',
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
        $query = StoreCashierShift::with(['store', 'orders', 'payments'])
            ->where('user_id', $user->id);

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $perPage = $request->per_page ?? 15;
        $shifts = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'shifts' => $shifts->items()->map(function ($shift) {
                    $orders = $shift->orders;
                    $payments = $shift->payments;

                    return [
                        'id' => $shift->id,
                        'store' => [
                            'id' => $shift->store->id,
                            'name' => $shift->store->name,
                            'code' => $shift->store->code,
                        ],
                        'status' => $shift->status,
                        'starting_cash' => $shift->starting_cash,
                        'ending_cash' => $shift->ending_cash,
                        'notes' => $shift->notes,
                        'orders_count' => $orders->count(),
                        'completed_orders' => $orders->where('status', 'completed')->count(),
                        'total_sales' => $orders->where('status', 'completed')->sum('total_price'),
                        'payments_count' => $payments->count(),
                        'total_payments' => $payments->sum('amount'),
                        'created_at' => $shift->created_at,
                        'updated_at' => $shift->updated_at,
                    ];
                }),
                'pagination' => [
                    'current_page' => $shifts->currentPage(),
                    'last_page' => $shifts->lastPage(),
                    'per_page' => $shifts->perPage(),
                    'total' => $shifts->total(),
                    'from' => $shifts->firstItem(),
                    'to' => $shifts->lastItem(),
                ]
            ]
        ]);
    }
}