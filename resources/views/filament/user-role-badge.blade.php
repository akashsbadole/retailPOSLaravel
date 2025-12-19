@if($user)
    <div class="px-3 py-2 mb-2">
        <div class="flex items-center gap-2">
            <span class="text-sm font-medium text-gray-600">Role:</span>
            @foreach($user->roles as $role)
                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium
                    {{ match($role->name) {
                        'Admin' => 'bg-red-100 text-red-800',
                        'Manager' => 'bg-yellow-100 text-yellow-800',
                        'Cashier' => 'bg-green-100 text-green-800',
                        default => 'bg-gray-100 text-gray-800'
                    } }}">
                    {{ $role->name }}
                </span>
            @endforeach
        </div>
    </div>
@endif