@php
    use App\Models\Store;
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Support\Facades\Session;
    
    $currentStoreId = Session::get('active_store_id', Auth::user()?->store_id);
    $stores = [];
    
    if (Auth::user() && Auth::user()->hasRole('super_admin')) {
        $stores = Store::active()->pluck('name', 'id')->toArray();
    } elseif (Auth::check() && Auth::user()->store_id) {
        $userStore = Store::find(Auth::user()->store_id);
        if ($userStore) {
            $stores = [$userStore->id => $userStore->name];
        }
    }
@endphp

@if(!empty($stores))
<div x-data="{
    currentStore: @json($currentStoreId),
    stores: @json($stores),
    switchStore(storeId) {
        this.currentStore = storeId;
        fetch('{{ route("filament.admin.stores.switch") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({ store_id: storeId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.reload();
            }
        })
        .catch(error => console.error('Error:', error));
    }
}" class="filament-forms-select-component">
    <label class="filament-forms-select-label text-sm font-medium leading-5 text-gray-700 dark:text-gray-300">
        Store
    </label>
    <select 
        x-model="currentStore"
        @change="switchStore($event.target.value)"
        class="filament-forms-select-input w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:focus:border-indigo-400 dark:focus:ring-indigo-400 sm:text-sm"
    >
        <option value="">Select a store</option>
        @foreach($stores as $id => $name)
            <option value="{{ $id }}" {{ ($currentStoreId == $id) ? 'selected' : '' }}>
                {{ $name }}
            </option>
        @endforeach
    </select>
</div>
@endif