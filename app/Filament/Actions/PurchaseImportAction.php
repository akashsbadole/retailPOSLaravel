<?php

namespace App\Filament\Actions;

use App\Filament\Resources\PurchaseResource;

class PurchaseImportAction extends BaseImportAction
{
    protected static string $resourceClass = PurchaseResource::class;
    
    protected static string $importClass = \App\Imports\PurchaseImport::class;
    
    protected static array $importOptions = [
        'supplier_relationship_handling' => true,
        'product_relationship_handling' => true,
        'payment_status_management' => true,
    ];
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->label('Import Purchases')
            ->modalHeading('Import Purchases')
            ->modalDescription('Import purchases from CSV or Excel file. You can create new purchases, update existing ones, or both.')
            ->modalWidth('2xl')
            ->extraAttributes([
                'template_url' => route('download.template', ['resource' => 'purchases']),
            ]);
    }
}