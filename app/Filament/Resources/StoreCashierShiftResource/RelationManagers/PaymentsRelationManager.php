<?php

namespace App\Filament\Resources\StoreCashierShiftResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Actions\ViewAction;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $title = 'Shift Payments';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->sortable()
                    ->label('Payment ID'),
                
                TextColumn::make('storeOrder.id')
                    ->searchable()
                    ->sortable()
                    ->label('Order ID'),
                
                TextColumn::make('user.name')
                    ->searchable()
                    ->sortable()
                    ->label('Processed By'),
                
                TextColumn::make('formatted_amount')
                    ->label('Amount')
                    ->sortable(),
                
                TextColumn::make('payment_method_text')
                    ->label('Payment Method')
                    ->badge()
                    ->color(fn ($record): string => $record->payment_method_badge_color),
                
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->label('Payment Date'),
            ])
            ->filters([
                Tables\Filters\Filter::make('cash')
                    ->query(fn (Builder $query): Builder => $query->cash())
                    ->label('Cash'),
                
                Tables\Filters\Filter::make('card')
                    ->query(fn (Builder $query): Builder => $query->card())
                    ->label('Card'),
                
                Tables\Filters\Filter::make('upi')
                    ->query(fn (Builder $query): Builder => $query->upi())
                    ->label('UPI'),
            ])
            ->headerActions([
                //
            ])
            ->actions([
                ViewAction::make()
                    ->visible(fn (): bool => auth()->user()->can('store-payment.view')),
            ])
            ->bulkActions([
                //
            ]);
    }
}