<?php

namespace App\Imports;

use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\Product;
use App\Models\PurchaseItem;
use App\Helpers\ImportExportHelper;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;

class PurchaseImport implements ToCollection, WithHeadingRow, WithValidation, WithBatchInserts, WithChunkReading
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
                
                \Log::error("Purchase import failed at row {$rowNumber}: " . $e->getMessage());
            }
        }
    }

    protected function processRow(array $row, int $rowNumber): void
    {
        // Validate required fields
        $validator = Validator::make($row, [
            'supplier_identifier' => 'required|string|max:255',
            'total_amount' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        // Clean and prepare data
        $data = $this->prepareData($row, $rowNumber);

        // Find existing purchase
        $existingPurchase = null;
        if (!empty($data['id'])) {
            $existingPurchase = Purchase::find($data['id']);
        }

        // Handle based on import mode
        if ($existingPurchase) {
            if ($this->importMode === 'create_only') {
                throw new \Exception("Purchase already exists and import mode is 'create only'");
            }
            
            if ($this->importMode === 'skip_duplicates') {
                return; // Skip this record
            }
            
            if ($this->importMode === 'update_only' || $this->importMode === 'create_update') {
                if (!$this->validateOnly) {
                    $existingPurchase->update($data);
                    $this->processPurchaseItems($existingPurchase, $row['items'] ?? [], $rowNumber);
                }
            }
        } else {
            if ($this->importMode === 'update_only') {
                throw new \Exception("Purchase not found and import mode is 'update only'");
            }
            
            if ($this->importMode === 'create_only' || $this->importMode === 'create_update') {
                if (!$this->validateOnly) {
                    $purchase = Purchase::create($data);
                    $this->processPurchaseItems($purchase, $row['items'] ?? [], $rowNumber);
                }
            }
        }
    }

    protected function prepareData(array $row, int $rowNumber): array
    {
        // Find supplier
        $supplier = $this->findSupplier($row['supplier_identifier'] ?? null, $rowNumber);
        
        $data = [
            'supplier_id' => $supplier ? $supplier->id : null,
            'purchase_date' => $this->processDate($row['purchase_date'] ?? now()->format('Y-m-d')),
            'total_amount' => (float) ($row['total_amount'] ?? 0),
            'discount_amount' => isset($row['discount_amount']) ? (float) $row['discount_amount'] : 0,
            'tax_amount' => isset($row['tax_amount']) ? (float) $row['tax_amount'] : 0,
            'final_amount' => (float) ($row['final_amount'] ?? 0),
            'status' => $this->processStatus($row['status'] ?? 'pending'),
            'payment_status' => $this->processPaymentStatus($row['payment_status'] ?? 'unpaid'),
            'notes' => trim($row['notes'] ?? ''),
        ];

        return $data;
    }

    protected function findSupplier($identifier, int $rowNumber): ?Supplier
    {
        if (empty($identifier)) {
            throw new \Exception("Supplier identifier is required");
        }

        // Try by ID first
        if (is_numeric($identifier)) {
            $supplier = Supplier::find($identifier);
            if ($supplier) return $supplier;
        }

        // Try by email
        if (ImportExportHelper::validateEmail($identifier)) {
            $supplier = Supplier::where('email', $identifier)->first();
            if ($supplier) return $supplier;
        }

        // Try by phone
        $supplier = Supplier::where('phone', $identifier)->first();
        if ($supplier) return $supplier;

        // Try by name
        $supplier = Supplier::where('name', $identifier)->first();
        if ($supplier) return $supplier;

        throw new \Exception("Supplier not found with identifier: {$identifier}");
    }

    protected function processDate($date): string
    {
        if (is_string($date)) {
            return $date;
        }
        
        return now()->format('Y-m-d');
    }

    protected function processStatus($status): string
    {
        if (is_string($status)) {
            $status = strtolower($status);
            
            return match ($status) {
                'pending', 'new' => 'pending',
                'received', 'completed', 'done' => 'received',
                'cancelled', 'canceled' => 'cancelled',
                default => 'pending',
            };
        }
        
        return 'pending';
    }

    protected function processPaymentStatus($status): string
    {
        if (is_string($status)) {
            $status = strtolower($status);
            
            return match ($status) {
                'unpaid', 'due' => 'unpaid',
                'partial', 'partially_paid' => 'partial',
                'paid', 'complete' => 'paid',
                default => 'unpaid',
            };
        }
        
        return 'unpaid';
    }

    protected function processPurchaseItems(Purchase $purchase, array $itemsData, int $rowNumber): void
    {
        if (empty($itemsData)) {
            return;
        }

        // Parse items data (could be JSON string or array)
        $items = [];
        if (is_string($itemsData)) {
            $items = json_decode($itemsData, true);
            if (!$items) {
                throw new \Exception("Invalid JSON format for purchase items");
            }
        } else {
            $items = $itemsData;
        }

        // Clear existing items if updating
        if ($purchase->exists) {
            $purchase->items()->delete();
        }

        // Process each item
        foreach ($items as $index => $itemData) {
            $product = $this->findProduct($itemData['product_identifier'] ?? null, $rowNumber, $index);
            
            if (!$product) {
                continue;
            }

            PurchaseItem::create([
                'purchase_id' => $purchase->id,
                'product_id' => $product->id,
                'quantity' => (int) ($itemData['quantity'] ?? 1),
                'price' => (float) ($itemData['price'] ?? $product->cost_price ?? 0),
                'total' => (float) ($itemData['total'] ?? 0),
            ]);
        }
    }

    protected function findProduct($identifier, int $rowNumber, int $itemIndex): ?Product
    {
        if (empty($identifier)) {
            throw new \Exception("Product identifier is required for item {$itemIndex}");
        }

        // Try by barcode first
        $product = Product::where('barcode', $identifier)->first();
        if ($product) return $product;
        
        // Try by SKU
        $product = Product::where('sku', $identifier)->first();
        if ($product) return $product;
        
        // Try by name
        $product = Product::where('name', $identifier)->first();
        if ($product) return $product;

        throw new \Exception("Product not found with identifier: {$identifier}");
    }

    public function rules(): array
    {
        return [
            'supplier_identifier' => 'required|string|max:255',
            'purchase_date' => 'nullable|date',
            'total_amount' => 'required|numeric|min:0',
            'discount_amount' => 'nullable|numeric|min:0',
            'tax_amount' => 'nullable|numeric|min:0',
            'final_amount' => 'nullable|numeric|min:0',
            'status' => 'nullable|string|max:50',
            'payment_status' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:1000',
            'items' => 'nullable|array',
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