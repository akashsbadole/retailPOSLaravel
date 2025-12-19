<?php

namespace App\Filament\Resources\CashierShiftResource\Pages;

use App\Filament\Resources\CashierShiftResource;
use Filament\Resources\Pages\ViewRecord;

class ViewCashierShift extends ViewRecord
{
    protected static string $resource = CashierShiftResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\EditAction::make()
                ->visible(fn ($record): bool => $record->isOpen()),
            \Filament\Actions\Action::make('close_shift_from_view')
                ->label('Close Shift')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn ($record): bool => $record->isOpen() && auth()->user()->can('cashier-shift.close'))
                ->form([
                    \Filament\Forms\Components\TextInput::make('closing_cash')
                        ->label('Closing Cash (₹)')
                        ->numeric()
                        ->prefix('₹')
                        ->step(0.01)
                        ->required()
                        ->helperText('Enter the actual cash amount in the drawer'),
                    \Filament\Forms\Components\Textarea::make('notes')
                        ->label('Closing Notes')
                        ->rows(2)
                        ->placeholder('Enter any notes about the shift...'),
                ])
                ->action(function (array $data) {
                    $this->record->closeShift($data['closing_cash'], $data['notes'] ?? null);
                    \Filament\Notifications\Notification::make()
                        ->title('Shift Closed')
                        ->body('The shift has been closed successfully.')
                        ->success()
                        ->send();
                }),
        ];
    }
}