<?php

namespace App\Filament\Resources\PurchaseResource\Pages;

use App\Filament\Resources\PurchaseResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePurchase extends CreateRecord
{
    protected static string $resource = PurchaseResource::class;
    
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Set default values
        $data['status'] = 'pending';
        $data['payment_status'] = 'unpaid';
        
        return $data;
    }
}