<?php

namespace App\Filament\Actions;

use App\Filament\Resources\CustomerResource;

class CustomerExportAction extends BaseExportAction
{
    protected static string $resourceClass = CustomerResource::class;
    
    protected static string $exportClass = \App\Exports\CustomerExport::class;
    
    protected static array $availableColumns = [
        'id' => 'ID',
        'first_name' => 'First Name',
        'last_name' => 'Last Name',
        'email' => 'Email',
        'phone' => 'Phone',
        'address' => 'Address',
        'avatar' => 'Avatar URL',
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
        
        $this->label('Export Customers')
            ->modalHeading('Export Customers')
            ->modalDescription('Export customers to CSV, Excel, or JSON format.')
            ->modalWidth('2xl');
    }
    
    protected static function supportsDateRange(): bool
    {
        return true;
    }
}