<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Maatwebsite\Excel\Excel;
use App\Filament\Resources\OrderResource\Pages\ListOrders;
use App\Filament\Resources\OrderResource\Pages\EditOrder;
use App\Filament\Resources\OrderResource\Pages;
use App\Models\Order;
use App\Models\Setting;
use App\Models\SalesReturn;
use App\Services\SalesReturnService;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Forms\Components\DatePicker;
use Filament\Actions\Action;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;
use pxlrbt\FilamentExcel\Columns\Column;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-shopping-cart';

    protected static ?int $navigationSort = 1;


    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        $currency_symbol = config('settings.currency_symbol');

        return $table
            ->columns([
                TextColumn::make('id')->label('ID')->sortable(),
                TextColumn::make('cashierShift.user.name')
                            ->label('Cashier')
                            ->formatStateUsing(fn ($record) => $record->cashierShift?->user?->name ?? '-')
                            ->sortable()
                            ->toggleable(),
                TextColumn::make('customer.first_name')
                            ->label('Customer Name')
                            ->searchable()
                            ->formatStateUsing(fn ($record) => $record->customer?->first_name . ' ' . $record->customer?->last_name ?? 'Walk-in Customer'),
                TextColumn::make('total_price')
                            ->formatStateUsing(fn ($record) => $currency_symbol.$record->total_price)->sortable(),
                TextColumn::make('payments.first.payment_method')
                            ->label('Payment Method')
                            ->formatStateUsing(fn ($state) => $state ? ucfirst($state) : '-')
                            ->sortable(),
                TextColumn::make('returns_count')
                            ->label('Returns')
                            ->counts('salesReturns')
                            ->sortable()
                            ->color('warning')
                            ->toggleable(),
                TextColumn::make('total_returned_amount')
                            ->label('Returned Amount')
                            ->getStateUsing(fn ($record) => $currency_symbol . $record->salesReturns->sum('total_refund_amount'))
                            ->sortable()
                            ->toggleable(),
                TextColumn::make('status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'pending' => 'warning',
                                'completed' => 'success',
                                'cancelled' => 'danger',
                                'refunded' => 'info',
                                default => 'gray',
                            })
                            ->formatStateUsing(fn (string $state): string =>
                                match($state) {
                                    'pending' => 'Pending',
                                    'completed' => 'Completed',
                                    'cancelled' => 'Cancelled',
                                    'refunded' => 'Refunded',
                                    default => ucfirst($state),
                                }
                            )
                            ->sortable(),
                TextColumn::make('created_at')->sortable()->dateTime(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                Filter::make('created_at')
                ->schema([
                    DatePicker::make('start_date')
                        ->label('From Date'),
                    DatePicker::make('end_date')
                        ->label('To Date'),
                ])
                ->query(function ($query, array $data) {
                    return $query
                        ->when($data['start_date'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
                        ->when($data['end_date'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '<=', $date));
                })
                ->indicateUsing(function (array $data) {
                    $indicators = [];
        
                    if (!empty($data['start_date'])) {
                        $indicators[] = 'From: ' . $data['start_date'];
                    }
        
                    if (!empty($data['end_date'])) {
                        $indicators[] = 'To: ' . $data['end_date'];
                    }
        
                    return $indicators;
                }),
                SelectFilter::make('payment_method')
                    ->label('Payment Method')
                    ->options([
                        'cash' => 'Cash',
                        'card' => 'Card',
                        'upi' => 'UPI',
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['value'] ?? null, function ($query, $paymentMethod) {
                                $query->whereHas('payments', function ($query) use ($paymentMethod) {
                                    $query->where('payment_method', $paymentMethod);
                                });
                            });
                    }),
                SelectFilter::make('status')
                    ->label('Order Status')
                    ->options([
                        'pending' => 'Pending',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                        'refunded' => 'Refunded',
                    ])
                    ->query(function ($query, array $data) {
                        return $query->when($data['value'] ?? null, fn ($query, $status) => $query->where('status', $status));
                    }),
                SelectFilter::make('cashier')
                    ->label('Cashier')
                    ->options(function () {
                        return \App\Models\User::whereHas('cashierShifts')->pluck('name', 'id')->toArray();
                    })
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['value'] ?? null, function ($query, $cashierId) {
                                $query->whereHas('cashierShift', function ($query) use ($cashierId) {
                                    $query->where('user_id', $cashierId);
                                });
                            });
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn ($record) => auth()->user()->can('order.edit')),
                DeleteAction::make()
                    ->visible(fn ($record) => auth()->user()->can('order.delete')),
                Action::make('create_return')
                    ->label('Create Return')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->url(fn ($record) => route('filament.admin.resources.sales-returns.create', ['order_id' => $record->id]))
                    ->visible(fn ($record) => auth()->user()->can('returns.create') && app(SalesReturnService::class)->isOrderEligibleForReturn($record)),
                Action::make('view_returns')
                    ->label('View Returns')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('info')
                    ->url(fn ($record) => route('filament.admin.resources.sales-returns.index', ['order_id' => $record->id]))
                    ->visible(fn ($record) => auth()->user()->can('returns.view') && $record->salesReturns->count() > 0),
                Action::make('view_shift')
                    ->label('View Shift')
                    ->icon('heroicon-o-clock')
                    ->color('success')
                    ->url(fn ($record) => $record->cashierShift ? route('filament.admin.resources.cashier-shifts.view', $record->cashierShift->id) : null)
                    ->visible(fn ($record) => auth()->user()->can('shift.view') && $record->cashierShift),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ExportBulkAction::make()->exports([
                        ExcelExport::make()
                            ->fromTable()
                            ->withFilename(fn ($resource) => $resource::getModelLabel() . '-' . date('Y-m-d'))
                            ->withWriterType(Excel::CSV)
                            ->withColumns([
                                Column::make('customer.phone')->heading('Mobile'),
                                Column::make('customer.email')->heading('Email'),
                                Column::make('customer.address')->heading('Address'),
                                Column::make('updated_at'),
                            ])
                    ])
                ]),
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Resources\OrderResource\RelationManagers\SalesReturnsRelationManager::class,
        ];
    }


    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
            // 'create' => Pages\CreateOrder::route('/create'),
            'edit' => EditOrder::route('/{record}/edit'),
        ];
    }
}
