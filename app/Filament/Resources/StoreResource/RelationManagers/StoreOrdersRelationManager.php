<?php

namespace App\Filament\Resources\StoreResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\DeleteAction;

class StoreOrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'orders';

    protected static ?string $title = 'Store Orders';

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
            ->headerActions([
                //
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
}