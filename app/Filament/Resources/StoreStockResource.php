<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StoreStockResource\Pages;
use App\Filament\Resources\StoreStockResource\RelationManagers;
use App\Models\StoreStock;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\Action;
use Filament\Notifications\Notification;

class StoreStockResource extends Resource
{
    protected static ?string $model = StoreStock::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cube';

    protected static string|\UnitEnum|null $navigationGroup = 'Store Management';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
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
                    }),
                
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
                    }),
                
                TextInput::make('quantity')
                    ->required()
                    ->numeric()
                    ->label('Quantity'),
                
                TextInput::make('minimum_stock')
                    ->numeric()
                    ->label('Minimum Stock')
                    ->default(0),
                
                TextInput::make('location')
                    ->label('Location'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('store.name')
                    ->searchable()
                    ->sortable()
                    ->label('Store'),
                
                TextColumn::make('product.name')
                    ->searchable()
                    ->sortable()
                    ->label('Product'),
                
                TextColumn::make('product.sku')
                    ->searchable()
                    ->sortable()
                    ->label('SKU'),
                
                TextColumn::make('quantity')
                    ->sortable()
                    ->label('Quantity'),
                
                TextColumn::make('minimum_stock')
                    ->sortable()
                    ->label('Minimum Stock'),
                
                TextColumn::make('location')
                    ->searchable()
                    ->sortable()
                    ->label('Location'),
                
                TextColumn::make('formatted_stock_value')
                    ->label('Stock Value')
                    ->sortable(),
                
                IconColumn::make('is_low_stock')
                    ->boolean()
                    ->trueLabel('Low Stock')
                    ->falseLabel('Normal')
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->falseIcon('heroicon-o-check-circle')
                    ->color(fn ($record): string => $record->is_low_stock ? 'warning' : 'success'),
                
                IconColumn::make('is_out_of_stock')
                    ->boolean()
                    ->trueLabel('Out of Stock')
                    ->falseLabel('In Stock')
                    ->trueIcon('heroicon-o-x-circle')
                    ->falseIcon('heroicon-o-check-circle')
                    ->color(fn ($record): string => $record->is_out_of_stock ? 'danger' : 'success'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('store_id')
                    ->relationship('store', 'name')
                    ->label('Store')
                    ->default(function () {
                        return auth()->user()->store_id;
                    }),
                
                Tables\Filters\Filter::make('low_stock')
                    ->query(fn (Builder $query): Builder => $query->lowStock())
                    ->label('Low Stock'),
                
                Tables\Filters\Filter::make('out_of_stock')
                    ->query(fn (Builder $query): Builder => $query->outOfStock())
                    ->label('Out of Stock'),
                
                Tables\Filters\Filter::make('in_stock')
                    ->query(fn (Builder $query): Builder => $query->inStock())
                    ->label('In Stock'),
            ])
            ->actions([
                EditAction::make()
                    ->visible(fn (): bool => auth()->user()->can('store-stock.edit')),
                
                DeleteAction::make()
                    ->visible(fn (): bool => auth()->user()->can('store-stock.delete')),
                
                Action::make('adjust_stock')
                    ->label('Adjust Stock')
                    ->icon('heroicon-o-pencil')
                    ->color('warning')
                    ->form([
                        \Filament\Forms\Components\Radio::make('adjustment_type')
                            ->label('Adjustment Type')
                            ->options([
                                'increase' => 'Increase Stock',
                                'decrease' => 'Decrease Stock',
                                'set' => 'Set Stock',
                            ])
                            ->required(),
                        
                        \Filament\Forms\Components\TextInput::make('quantity')
                            ->label('Quantity')
                            ->numeric()
                            ->required(),
                        
                        \Filament\Forms\Components\Textarea::make('reason')
                            ->label('Reason')
                            ->rows(3)
                            ->required(),
                    ])
                    ->action(function ($record, array $data) {
                        switch ($data['adjustment_type']) {
                            case 'increase':
                                $record->increaseStock($data['quantity']);
                                break;
                            case 'decrease':
                                $record->decreaseStock($data['quantity']);
                                break;
                            case 'set':
                                $record->update(['quantity' => $data['quantity']]);
                                break;
                        }
                        
                        // Create stock adjustment record
                        \App\Models\StockAdjustment::create([
                            'product_id' => $record->product_id,
                            'store_id' => $record->store_id,
                            'quantity' => $data['quantity'],
                            'adjustment_type' => $data['adjustment_type'],
                            'reason' => $data['reason'],
                            'user_id' => auth()->id(),
                        ]);
                        
                        Notification::make()
                            ->title('Stock adjusted successfully')
                            ->success()
                            ->send();
                    })
                    ->visible(fn (): bool => auth()->user()->can('store-stock.edit')),
                
                Action::make('transfer_stock')
                    ->label('Transfer Stock')
                    ->icon('heroicon-o-arrow-right')
                    ->color('info')
                    ->form([
                        \Filament\Forms\Components\Select::make('destination_store_id')
                            ->label('Destination Store')
                            ->options(\App\Models\Store::where('id', '!=', function ($query) use ($table) {
                                return $query->select('store_id')->from('store_stocks')->where('id', $table->getRecord()->id);
                            })->pluck('name', 'id'))
                            ->required(),
                        
                        \Filament\Forms\Components\TextInput::make('quantity')
                            ->label('Quantity to Transfer')
                            ->numeric()
                            ->required()
                            ->max(fn ($record) => $record->quantity),
                    ])
                    ->action(function ($record, array $data) {
                        $destinationStore = \App\Models\Store::find($data['destination_store_id']);
                        $record->transferTo($destinationStore, $data['quantity']);
                        
                        Notification::make()
                            ->title('Stock transferred successfully')
                            ->success()
                            ->send();
                    })
                    ->visible(fn (): bool => auth()->user()->can('store-stock.edit')),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    DeleteAction::make()
                        ->visible(fn (): bool => auth()->user()->can('store-stock.delete')),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStoreStocks::route('/'),
            'create' => Pages\CreateStoreStock::route('/create'),
            'edit' => Pages\EditStoreStock::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('store-stock.view') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('store-stock.create') ?? false;
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->can('store-stock.edit') ?? false;
    }

    public static function canDelete($record): bool
    {
        return auth()->user()?->can('store-stock.delete') ?? false;
    }

    public static function canDeleteAny(): bool
    {
        return auth()->user()?->can('store-stock.delete') ?? false;
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }
}