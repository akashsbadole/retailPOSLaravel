<?php

namespace App\Filament\Resources\SalesReturnResource\Pages;

use App\Filament\Resources\SalesReturnResource;
use App\Models\Order;
use App\Services\SalesReturnService;
use Filament\Resources\Pages\CreateRecord;
use Filament\Forms;
use Illuminate\Support\Facades\Auth;

class CreateSalesReturn extends CreateRecord
{
    protected static string $resource = SalesReturnResource::class;

    public function mount(): void
    {
        parent::mount();
        
        // If order_id is passed in URL, pre-fill the form
        if (request()->has('order_id')) {
            $orderId = request()->get('order_id');
            $order = Order::find($orderId);
            
            if ($order) {
                $this->form->fill([
                    'order_id' => $orderId,
                    'return_date' => now()->format('Y-m-d'),
                ]);
            }
        }
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = Auth::id();
        
        // Calculate total refund amount from items
        $totalRefundAmount = 0;
        if (isset($data['items'])) {
            foreach ($data['items'] as $item) {
                $totalRefundAmount += $item['refund_amount'];
            }
        }
        $data['total_refund_amount'] = $totalRefundAmount;
        
        // Set status based on approval requirements
        $salesReturnService = app(SalesReturnService::class);
        if (!$salesReturnService->isApprovalRequired()) {
            $data['status'] = 'approved';
            $data['approved_by'] = Auth::id();
            $data['approved_at'] = now();
        } else {
            $data['status'] = 'pending';
        }
        
        return $data;
    }

    protected function afterCreate(): void
    {
        // Update the total refund amount after creating the record
        $this->record->updateTotalRefundAmount();
        
        // If auto stock adjustment is enabled and return is approved
        $salesReturnService = app(SalesReturnService::class);
        if ($this->record->isApproved() && $salesReturnService->isAutoStockAdjustmentEnabled()) {
            $salesReturnService->processStockAdjustments($this->record);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}