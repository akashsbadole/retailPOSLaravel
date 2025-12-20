<?php

namespace App\Exports;

use App\Models\Product;
use App\Helpers\ImportExportHelper;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProductExport implements FromCollection, WithHeadings, WithMapping, WithColumnFormatting, WithStyles
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
        $query = Product::query();

        // Apply store filter if specified
        if ($this->storeId) {
            $query->whereHas('stores', function ($q) {
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

        $products = $query->get();

        // Update job with total records
        if ($this->job) {
            $this->job->update(['total_records' => $products->count()]);
        }

        return $products;
    }

    public function headings(): array
    {
        $headings = [];
        
        foreach ($this->columns as $column) {
            $headings[] = $this->getColumnHeading($column);
        }

        return $headings;
    }

    public function map($product): array
    {
        $row = [];
        
        foreach ($this->columns as $column) {
            $row[] = $this->getColumnValue($product, $column);
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
            'barcode' => 'Barcode',
            'sku' => 'SKU',
            'price' => 'Selling Price',
            'cost_price' => 'Cost Price',
            'quantity' => 'Quantity',
            'minimum_stock' => 'Minimum Stock',
            'tax' => 'Tax (%)',
            'status' => 'Status',
            'image' => 'Image URL',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
        ];

        return $headings[$column] ?? ucfirst(str_replace('_', ' ', $column));
    }

    protected function getColumnValue(Product $product, string $column)
    {
        switch ($column) {
            case 'id':
                return $product->id;
            case 'name':
                return $product->name;
            case 'barcode':
                return $product->barcode;
            case 'sku':
                return $product->sku;
            case 'price':
                return $product->price;
            case 'cost_price':
                return auth()->user()->can('product.view-cost') ? $product->cost_price : null;
            case 'quantity':
                return $product->quantity;
            case 'minimum_stock':
                return $product->minimum_stock;
            case 'tax':
                return $product->tax;
            case 'status':
                return $product->status ? 'Active' : 'Inactive';
            case 'image':
                return ImportExportHelper::handleImageExport($product->image);
            case 'created_at':
                return $product->created_at->format('Y-m-d H:i:s');
            case 'updated_at':
                return $product->updated_at->format('Y-m-d H:i:s');
            default:
                return $product->{$column} ?? null;
        }
    }

    protected function getDefaultColumns(): array
    {
        return [
            'name',
            'barcode',
            'sku',
            'price',
            'cost_price',
            'quantity',
            'minimum_stock',
            'tax',
            'status',
        ];
    }

    public function columnFormats(): array
    {
        return [
            'B' => '@', // Barcode as text
            'C' => '@', // SKU as text
            'D' => '#,##0.00', // Price
            'E' => '#,##0.00', // Cost Price
            'F' => '0', // Quantity
            'G' => '0', // Minimum Stock
            'H' => '#,##0.00', // Tax
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