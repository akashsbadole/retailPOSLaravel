<?php

namespace App\Filament\Resources\SalesReturnResource\Pages;

use App\Filament\Resources\SalesReturnResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSalesReturn extends EditRecord
{
    protected static string $resource = SalesReturnResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Calculate total refund amount from items
        $totalRefundAmount = 0;
        if (isset($data['items'])) {
            foreach ($data['items'] as $item) {
                $totalRefundAmount += $item['refund_amount'];
            }
        }
        $data['total_refund_amount'] = $totalRefundAmount;
        
        return $data;
    }

    protected function afterSave(): void
    {
        // Update the total refund amount after saving the record
        $this->record->updateTotalRefundAmount();
    }
}