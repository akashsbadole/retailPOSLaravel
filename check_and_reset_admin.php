<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

echo "=== Checking Current Users ===\n";

// Get all users
$users = User::all();
echo "Total users found: " . $users->count() . "\n\n";

foreach ($users as $user) {
    echo "ID: {$user->id}\n";
    echo "Name: {$user->name}\n";
    echo "Email: {$user->email}\n";
    echo "Roles: " . $user->getRoleNames()->implode(', ') . "\n";
    echo "Store ID: " . ($user->store_id ?? 'None') . "\n";
    echo "API Access: " . ($user->api_access ? 'Yes' : 'No') . "\n";
    echo "------------------------\n";
}

echo "\n=== Resetting Admin Password ===\n";

// Find or create admin user with supermart.com domain
$adminUser = User::firstOrCreate(
    ['email' => 'admin@supermart.com'],
    [
        'name' => 'Super Admin',
        'password' => Hash::make('admin123'),
    ]
);

// Ensure admin role exists and is assigned
$adminRole = Role::firstOrCreate(['name' => 'Admin']);
if (!$adminUser->hasRole('Admin')) {
    $adminUser->assignRole('Admin');
}

// Update password to ensure it works
$adminUser->password = Hash::make('admin123');
$adminUser->save();

echo "Admin user updated successfully!\n";
echo "Email: admin@supermart.com\n";
echo "Password: admin123\n";
echo "Role: Admin\n";

// Also create/update manager and cashier users with consistent passwords
echo "\n=== Updating Other Users ===\n";

$managerUser = User::firstOrCreate(
    ['email' => 'manager@supermart.com'],
    [
        'name' => 'Store Manager',
        'password' => Hash::make('manager123'),
    ]
);

$managerRole = Role::firstOrCreate(['name' => 'Manager']);
if (!$managerUser->hasRole('Manager')) {
    $managerUser->assignRole('Manager');
}
$managerUser->password = Hash::make('manager123');
$managerUser->save();

echo "Manager user updated!\n";
echo "Email: manager@supermart.com\n";
echo "Password: manager123\n";

$cashierUser = User::firstOrCreate(
    ['email' => 'cashier@supermart.com'],
    [
        'name' => 'Store Cashier',
        'password' => Hash::make('cashier123'),
    ]
);

$cashierRole = Role::firstOrCreate(['name' => 'Cashier']);
if (!$cashierUser->hasRole('Cashier')) {
    $cashierUser->assignRole('Cashier');
}
$cashierUser->password = Hash::make('cashier123');
$cashierUser->save();

echo "Cashier user updated!\n";
echo "Email: cashier@supermart.com\n";
echo "Password: cashier123\n";

echo "\n=== All Done! ===\n";
echo "You can now login with these credentials:\n";
echo "Admin: admin@supermart.com / admin123\n";
echo "Manager: manager@supermart.com / manager123\n";
echo "Cashier: cashier@supermart.com / cashier123\n";