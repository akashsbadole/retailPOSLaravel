<?php

namespace App\Filament\Resources\SupplierPaymentResource\Pages;

use App\Filament\Resources\SupplierPaymentResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Support\Collection;

class ListSupplierPayments extends ListRecords
{
    protected static string $resource = SupplierPaymentResource::class;
    
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