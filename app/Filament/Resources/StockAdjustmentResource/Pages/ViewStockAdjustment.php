<?php

namespace App\Filament\Resources\StockAdjustmentResource\Pages;

use App\Filament\Resources\StockAdjustmentResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewStockAdjustment extends ViewRecord
{
    protected static string $resource = StockAdjustmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->visible(fn ($record): bool => !$record->approved && auth()->user()->can('stock-adjustment.edit')),
            Actions\DeleteAction::make()
                ->visible(fn ($record): bool => !$record->approved && auth()->user()->can('stock-adjustment.delete')),
            Actions\Action::make('approve')
                ->label('Approve')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn ($record): bool => !$record->approved && auth()->user()->can('stock-adjustment.approve'))
                ->action(function ($record) {
                    $record->approve();
                    $this->refresh();
                    
                    \Filament\Notifications\Notification::make()
                        ->title('Stock adjustment approved')
                        ->body('The stock adjustment has been approved and applied.')
                        ->success()
                        ->send();
                }),
        ];
    }
}