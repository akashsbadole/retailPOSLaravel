<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SalesReturnResource\Pages;
use App\Filament\Resources\SalesReturnResource\RelationManagers;
use App\Models\SalesReturn;
use App\Models\Setting;
use App\Services\SalesReturnService;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

class SalesReturnResource extends Resource
{
    protected static ?string $model = SalesReturn::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-arrow-uturn-left';

    protected static string|\UnitEnum|null $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Return Information')
                    ->schema([
                        Forms\Components\Select::make('order_id')
                            ->relationship('order', 'id', function ($query) {
                                return $query->with(['customer', 'items.product'])->latest();
                            })
                            ->getOptionLabelFromRecordUsing(function ($record) {
                                $customerName = $record->customer ? 
                                    $record->customer->first_name . ' ' . $record->customer->last_name : 
                                    'Walk-in Customer';
                                return "Order #{$record->id} - {$customerName} - {$record->created_at->format('M d, Y')}";
                            })
                            ->searchable()
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(fn ($state, callable $set) => $set('items', []))
                            ->label('Order'),

                        Forms\Components\DatePicker::make('return_date')
                            ->default(now())
                            ->required()
                            ->label('Return Date'),

                        Forms\Components\Select::make('reason')
                            ->options(function () {
                                $salesReturnService = app(SalesReturnService::class);
                                return $salesReturnService->getReturnReasons();
                            })
                            ->required()
                            ->label('Return Reason'),

                        Forms\Components\Textarea::make('notes')
                            ->rows(3)
                            ->label('Notes'),

                        Forms\Components\Select::make('refund_method')
                            ->options([
                                'cash' => 'Cash',
                                'card' => 'Card',
                                'bank_transfer' => 'Bank Transfer',
                                'store_credit' => 'Store Credit',
                                'original_payment' => 'Original Payment Method',
                            ])
                            ->default('original_payment')
                            ->required()
                            ->label('Refund Method'),

                        Forms\Components\TextInput::make('total_refund_amount')
                            ->numeric()
                            ->prefix('$')
                            ->step(0.01)
                            ->disabled()
                            ->dehydrated(false)
                            ->label('Total Refund Amount'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Return Items')
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->relationship()
                            ->schema([
                                Forms\Components\Select::make('order_item_id')
                                    ->label('Product')
                                    ->options(function (callable $get) {
                                        $orderId = $get('../../order_id');
                                        if (!$orderId) return [];
                                        
                                        return \App\Models\OrderItem::where('order_id', $orderId)
                                            ->with('product')
                                            ->get()
                                            ->mapWithKeys(function ($item) {
                                                $alreadyReturned = \App\Models\SalesReturnItem::where('order_item_id', $item->id)
                                                    ->sum('quantity');
                                                $availableQuantity = $item->quantity - $alreadyReturned;
                                                
                                                if ($availableQuantity <= 0) return [];
                                                
                                                return [
                                                    $item->id => "{$item->name} (Available: {$availableQuantity})"
                                                ];
                                            });
                                    })
                                    ->required()
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, callable $get, callable $set) {
                                        $set('quantity', 1);
                                        
                                        if ($state) {
                                            $orderItem = \App\Models\OrderItem::find($state);
                                            if ($orderItem) {
                                                $salesReturnService = app(SalesReturnService::class);
                                                $refundAmount = $salesReturnService->calculateRefundAmount($orderItem, 1);
                                                $set('refund_amount', $refundAmount);
                                            }
                                        }
                                    }),

                                Forms\Components\TextInput::make('quantity')
                                    ->numeric()
                                    ->default(1)
                                    ->required()
                                    ->minValue(1)
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, callable $get, callable $set) {
                                        $orderItemId = $get('order_item_id');
                                        if ($orderItemId) {
                                            $orderItem = \App\Models\OrderItem::find($orderItemId);
                                            if ($orderItem) {
                                                $salesReturnService = app(SalesReturnService::class);
                                                $refundAmount = $salesReturnService->calculateRefundAmount($orderItem, $state);
                                                $set('refund_amount', $refundAmount);
                                            }
                                        }
                                    })
                                    ->label('Quantity'),

                                Forms\Components\TextInput::make('refund_amount')
                                    ->numeric()
                                    ->prefix('$')
                                    ->step(0.01)
                                    ->required()
                                    ->label('Refund Amount'),

                                Forms\Components\Textarea::make('reason')
                                    ->rows(2)
                                    ->label('Item Reason'),
                            ])
                            ->columns(4)
                            ->collapsed()
                            ->itemLabel(fn (array $state): ?string => 
                                isset($state['order_item_id']) ? 
                                \App\Models\OrderItem::find($state['order_item_id'])?->name : null
                            )
                            ->addActionLabel('Add Item')
                            ->collapsible()
                            ->reorderableWithButtons()
                            ->cloneable()
                            ->afterStateUpdated(function ($state, callable $get, callable $set) {
                                $total = array_sum(array_column($state, 'refund_amount'));
                                $set('../../total_refund_amount', $total);
                            }),
                    ])
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                Tables\Columns\TextColumn::make('order.id')
                    ->label('Order ID')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('order.customer.first_name')
                    ->label('Customer')
                    ->formatStateUsing(fn ($record) => 
                        $record->order->customer ? 
                        $record->order->customer->first_name . ' ' . $record->order->customer->last_name : 
                        'Walk-in Customer'
                    )
                    ->searchable(),

                Tables\Columns\TextColumn::make('return_date')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('reason')
                    ->formatStateUsing(fn ($state) => 
                        match($state) {
                            'damaged' => 'Damaged',
                            'defective' => 'Defective',
                            'wrong_item' => 'Wrong Item',
                            'not_needed' => 'Not Needed',
                            'customer_dissatisfaction' => 'Customer Dissatisfaction',
                            'expired' => 'Expired',
                            'other' => 'Other',
                            default => ucfirst($state),
                        }
                    )
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_refund_amount')
                    ->money('USD')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        'processed' => 'info',
                        'completed' => 'primary',
                    })
                    ->formatStateUsing(fn (string $state): string => 
                        match($state) {
                            'pending' => 'Pending',
                            'approved' => 'Approved',
                            'rejected' => 'Rejected',
                            'processed' => 'Processed',
                            'completed' => 'Completed',
                            default => ucfirst($state),
                        }
                    ),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Created By')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                        'processed' => 'Processed',
                        'completed' => 'Completed',
                    ]),

                Tables\Filters\SelectFilter::make('reason')
                    ->options(function () {
                        $salesReturnService = app(SalesReturnService::class);
                        return $salesReturnService->getReturnReasons();
                    }),

                Tables\Filters\Filter::make('return_date')
                    ->form([
                        Forms\Components\DatePicker::make('from'),
                        Forms\Components\DatePicker::make('to'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('return_date', '>=', $date),
                            )
                            ->when(
                                $data['to'],
                                fn (Builder $query, $date): Builder => $query->whereDate('return_date', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('print_credit_note')
                    ->label('Print Credit Note')
                    ->icon('heroicon-o-document-text')
                    ->color('info')
                    ->url(fn ($record) => route('utility.print-credit-note', $record->id))
                    ->openUrlInNewTab(),
                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (SalesReturn $record): bool => $record->canBeApproved())
                    ->action(function (SalesReturn $record) {
                        $record->approve();
                    }),
                Tables\Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (SalesReturn $record): bool => $record->canBeRejected())
                    ->action(function (SalesReturn $record) {
                        $record->reject();
                    }),
                Tables\Actions\Action::make('process')
                    ->label('Process')
                    ->icon('heroicon-o-cog')
                    ->color('info')
                    ->requiresConfirmation()
                    ->visible(fn (SalesReturn $record): bool => $record->canBeProcessed())
                    ->action(function (SalesReturn $record) {
                        $record->process();
                    }),
                Tables\Actions\Action::make('complete')
                    ->label('Complete')
                    ->icon('heroicon-o-check-circle')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->visible(fn (SalesReturn $record): bool => $record->isProcessed())
                    ->action(function (SalesReturn $record) {
                        $record->complete();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSalesReturns::route('/'),
            'create' => Pages\CreateSalesReturn::route('/create'),
            'view' => Pages\ViewSalesReturn::route('/{record}'),
            'edit' => Pages\EditSalesReturn::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function canViewAny(): bool
    {
        return Auth::user()->can('view_any_sales_return');
    }

    public static function canCreate(): bool
    {
        return Auth::user()->can('create_sales_return');
    }

    public static function canEdit($record): bool
    {
        return Auth::user()->can('update_sales_return') && $record->isPending();
    }

    public static function canDelete($record): bool
    {
        return Auth::user()->can('delete_sales_return') && $record->isPending();
    }

    public static function canDeleteAny(): bool
    {
        return Auth::user()->can('delete_any_sales_return');
    }
}