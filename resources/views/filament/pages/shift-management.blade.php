<x-filament-panels::page>
    <div class="space-y-6">
        @if($currentShift)
            <!-- Current Shift Status -->
            <div class="bg-white rounded-lg shadow p-6 border border-green-200">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-green-600">Current Shift Active</h3>
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                        <svg class="w-2 h-2 mr-1" fill="currentColor" viewBox="0 0 8 8">
                            <circle cx="4" cy="4" r="3"/>
                        </svg>
                        Open
                    </span>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <p class="text-sm text-gray-500">Started</p>
                        <p class="text-lg font-medium">{{ $currentShift->start_time->format('g:i A') }}</p>
                        <p class="text-xs text-gray-400">{{ $currentShift->start_time->diffForHumans() }}</p>
                    </div>
                    
                    <div>
                        <p class="text-sm text-gray-500">Duration</p>
                        <p class="text-lg font-medium">{{ $currentShift->duration }}</p>
                    </div>
                    
                    <div>
                        <p class="text-sm text-gray-500">Total Sales</p>
                        <p class="text-lg font-medium">₹{{ number_format($currentShift->total_sales, 2) }}</p>
                        <p class="text-xs text-gray-400">{{ $currentShift->orders()->count() }} orders</p>
                    </div>
                    
                    <div>
                        <p class="text-sm text-gray-500">Cash Collected</p>
                        <p class="text-lg font-medium">₹{{ number_format($currentShift->cash_collected, 2) }}</p>
                        <p class="text-xs text-gray-400">Expected: ₹{{ number_format($currentShift->expected_cash, 2) }}</p>
                    </div>
                </div>
                
                @if(!empty($currentShift->notes))
                    <div class="mt-4 p-3 bg-gray-50 rounded">
                        <p class="text-sm text-gray-600"><strong>Notes:</strong> {{ $currentShift->notes }}</p>
                    </div>
                @endif
            </div>
        @else
            <!-- No Active Shift -->
            <div class="bg-white rounded-lg shadow p-6 border border-gray-200">
                <div class="text-center py-8">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No Active Shift</h3>
                    <p class="mt-1 text-sm text-gray-500">Get started by opening a new shift to begin taking orders.</p>
                    <div class="mt-6">
                        {{ $this->getFormAction('open_shift') }}
                    </div>
                </div>
            </div>
        @endif
        
        <!-- Quick Actions -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @if(!$currentShift)
                <div class="bg-white rounded-lg shadow p-6 border border-blue-200">
                    <h3 class="text-lg font-semibold text-blue-600 mb-2">Open New Shift</h3>
                    <p class="text-sm text-gray-600 mb-4">Start a new shift by counting opening cash and entering details.</p>
                    {{ $this->getFormAction('open_shift') }}
                </div>
            @else
                <div class="bg-white rounded-lg shadow p-6 border border-red-200">
                    <h3 class="text-lg font-semibold text-red-600 mb-2">Close Current Shift</h3>
                    <p class="text-sm text-gray-600 mb-4">End your shift by reconciling cash and reviewing sales summary.</p>
                    {{ $this->getFormAction('close_shift') }}
                </div>
            @endif
            
            <div class="bg-white rounded-lg shadow p-6 border border-gray-200">
                <h3 class="text-lg font-semibold text-gray-600 mb-2">Shift History</h3>
                <p class="text-sm text-gray-600 mb-4">View your previous shifts and sales performance.</p>
                <a href="{{ route('filament.admin.resources.cashier-shifts.index') }}" 
                   class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-gray-600 hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                    View History
                </a>
            </div>
        </div>
        
        <!-- Recent Orders (if shift is active) -->
        @if($currentShift && $currentShift->orders()->count() > 0)
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Recent Orders in This Shift</h3>
                <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
                    <table class="min-w-full divide-y divide-gray-300">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Order ID
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Customer
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Total
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Time
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($currentShift->orders()->latest()->take(5)->get() as $order)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        #{{ $order->id }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ $order->getCustomerName() }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        ₹{{ number_format($order->total_price, 2) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ $order->created_at->format('g:i A') }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                
                @if($currentShift->orders()->count() > 5)
                    <div class="mt-4 text-center">
                        <a href="{{ route('filament.admin.resources.orders.index') }}?cashier_shift_id={{ $currentShift->id }}" 
                           class="text-sm text-blue-600 hover:text-blue-500">
                            View all {{ $currentShift->orders()->count() }} orders in this shift →
                        </a>
                    </div>
                @endif
            </div>
        @endif
    </div>
</x-filament-panels::page>