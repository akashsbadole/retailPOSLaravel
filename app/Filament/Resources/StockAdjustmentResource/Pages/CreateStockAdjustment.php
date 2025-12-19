<?php

namespace App\Filament\Resources\StockAdjustmentResource\Pages;

use App\Filament\Resources\StockAdjustmentResource;
use App\Models\Product;
use App\Models\StockAdjustment;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Filament\Notifications\Notification;

class CreateStockAdjustment extends CreateRecord
{
    protected static string $resource = StockAdjustmentResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Get the product to calculate previous and new quantities
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

        // Set approval status based on settings
        $data['approved'] = !config('settings.stock_adjustment_require_approval', false);
        
        if ($data['approved']) {
            $data['approved_by'] = auth()->id();
            $data['approved_at'] = now();
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var StockAdjustment $record */
        $record = $this->record;
        
        // If approved, update the product stock
        if ($record->approved) {
            $product = $record->product;
            $product->quantity = $record->new_quantity;
            $product->save();
        }

        Notification::make()
            ->title('Stock adjustment created')
            ->body($record->approved 
                ? 'The stock adjustment has been created and applied to the product stock.' 
                : 'The stock adjustment has been created and is pending approval.')
            ->success()
            ->send();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}