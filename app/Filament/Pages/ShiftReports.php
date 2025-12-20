<?php

namespace App\Filament\Pages;

use App\Models\CashierShift;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\ExportsAction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\Summarizers\Sum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ShiftReports extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    // protected static string|\UnitEnum|null $navigationGroup = 'Reports';

    protected string $view = 'filament.pages.shift-reports';

    protected static ?string $navigationLabel = 'Shift Reports';

    protected static ?string $title = 'Shift Reports';

    protected static ?int $navigationSort = 3;


    public array $filterData = [];

    public function mount(): void
    {
        $this->filterData = [
            'date_range' => 'today',
            'user_id' => null,
            'status' => null,
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
                            ->default('today')
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

                    Forms\Components\Select::make('status')
                        ->label('Shift Status')
                        ->options([
                            'all' => 'All Shifts',
                            'open' => 'Open Only',
                            'closed' => 'Closed Only',
                        ])
                        ->default('all'),
                ]),
        ]);
    }

    public function table(Table $table): Table
    {
        $query = $this->getQuery();

        return $table
            ->query($query)
            ->columns([
                TextColumn::make('user.name')
                    ->label('Cashier')
                    ->searchable()
                    ->sortable()
                    ->description(fn($record): string => $record->user->email ?? ''),

                TextColumn::make('start_time')
                    ->label('Start Time')
                    ->dateTime('M j, Y g:i A')
                    ->sortable()
                    ->description(fn($record): string => $record->start_time->diffForHumans()),

                TextColumn::make('end_time')
                    ->label('End Time')
                    ->dateTime('M j, Y g:i A')
                    ->sortable()
                    ->placeholder('Still Open')
                    ->description(fn($record): string => $record->end_time ? $record->end_time->diffForHumans() : ''),

                TextColumn::make('duration')
                    ->label('Duration')
                    ->getStateUsing(fn($record): string => $record->duration)
                    ->sortable(false),

                IconColumn::make('status')
                    ->label('Status')
                    ->boolean()
                    ->trueLabel('Closed')
                    ->falseLabel('Open')
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-clock')
                    ->color(fn($record): string => $record->status === 'closed' ? 'success' : 'warning'),

                TextColumn::make('total_sales')
                    ->label('Total Sales')
                    ->money('INR')
                    ->sortable()
                    ->summarize(Sum::make()->money('INR')),

                TextColumn::make('cash_collected')
                    ->label('Cash Sales')
                    ->money('INR')
                    ->sortable()
                    ->summarize(Sum::make()->money('INR')),

                TextColumn::make('cash_discrepancy')
                    ->label('Cash Discrepancy')
                    ->money('INR')
                    ->getStateUsing(fn($record): float => $record->cash_discrepancy)
                    ->color(fn($record): string => $record->cash_discrepancy === 0 ? 'success' : 'danger')
                    ->sortable(false),

                TextColumn::make('orders_count')
                    ->label('Orders')
                    ->getStateUsing(fn($record): int => $record->orders()->count())
                    ->sortable()
                    ->summarize(Sum::make()),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'open' => 'Open',
                        'closed' => 'Closed',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->url(fn($record): string => route('filament.admin.resources.cashier-shifts.view', $record)),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Action::make('export_selected')
                        ->label('Export Selected')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->action(function ($records) {
                            $this->exportShifts($records);
                        }),
                ]),
            ])
            ->emptyStateActions([
                Action::make('create_shift')
                    ->label('Create Shift')
                    ->icon('heroicon-o-plus')
                    ->url(route('filament.admin.resources.cashier-shifts.create')),
            ]);
    }

    protected function getQuery(): Builder
    {
        $query = CashierShift::with(['user', 'orders']);

        // Apply date range filter
        switch ($this->filterData['date_range']) {
            case 'today':
                $query->whereDate('start_time', today());
                break;
            case 'yesterday':
                $query->whereDate('start_time', yesterday());
                break;
            case 'this_week':
                $query->whereBetween('start_time', [now()->startOfWeek(), now()->endOfWeek()]);
                break;
            case 'last_week':
                $query->whereBetween('start_time', [now()->subWeek()->startOfWeek(), now()->subWeek()->endOfWeek()]);
                break;
            case 'this_month':
                $query->whereMonth('start_time', now()->month)
                    ->whereYear('start_time', now()->year);
                break;
            case 'last_month':
                $query->whereMonth('start_time', now()->subMonth()->month)
                    ->whereYear('start_time', now()->subMonth()->year);
                break;
            case 'custom':
                if (!empty($this->filterData['start_date'])) {
                    $query->whereDate('start_time', '>=', $this->filterData['start_date']);
                }
                if (!empty($this->filterData['end_date'])) {
                    $query->whereDate('start_time', '<=', $this->filterData['end_date']);
                }
                break;
        }

        // Apply user filter
        if (!empty($this->filterData['user_id'])) {
            $query->where('user_id', $this->filterData['user_id']);
        }

        // Apply status filter
        if (!empty($this->filterData['status']) && $this->filterData['status'] !== 'all') {
            $query->where('status', $this->filterData['status']);
        }

        return $query;
    }

    public function getTableRecords(): \Illuminate\Support\Collection
    {
        return $this->tableQuery()->get();
    }

    protected function getTableRecordsPerPage(): int
    {
        return 25;
    }

    public function exportShifts($records): void
    {
        $csvData = [];
        $csvData[] = [
            'Shift ID',
            'Cashier',
            'Email',
            'Start Time',
            'End Time',
            'Duration',
            'Status',
            'Opening Cash',
            'Closing Cash',
            'Total Sales',
            'Cash Sales',
            'Card Sales',
            'UPI Sales',
            'Cash Discrepancy',
            'Orders Count',
            'Notes',
        ];

        foreach ($records as $shift) {
            $csvData[] = [
                $shift->id,
                $shift->user->name,
                $shift->user->email,
                $shift->start_time->format('Y-m-d H:i:s'),
                $shift->end_time ? $shift->end_time->format('Y-m-d H:i:s') : '',
                $shift->duration,
                $shift->status,
                $shift->opening_cash,
                $shift->closing_cash,
                $shift->total_sales,
                $shift->cash_collected,
                $shift->card_sales,
                $shift->upi_sales,
                $shift->cash_discrepancy,
                $shift->orders()->count(),
                $shift->notes,
            ];
        }

        $filename = 'shifts_report_' . now()->format('Y-m-d_H-i-s') . '.csv';

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'w');
        foreach ($csvData as $row) {
            fputcsv($output, $row);
        }
        fclose($output);
        exit;
    }

    public function getSummaryStats(): array
    {
        $query = $this->getQuery();

        return [
            'total_shifts' => $query->count(),
            'total_sales' => $query->sum('total_sales'),
            'total_cash_collected' => $query->sum('cash_collected'),
            'total_card_sales' => $query->sum('card_sales'),
            'total_upi_sales' => $query->sum('upi_sales'),
            'total_discrepancy' => $query->sum(DB::raw('closing_cash - (opening_cash + cash_collected)')),
            'total_orders' => $query->withCount('orders')->get()->sum('orders_count'),
        ];
    }

    public function getHeaderActions(): array
    {
        return [
            Action::make('export_all')
                ->label('Export All')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(function () {
                    $this->exportShifts($this->getTableRecords());
                }),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()->hasRole(['manager', 'admin']);
    }
}