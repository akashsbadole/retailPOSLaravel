<?php

namespace App\Filament\Resources\StoreStockResource\Pages;

use App\Filament\Resources\StoreStockResource;
use Filament\Resources\Pages\CreateRecord;

class CreateStoreStock extends CreateRecord
{
    protected static string $resource = StoreStockResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Set default store_id from query parameter or user's store
        if (!isset($data['store_id'])) {
            $storeId = request()->get('store_id');
            if ($storeId) {
                $data['store_id'] = $storeId;
            } elseif (auth()->user()->store_id) {
                $data['store_id'] = auth()->user()->store_id;
            }
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        $storeId = $this->record->store_id;
        return $this->getResource()::getUrl('index', ['store_id' => $storeId]);
    }
}