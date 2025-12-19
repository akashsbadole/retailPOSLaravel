<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CashierShiftResource\Pages;
use App\Models\CashierShift;
use App\Models\User;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\DateTimePicker;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\IconEntry;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class CashierShiftResource extends Resource
{
    protected static ?string $model = CashierShift::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    // protected static ?string $navigationGroup = 'Shift Management';

    protected static ?string $navigationLabel = 'Cashier Shifts';

    protected static ?string $modelLabel = 'Cashier Shift';

    protected static ?string $pluralModelLabel = 'Cashier Shifts';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Shift Details')
                    ->description('Manage cashier shift information')
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('user_id')
                                ->label('Cashier')
                                ->relationship('user', 'name')
                                ->searchable()
                                ->default(fn() => Auth::id())
                                ->required()
                                ->disabled(fn($context) => $context === 'edit'),

                            Select::make('status')
                                ->label('Status')
                                ->options([
                                    'open' => 'Open',
                                    'closed' => 'Closed',
                                ])
                                ->required()
                                ->default('open')
                                ->disabled(fn($context) => $context === 'edit'),
                        ]),

                        Grid::make(2)->schema([
                            DateTimePicker::make('start_time')
                                ->label('Start Time')
                                ->required()
                                ->default(now())
                                ->disabled(fn($context) => $context === 'edit'),

                            DateTimePicker::make('end_time')
                                ->label('End Time')
                                ->disabled(fn($context) => $context === 'edit'),
                        ]),

                        Grid::make(2)->schema([
                            TextInput::make('opening_cash')
                                ->label('Opening Cash (₹)')
                                ->numeric()
                                ->prefix('₹')
                                ->step(0.01)
                                ->default(0)
                                ->required(),

                            TextInput::make('closing_cash')
                                ->label('Closing Cash (₹)')
                                ->numeric()
                                ->prefix('₹')
                                ->step(0.01)
                                ->default(0),
                        ]),

                        Section::make('Sales Summary')
                            ->schema([
                                Grid::make(3)->schema([
                                    TextInput::make('total_sales')
                                        ->label('Total Sales (₹)')
                                        ->numeric()
                                        ->prefix('₹')
                                        ->step(0.01)
                                        ->default(0)
                                        ->disabled(),

                                    TextInput::make('cash_collected')
                                        ->label('Cash Sales (₹)')
                                        ->numeric()
                                        ->prefix('₹')
                                        ->step(0.01)
                                        ->default(0)
                                        ->disabled(),

                                    TextInput::make('card_sales')
                                        ->label('Card Sales (₹)')
                                        ->numeric()
                                        ->prefix('₹')
                                        ->step(0.01)
                                        ->default(0)
                                        ->disabled(),
                                ]),

                                Grid::make(2)->schema([
                                    TextInput::make('upi_sales')
                                        ->label('UPI Sales (₹)')
                                        ->numeric()
                                        ->prefix('₹')
                                        ->step(0.01)
                                        ->default(0)
                                        ->disabled(),

                                    Placeholder::make('cash_discrepancy')
                                        ->label('Cash Discrepancy (₹)')
                                        ->content(function (callable $get) {
                                            $openingCash = (float) $get('opening_cash');
                                            $cashCollected = (float) $get('cash_collected');
                                            $closingCash = (float) $get('closing_cash');
                                            $expected = $openingCash + $cashCollected;
                                            $discrepancy = $closingCash - $expected;
                                            return '₹' . number_format($discrepancy, 2);
                                        })
                                        ->color(function (callable $get) {
                                            $openingCash = (float) $get('opening_cash');
                                            $cashCollected = (float) $get('cash_collected');
                                            $closingCash = (float) $get('closing_cash');
                                            $expected = $openingCash + $cashCollected;
                                            $discrepancy = $closingCash - $expected;
                                            return $discrepancy === 0 ? 'success' : 'danger';
                                        }),
                                ]),
                            ])
                            ->collapsible(),

                        Textarea::make('notes')
                            ->label('Notes')
                            ->rows(3)
                            ->placeholder('Enter any additional notes about this shift...'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Cashier')
                    ->searchable()
                    ->sortable()
                    ->description(fn($record): string => $record->user->email ?? ''),

                TextColumn::make('start_time')
                    ->label('Start Time')
                    ->dateTime('M j, Y g:i A')
                    ->sortable()
                    ->description(fn($record): string => $record->start_time->diffForHumans()),

                TextColumn::make('end_time')
                    ->label('End Time')
                    ->dateTime('M j, Y g:i A')
                    ->sortable()
                    ->placeholder('Still Open')
                    ->description(fn($record): string => $record->end_time ? $record->end_time->diffForHumans() : ''),

                TextColumn::make('duration')
                    ->label('Duration')
                    ->getStateUsing(fn($record): string => $record->duration)
                    ->sortable(false),

                IconColumn::make('status')
                    ->label('Status')
                    ->boolean()
                    ->trueLabel('Closed')
                    ->falseLabel('Open')
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-clock')
                    ->color(fn($record): string => $record->status === 'closed' ? 'success' : 'warning'),

                TextColumn::make('total_sales')
                    ->label('Total Sales')
                    ->money('INR')
                    ->sortable()
                    ->summarize(Sum::make()->money('INR')),

                TextColumn::make('cash_discrepancy')
                    ->label('Cash Discrepancy')
                    ->money('INR')
                    ->getStateUsing(fn($record): float => $record->cash_discrepancy)
                    ->color(fn($record): string => $record->cash_discrepancy === 0 ? 'success' : 'danger')
                    ->sortable(false),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'open' => 'Open',
                        'closed' => 'Closed',
                    ]),

                Tables\Filters\SelectFilter::make('user_id')
                    ->label('Cashier')
                    ->options(fn() => User::whereHas('cashierShifts')->pluck('name', 'id')),

                Tables\Filters\Filter::make('start_time')
                    ->form([
                        Forms\Components\DatePicker::make('start_from')
                            ->label('Start From'),
                        Forms\Components\DatePicker::make('start_until')
                            ->label('Start Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['start_from'],
                                fn(Builder $query, $date): Builder => $query->whereDate('start_time', '>=', $date),
                            )
                            ->when(
                                $data['start_until'],
                                fn(Builder $query, $date): Builder => $query->whereDate('start_time', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn($record): bool => $record->isOpen() && auth()->user()->can('cashier-shift.edit')),
                Action::make('close_shift_from_table')
                    ->label('Close Shift')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn($record): bool => $record->isOpen() && auth()->user()->can('cashier-shift.close'))
                    ->form([
                        TextInput::make('closing_cash')
                            ->label('Closing Cash (₹)')
                            ->numeric()
                            ->prefix('₹')
                            ->step(0.01)
                            ->required()
                            ->helperText('Enter the actual cash amount in the drawer'),
                        Textarea::make('notes')
                            ->label('Closing Notes')
                            ->rows(2)
                            ->placeholder('Enter any notes about the shift...'),
                    ])
                    ->action(function (CashierShift $record, array $data) {
                        $record->closeShift($data['closing_cash'], $data['notes'] ?? null);
                        Notification::make()
                            ->title('Shift Closed')
                            ->body('The shift has been closed successfully.')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn(): bool => auth()->user()->can('cashier-shift.delete')),
                ]),
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()
                    ->visible(fn(): bool => auth()->user()->can('cashier-shift.create')),
            ]);
    }


    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCashierShifts::route('/'),
            'create' => Pages\CreateCashierShift::route('/create'),
            'view' => Pages\ViewCashierShift::route('/{record}'),
            'edit' => Pages\EditCashierShift::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()->can('cashier-shift.view');
    }

    public static function canCreate(): bool
    {
        return auth()->user()->can('cashier-shift.create');
    }

    public static function canEdit($record): bool
    {
        return auth()->user()->can('cashier-shift.edit') && $record->isOpen();
    }

    public static function canDelete($record): bool
    {
        return auth()->user()->can('cashier-shift.delete') && $record->isOpen();
    }

    public static function canDeleteAny(): bool
    {
        return auth()->user()->can('cashier-shift.delete');
    }
}
