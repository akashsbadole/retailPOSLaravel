<?php

namespace App\Filament\Resources\PurchaseResource\Pages;

use App\Filament\Resources\PurchaseResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Support\Collection;

class ListPurchases extends ListRecords
{
    protected static string $resource = PurchaseResource::class;
    
    public function getTableRecords(): Collection|Paginator|CursorPaginator
    {
        $records = parent::getTableRecords();
        
        // Filter by supplier_id if provided in query params
        if (request()->has('tableFilters.supplier_id')) {
            $records = $records->where('supplier_id', request()->input('tableFilters.supplier_id'));
        }
        
        return $records;
    }
}