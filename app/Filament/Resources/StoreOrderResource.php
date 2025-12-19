<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StoreOrderResource\Pages;
use App\Filament\Resources\StoreOrderResource\RelationManagers;
use App\Models\StoreOrder;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Notifications\Notification;

class StoreOrderResource extends Resource
{
    protected static ?string $model = StoreOrder::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shopping-cart';

    protected static string|\UnitEnum|null $navigationGroup = 'Store Management';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Order Information')
                    ->schema([
                        Select::make('store_id')
                            ->relationship('store', 'name')
                            ->required()
                            ->label('Store')
                            ->default(function () {
                                $storeId = request()->get('store_id');
                                if ($storeId) {
                                    return $storeId;
                                }
                                return auth()->user()->store_id;
                            })
                            ->disabled(fn ($context) => $context === 'edit'),
                        
                        Select::make('customer_id')
                            ->relationship('customer', 'name')
                            ->label('Customer')
                            ->searchable()
                            ->getSearchResultsUsing(function (string $search) {
                                return \App\Models\Customer::where('name', 'like', "%{$search}%")
                                    ->orWhere('phone', 'like', "%{$search}%")
                                    ->limit(50)
                                    ->pluck('name', 'id');
                            })
                            ->getOptionLabelUsing(function ($value) {
                                $customer = \App\Models\Customer::find($value);
                                return $customer ? $customer->name . ' (' . $customer->phone . ')' : $value;
                            }),
                        
                        Select::make('cashier_shift_id')
                            ->relationship('cashierShift', 'id', fn (Builder $query) => $query->where('status', 'active'))
                            ->label('Cashier Shift')
                            ->default(function () {
                                // Get active shift for current user and store
                                $storeId = request()->get('store_id') ?: auth()->user()->store_id;
                                return \App\Models\StoreCashierShift::where('user_id', auth()->id())
                                    ->where('store_id', $storeId)
                                    ->where('status', 'active')
                                    ->first()?->id;
                            })
                            ->disabled(fn ($context) => $context === 'edit'),
                        
                        TextInput::make('total_price')
                            ->required()
                            ->numeric()
                            ->prefix('₹')
                            ->label('Total Price')
                            ->disabled(fn ($context) => $context === 'edit'),
                        
                        TextInput::make('discount_amount')
                            ->numeric()
                            ->prefix('₹')
                            ->label('Discount Amount')
                            ->default(0),
                        
                        Select::make('status')
                            ->options([
                                'pending' => 'Pending',
                                'completed' => 'Completed',
                                'cancelled' => 'Cancelled',
                            ])
                            ->required()
                            ->label('Status'),
                        
                        Textarea::make('notes')
                            ->label('Notes')
                            ->rows(3),
                    ]),
                
                Section::make('Order Items')
                    ->schema([
                        Repeater::make('items')
                            ->relationship('items')
                            ->schema([
                                Select::make('product_id')
                                    ->relationship('product', 'name')
                                    ->required()
                                    ->label('Product')
                                    ->searchable()
                                    ->getSearchResultsUsing(function (string $search) {
                                        return \App\Models\Product::where('name', 'like', "%{$search}%")
                                            ->orWhere('sku', 'like', "%{$search}%")
                                            ->limit(50)
                                            ->pluck('name', 'id');
                                    })
                                    ->getOptionLabelUsing(function ($value) {
                                        $product = \App\Models\Product::find($value);
                                        return $product ? $product->name . ' (' . $product->sku . ')' : $value;
                                    })
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                        if ($state) {
                                            $product = \App\Models\Product::find($state);
                                            if ($product) {
                                                $set('name', $product->name);
                                                $set('price', $product->price);
                                            }
                                        }
                                    }),
                                
                                TextInput::make('name')
                                    ->required()
                                    ->label('Name')
                                    ->disabled(),
                                
                                TextInput::make('price')
                                    ->required()
                                    ->numeric()
                                    ->prefix('₹')
                                    ->label('Price'),
                                
                                TextInput::make('quantity')
                                    ->required()
                                    ->numeric()
                                    ->label('Quantity')
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                        $price = $get('price') ?? 0;
                                        $quantity = $state ?? 0;
                                        $set('subtotal', $price * $quantity);
                                    }),
                                
                                TextInput::make('tax')
                                    ->numeric()
                                    ->prefix('₹')
                                    ->label('Tax')
                                    ->default(0),
                                
                                TextInput::make('discount_percentage')
                                    ->numeric()
                                    ->suffix('%')
                                    ->label('Discount %')
                                    ->default(0),
                            ])
                            ->columns(2)
                            ->collapsed()
                            ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                            ->collapsible()
                            ->cloneable()
                            ->orderable(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->sortable()
                    ->label('Order ID'),
                
                TextColumn::make('store.name')
                    ->searchable()
                    ->sortable()
                    ->label('Store'),
                
                TextColumn::make('customer.name')
                    ->searchable()
                    ->sortable()
                    ->label('Customer')
                    ->placeholder('Walk-in Customer'),
                
                TextColumn::make('cashierShift.user.name')
                    ->searchable()
                    ->sortable()
                    ->label('Cashier'),
                
                TextColumn::make('formatted_total_price')
                    ->label('Total Price')
                    ->sortable(),
                
                TextColumn::make('formatted_total_discount')
                    ->label('Discount')
                    ->sortable(),
                
                TextColumn::make('items_count')
                    ->label('Items')
                    ->counts('items')
                    ->sortable(),
                
                TextColumn::make('status_text')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($record): string => $record->status_badge_color),
                
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->label('Order Date'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('store_id')
                    ->relationship('store', 'name')
                    ->label('Store')
                    ->default(function () {
                        return auth()->user()->store_id;
                    }),
                
                Tables\Filters\Filter::make('pending')
                    ->query(fn (Builder $query): Builder => $query->pending())
                    ->label('Pending'),
                
                Tables\Filters\Filter::make('completed')
                    ->query(fn (Builder $query): Builder => $query->completed())
                    ->label('Completed'),
                
                Tables\Filters\Filter::make('cancelled')
                    ->query(fn (Builder $query): Builder => $query->cancelled())
                    ->label('Cancelled'),
                
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('created_from')
                            ->label('From'),
                        \Filament\Forms\Components\DatePicker::make('created_until')
                            ->label('Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['created_from'] ?? null) {
                            $indicators['created_from'] = 'Order from: ' . $data['created_from'];
                        }
                        if ($data['created_until'] ?? null) {
                            $indicators['created_until'] = 'Order until: ' . $data['created_until'];
                        }
                        return $indicators;
                    }),
            ])
            ->actions([
                ViewAction::make()
                    ->visible(fn (): bool => auth()->user()->can('store-order.view')),
                
                EditAction::make()
                    ->visible(fn (): bool => auth()->user()->can('store-order.edit')),
                
                DeleteAction::make()
                    ->visible(fn (): bool => auth()->user()->can('store-order.delete')),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    DeleteAction::make()
                        ->visible(fn (): bool => auth()->user()->can('store-order.delete')),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStoreOrders::route('/'),
            'create' => Pages\CreateStoreOrder::route('/create'),
            'edit' => Pages\EditStoreOrder::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('store-order.view') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('store-order.create') ?? false;
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->can('store-order.edit') ?? false;
    }

    public static function canDelete($record): bool
    {
        return auth()->user()?->can('store-order.delete') ?? false;
    }

    public static function canDeleteAny(): bool
    {
        return auth()->user()?->can('store-order.delete') ?? false;
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ItemsRelationManager::class,
            RelationManagers\PaymentsRelationManager::class,
        ];
    }
}