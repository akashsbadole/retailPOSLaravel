<?php

namespace App\Filament\Resources\StoreOrderResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\DeleteAction;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Order Items';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('product.name')
                    ->searchable()
                    ->sortable()
                    ->label('Product'),
                
                TextColumn::make('product.sku')
                    ->searchable()
                    ->sortable()
                    ->label('SKU'),
                
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->label('Item Name'),
                
                TextColumn::make('formatted_price')
                    ->label('Price')
                    ->sortable(),
                
                TextColumn::make('quantity')
                    ->sortable()
                    ->label('Quantity'),
                
                TextColumn::make('formatted_subtotal')
                    ->label('Subtotal')
                    ->sortable(),
                
                TextColumn::make('formatted_discount_amount')
                    ->label('Discount')
                    ->sortable(),
                
                TextColumn::make('formatted_tax')
                    ->label('Tax')
                    ->sortable(),
                
                TextColumn::make('formatted_total')
                    ->label('Total')
                    ->sortable(),
                
                TextColumn::make('formatted_profit')
                    ->label('Profit')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                //
            ])
            ->actions([
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
}