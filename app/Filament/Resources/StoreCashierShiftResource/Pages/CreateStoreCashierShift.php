<?php

namespace App\Filament\Resources\StoreCashierShiftResource\Pages;

use App\Filament\Resources\StoreCashierShiftResource;
use Filament\Resources\Pages\CreateRecord;

class CreateStoreCashierShift extends CreateRecord
{
    protected static string $resource = StoreCashierShiftResource::class;

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