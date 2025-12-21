<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StoreCashierShiftResource\Pages;
use App\Filament\Resources\StoreCashierShiftResource\RelationManagers;
use App\Models\StoreCashierShift;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\DateTimePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class StoreCashierShiftResource extends Resource
{
    protected static ?string $model = StoreCashierShift::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected static string|\UnitEnum|null $navigationGroup = 'Store Management';

    protected static ?int $navigationSort = 4;

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
                    })
                    ->disabled(fn ($context) => $context === 'edit'),
                
                Select::make('user_id')
                    ->relationship('user', 'name')
                    ->required()
                    ->label('Cashier')
                    ->default(auth()->id())
                    ->disabled(fn ($context) => $context === 'edit'),
                
                DateTimePicker::make('start_time')
                    ->required()
                    ->label('Start Time')
                    ->default(now())
                    ->disabled(fn ($context) => $context === 'edit'),
                
                DateTimePicker::make('end_time')
                    ->label('End Time')
                    ->disabled(fn ($context) => $context === 'edit'),
                
                TextInput::make('opening_cash')
                    ->required()
                    ->numeric()
                    ->prefix('₹')
                    ->label('Opening Cash')
                    ->default(0),
                
                TextInput::make('closing_cash')
                    ->numeric()
                    ->prefix('₹')
                    ->label('Closing Cash')
                    ->disabled(fn ($context) => $context === 'edit'),
                
                TextInput::make('total_sales')
                    ->numeric()
                    ->prefix('₹')
                    ->label('Total Sales')
                    ->default(0),
                
                TextInput::make('cash_collected')
                    ->numeric()
                    ->prefix('₹')
                    ->label('Cash Collected')
                    ->default(0),
                
                TextInput::make('card_sales')
                    ->numeric()
                    ->prefix('₹')
                    ->label('Card Sales')
                    ->default(0),
                
                TextInput::make('upi_sales')
                    ->numeric()
                    ->prefix('₹')
                    ->label('UPI Sales')
                    ->default(0),
                
                Select::make('status')
                    ->options([
                        'active' => 'Active',
                        'closed' => 'Closed',
                        'cancelled' => 'Cancelled',
                    ])
                    ->required()
                    ->label('Status'),
                
                Textarea::make('notes')
                    ->label('Notes')
                    ->rows(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->sortable()
                    ->label('Shift ID'),
                
                TextColumn::make('store.name')
                    ->searchable()
                    ->sortable()
                    ->label('Store'),
                
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
                
                TextColumn::make('formatted_cash_difference')
                    ->label('Cash Difference')
                    ->sortable()
                    ->color(fn ($record): string => $record->cash_difference >= 0 ? 'success' : 'danger'),
                
                TextColumn::make('status_text')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($record): string => $record->status_badge_color),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('store_id')
                    ->relationship('store', 'name')
                    ->label('Store')
                    ->default(function () {
                        return auth()->user()->store_id;
                    }),
                
                Tables\Filters\SelectFilter::make('user_id')
                    ->relationship('user', 'name')
                    ->label('Cashier'),
                
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
            ->actions([
                EditAction::make()
                    ->visible(fn (): bool => auth()->user()->can('store-cashier-shift.edit')),
                
                Action::make('close_shift')
                    ->label('Close Shift')
                    ->icon('heroicon-o-stop')
                    ->color('danger')
                    ->form([
                        TextInput::make('closing_cash')
                            ->label('Closing Cash')
                            ->numeric()
                            ->required(),
                        
                        Textarea::make('notes')
                            ->label('Notes')
                            ->rows(3),
                    ])
                    ->action(function ($record, array $data) {
                        $record->close($data['closing_cash'], $data['notes'] ?? null);
                        
                        Notification::make()
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStoreCashierShifts::route('/'),
            'create' => Pages\CreateStoreCashierShift::route('/create'),
            'edit' => Pages\EditStoreCashierShift::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('store-cashier-shift.view') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('store-cashier-shift.create') ?? false;
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->can('store-cashier-shift.edit') ?? false;
    }

    public static function canDelete($record): bool
    {
        return auth()->user()?->can('store-cashier-shift.delete') ?? false;
    }

    public static function canDeleteAny(): bool
    {
        return auth()->user()?->can('store-cashier-shift.delete') ?? false;
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\OrdersRelationManager::class,
            RelationManagers\PaymentsRelationManager::class,
        ];
    }
}