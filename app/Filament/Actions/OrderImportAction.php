<?php

namespace App\Filament\Actions;

use App\Filament\Resources\OrderResource;

class OrderImportAction extends BaseImportAction
{
    protected static string $resourceClass = OrderResource::class;
    
    protected static string $importClass = \App\Imports\OrderImport::class;
    
    protected static array $importOptions = [
        'customer_relationship_handling' => true,
        'product_relationship_handling' => true,
        'order_items_processing' => true,
    ];
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->label('Import Orders')
            ->modalHeading('Import Orders')
            ->modalDescription('Import orders from CSV or Excel file. You can create new orders, update existing ones, or both.')
            ->modalWidth('2xl')
            ->extraAttributes([
                'template_url' => route('download.template', ['resource' => 'orders']),
            ]);
    }
}