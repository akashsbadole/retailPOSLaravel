<?php

namespace App\Filament\Resources\StoreCashierShiftResource\Pages;

use App\Filament\Resources\StoreCashierShiftResource;
use Filament\Resources\Pages\ListRecords;

class ListStoreCashierShifts extends ListRecords
{
    protected static string $resource = StoreCashierShiftResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\CreateAction::make(),
        ];
    }

    protected function getTableRecordsPerPageSelectOptions(): array
    {
        return [10, 25, 50, 100];
    }
}