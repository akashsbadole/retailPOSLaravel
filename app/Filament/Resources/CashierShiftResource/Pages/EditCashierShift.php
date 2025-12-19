<?php

namespace App\Filament\Resources\CashierShiftResource\Pages;

use App\Filament\Resources\CashierShiftResource;
use Filament\Resources\Pages\EditRecord;

class EditCashierShift extends EditRecord
{
    protected static string $resource = CashierShiftResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Prevent editing closed shifts
        if ($this->record->isClosed()) {
            throw new \Exception('Cannot edit a closed shift.');
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}