<?php

namespace App\Exports;

use App\Models\Customer;
use App\Helpers\ImportExportHelper;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CustomerExport implements FromCollection, WithHeadings, WithMapping, WithColumnFormatting, WithStyles
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
        $query = Customer::query();

        // Apply date range filter if specified
        if ($this->dateFrom) {
            $query->whereDate('created_at', '>=', $this->dateFrom);
        }

        if ($this->dateTo) {
            $query->whereDate('created_at', '<=', $this->dateTo);
        }

        $customers = $query->get();

        // Update job with total records
        if ($this->job) {
            $this->job->update(['total_records' => $customers->count()]);
        }

        return $customers;
    }

    public function headings(): array
    {
        $headings = [];
        
        foreach ($this->columns as $column) {
            $headings[] = $this->getColumnHeading($column);
        }

        return $headings;
    }

    public function map($customer): array
    {
        $row = [];
        
        foreach ($this->columns as $column) {
            $row[] = $this->getColumnValue($customer, $column);
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
            'first_name' => 'First Name',
            'last_name' => 'Last Name',
            'email' => 'Email',
            'phone' => 'Phone',
            'address' => 'Address',
            'avatar' => 'Avatar URL',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
        ];

        return $headings[$column] ?? ucfirst(str_replace('_', ' ', $column));
    }

    protected function getColumnValue(Customer $customer, string $column)
    {
        switch ($column) {
            case 'id':
                return $customer->id;
            case 'first_name':
                return $customer->first_name;
            case 'last_name':
                return $customer->last_name;
            case 'email':
                return $customer->email;
            case 'phone':
                return $customer->phone;
            case 'address':
                return $customer->address;
            case 'avatar':
                return ImportExportHelper::handleImageExport($customer->avatar);
            case 'created_at':
                return $customer->created_at->format('Y-m-d H:i:s');
            case 'updated_at':
                return $customer->updated_at->format('Y-m-d H:i:s');
            default:
                return $customer->{$column} ?? null;
        }
    }

    protected function getDefaultColumns(): array
    {
        return [
            'first_name',
            'last_name',
            'email',
            'phone',
            'address',
        ];
    }

    public function columnFormats(): array
    {
        return [
            'A' => '@', // ID as text
            'D' => '@', // Email as text
            'E' => '@', // Phone as text
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
        ];
    }
}