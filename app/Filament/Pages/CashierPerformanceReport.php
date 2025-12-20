<?php

namespace App\Filament\Pages;

use App\Models\CashierShift;
use App\Models\Order;
use App\Models\User;
use App\Models\Payment;
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
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;

class CashierPerformanceReport extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-pie';

    // protected static string|\UnitEnum|null $navigationGroup = 'Reports';

    protected string $view = 'filament.pages.cashier-performance-report';

    protected static ?string $navigationLabel = 'Cashier Performance';

    protected static ?string $title = 'Cashier Performance Report';

    protected static ?int $navigationSort = 4;

    public array $filterData = [];

    public function mount(): void
    {
        $this->filterData = [
            'date_range' => 'this_month',
            'user_id' => null,
        ];
    }

    public function filterForm(): Form
    {
        return Forms\Form::make([
            Forms\Components\Section::make('Filter Reports')
                ->schema([
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\Select::make('date_range')
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

                        Forms\Components\Select::make('user_id')
                            ->label('Cashier')
                            ->options(User::whereHas('cashierShifts')->pluck('name', 'id'))
                            ->placeholder('All Cashiers')
                            ->reactive(),
                    ]),

                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\DatePicker::make('start_date')
                            ->label('Start Date')
                            ->visible(fn(callable $get) => $get('date_range') === 'custom')
                            ->required(fn(callable $get) => $get('date_range') === 'custom'),

                        Forms\Components\DatePicker::make('end_date')
                            ->label('End Date')
                            ->visible(fn(callable $get) => $get('date_range') === 'custom')
                            ->required(fn(callable $get) => $get('date_range') === 'custom'),
                    ]),
                ]),
        ]);
    }

    public function table(Table $table): Table
    {
        $query = $this->getCashierPerformanceQuery();

        return $table
            ->query($query)
            ->columns([
                TextColumn::make('name')
                    ->label('Cashier')
                    ->searchable()
                    ->sortable()
                    ->description(fn($record): string => $record->email ?? ''),

                TextColumn::make('total_shifts')
                    ->label('Total Shifts')
                    ->sortable()
                    ->alignCenter(),

                TextColumn::make('total_orders')
                    ->label('Total Orders')
                    ->sortable()
                    ->alignCenter()
                    ->summarize(Sum::make()),

                TextColumn::make('total_sales')
                    ->label('Total Sales')
                    ->money('INR')
                    ->sortable()
                    ->summarize(Sum::make()->money('INR')),

                TextColumn::make('average_order_value')
                    ->label('Avg Order Value')
                    ->money('INR')
                    ->getStateUsing(fn($record): float => $record->total_orders > 0 ? $record->total_sales / $record->total_orders : 0)
                    ->sortable(false),

                TextColumn::make('total_hours')
                    ->label('Total Hours')
                    ->getStateUsing(fn($record): string => number_format($record->total_hours, 2))
                    ->sortable()
                    ->alignCenter(),

                TextColumn::make('transactions_per_hour')
                    ->label('Transactions/Hour')
                    ->getStateUsing(fn($record): float => $record->total_hours > 0 ? $record->total_orders / $record->total_hours : 0)
                    ->sortable(false)
                    ->alignCenter(),

                TextColumn::make('cash_handling_accuracy')
                    ->label('Cash Accuracy')
                    ->getStateUsing(fn($record): string => $record->total_shifts > 0
                        ? number_format((1 - abs($record->total_discrepancy) / ($record->total_cash_sales + $record->opening_cash_total)) * 100, 2) . '%'
                        : '100%')
                    ->color(fn($record): string =>
                        $record->total_shifts > 0 && abs($record->total_discrepancy) / ($record->total_cash_sales + $record->opening_cash_total) > 0.02
                        ? 'danger'
                        : 'success')
                    ->sortable(false)
                    ->alignCenter(),
            ])
            ->emptyStateActions([
                Action::make('create_shift')
                    ->label('Create Shift')
                    ->icon('heroicon-o-plus')
                    ->url(route('filament.admin.resources.cashier-shifts.create')),
            ]);
    }

    protected function getCashierPerformanceQuery(): Builder
    {
        $query = User::whereHas('cashierShifts')
            ->with([
                'cashierShifts' => function ($query) {
                    $query->with('orders');
                }
            ]);

        // Apply date range filter
        $startDate = null;
        $endDate = null;

        switch ($this->filterData['date_range']) {
            case 'today':
                $startDate = today()->startOfDay();
                $endDate = today()->endOfDay();
                break;
            case 'yesterday':
                $startDate = yesterday()->startOfDay();
                $endDate = yesterday()->endOfDay();
                break;
            case 'this_week':
                $startDate = now()->startOfWeek();
                $endDate = now()->endOfWeek();
                break;
            case 'last_week':
                $startDate = now()->subWeek()->startOfWeek();
                $endDate = now()->subWeek()->endOfWeek();
                break;
            case 'this_month':
                $startDate = now()->startOfMonth();
                $endDate = now()->endOfMonth();
                break;
            case 'last_month':
                $startDate = now()->subMonth()->startOfMonth();
                $endDate = now()->subMonth()->endOfMonth();
                break;
            case 'custom':
                if (!empty($this->filterData['start_date'])) {
                    $startDate = Carbon::parse($this->filterData['start_date'])->startOfDay();
                }
                if (!empty($this->filterData['end_date'])) {
                    $endDate = Carbon::parse($this->filterData['end_date'])->endOfDay();
                }
                break;
        }

        // Apply user filter
        if (!empty($this->filterData['user_id'])) {
            $query->where('id', $this->filterData['user_id']);
        }

        return $query->select([
            'users.*',
            DB::raw('(SELECT COUNT(*) FROM cashier_shifts WHERE cashier_shifts.user_id = users.id ' .
                ($startDate ? 'AND cashier_shifts.start_time >= "' . $startDate . '" ' : '') .
                ($endDate ? 'AND cashier_shifts.start_time <= "' . $endDate . '" ' : '') .
                ') as total_shifts'),
            DB::raw('(SELECT COUNT(*) FROM orders 
                    INNER JOIN cashier_shifts ON orders.cashier_shift_id = cashier_shifts.id 
                    WHERE cashier_shifts.user_id = users.id ' .
                ($startDate ? 'AND cashier_shifts.start_time >= "' . $startDate . '" ' : '') .
                ($endDate ? 'AND cashier_shifts.start_time <= "' . $endDate . '" ' : '') .
                ') as total_orders'),
            DB::raw('(SELECT COALESCE(SUM(orders.total_price), 0) FROM orders 
                    INNER JOIN cashier_shifts ON orders.cashier_shift_id = cashier_shifts.id 
                    WHERE cashier_shifts.user_id = users.id ' .
                ($startDate ? 'AND cashier_shifts.start_time >= "' . $startDate . '" ' : '') .
                ($endDate ? 'AND cashier_shifts.start_time <= "' . $endDate . '" ' : '') .
                ') as total_sales'),
            DB::raw('(SELECT COALESCE(SUM(TIMESTAMPDIFF(MINUTE, cashier_shifts.start_time, COALESCE(cashier_shifts.end_time, NOW()))) / 60, 0) FROM cashier_shifts
                    WHERE cashier_shifts.user_id = users.id ' .
                ($startDate ? 'AND cashier_shifts.start_time >= "' . $startDate . '" ' : '') .
                ($endDate ? 'AND cashier_shifts.start_time <= "' . $endDate . '" ' : '') .
                ') as total_hours'),
            DB::raw('(SELECT COALESCE(SUM(cashier_shifts.cash_collected), 0) FROM cashier_shifts
                    WHERE cashier_shifts.user_id = users.id ' .
                ($startDate ? 'AND cashier_shifts.start_time >= "' . $startDate . '" ' : '') .
                ($endDate ? 'AND cashier_shifts.start_time <= "' . $endDate . '" ' : '') .
                ') as total_cash_sales'),
            DB::raw('(SELECT COALESCE(SUM(cashier_shifts.card_sales), 0) FROM cashier_shifts
                    WHERE cashier_shifts.user_id = users.id ' .
                ($startDate ? 'AND cashier_shifts.start_time >= "' . $startDate . '" ' : '') .
                ($endDate ? 'AND cashier_shifts.start_time <= "' . $endDate . '" ' : '') .
                ') as total_card_sales'),
            DB::raw('(SELECT COALESCE(SUM(cashier_shifts.upi_sales), 0) FROM cashier_shifts
                    WHERE cashier_shifts.user_id = users.id ' .
                ($startDate ? 'AND cashier_shifts.start_time >= "' . $startDate . '" ' : '') .
                ($endDate ? 'AND cashier_shifts.start_time <= "' . $endDate . '" ' : '') .
                ') as total_upi_sales'),
            DB::raw('(SELECT COALESCE(SUM(cashier_shifts.opening_cash), 0) FROM cashier_shifts
                    WHERE cashier_shifts.user_id = users.id ' .
                ($startDate ? 'AND cashier_shifts.start_time >= "' . $startDate . '" ' : '') .
                ($endDate ? 'AND cashier_shifts.start_time <= "' . $endDate . '" ' : '') .
                ') as opening_cash_total'),
            DB::raw('(SELECT COALESCE(SUM(cashier_shifts.cash_discrepancy), 0) FROM cashier_shifts 
                    WHERE cashier_shifts.user_id = users.id ' .
                ($startDate ? 'AND cashier_shifts.start_time >= "' . $startDate . '" ' : '') .
                ($endDate ? 'AND cashier_shifts.start_time <= "' . $endDate . '" ' : '') .
                ') as total_discrepancy'),
        ]);
    }

    public function getTableRecords(): \Illuminate\Support\Collection
    {
        return $this->tableQuery()->get();
    }

    protected function getTableRecordsPerPage(): int
    {
        return 25;
    }

    public function getCashierStats(): array
    {
        $query = $this->getCashierPerformanceQuery();
        $cashiers = $query->get();

        return [
            'total_cashiers' => $cashiers->count(),
            'total_shifts' => $cashiers->sum('total_shifts'),
            'total_orders' => $cashiers->sum('total_orders'),
            'total_sales' => $cashiers->sum('total_sales'),
            'total_hours' => $cashiers->sum('total_hours'),
            'avg_transactions_per_hour' => $cashiers->sum('total_hours') > 0
                ? $cashiers->sum('total_orders') / $cashiers->sum('total_hours')
                : 0,
            'total_cash_sales' => $cashiers->sum('total_cash_sales'),
            'total_discrepancy' => $cashiers->sum('total_discrepancy'),
        ];
    }

    public function getPerformanceChartData(): array
    {
        $query = $this->getCashierPerformanceQuery();
        $cashiers = $query->get();

        $labels = $cashiers->pluck('name')->toArray();
        $sales = $cashiers->pluck('total_sales')->toArray();
        $orders = $cashiers->pluck('total_orders')->toArray();
        $hours = $cashiers->pluck('total_hours')->toArray();

        return [
            'labels' => $labels,
            'sales' => $sales,
            'orders' => $orders,
            'hours' => $hours,
            'max_sales' => max($sales) > 0 ? max($sales) : 1,
            'max_orders' => max($orders) > 0 ? max($orders) : 1,
        ];
    }

    public function exportCashierPerformance(): void
    {
        $query = $this->getCashierPerformanceQuery();
        $cashiers = $query->get();

        $csvData = [];
        $csvData[] = [
            'Cashier',
            'Email',
            'Total Shifts',
            'Total Orders',
            'Total Sales',
            'Average Order Value',
            'Total Hours',
            'Transactions Per Hour',
            'Cash Sales',
            'Card Sales',
            'UPI Sales',
            'Cash Discrepancy',
            'Cash Handling Accuracy',
        ];

        foreach ($cashiers as $cashier) {
            $accuracy = $cashier->total_shifts > 0
                ? (1 - abs($cashier->total_discrepancy) / ($cashier->total_cash_sales + $cashier->opening_cash_total)) * 100
                : 100;

            $csvData[] = [
                $cashier->name,
                $cashier->email,
                $cashier->total_shifts,
                $cashier->total_orders,
                $cashier->total_sales,
                $cashier->total_orders > 0 ? $cashier->total_sales / $cashier->total_orders : 0,
                number_format($cashier->total_hours, 2),
                $cashier->total_hours > 0 ? $cashier->total_orders / $cashier->total_hours : 0,
                $cashier->total_cash_sales,
                $cashier->total_card_sales,
                $cashier->total_upi_sales,
                $cashier->total_discrepancy,
                number_format($accuracy, 2) . '%',
            ];
        }

        $filename = 'cashier_performance_report_' . now()->format('Y-m-d_H-i-s') . '.csv';

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'w');
        foreach ($csvData as $row) {
            fputcsv($output, $row);
        }
        fclose($output);
        exit;
    }

    public function getHeaderActions(): array
    {
        return [
            Action::make('export_all')
                ->label('Export All')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(function () {
                    $this->exportCashierPerformance();
                }),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()->hasRole(['Manager', 'Admin']);
    }
}