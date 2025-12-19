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

class StorePaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $title = 'Store Payments';

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
                            $indicators['created_from'] = 'Payment from: ' . $data['created_from'];
                        }
                        if ($data['created_until'] ?? null) {
                            $indicators['created_until'] = 'Payment until: ' . $data['created_until'];
                        }
                        return $indicators;
                    }),
            ])
            ->headerActions([
                //
            ])
            ->actions([
                ViewAction::make()
                    ->visible(fn (): bool => auth()->user()->can('store-payment.view')),
                
                EditAction::make()
                    ->visible(fn (): bool => auth()->user()->can('store-payment.edit')),
                
                DeleteAction::make()
                    ->visible(fn (): bool => auth()->user()->can('store-payment.delete')),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    DeleteAction::make()
                        ->visible(fn (): bool => auth()->user()->can('store-payment.delete')),
                ]),
            ]);
    }
}