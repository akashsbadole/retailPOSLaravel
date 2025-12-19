<?php

namespace App\Http\Controllers;

use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Filament\Notifications\Notification;

class StoreController extends Controller
{
    public function switch(Request $request)
    {
        $storeId = $request->input('store_id');
        
        if (!$storeId) {
            return response()->json(['success' => false, 'message' => 'Store ID is required']);
        }
        
        // Validate that the user can access this store
        $user = Auth::user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User not authenticated']);
        }
        
        // Check if user has access to this store
        if ($user->hasRole('super_admin')) {
            // Super admin can access any store
            $store = Store::find($storeId);
        } elseif ($user->store_id == $storeId) {
            // User can access their assigned store
            $store = Store::find($storeId);
        } else {
            return response()->json(['success' => false, 'message' => 'Access denied']);
        }
        
        if (!$store) {
            return response()->json(['success' => false, 'message' => 'Store not found']);
        }
        
        // Update session
        Session::put('active_store_id', $storeId);
        
        return response()->json([
            'success' => true, 
            'message' => 'Switched to ' . $store->name
        ]);
    }
}