<?php

namespace App\Imports;

use App\Models\Product;
use App\Helpers\ImportExportHelper;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;

class ProductImport implements ToCollection, WithHeadingRow, WithValidation, WithBatchInserts, WithChunkReading
{
    protected string $importMode;
    protected bool $validateOnly;
    protected ?\App\Models\ImportExportJob $job = null;
    protected int $processedCount = 0;
    protected int $failedCount = 0;

    public function __construct(string $importMode = 'create_update', bool $validateOnly = false)
    {
        $this->importMode = $importMode;
        $this->validateOnly = $validateOnly;
    }

    public function withJob(\App\Models\ImportExportJob $job): self
    {
        $this->job = $job;
        return $this;
    }

    public function collection(Collection $rows)
    {
        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2; // +2 because of heading row and 0-based index
            
            try {
                $this->processRow($row->toArray(), $rowNumber);
                $this->processedCount++;
                
                if ($this->job) {
                    $this->job->updateProgress($this->processedCount);
                }
            } catch (\Exception $e) {
                $this->failedCount++;
                
                if ($this->job) {
                    $this->job->addError($rowNumber, 'general', $e->getMessage());
                }
                
                \Log::error("Product import failed at row {$rowNumber}: " . $e->getMessage());
            }
        }
    }

    protected function processRow(array $row, int $rowNumber): void
    {
        // Validate required fields
        $validator = Validator::make($row, [
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        // Clean and prepare data
        $data = $this->prepareData($row, $rowNumber);

        // Find existing product
        $existingProduct = null;
        if (!empty($data['barcode'])) {
            $existingProduct = Product::where('barcode', $data['barcode'])->first();
        } elseif (!empty($data['sku'])) {
            $existingProduct = Product::where('sku', $data['sku'])->first();
        } else {
            $existingProduct = Product::where('name', $data['name'])->first();
        }

        // Handle based on import mode
        if ($existingProduct) {
            if ($this->importMode === 'create_only') {
                throw new \Exception("Product already exists and import mode is 'create only'");
            }
            
            if ($this->importMode === 'skip_duplicates') {
                return; // Skip this record
            }
            
            if ($this->importMode === 'update_only' || $this->importMode === 'create_update') {
                if (!$this->validateOnly) {
                    $existingProduct->update($data);
                }
            }
        } else {
            if ($this->importMode === 'update_only') {
                throw new \Exception("Product not found and import mode is 'update only'");
            }
            
            if ($this->importMode === 'create_only' || $this->importMode === 'create_update') {
                if (!$this->validateOnly) {
                    Product::create($data);
                }
            }
        }
    }

    protected function prepareData(array $row, int $rowNumber): array
    {
        $data = [
            'name' => trim($row['name'] ?? ''),
            'barcode' => $this->processBarcode($row['barcode'] ?? null),
            'sku' => trim($row['sku'] ?? ''),
            'price' => (float) ($row['price'] ?? 0),
            'cost_price' => isset($row['cost_price']) ? (float) $row['cost_price'] : null,
            'quantity' => isset($row['quantity']) ? (int) $row['quantity'] : 1,
            'minimum_stock' => isset($row['minimum_stock']) ? (int) $row['minimum_stock'] : 10,
            'tax' => isset($row['tax']) ? (float) $row['tax'] : 0,
            'status' => $this->processStatus($row['status'] ?? true),
        ];

        // Handle image
        if (!empty($row['image'])) {
            $data['image'] = ImportExportHelper::handleImageImport($row['image']);
        }

        // Validate barcode uniqueness
        if (!empty($data['barcode'])) {
            $existingProduct = Product::where('barcode', $data['barcode'])->first();
            if ($existingProduct && $existingProduct->name !== $data['name']) {
                throw new \Exception("Barcode '{$data['barcode']}' already exists for another product");
            }
        }

        // Validate SKU uniqueness
        if (!empty($data['sku'])) {
            $existingProduct = Product::where('sku', $data['sku'])->first();
            if ($existingProduct && $existingProduct->name !== $data['name']) {
                throw new \Exception("SKU '{$data['sku']}' already exists for another product");
            }
        }

        return $data;
    }

    protected function processBarcode(?string $barcode): ?string
    {
        if (empty($barcode)) {
            return ImportExportHelper::generateUniqueBarcode('PROD');
        }
        
        return trim($barcode);
    }

    protected function processStatus($status): bool
    {
        if (is_string($status)) {
            return in_array(strtolower($status), ['true', '1', 'yes', 'active', 'enabled']);
        }
        
        return (bool) $status;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'barcode' => 'nullable|string|max:255',
            'sku' => 'nullable|string|max:255',
            'cost_price' => 'nullable|numeric|min:0',
            'quantity' => 'nullable|integer|min:0',
            'minimum_stock' => 'nullable|integer|min:0',
            'tax' => 'nullable|numeric|min:0',
            'status' => 'nullable|boolean',
            'image' => 'nullable|string',
        ];
    }

    public function batchSize(): int
    {
        return 100;
    }

    public function chunkSize(): int
    {
        return 100;
    }
}