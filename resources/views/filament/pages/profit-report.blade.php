<x-filament-panels::page>
    <form wire:submit="filterForm">
        {{ $this->filterForm }}
    </form>

    <div class="space-y-6">
        <!-- Summary Stats -->
        @php
            $stats = $this->getSummaryStats();
        @endphp
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="text-sm font-medium text-gray-500">Total Revenue</div>
                <div class="text-2xl font-bold text-gray-900">₹{{ number_format($stats['total_revenue'], 2) }}</div>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <div class="text-sm font-medium text-gray-500">Total Cost</div>
                <div class="text-2xl font-bold text-gray-900">₹{{ number_format($stats['total_cost'], 2) }}</div>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <div class="text-sm font-medium text-gray-500">Total Profit</div>
                <div class="text-2xl font-bold {{ $stats['total_profit'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                    ₹{{ number_format($stats['total_profit'], 2) }}
                </div>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <div class="text-sm font-medium text-gray-500">Profit Margin</div>
                <div class="text-2xl font-bold {{ $stats['profit_margin'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                    {{ number_format($stats['profit_margin'], 2) }}%
                </div>
            </div>
        </div>

        <!-- Charts -->
        @php
            $chartData = $this->getChartData();
        @endphp
        
        @if($this->filterData['view_type'] === 'category')
            <!-- Category Profit Chart -->
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Profit by Category</h3>
                <div class="h-80">
                    <canvas id="categoryChart"></canvas>
                </div>
            </div>
        @else
            <!-- Daily Profit Trend Chart -->
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Daily Profit Trend</h3>
                <div class="h-80">
                    <canvas id="dailyChart"></canvas>
                </div>
            </div>
        @endif

        <!-- Data Tables -->
        @if($this->filterData['view_type'] === 'summary' || $this->filterData['view_type'] === 'detailed')
            <!-- Product Profit Table -->
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Product Profit Details</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Revenue</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Cost</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Profit</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Margin</th>
                                @if($this->filterData['view_type'] === 'detailed')
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Avg Price</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @php
                                $profitData = $this->getProfitData();
                            @endphp
                            @foreach($profitData as $item)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $item->product_name }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $item->category_name }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">{{ $item->total_quantity }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">₹{{ number_format($item->total_revenue, 2) }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">₹{{ number_format($item->total_cost, 2) }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium {{ $item->profit >= 0 ? 'text-green-600' : 'text-red-600' }} text-right">
                                        ₹{{ number_format($item->profit, 2) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium {{ $item->profit_margin >= 0 ? 'text-green-600' : 'text-red-600' }} text-right">
                                        {{ number_format($item->profit_margin, 2) }}%
                                    </td>
                                    @if($this->filterData['view_type'] === 'detailed')
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">₹{{ number_format($item->avg_selling_price, 2) }}</td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        @if($this->filterData['view_type'] === 'category')
            <!-- Category Profit Table -->
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Category Profit Details</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Products</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Revenue</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Cost</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Profit</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Margin</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @php
                                $categoryData = $this->getCategoryProfitData();
                            @endphp
                            @foreach($categoryData as $item)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $item->category_name }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">{{ $item->product_count }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">{{ $item->total_quantity }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">₹{{ number_format($item->total_revenue, 2) }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">₹{{ number_format($item->total_cost, 2) }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium {{ $item->profit >= 0 ? 'text-green-600' : 'text-red-600' }} text-right">
                                        ₹{{ number_format($item->profit, 2) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium {{ $item->profit_margin >= 0 ? 'text-green-600' : 'text-red-600' }} text-right">
                                        {{ number_format($item->profit_margin, 2) }}%
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <!-- Daily Profit Trend Table -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Daily Profit Trend</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Revenue</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Cost</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Profit</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Margin</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @php
                            $dailyData = $this->getDailyProfitData();
                        @endphp
                        @foreach($dailyData as $item)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ \Carbon\Carbon::parse($item->date)->format('M d, Y') }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">{{ $item->total_quantity }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">₹{{ number_format($item->total_revenue, 2) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">₹{{ number_format($item->total_cost, 2) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium {{ $item->profit >= 0 ? 'text-green-600' : 'text-red-600' }} text-right">
                                    ₹{{ number_format($item->profit, 2) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium {{ $item->profit_margin >= 0 ? 'text-green-600' : 'text-red-600' }} text-right">
                                    {{ number_format($item->profit_margin, 2) }}%
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                @if($this->filterData['view_type'] === 'category')
                    // Category Profit Chart
                    const categoryCtx = document.getElementById('categoryChart').getContext('2d');
                    new Chart(categoryCtx, {
                        type: 'doughnut',
                        data: {
                            labels: @json($chartData['category_chart']['labels']),
                            datasets: [{
                                label: 'Profit by Category',
                                data: @json($chartData['category_chart']['profit_data']),
                                backgroundColor: [
                                    '#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6',
                                    '#EC4899', '#14B8A6', '#F97316', '#06B6D4', '#84CC16'
                                ],
                                borderWidth: 1
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'right',
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            const label = context.label || '';
                                            const value = '₹' + parseFloat(context.raw).toFixed(2);
                                            return label + ': ' + value;
                                        }
                                    }
                                }
                            }
                        }
                    });
                @else
                    // Daily Profit Trend Chart
                    const dailyCtx = document.getElementById('dailyChart').getContext('2d');
                    new Chart(dailyCtx, {
                        type: 'line',
                        data: {
                            labels: @json($chartData['daily_chart']['labels']),
                            datasets: [
                                {
                                    label: 'Revenue',
                                    data: @json($chartData['daily_chart']['revenue_data']),
                                    borderColor: '#3B82F6',
                                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                                    tension: 0.1
                                },
                                {
                                    label: 'Cost',
                                    data: @json($chartData['daily_chart']['cost_data']),
                                    borderColor: '#EF4444',
                                    backgroundColor: 'rgba(239, 68, 68, 0.1)',
                                    tension: 0.1
                                },
                                {
                                    label: 'Profit',
                                    data: @json($chartData['daily_chart']['profit_data']),
                                    borderColor: '#10B981',
                                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                                    tension: 0.1
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'top',
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            let label = context.dataset.label || '';
                                            if (label) {
                                                label += ': ';
                                            }
                                            label += '₹' + parseFloat(context.raw).toFixed(2);
                                            return label;
                                        }
                                    }
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        callback: function(value) {
                                            return '₹' + value;
                                        }
                                    }
                                }
                            }
                        }
                    });
                @endif
            });
        </script>
    @endpush
</x-filament-panels::page>