<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Create permissions
        $permissions = [
            // Product permissions
            'product.view',
            'product.create',
            'product.edit',
            'product.delete',
            
            // Discount permissions
            'discount.full',
            'discount.limited',
            'discount.none',
            
            // Stock management permissions
            'stock.adjust',
            'stock.view',
            
            // Stock adjustment permissions
            'stock-adjustment.view',
            'stock-adjustment.create',
            'stock-adjustment.edit',
            'stock-adjustment.delete',
            'stock-adjustment.approve',
            
            // Cashier shift permissions
            'cashier-shift.view',
            'cashier-shift.create',
            'cashier-shift.edit',
            'cashier-shift.delete',
            'cashier-shift.close',
            'cashier-shift.report',
            
            // Sales Return permissions
            'view_any_sales_return',
            'view_sales_return',
            'create_sales_return',
            'update_sales_return',
            'delete_sales_return',
            'delete_any_sales_return',
            'approve_sales_return',
            'reject_sales_return',
            'process_sales_return',
            'complete_sales_return',
            'return.process',
            'return.view',
            'view_return_reports',
            
            // Report permissions
            'report.view',
            'report.full',
            'report.limited',
            
            // User management permissions
            'user.view',
            'user.create',
            'user.edit',
            'user.delete',
            'user.manage-roles',
            
            // Customer management permissions
            'customer.view',
            'customer.create',
            'customer.edit',
            'customer.delete',
            
            // Order management permissions
            'order.view',
            'order.create',
            'order.edit',
            'order.delete',
            
            // Settings permissions
            'settings.view',
            'settings.edit',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Create roles
        $adminRole = Role::firstOrCreate(['name' => 'Admin']);
        $managerRole = Role::firstOrCreate(['name' => 'Manager']);
        $cashierRole = Role::firstOrCreate(['name' => 'Cashier']);

        // Assign permissions to Admin role (full access)
        $adminRole->givePermissionTo(Permission::all());

        // Assign permissions to Manager role
        $managerRole->givePermissionTo([
            'product.view',
            'product.create',
            'product.edit',
            'discount.full',
            'stock.adjust',
            'stock.view',
            'stock-adjustment.view',
            'stock-adjustment.create',
            'stock-adjustment.edit',
            'stock-adjustment.approve',
            'cashier-shift.view',
            'cashier-shift.create',
            'cashier-shift.edit',
            'cashier-shift.delete',
            'cashier-shift.close',
            'cashier-shift.report',
            'view_any_sales_return',
            'view_sales_return',
            'create_sales_return',
            'update_sales_return',
            'delete_sales_return',
            'delete_any_sales_return',
            'approve_sales_return',
            'reject_sales_return',
            'process_sales_return',
            'complete_sales_return',
            'return.process',
            'return.view',
            'view_return_reports',
            'report.full',
            'customer.view',
            'customer.create',
            'customer.edit',
            'order.view',
            'order.create',
            'order.edit',
            'settings.view',
        ]);

        // Assign permissions to Cashier role
        $cashierRole->givePermissionTo([
            'product.view',
            'discount.limited',
            'stock.view',
            'stock-adjustment.view',
            'cashier-shift.view',
            'view_any_sales_return',
            'view_sales_return',
            'return.view',
            'report.limited',
            'customer.view',
            'customer.create',
            'order.view',
            'order.create',
        ]);

        // Create admin user and assign Admin role
        $adminUser = User::firstOrCreate(
            ['email' => 'admin@supermart.com'],
            [
                'name' => 'Super Admin',
                'password' => bcrypt('password'),
            ]
        );
        $adminUser->assignRole('Admin');

        // Create a manager user and assign Manager role
        $managerUser = User::firstOrCreate(
            ['email' => 'manager@supermart.com'],
            [
                'name' => 'Store Manager',
                'password' => bcrypt('password'),
            ]
        );
        $managerUser->assignRole('Manager');

        // Create a cashier user and assign Cashier role
        $cashierUser = User::firstOrCreate(
            ['email' => 'cashier@supermart.com'],
            [
                'name' => 'Store Cashier',
                'password' => bcrypt('password'),
            ]
        );
        $cashierUser->assignRole('Cashier');
    }
}