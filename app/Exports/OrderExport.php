<?php

namespace App\Exports;

use App\Models\Order;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class OrderExport implements FromCollection, WithHeadings, WithMapping, WithColumnFormatting, WithStyles
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
        $query = Order::with(['customer']);

        // Apply store filter if specified
        if ($this->storeId) {
            $query->whereHas('cashierShift', function ($q) {
                $q->where('store_id', $this->storeId);
            });
        }

        // Apply date range filter if specified
        if ($this->dateFrom) {
            $query->whereDate('created_at', '>=', $this->dateFrom);
        }

        if ($this->dateTo) {
            $query->whereDate('created_at', '<=', $this->dateTo);
        }

        $orders = $query->get();

        // Update job with total records
        if ($this->job) {
            $this->job->update(['total_records' => $orders->count()]);
        }

        return $orders;
    }

    public function headings(): array
    {
        $headings = [];
        
        foreach ($this->columns as $column) {
            $headings[] = $this->getColumnHeading($column);
        }

        return $headings;
    }

    public function map($order): array
    {
        $row = [];
        
        foreach ($this->columns as $column) {
            $row[] = $this->getColumnValue($order, $column);
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
            'customer_name' => 'Customer Name',
            'customer_email' => 'Customer Email',
            'customer_phone' => 'Customer Phone',
            'total_price' => 'Total Price',
            'discount_amount' => 'Discount Amount',
            'final_amount' => 'Final Amount',
            'status' => 'Status',
            'items_count' => 'Items Count',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
        ];

        return $headings[$column] ?? ucfirst(str_replace('_', ' ', $column));
    }

    protected function getColumnValue(Order $order, string $column)
    {
        switch ($column) {
            case 'id':
                return $order->id;
            case 'customer_name':
                return $order->customer ? $order->customer->first_name . ' ' . $order->customer->last_name : null;
            case 'customer_email':
                return $order->customer ? $order->customer->email : null;
            case 'customer_phone':
                return $order->customer ? $order->customer->phone : null;
            case 'total_price':
                return $order->total_price;
            case 'discount_amount':
                return $order->discount_amount;
            case 'final_amount':
                return $order->total_price - $order->discount_amount;
            case 'status':
                return ucfirst($order->status);
            case 'items_count':
                return $order->items ? $order->items->count() : 0;
            case 'created_at':
                return $order->created_at->format('Y-m-d H:i:s');
            case 'updated_at':
                return $order->updated_at->format('Y-m-d H:i:s');
            default:
                return $order->{$column} ?? null;
        }
    }

    protected function getDefaultColumns(): array
    {
        return [
            'id',
            'customer_name',
            'customer_email',
            'total_price',
            'discount_amount',
            'final_amount',
            'status',
            'created_at',
        ];
    }

    public function columnFormats(): array
    {
        return [
            'A' => '@', // ID as text
            'B' => '@', // Customer Name as text
            'C' => '@', // Customer Email as text
            'D' => '@', // Customer Phone as text
            'E' => '#,##0.00', // Total Price
            'F' => '#,##0.00', // Discount Amount
            'G' => '#,##0.00', // Final Amount
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
        ];
    }
}