<x-filament-panels::page>
    <form wire:submit="generateReport">
        {{ $this->form }}
        
        <div class="mt-4">
            <x-filament::button type="submit" icon="heroicon-o-chart-bar">
                Generate Report
            </x-filament::button>
        </div>
    </form>

    @if($this->data)
        <div class="mt-8">
            <!-- Statistics Overview -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Total Returns</h3>
                    <p class="text-3xl font-bold text-blue-600">{{ $this->getStatistics()['total_returns'] }}</p>
                </div>
                
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Pending Returns</h3>
                    <p class="text-3xl font-bold text-yellow-600">{{ $this->getStatistics()['pending_returns'] }}</p>
                </div>
                
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Total Refund Amount</h3>
                    <p class="text-3xl font-bold text-red-600">{{ config('settings.currency_symbol', '$') . number_format($this->getStatistics()['total_refund_amount'], 2) }}</p>
                </div>
                
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Average Refund</h3>
                    <p class="text-3xl font-bold text-green-600">{{ config('settings.currency_symbol', '$') . number_format($this->getStatistics()['average_refund_amount'], 2) }}</p>
                </div>
            </div>

            <!-- Return Reasons Statistics -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Return Reasons</h3>
                    <div class="space-y-2">
                        @foreach($this->getReasonStatistics() as $reason => $stats)
                            <div class="flex justify-between items-center">
                                <span class="text-gray-700">{{ ucfirst(str_replace('_', ' ', $reason)) }}</span>
                                <div class="flex items-center space-x-2">
                                    <span class="text-gray-600">{{ $stats['count'] }} ({{ number_format($stats['percentage'], 1) }}%)</span>
                                    <span class="text-gray-500">{{ config('settings.currency_symbol', '$') . number_format($stats['total_amount'], 2) }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- Top Returned Products -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Top Returned Products</h3>
                    <div class="space-y-2">
                        @foreach(array_slice($this->getProductStatistics(), 0, 5) as $product)
                            <div class="flex justify-between items-center">
                                <div>
                                    <span class="text-gray-700">{{ $product['product_name'] }}</span>
                                    <span class="text-gray-500 text-sm ml-2">({{ $product['product_sku'] }})</span>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <span class="text-gray-600">{{ $product['total_quantity'] }} units</span>
                                    <span class="text-gray-500">{{ config('settings.currency_symbol', '$') . number_format($product['total_amount'], 2) }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Returns Table -->
            <div class="bg-white rounded-lg shadow">
                <h3 class="text-lg font-semibold text-gray-900 p-6 pb-4">Returns Details</h3>
                {{ $this->table }}
            </div>
        </div>
    @endif
</x-filament-panels::page>