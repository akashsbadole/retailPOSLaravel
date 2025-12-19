<?php

namespace App\Filament\Resources\StoreCashierShiftResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Actions\ViewAction;

class OrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'orders';

    protected static ?string $title = 'Shift Orders';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->sortable()
                    ->label('Order ID'),
                
                TextColumn::make('customer.name')
                    ->searchable()
                    ->sortable()
                    ->label('Customer')
                    ->placeholder('Walk-in Customer'),
                
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
                Tables\Filters\Filter::make('pending')
                    ->query(fn (Builder $query): Builder => $query->pending())
                    ->label('Pending'),
                
                Tables\Filters\Filter::make('completed')
                    ->query(fn (Builder $query): Builder => $query->completed())
                    ->label('Completed'),
                
                Tables\Filters\Filter::make('cancelled')
                    ->query(fn (Builder $query): Builder => $query->cancelled())
                    ->label('Cancelled'),
            ])
            ->headerActions([
                //
            ])
            ->actions([
                ViewAction::make()
                    ->visible(fn (): bool => auth()->user()->can('store-order.view')),
            ])
            ->bulkActions([
                //
            ]);
    }
}