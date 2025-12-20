<?php

namespace App\Filament\Actions;

use App\Filament\Resources\SupplierResource;

class SupplierImportAction extends BaseImportAction
{
    protected static string $resourceClass = SupplierResource::class;
    
    protected static string $importClass = \App\Imports\SupplierImport::class;
    
    protected static array $importOptions = [
        'gst_validation' => true,
        'duplicate_prevention' => true,
        'financial_tracking' => true,
    ];
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->label('Import Suppliers')
            ->modalHeading('Import Suppliers')
            ->modalDescription('Import suppliers from CSV or Excel file. You can create new suppliers, update existing ones, or both.')
            ->modalWidth('2xl')
            ->extraAttributes([
                'template_url' => route('download.template', ['resource' => 'suppliers']),
            ]);
    }
}