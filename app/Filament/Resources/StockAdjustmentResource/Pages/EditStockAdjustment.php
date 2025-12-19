<?php

namespace App\Filament\Resources\StockAdjustmentResource\Pages;

use App\Filament\Resources\StockAdjustmentResource;
use App\Models\Product;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Filament\Notifications\Notification;

class EditStockAdjustment extends EditRecord
{
    protected static string $resource = StockAdjustmentResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Convert negative quantity back to positive for form display
        if ($data['quantity'] < 0) {
            $data['quantity'] = abs($data['quantity']);
        }
        
        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Get the product to calculate new quantities
        $product = Product::find($data['product_id']);
        if (!$product) {
            throw new \Exception('Product not found');
        }

        $data['previous_quantity'] = $product->quantity;
        
        // Calculate new quantity based on adjustment type
        if ($data['adjustment_type'] === 'increase') {
            $data['new_quantity'] = $product->quantity + $data['quantity'];
            $data['quantity'] = $data['quantity']; // Keep positive for increase
        } else {
            $data['new_quantity'] = $product->quantity - $data['quantity'];
            $data['quantity'] = -$data['quantity']; // Make negative for decrease
        }

        // Reset approval status if adjustment is modified
        $data['approved'] = false;
        $data['approved_by'] = null;
        $data['approved_at'] = null;

        return $data;
    }

    protected function afterSave(): void
    {
        Notification::make()
            ->title('Stock adjustment updated')
            ->body('The stock adjustment has been updated and is pending approval.')
            ->success()
            ->send();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}