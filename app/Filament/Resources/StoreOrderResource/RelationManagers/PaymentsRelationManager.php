<?php

namespace App\Filament\Resources\StoreOrderResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $title = 'Order Payments';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->sortable()
                    ->label('Payment ID'),
                
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
                CreateAction::make()
                    ->form([
                        Select::make('user_id')
                            ->relationship('user', 'name')
                            ->default(auth()->id())
                            ->required()
                            ->label('Processed By'),
                        
                        TextInput::make('amount')
                            ->numeric()
                            ->prefix('₹')
                            ->required()
                            ->label('Amount'),
                        
                        Select::make('payment_method')
                            ->options([
                                'cash' => 'Cash',
                                'card' => 'Card',
                                'upi' => 'UPI',
                                'bank_transfer' => 'Bank Transfer',
                                'cheque' => 'Cheque',
                            ])
                            ->required()
                            ->label('Payment Method'),
                        
                        Textarea::make('notes')
                            ->label('Notes')
                            ->rows(3),
                    ])
                    ->action(function (array $data) {
                        $this->ownerRecord->payments()->create([
                            'user_id' => $data['user_id'],
                            'store_id' => $this->ownerRecord->store_id,
                            'amount' => $data['amount'],
                            'payment_method' => $data['payment_method'],
                            'notes' => $data['notes'] ?? null,
                        ]);
                        
                        \Filament\Notifications\Notification::make()
                            ->title('Payment added successfully')
                            ->success()
                            ->send();
                    })
                    ->visible(fn (): bool => auth()->user()->can('store-payment.create')),
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