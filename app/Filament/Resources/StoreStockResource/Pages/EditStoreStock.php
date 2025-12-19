<?php

namespace App\Filament\Resources\StoreStockResource\Pages;

use App\Filament\Resources\StoreStockResource;
use Filament\Resources\Pages\EditRecord;

class EditStoreStock extends EditRecord
{
    protected static string $resource = StoreStockResource::class;

    protected function getRedirectUrl(): string
    {
        $storeId = $this->record->store_id;
        return $this->getResource()::getUrl('index', ['store_id' => $storeId]);
    }
}