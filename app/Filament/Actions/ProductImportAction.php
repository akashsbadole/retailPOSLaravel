<?php

namespace App\Filament\Actions;

use App\Filament\Resources\ProductResource;

class ProductImportAction extends BaseImportAction
{
    protected static string $resourceClass = ProductResource::class;
    
    protected static string $importClass = \App\Imports\ProductImport::class;
    
    protected static array $importOptions = [
        'barcode_generation' => true,
        'image_handling' => true,
        'duplicate_prevention' => true,
    ];
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->label('Import Products')
            ->modalHeading('Import Products')
            ->modalDescription('Import products from CSV or Excel file. You can create new products, update existing ones, or both.')
            ->modalWidth('2xl')
            ->extraAttributes([
                'template_url' => route('download.template', ['resource' => 'products']),
            ]);
    }
}