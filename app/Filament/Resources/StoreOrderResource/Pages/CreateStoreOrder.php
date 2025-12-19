<?php

namespace App\Filament\Resources\StoreOrderResource\Pages;

use App\Filament\Resources\StoreOrderResource;
use Filament\Resources\Pages\CreateRecord;

class CreateStoreOrder extends CreateRecord
{
    protected static string $resource = StoreOrderResource::class;

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

    protected function afterCreate(): void
    {
        // Update stock quantities after order is created
        foreach ($this->record->items as $item) {
            $storeStock = \App\Models\StoreStock::where('store_id', $this->record->store_id)
                ->where('product_id', $item->product_id)
                ->first();
            
            if ($storeStock) {
                $storeStock->decreaseStock($item->quantity);
            }
        }
    }

    protected function getRedirectUrl(): string
    {
        $storeId = $this->record->store_id;
        return $this->getResource()::getUrl('index', ['store_id' => $storeId]);
    }
}