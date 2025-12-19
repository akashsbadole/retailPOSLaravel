<?php

namespace App\Filament\Resources\StoreOrderResource\Pages;

use App\Filament\Resources\StoreOrderResource;
use Filament\Resources\Pages\EditRecord;

class EditStoreOrder extends EditRecord
{
    protected static string $resource = StoreOrderResource::class;

    protected function getRedirectUrl(): string
    {
        $storeId = $this->record->store_id;
        return $this->getResource()::getUrl('index', ['store_id' => $storeId]);
    }
}