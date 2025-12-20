<?php

namespace App\Filament\Actions;

use App\Filament\Resources\SupplierResource;

class SupplierExportAction extends BaseExportAction
{
    protected static string $resourceClass = SupplierResource::class;
    
    protected static string $exportClass = \App\Exports\SupplierExport::class;
    
    protected static array $availableColumns = [
        'id' => 'ID',
        'name' => 'Name',
        'contact_person' => 'Contact Person',
        'email' => 'Email',
        'phone' => 'Phone',
        'address' => 'Address',
        'gst_number' => 'GST Number',
        'status' => 'Status',
        'total_purchases' => 'Total Purchases',
        'total_paid' => 'Total Paid',
        'total_due' => 'Total Due',
        'created_at' => 'Created At',
        'updated_at' => 'Updated At',
    ];
    
    protected static array $exportOptions = [
        'store_filter' => false,
        'date_range' => true,
        'column_selection' => true,
        'relations' => false,
    ];
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->label('Export Suppliers')
            ->modalHeading('Export Suppliers')
            ->modalDescription('Export suppliers to CSV, Excel, or JSON format.')
            ->modalWidth('2xl');
    }
    
    protected static function supportsDateRange(): bool
    {
        return true;
    }
}