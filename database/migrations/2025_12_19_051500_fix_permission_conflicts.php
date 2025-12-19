<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, let's clean up existing permission assignments
        DB::table('model_has_permissions')->delete();
        
        // Define clear permission hierarchy
        $permissions = [
            // User Management
            'user.view' => 'View users',
            'user.create' => 'Create users',
            'user.edit' => 'Edit users',
            'user.delete' => 'Delete users',
            'user.manage-roles' => 'Manage user roles',
            
            // Product Management
            'product.view' => 'View products',
            'product.create' => 'Create products',
            'product.edit' => 'Edit products',
            'product.delete' => 'Delete products',
            'product.view-cost' => 'View product cost price',
            
            // Order Management
            'order.view' => 'View orders',
            'order.create' => 'Create orders',
            'order.edit' => 'Edit orders',
            'order.delete' => 'Delete orders',
            
            // Discount Permissions (hierarchical)
            'discount.none' => 'No discount access',
            'discount.limited' => 'Limited discount (up to 10%)',
            'discount.full' => 'Full discount access (up to 50%)',
            
            // Stock Management
            'stock.view' => 'View stock levels',
            'stock.adjust' => 'Adjust stock levels',
            'stock.approve-adjustments' => 'Approve stock adjustments',
            
            // Shift Management
            'shift.view' => 'View shifts',
            'shift.open' => 'Open shifts',
            'shift.close' => 'Close shifts',
            'shift.manage' => 'Manage all shifts',
            
            // Sales Returns
            'returns.view' => 'View returns',
            'returns.create' => 'Create returns',
            'returns.approve' => 'Approve returns',
            'returns.process' => 'Process returns',
            
            // Reports
            'reports.view' => 'View reports',
            'reports.export' => 'Export reports',
            
            // Settings
            'settings.view' => 'View settings',
            'settings.edit' => 'Edit settings',
        ];
        
        // Insert or update permissions with descriptions
        foreach ($permissions as $name => $description) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $name],
                ['name' => $name, 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]
            );
        }
        
        // Define role permissions with clear hierarchy
        $rolePermissions = [
            'Admin' => [
                'user.view', 'user.create', 'user.edit', 'user.delete', 'user.manage-roles',
                'product.view', 'product.create', 'product.edit', 'product.delete', 'product.view-cost',
                'order.view', 'order.create', 'order.edit', 'order.delete',
                'discount.full', // Admin gets full discount access
                'stock.view', 'stock.adjust', 'stock.approve-adjustments',
                'shift.view', 'shift.open', 'shift.close', 'shift.manage',
                'returns.view', 'returns.create', 'returns.approve', 'returns.process',
                'reports.view', 'reports.export',
                'settings.view', 'settings.edit',
            ],
            'Manager' => [
                'user.view', 'user.edit',
                'product.view', 'product.create', 'product.edit', 'product.view-cost',
                'order.view', 'order.create', 'order.edit',
                'discount.full', // Manager gets full discount access
                'stock.view', 'stock.adjust', 'stock.approve-adjustments',
                'shift.view', 'shift.manage',
                'returns.view', 'returns.create', 'returns.approve', 'returns.process',
                'reports.view', 'reports.export',
                'settings.view',
            ],
            'Cashier' => [
                'product.view',
                'order.view', 'order.create',
                'discount.limited', // Cashier gets limited discount access
                'stock.view',
                'shift.view', 'shift.open', 'shift.close',
                'returns.view', 'returns.create',
            ],
        ];
        
        // Assign permissions to roles
        foreach ($rolePermissions as $roleName => $permissions) {
            $role = DB::table('roles')->where('name', $roleName)->first();
            if ($role) {
                foreach ($permissions as $permissionName) {
                    $permission = DB::table('permissions')->where('name', $permissionName)->first();
                    if ($permission) {
                        DB::table('role_has_permissions')->updateOrInsert(
                            ['role_id' => $role->id, 'permission_id' => $permission->id],
                            ['role_id' => $role->id, 'permission_id' => $permission->id]
                        );
                    }
                }
            }
        }
        
        // Remove conflicting discount permissions
        DB::table('role_has_permissions')
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('permissions')
                    ->whereColumn('permissions.id', 'role_has_permissions.permission_id')
                    ->whereIn('name', ['discount.none', 'discount.limited', 'discount.full']);
            })
            ->delete();
            
        // Re-assign only the correct discount permissions
        $discountAssignments = [
            'Admin' => 'discount.full',
            'Manager' => 'discount.full', 
            'Cashier' => 'discount.limited',
        ];
        
        foreach ($discountAssignments as $roleName => $permissionName) {
            $role = DB::table('roles')->where('name', $roleName)->first();
            $permission = DB::table('permissions')->where('name', $permissionName)->first();
            
            if ($role && $permission) {
                DB::table('role_has_permissions')->insert([
                    'role_id' => $role->id,
                    'permission_id' => $permission->id,
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Clean up all permission assignments
        DB::table('model_has_permissions')->delete();
        DB::table('role_has_permissions')->delete();
        
        // Remove all permissions we created
        DB::table('permissions')->whereIn('name', [
            'user.view', 'user.create', 'user.edit', 'user.delete', 'user.manage-roles',
            'product.view', 'product.create', 'product.edit', 'product.delete', 'product.view-cost',
            'order.view', 'order.create', 'order.edit', 'order.delete',
            'discount.none', 'discount.limited', 'discount.full',
            'stock.view', 'stock.adjust', 'stock.approve-adjustments',
            'shift.view', 'shift.open', 'shift.close', 'shift.manage',
            'returns.view', 'returns.create', 'returns.approve', 'returns.process',
            'reports.view', 'reports.export',
            'settings.view', 'settings.edit',
        ])->delete();
    }
};