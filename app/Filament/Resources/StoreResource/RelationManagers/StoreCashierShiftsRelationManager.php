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
use Filament\Tables\Actions\Action;

class StoreCashierShiftsRelationManager extends RelationManager
{
    protected static string $relationship = 'cashierShifts';

    protected static ?string $title = 'Cashier Shifts';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->sortable()
                    ->label('Shift ID'),
                
                TextColumn::make('user.name')
                    ->searchable()
                    ->sortable()
                    ->label('Cashier'),
                
                TextColumn::make('start_time')
                    ->dateTime()
                    ->sortable()
                    ->label('Start Time'),
                
                TextColumn::make('end_time')
                    ->dateTime()
                    ->sortable()
                    ->label('End Time')
                    ->placeholder('Active'),
                
                TextColumn::make('duration')
                    ->label('Duration (Hours)')
                    ->sortable(),
                
                TextColumn::make('formatted_opening_cash')
                    ->label('Opening Cash')
                    ->sortable(),
                
                TextColumn::make('formatted_total_sales')
                    ->label('Total Sales')
                    ->sortable(),
                
                TextColumn::make('formatted_cash_collected')
                    ->label('Cash Collected')
                    ->sortable(),
                
                TextColumn::make('status_text')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($record): string => $record->status_badge_color),
            ])
            ->filters([
                Tables\Filters\Filter::make('active')
                    ->query(fn (Builder $query): Builder => $query->active())
                    ->label('Active'),
                
                Tables\Filters\Filter::make('closed')
                    ->query(fn (Builder $query): Builder => $query->closed())
                    ->label('Closed'),
                
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
                            $indicators['created_from'] = 'Shift from: ' . $data['created_from'];
                        }
                        if ($data['created_until'] ?? null) {
                            $indicators['created_until'] = 'Shift until: ' . $data['created_until'];
                        }
                        return $indicators;
                    }),
            ])
            ->headerActions([
                //
            ])
            ->actions([
                ViewAction::make()
                    ->visible(fn (): bool => auth()->user()->can('store-cashier-shift.view')),
                
                EditAction::make()
                    ->visible(fn (): bool => auth()->user()->can('store-cashier-shift.edit')),
                
                Action::make('close_shift')
                    ->label('Close Shift')
                    ->icon('heroicon-o-stop')
                    ->color('danger')
                    ->form([
                        \Filament\Forms\Components\TextInput::make('closing_cash')
                            ->label('Closing Cash')
                            ->numeric()
                            ->required(),
                        
                        \Filament\Forms\Components\Textarea::make('notes')
                            ->label('Notes')
                            ->rows(3),
                    ])
                    ->action(function ($record, array $data) {
                        $record->close($data['closing_cash'], $data['notes'] ?? null);
                        
                        \Filament\Notifications\Notification::make()
                            ->title('Shift closed successfully')
                            ->success()
                            ->send();
                    })
                    ->visible(fn ($record): bool => auth()->user()->can('store-cashier-shift.edit') && $record->is_active),
                
                DeleteAction::make()
                    ->visible(fn (): bool => auth()->user()->can('store-cashier-shift.delete')),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    DeleteAction::make()
                        ->visible(fn (): bool => auth()->user()->can('store-cashier-shift.delete')),
                ]),
            ]);
    }
}