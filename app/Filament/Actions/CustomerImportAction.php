<?php

namespace App\Filament\Actions;

use App\Filament\Resources\CustomerResource;

class CustomerImportAction extends BaseImportAction
{
    protected static string $resourceClass = CustomerResource::class;
    
    protected static string $importClass = \App\Imports\CustomerImport::class;
    
    protected static array $importOptions = [
        'avatar_handling' => true,
        'duplicate_prevention' => true,
        'email_phone_validation' => true,
    ];
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->label('Import Customers')
            ->modalHeading('Import Customers')
            ->modalDescription('Import customers from CSV or Excel file. You can create new customers, update existing ones, or both.')
            ->modalWidth('2xl')
            ->extraAttributes([
                'template_url' => route('download.template', ['resource' => 'customers']),
            ]);
    }
}