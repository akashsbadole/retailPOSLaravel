<?php

namespace App\Filament\Pages;

use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Order;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\Summarizers\Sum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Illuminate\Support\Number;

class ProfitReport extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-currency-dollar';

    // protected static string|\UnitEnum|null $navigationGroup = 'Reports';

    protected string $view = 'filament.pages.profit-report';

    protected static ?string $navigationLabel = 'Profit Report';

    protected static ?string $title = 'Profit Report';

    protected static ?int $navigationSort = 5;

    public array $filterData = [];

    public function mount(): void
    {
        $this->filterData = [
            'date_range' => 'this_month',
            'start_date' => null,
            'end_date' => null,
        ];
    }

    public function filterForm(): Form
    {
        return Forms\Form::make([
            Section::make('Filter Report')
                ->schema([
                    Grid::make(2)->schema([
                        Select::make('date_range')
                            ->label('Date Range')
                            ->options([
                                'today' => 'Today',
                                'yesterday' => 'Yesterday',
                                'this_week' => 'This Week',
                                'last_week' => 'Last Week',
                                'this_month' => 'This Month',
                                'last_month' => 'Last Month',
                                'custom' => 'Custom Range',
                            ])
                            ->default('this_month')
                            ->reactive()
                            ->afterStateUpdated(
                                fn($state, callable $set) =>
                                $set('custom_date_range', $state === 'custom' ? now()->format('Y-m-d') : null)
                            ),

                        Select::make('view_type')
                            ->label('View Type')
                            ->options([
                                'summary' => 'Summary View',
                                'detailed' => 'Detailed View',
                                'category' => 'By Category',
                            ])
                            ->default('summary'),
                    ]),

                    Grid::make(2)->schema([
                        DatePicker::make('start_date')
                            ->label('Start Date')
                            ->visible(fn(callable $get) => $get('date_range') === 'custom')
                            ->required(fn(callable $get) => $get('date_range') === 'custom'),

                        DatePicker::make('end_date')
                            ->label('End Date')
                            ->visible(fn(callable $get) => $get('date_range') === 'custom')
                            ->required(fn(callable $get) => $get('date_range') === 'custom'),
                    ]),
                ]),
        ]);
    }

    protected function getDateRange(): array
    {
        switch ($this->filterData['date_range']) {
            case 'today':
                return [today(), today()];
            case 'yesterday':
                return [yesterday(), yesterday()];
            case 'this_week':
                return [now()->startOfWeek(), now()->endOfWeek()];
            case 'last_week':
                return [now()->subWeek()->startOfWeek(), now()->subWeek()->endOfWeek()];
            case 'this_month':
                return [now()->startOfMonth(), now()->endOfMonth()];
            case 'last_month':
                return [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()];
            case 'custom':
                return [
                    $this->filterData['start_date'] ? \Carbon\Carbon::parse($this->filterData['start_date']) : now()->startOfMonth(),
                    $this->filterData['end_date'] ? \Carbon\Carbon::parse($this->filterData['end_date']) : now()->endOfMonth()
                ];
            default:
                return [now()->startOfMonth(), now()->endOfMonth()];
        }
    }

    public function getProfitData(): \Illuminate\Support\Collection
    {
        [$startDate, $endDate] = $this->getDateRange();
        $viewType = $this->filterData['view_type'] ?? 'summary';

        $query = OrderItem::with(['product'])
            ->selectRaw('
                order_items.product_id,
                order_items.name as product_name,
                SUM(order_items.quantity) as total_quantity,
                SUM(order_items.price * order_items.quantity) as total_revenue,
                AVG(order_items.price) as avg_selling_price,
                COUNT(DISTINCT order_items.order_id) as order_count,
                products.cost_price
            ')
            ->leftJoin('products', 'order_items.product_id', '=', 'products.id')
            ->whereHas('order', function ($query) use ($startDate, $endDate) {
                $query->whereBetween('created_at', [$startDate, $endDate]);
            })
            ->groupBy('order_items.product_id', 'order_items.name', 'products.cost_price');

        $results = $query->get();

        // Calculate profit for each item
        return $results->map(function ($item) {
            $costPrice = $item->cost_price ?? 0;
            $totalCost = $costPrice * $item->total_quantity;
            $profit = $item->total_revenue - $totalCost;
            $profitMargin = $item->total_revenue > 0 ? ($profit / $item->total_revenue) * 100 : 0;

            return (object) [
                'product_id' => $item->product_id,
                'product_name' => $item->product_name,
                'category_name' => $item->category_name ?? 'Uncategorized',
                'total_quantity' => $item->total_quantity,
                'total_revenue' => $item->total_revenue,
                'avg_selling_price' => $item->avg_selling_price,
                'cost_price' => $costPrice,
                'total_cost' => $totalCost,
                'profit' => $profit,
                'profit_margin' => $profitMargin,
                'order_count' => $item->order_count,
            ];
        });
    }

    public function getDailyProfitData(): \Illuminate\Support\Collection
    {
        [$startDate, $endDate] = $this->getDateRange();

        return OrderItem::with(['product'])
            ->selectRaw('
                DATE(orders.created_at) as date,
                SUM(order_items.quantity) as total_quantity,
                SUM(order_items.price * order_items.quantity) as total_revenue,
                SUM(products.cost_price * order_items.quantity) as total_cost
            ')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->leftJoin('products', 'order_items.product_id', '=', 'products.id')
            ->whereBetween('orders.created_at', [$startDate, $endDate])
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(function ($item) {
                $profit = $item->total_revenue - $item->total_cost;
                $profitMargin = $item->total_revenue > 0 ? ($profit / $item->total_revenue) * 100 : 0;

                return (object) [
                    'date' => $item->date,
                    'total_quantity' => $item->total_quantity,
                    'total_revenue' => $item->total_revenue,
                    'total_cost' => $item->total_cost,
                    'profit' => $profit,
                    'profit_margin' => $profitMargin,
                ];
            });
    }

    public function getMonthlyProfitData(): \Illuminate\Support\Collection
    {
        [$startDate, $endDate] = $this->getDateRange();

        return OrderItem::with(['product'])
            ->selectRaw('
                YEAR(orders.created_at) as year,
                MONTH(orders.created_at) as month,
                SUM(order_items.quantity) as total_quantity,
                SUM(order_items.price * order_items.quantity) as total_revenue,
                SUM(products.cost_price * order_items.quantity) as total_cost
            ')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->leftJoin('products', 'order_items.product_id', '=', 'products.id')
            ->whereBetween('orders.created_at', [$startDate, $endDate])
            ->groupBy('year', 'month')
            ->orderBy('year')
            ->orderBy('month')
            ->get()
            ->map(function ($item) {
                $profit = $item->total_revenue - $item->total_cost;
                $profitMargin = $item->total_revenue > 0 ? ($profit / $item->total_revenue) * 100 : 0;

                return (object) [
                    'year' => $item->year,
                    'month' => $item->month,
                    'month_name' => \Carbon\Carbon::createFromDate($item->year, $item->month, 1)->format('F'),
                    'total_quantity' => $item->total_quantity,
                    'total_revenue' => $item->total_revenue,
                    'total_cost' => $item->total_cost,
                    'profit' => $profit,
                    'profit_margin' => $profitMargin,
                ];
            });
    }

    public function getCategoryProfitData(): \Illuminate\Support\Collection
    {
        [$startDate, $endDate] = $this->getDateRange();

        // Since categories table doesn't exist, we'll return a single "Uncategorized" entry
        $stats = OrderItem::with(['product'])
            ->selectRaw('
                SUM(order_items.quantity) as total_quantity,
                SUM(order_items.price * order_items.quantity) as total_revenue,
                SUM(products.cost_price * order_items.quantity) as total_cost,
                COUNT(DISTINCT order_items.product_id) as product_count
            ')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->leftJoin('products', 'order_items.product_id', '=', 'products.id')
            ->whereBetween('orders.created_at', [$startDate, $endDate])
            ->first();

        $profit = ($stats->total_revenue ?? 0) - ($stats->total_cost ?? 0);
        $profitMargin = $stats->total_revenue > 0 ? ($profit / $stats->total_revenue) * 100 : 0;

        return collect([
            (object) [
                'category_name' => 'Uncategorized',
                'total_quantity' => $stats->total_quantity ?? 0,
                'total_revenue' => $stats->total_revenue ?? 0,
                'total_cost' => $stats->total_cost ?? 0,
                'profit' => $profit,
                'profit_margin' => $profitMargin,
                'product_count' => $stats->product_count ?? 0,
            ]
        ]);
    }

    public function getSummaryStats(): array
    {
        [$startDate, $endDate] = $this->getDateRange();

        $stats = OrderItem::with(['product'])
            ->selectRaw('
                SUM(order_items.quantity) as total_quantity,
                SUM(order_items.price * order_items.quantity) as total_revenue,
                SUM(products.cost_price * order_items.quantity) as total_cost,
                COUNT(DISTINCT order_items.product_id) as unique_products,
                COUNT(DISTINCT order_items.order_id) as total_orders
            ')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->leftJoin('products', 'order_items.product_id', '=', 'products.id')
            ->whereBetween('orders.created_at', [$startDate, $endDate])
            ->first();

        $totalProfit = ($stats->total_revenue ?? 0) - ($stats->total_cost ?? 0);
        $overallProfitMargin = $stats->total_revenue > 0 ? ($totalProfit / $stats->total_revenue) * 100 : 0;

        return [
            'total_quantity' => $stats->total_quantity ?? 0,
            'total_revenue' => $stats->total_revenue ?? 0,
            'total_cost' => $stats->total_cost ?? 0,
            'total_profit' => $totalProfit,
            'profit_margin' => $overallProfitMargin,
            'unique_products' => $stats->unique_products ?? 0,
            'total_orders' => $stats->total_orders ?? 0,
        ];
    }

    public function getChartData(): array
    {
        $viewType = $this->filterData['view_type'] ?? 'summary';

        if ($viewType === 'category') {
            $categoryData = $this->getCategoryProfitData();
            return [
                'category_chart' => [
                    'labels' => $categoryData->pluck('category_name')->toArray(),
                    'profit_data' => $categoryData->pluck('profit')->toArray(),
                    'margin_data' => $categoryData->pluck('profit_margin')->toArray(),
                ],
            ];
        } else {
            $dailyData = $this->getDailyProfitData();
            return [
                'daily_chart' => [
                    'labels' => $dailyData->pluck('date')->map(fn($date) => \Carbon\Carbon::parse($date)->format('M d'))->toArray(),
                    'revenue_data' => $dailyData->pluck('total_revenue')->toArray(),
                    'cost_data' => $dailyData->pluck('total_cost')->toArray(),
                    'profit_data' => $dailyData->pluck('profit')->toArray(),
                ],
            ];
        }
    }

    public function exportReport(): void
    {
        $viewType = $this->filterData['view_type'] ?? 'summary';
        $filename = 'profit_report_' . $viewType . '_' . now()->format('Y-m-d_H-i-s') . '.csv';

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'w');

        // Header
        fputcsv($output, ['Profit Report']);
        fputcsv($output, ['Generated:', now()->format('Y-m-d H:i:s')]);
        fputcsv($output, ['Date Range:', $this->filterData['date_range']]);
        fputcsv($output, []);

        // Summary Stats
        $stats = $this->getSummaryStats();
        fputcsv($output, ['Summary Statistics']);
        fputcsv($output, ['Total Revenue', '₹' . number_format($stats['total_revenue'], 2)]);
        fputcsv($output, ['Total Cost', '₹' . number_format($stats['total_cost'], 2)]);
        fputcsv($output, ['Total Profit', '₹' . number_format($stats['total_profit'], 2)]);
        fputcsv($output, ['Profit Margin', number_format($stats['profit_margin'], 2) . '%']);
        fputcsv($output, ['Total Quantity Sold', $stats['total_quantity']]);
        fputcsv($output, ['Total Orders', $stats['total_orders']]);
        fputcsv($output, ['Unique Products', $stats['unique_products']]);
        fputcsv($output, []);

        if ($viewType === 'summary' || $viewType === 'detailed') {
            // Product Profit Details
            $profitData = $this->getProfitData();
            fputcsv($output, ['Product Profit Details']);
            fputcsv($output, ['Product Name', 'Category', 'Quantity Sold', 'Revenue', 'Cost', 'Profit', 'Profit Margin', 'Avg Selling Price']);

            foreach ($profitData as $item) {
                fputcsv($output, [
                    $item->product_name,
                    $item->category_name,
                    $item->total_quantity,
                    '₹' . number_format($item->total_revenue, 2),
                    '₹' . number_format($item->total_cost, 2),
                    '₹' . number_format($item->profit, 2),
                    number_format($item->profit_margin, 2) . '%',
                    '₹' . number_format($item->avg_selling_price, 2),
                ]);
            }
            fputcsv($output, []);
        }

        if ($viewType === 'category') {
            // Category Profit Details
            $categoryData = $this->getCategoryProfitData();
            fputcsv($output, ['Category Profit Details']);
            fputcsv($output, ['Category', 'Quantity Sold', 'Revenue', 'Cost', 'Profit', 'Profit Margin', 'Product Count']);

            foreach ($categoryData as $item) {
                fputcsv($output, [
                    $item->category_name,
                    $item->total_quantity,
                    '₹' . number_format($item->total_revenue, 2),
                    '₹' . number_format($item->total_cost, 2),
                    '₹' . number_format($item->profit, 2),
                    number_format($item->profit_margin, 2) . '%',
                    $item->product_count,
                ]);
            }
            fputcsv($output, []);
        }

        // Daily Profit Data
        $dailyData = $this->getDailyProfitData();
        fputcsv($output, ['Daily Profit Trend']);
        fputcsv($output, ['Date', 'Quantity Sold', 'Revenue', 'Cost', 'Profit', 'Profit Margin']);

        foreach ($dailyData as $item) {
            fputcsv($output, [
                $item->date,
                $item->total_quantity,
                '₹' . number_format($item->total_revenue, 2),
                '₹' . number_format($item->total_cost, 2),
                '₹' . number_format($item->profit, 2),
                number_format($item->profit_margin, 2) . '%',
            ]);
        }

        fclose($output);
        exit;
    }

    public function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Export Report')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(function () {
                    $this->exportReport();
                }),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()->hasRole(['Manager', 'Admin']);
    }
}