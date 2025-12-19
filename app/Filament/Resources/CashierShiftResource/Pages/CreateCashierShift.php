<?php

namespace App\Filament\Resources\CashierShiftResource\Pages;

use App\Filament\Resources\CashierShiftResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCashierShift extends CreateRecord
{
    protected static string $resource = CashierShiftResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Check if user already has an open shift
        $existingShift = \App\Models\CashierShift::getOpenShiftForUser(auth()->user());
        if ($existingShift) {
            throw new \Exception('You already have an open shift. Please close it before opening a new one.');
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}