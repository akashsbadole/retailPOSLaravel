<?php

use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Checking Permissions...\n";

// Check Permissions
$permissions = Permission::all()->pluck('name')->toArray();
echo "Total Permissions: " . count($permissions) . "\n";
if (in_array('order.create', $permissions)) {
    echo "Permission 'order.create' EXISTS.\n";
} else {
    echo "Permission 'order.create' MISSING.\n";
}

// Check Roles
$roles = Role::all();
echo "Roles: " . $roles->pluck('name')->implode(', ') . "\n";

foreach ($roles as $role) {
    echo "Role '{$role->name}' permissions: " . $role->permissions->count() . "\n";
}

// Check Users
$users = User::all();
echo "Total Users: " . $users->count() . "\n";

foreach ($users as $user) {
    echo "User: {$user->email} ({$user->name})\n";
    $userRoles = $user->getRoleNames();
    echo "  Roles: " . $userRoles->implode(', ') . "\n";

    if ($user->can('order.create')) {
        echo "  Can create order: YES\n";
    } else {
        echo "  Can create order: NO\n";
    }
}
