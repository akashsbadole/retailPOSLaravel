<?php

namespace App\Exports;

use App\Models\Purchase;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PurchaseExport implements FromCollection, WithHeadings, WithMapping, WithColumnFormatting, WithStyles
{
    protected ?int $storeId = null;
    protected ?string $dateFrom = null;
    protected ?string $dateTo = null;
    protected array $columns = [];
    protected bool $includeRelations = false;
    protected ?\App\Models\ImportExportJob $job = null;

    public function __construct(
        ?int $storeId = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        array $columns = [],
        bool $includeRelations = false
    ) {
        $this->storeId = $storeId;
        $this->dateFrom = $dateFrom;
        $this->dateTo = $dateTo;
        $this->columns = empty($columns) ? $this->getDefaultColumns() : $columns;
        $this->includeRelations = $includeRelations;
    }

    public function withJob(\App\Models\ImportExportJob $job): self
    {
        $this->job = $job;
        return $this;
    }

    public function collection()
    {
        $query = Purchase::with(['supplier']);

        // Apply date range filter if specified
        if ($this->dateFrom) {
            $query->whereDate('purchase_date', '>=', $this->dateFrom);
        }

        if ($this->dateTo) {
            $query->whereDate('purchase_date', '<=', $this->dateTo);
        }

        $purchases = $query->get();

        // Update job with total records
        if ($this->job) {
            $this->job->update(['total_records' => $purchases->count()]);
        }

        return $purchases;
    }

    public function headings(): array
    {
        $headings = [];
        
        foreach ($this->columns as $column) {
            $headings[] = $this->getColumnHeading($column);
        }

        return $headings;
    }

    public function map($purchase): array
    {
        $row = [];
        
        foreach ($this->columns as $column) {
            $row[] = $this->getColumnValue($purchase, $column);
        }

        // Update job progress
        if ($this->job) {
            $this->job->updateProgress($this->job->processed_records + 1);
        }

        return $row;
    }

    protected function getColumnHeading(string $column): string
    {
        $headings = [
            'id' => 'ID',
            'supplier_name' => 'Supplier Name',
            'supplier_email' => 'Supplier Email',
            'supplier_phone' => 'Supplier Phone',
            'purchase_date' => 'Purchase Date',
            'total_amount' => 'Total Amount',
            'discount_amount' => 'Discount Amount',
            'tax_amount' => 'Tax Amount',
            'final_amount' => 'Final Amount',
            'status' => 'Status',
            'payment_status' => 'Payment Status',
            'items_count' => 'Items Count',
            'total_paid' => 'Total Paid',
            'remaining_amount' => 'Remaining Amount',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
        ];

        return $headings[$column] ?? ucfirst(str_replace('_', ' ', $column));
    }

    protected function getColumnValue(Purchase $purchase, string $column)
    {
        switch ($column) {
            case 'id':
                return $purchase->id;
            case 'supplier_name':
                return $purchase->supplier ? $purchase->supplier->name : null;
            case 'supplier_email':
                return $purchase->supplier ? $purchase->supplier->email : null;
            case 'supplier_phone':
                return $purchase->supplier ? $purchase->supplier->phone : null;
            case 'purchase_date':
                return $purchase->purchase_date->format('Y-m-d');
            case 'total_amount':
                return $purchase->total_amount;
            case 'discount_amount':
                return $purchase->discount_amount;
            case 'tax_amount':
                return $purchase->tax_amount;
            case 'final_amount':
                return $purchase->final_amount;
            case 'status':
                return ucfirst($purchase->status);
            case 'payment_status':
                return ucfirst($purchase->payment_status);
            case 'items_count':
                return $purchase->items ? $purchase->items->count() : 0;
            case 'total_paid':
                return $purchase->total_paid;
            case 'remaining_amount':
                return $purchase->remaining_amount;
            case 'created_at':
                return $purchase->created_at->format('Y-m-d H:i:s');
            case 'updated_at':
                return $purchase->updated_at->format('Y-m-d H:i:s');
            default:
                return $purchase->{$column} ?? null;
        }
    }

    protected function getDefaultColumns(): array
    {
        return [
            'id',
            'supplier_name',
            'supplier_email',
            'purchase_date',
            'total_amount',
            'discount_amount',
            'tax_amount',
            'final_amount',
            'status',
            'payment_status',
            'created_at',
        ];
    }

    public function columnFormats(): array
    {
        return [
            'A' => '@', // ID as text
            'B' => '@', // Supplier Name as text
            'C' => '@', // Supplier Email as text
            'D' => '@', // Supplier Phone as text
            'E' => 'yyyy-mm-dd', // Purchase Date
            'F' => '#,##0.00', // Total Amount
            'G' => '#,##0.00', // Discount Amount
            'H' => '#,##0.00', // Tax Amount
            'I' => '#,##0.00', // Final Amount
            'J' => '#,##0.00', // Total Paid
            'K' => '#,##0.00', // Remaining Amount
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            // Style the first row as bold text
            1 => ['font' => ['bold' => true]],
            
            // Auto-size columns
            'A' => ['autosize' => true],
            'B' => ['autosize' => true],
            'C' => ['autosize' => true],
            'D' => ['autosize' => true],
            'E' => ['autosize' => true],
            'F' => ['autosize' => true],
            'G' => ['autosize' => true],
            'H' => ['autosize' => true],
            'I' => ['autosize' => true],
            'J' => ['autosize' => true],
            'K' => ['autosize' => true],
            'L' => ['autosize' => true],
            'M' => ['autosize' => true],
            'N' => ['autosize' => true],
        ];
    }
}