<?php

namespace App\Filament\Pages;

use App\Models\OrderItem;
use App\Models\Product;
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
use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;

class ProductSalesReport extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-pie';

    // protected static string|\UnitEnum|null $navigationGroup = 'Reports';

    protected string $view = 'filament.pages.product-sales-report';

    protected static ?string $navigationLabel = 'Product Sales Report';

    protected static ?string $title = 'Product Sales Report';

    protected static ?int $navigationSort = 4;

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

                        Select::make('limit')
                            ->label('Number of Products')
                            ->options([
                                '10' => 'Top 10',
                                '20' => 'Top 20',
                                '50' => 'Top 50',
                                '100' => 'Top 100',
                            ])
                            ->default('10'),
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

    public function getTopSellingProductsByQuantity(): \Illuminate\Support\Collection
    {
        [$startDate, $endDate] = $this->getDateRange();
        $limit = $this->filterData['limit'] ?? 10;

        return OrderItem::with(['product'])
            ->selectRaw('
                product_id,
                SUM(quantity) as total_quantity,
                SUM(price * quantity) as total_revenue,
                COUNT(DISTINCT order_id) as order_count
            ')
            ->whereHas('order', function ($query) use ($startDate, $endDate) {
                $query->whereBetween('created_at', [$startDate, $endDate]);
            })
            ->groupBy('product_id')
            ->orderBy('total_quantity', 'desc')
            ->limit($limit)
            ->get();
    }

    public function getTopSellingProductsByRevenue(): \Illuminate\Support\Collection
    {
        [$startDate, $endDate] = $this->getDateRange();
        $limit = $this->filterData['limit'] ?? 10;

        return OrderItem::with(['product'])
            ->selectRaw('
                product_id,
                SUM(quantity) as total_quantity,
                SUM(price * quantity) as total_revenue,
                COUNT(DISTINCT order_id) as order_count
            ')
            ->whereHas('order', function ($query) use ($startDate, $endDate) {
                $query->whereBetween('created_at', [$startDate, $endDate]);
            })
            ->groupBy('product_id')
            ->orderBy('total_revenue', 'desc')
            ->limit($limit)
            ->get();
    }

    public function getLowSellingProductsByQuantity(): \Illuminate\Support\Collection
    {
        [$startDate, $endDate] = $this->getDateRange();
        $limit = $this->filterData['limit'] ?? 10;

        return OrderItem::with(['product'])
            ->selectRaw('
                product_id,
                SUM(quantity) as total_quantity,
                SUM(price * quantity) as total_revenue,
                COUNT(DISTINCT order_id) as order_count
            ')
            ->whereHas('order', function ($query) use ($startDate, $endDate) {
                $query->whereBetween('created_at', [$startDate, $endDate]);
            })
            ->groupBy('product_id')
            ->orderBy('total_quantity', 'asc')
            ->limit($limit)
            ->get();
    }

    public function getLowSellingProductsByRevenue(): \Illuminate\Support\Collection
    {
        [$startDate, $endDate] = $this->getDateRange();
        $limit = $this->filterData['limit'] ?? 10;

        return OrderItem::with(['product'])
            ->selectRaw('
                product_id,
                SUM(quantity) as total_quantity,
                SUM(price * quantity) as total_revenue,
                COUNT(DISTINCT order_id) as order_count
            ')
            ->whereHas('order', function ($query) use ($startDate, $endDate) {
                $query->whereBetween('created_at', [$startDate, $endDate]);
            })
            ->groupBy('product_id')
            ->orderBy('total_revenue', 'asc')
            ->limit($limit)
            ->get();
    }

    public function getSummaryStats(): array
    {
        [$startDate, $endDate] = $this->getDateRange();

        $stats = OrderItem::whereHas('order', function ($query) use ($startDate, $endDate) {
            $query->whereBetween('created_at', [$startDate, $endDate]);
        })->selectRaw('
            COUNT(DISTINCT product_id) as unique_products,
            SUM(quantity) as total_quantity,
            SUM(price * quantity) as total_revenue,
            COUNT(DISTINCT order_id) as total_orders,
            AVG(price) as avg_price
        ')->first();

        return [
            'unique_products' => $stats->unique_products ?? 0,
            'total_quantity' => $stats->total_quantity ?? 0,
            'total_revenue' => $stats->total_revenue ?? 0,
            'total_orders' => $stats->total_orders ?? 0,
            'avg_price' => $stats->avg_price ?? 0,
        ];
    }

    public function getChartData(): array
    {
        $topByQuantity = $this->getTopSellingProductsByQuantity();
        $topByRevenue = $this->getTopSellingProductsByRevenue();

        return [
            'quantity_chart' => [
                'labels' => $topByQuantity->map(fn($item) => $item->product?->name ?? 'Unknown')->toArray(),
                'data' => $topByQuantity->pluck('total_quantity')->toArray(),
            ],
            'revenue_chart' => [
                'labels' => $topByRevenue->map(fn($item) => $item->product?->name ?? 'Unknown')->toArray(),
                'data' => $topByRevenue->pluck('total_revenue')->toArray(),
            ],
        ];
    }

    public function exportReport(): void
    {
        $topByQuantity = $this->getTopSellingProductsByQuantity();
        $topByRevenue = $this->getTopSellingProductsByRevenue();
        $lowByQuantity = $this->getLowSellingProductsByQuantity();
        $lowByRevenue = $this->getLowSellingProductsByRevenue();

        $filename = 'product_sales_report_' . now()->format('Y-m-d_H-i-s') . '.csv';

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'w');

        // Header
        fputcsv($output, ['Product Sales Report']);
        fputcsv($output, ['Generated:', now()->format('Y-m-d H:i:s')]);
        fputcsv($output, ['Date Range:', $this->filterData['date_range']]);
        fputcsv($output, []);

        // Summary Stats
        $stats = $this->getSummaryStats();
        fputcsv($output, ['Summary Statistics']);
        fputcsv($output, ['Unique Products', $stats['unique_products']]);
        fputcsv($output, ['Total Quantity Sold', $stats['total_quantity']]);
        fputcsv($output, ['Total Revenue', '₹' . number_format($stats['total_revenue'], 2)]);
        fputcsv($output, ['Total Orders', $stats['total_orders']]);
        fputcsv($output, ['Average Price', '₹' . number_format($stats['avg_price'], 2)]);
        fputcsv($output, []);

        // Top Selling by Quantity
        fputcsv($output, ['Top Selling Products by Quantity']);
        fputcsv($output, ['Product Name', 'SKU', 'Total Quantity', 'Total Revenue', 'Order Count']);
        foreach ($topByQuantity as $item) {
            fputcsv($output, [
                $item->product?->name ?? 'Unknown',
                $item->product?->sku ?? 'N/A',
                $item->total_quantity,
                '₹' . number_format($item->total_revenue, 2),
                $item->order_count
            ]);
        }
        fputcsv($output, []);

        // Top Selling by Revenue
        fputcsv($output, ['Top Selling Products by Revenue']);
        fputcsv($output, ['Product Name', 'SKU', 'Total Quantity', 'Total Revenue', 'Order Count']);
        foreach ($topByRevenue as $item) {
            fputcsv($output, [
                $item->product?->name ?? 'Unknown',
                $item->product?->sku ?? 'N/A',
                $item->total_quantity,
                '₹' . number_format($item->total_revenue, 2),
                $item->order_count
            ]);
        }
        fputcsv($output, []);

        // Low Selling by Quantity
        fputcsv($output, ['Low Selling Products by Quantity']);
        fputcsv($output, ['Product Name', 'SKU', 'Total Quantity', 'Total Revenue', 'Order Count']);
        foreach ($lowByQuantity as $item) {
            fputcsv($output, [
                $item->product?->name ?? 'Unknown',
                $item->product?->sku ?? 'N/A',
                $item->total_quantity,
                '₹' . number_format($item->total_revenue, 2),
                $item->order_count
            ]);
        }
        fputcsv($output, []);

        // Low Selling by Revenue
        fputcsv($output, ['Low Selling Products by Revenue']);
        fputcsv($output, ['Product Name', 'SKU', 'Total Quantity', 'Total Revenue', 'Order Count']);
        foreach ($lowByRevenue as $item) {
            fputcsv($output, [
                $item->product?->name ?? 'Unknown',
                $item->product?->sku ?? 'N/A',
                $item->total_quantity,
                '₹' . number_format($item->total_revenue, 2),
                $item->order_count
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