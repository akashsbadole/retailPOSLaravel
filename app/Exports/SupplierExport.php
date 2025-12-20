<?php

namespace App\Exports;

use App\Models\Supplier;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SupplierExport implements FromCollection, WithHeadings, WithMapping, WithColumnFormatting, WithStyles
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
        $query = Supplier::query();

        // Apply date range filter if specified
        if ($this->dateFrom) {
            $query->whereDate('created_at', '>=', $this->dateFrom);
        }

        if ($this->dateTo) {
            $query->whereDate('created_at', '<=', $this->dateTo);
        }

        $suppliers = $query->get();

        // Update job with total records
        if ($this->job) {
            $this->job->update(['total_records' => $suppliers->count()]);
        }

        return $suppliers;
    }

    public function headings(): array
    {
        $headings = [];
        
        foreach ($this->columns as $column) {
            $headings[] = $this->getColumnHeading($column);
        }

        return $headings;
    }

    public function map($supplier): array
    {
        $row = [];
        
        foreach ($this->columns as $column) {
            $row[] = $this->getColumnValue($supplier, $column);
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

        return $headings[$column] ?? ucfirst(str_replace('_', ' ', $column));
    }

    protected function getColumnValue(Supplier $supplier, string $column)
    {
        switch ($column) {
            case 'id':
                return $supplier->id;
            case 'name':
                return $supplier->name;
            case 'contact_person':
                return $supplier->contact_person;
            case 'email':
                return $supplier->email;
            case 'phone':
                return $supplier->phone;
            case 'address':
                return $supplier->address;
            case 'gst_number':
                return $supplier->gst_number;
            case 'status':
                return $supplier->status ? 'Active' : 'Inactive';
            case 'total_purchases':
                return $supplier->total_purchases;
            case 'total_paid':
                return $supplier->total_paid;
            case 'total_due':
                return $supplier->total_due;
            case 'created_at':
                return $supplier->created_at->format('Y-m-d H:i:s');
            case 'updated_at':
                return $supplier->updated_at->format('Y-m-d H:i:s');
            default:
                return $supplier->{$column} ?? null;
        }
    }

    protected function getDefaultColumns(): array
    {
        return [
            'name',
            'contact_person',
            'email',
            'phone',
            'address',
            'gst_number',
            'status',
        ];
    }

    public function columnFormats(): array
    {
        return [
            'A' => '@', // ID as text
            'D' => '@', // Email as text
            'E' => '@', // Phone as text
            'G' => '@', // GST Number as text
            'I' => '#,##0.00', // Total Purchases
            'J' => '#,##0.00', // Total Paid
            'K' => '#,##0.00', // Total Due
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
        ];
    }
}