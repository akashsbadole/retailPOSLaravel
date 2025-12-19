<?php

namespace App\Filament\Resources\SalesReturnResource\Pages;

use App\Filament\Resources\SalesReturnResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewSalesReturn extends ViewRecord
{
    protected static string $resource = SalesReturnResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->visible(fn ($record) => $record->isPending()),
            Actions\Action::make('approve')
                ->label('Approve')
                ->icon('heroicon-o-check')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn ($record) => $record->canBeApproved())
                ->action(function ($record) {
                    $record->approve();
                }),
            Actions\Action::make('reject')
                ->label('Reject')
                ->icon('heroicon-o-x-mark')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn ($record) => $record->canBeRejected())
                ->action(function ($record) {
                    $record->reject();
                }),
            Actions\Action::make('process')
                ->label('Process')
                ->icon('heroicon-o-cog')
                ->color('info')
                ->requiresConfirmation()
                ->visible(fn ($record) => $record->canBeProcessed())
                ->action(function ($record) {
                    $record->process();
                }),
            Actions\Action::make('complete')
                ->label('Complete')
                ->icon('heroicon-o-check-circle')
                ->color('primary')
                ->requiresConfirmation()
                ->visible(fn ($record) => $record->isProcessed())
                ->action(function ($record) {
                    $record->complete();
                }),
        ];
    }
}