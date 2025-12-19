<?php

namespace App\Filament\Resources\StoreCashierShiftResource\Pages;

use App\Filament\Resources\StoreCashierShiftResource;
use Filament\Resources\Pages\EditRecord;

class EditStoreCashierShift extends EditRecord
{
    protected static string $resource = StoreCashierShiftResource::class;

    protected function getRedirectUrl(): string
    {
        $storeId = $this->record->store_id;
        return $this->getResource()::getUrl('index', ['store_id' => $storeId]);
    }
}