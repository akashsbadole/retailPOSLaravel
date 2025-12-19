<?php

namespace App\Filament\Resources\StoreStockResource\Pages;

use App\Filament\Resources\StoreStockResource;
use Filament\Resources\Pages\ListRecords;

class ListStoreStocks extends ListRecords
{
    protected static string $resource = StoreStockResource::class;

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