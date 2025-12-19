<?php

namespace App\Filament\Resources\StoreResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\Action;

class StoreStocksRelationManager extends RelationManager
{
    protected static string $relationship = 'stocks';

    protected static ?string $title = 'Store Inventory';

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
            ->headerActions([
                CreateAction::make()
                    ->visible(fn (): bool => auth()->user()->can('store-stock.create')),
                
                Action::make('import_products')
                    ->label('Import Products')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('info')
                    ->action(function () {
                        // This would open a modal to import products to store
                        // Implementation would depend on requirements
                    })
                    ->visible(fn (): bool => auth()->user()->can('store-stock.create')),
            ])
            ->actions([
                EditAction::make()
                    ->visible(fn (): bool => auth()->user()->can('store-stock.edit')),
                
                DeleteAction::make()
                    ->visible(fn (): bool => auth()->user()->can('store-stock.delete')),
                
                Action::make('transfer_stock')
                    ->label('Transfer Stock')
                    ->icon('heroicon-o-arrow-right')
                    ->color('warning')
                    ->form([
                        \Filament\Forms\Components\Select::make('destination_store_id')
                            ->label('Destination Store')
                            ->options(\App\Models\Store::where('id', '!=', $this->ownerRecord->id)->pluck('name', 'id'))
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
                        
                        \Filament\Notifications\Notification::make()
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
}