<?php

namespace App\Imports;

use App\Models\Order;
use App\Models\Customer;
use App\Models\Product;
use App\Models\OrderItem;
use App\Helpers\ImportExportHelper;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;

class OrderImport implements ToCollection, WithHeadingRow, WithValidation, WithBatchInserts, WithChunkReading
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
                
                \Log::error("Order import failed at row {$rowNumber}: " . $e->getMessage());
            }
        }
    }

    protected function processRow(array $row, int $rowNumber): void
    {
        // Validate required fields
        $validator = Validator::make($row, [
            'customer_identifier' => 'required|string|max:255',
            'total_price' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        // Clean and prepare data
        $data = $this->prepareData($row, $rowNumber);

        // Find existing order
        $existingOrder = null;
        if (!empty($data['id'])) {
            $existingOrder = Order::find($data['id']);
        }

        // Handle based on import mode
        if ($existingOrder) {
            if ($this->importMode === 'create_only') {
                throw new \Exception("Order already exists and import mode is 'create only'");
            }
            
            if ($this->importMode === 'skip_duplicates') {
                return; // Skip this record
            }
            
            if ($this->importMode === 'update_only' || $this->importMode === 'create_update') {
                if (!$this->validateOnly) {
                    $existingOrder->update($data);
                    $this->processOrderItems($existingOrder, $row['items'] ?? [], $rowNumber);
                }
            }
        } else {
            if ($this->importMode === 'update_only') {
                throw new \Exception("Order not found and import mode is 'update only'");
            }
            
            if ($this->importMode === 'create_only' || $this->importMode === 'create_update') {
                if (!$this->validateOnly) {
                    $order = Order::create($data);
                    $this->processOrderItems($order, $row['items'] ?? [], $rowNumber);
                }
            }
        }
    }

    protected function prepareData(array $row, int $rowNumber): array
    {
        // Find customer
        $customer = $this->findCustomer($row['customer_identifier'] ?? null, $rowNumber);
        
        $data = [
            'customer_id' => $customer ? $customer->id : null,
            'total_price' => (float) ($row['total_price'] ?? 0),
            'discount_amount' => isset($row['discount_amount']) ? (float) $row['discount_amount'] : 0,
            'status' => $this->processStatus($row['status'] ?? 'pending'),
        ];

        return $data;
    }

    protected function findCustomer($identifier, int $rowNumber): ?Customer
    {
        if (empty($identifier)) {
            throw new \Exception("Customer identifier is required");
        }

        // Try by ID first
        if (is_numeric($identifier)) {
            $customer = Customer::find($identifier);
            if ($customer) return $customer;
        }

        // Try by email
        if (ImportExportHelper::validateEmail($identifier)) {
            $customer = Customer::where('email', $identifier)->first();
            if ($customer) return $customer;
        }

        // Try by phone
        $customer = Customer::where('phone', $identifier)->first();
        if ($customer) return $customer;

        // Try by name
        $parts = explode(' ', $identifier, 2);
        $firstName = $parts[0] ?? '';
        $lastName = $parts[1] ?? '';
        
        $customer = Customer::where('first_name', $firstName)
            ->where('last_name', $lastName)
            ->first();
            
        if ($customer) return $customer;

        throw new \Exception("Customer not found with identifier: {$identifier}");
    }

    protected function processOrderItems(Order $order, array $itemsData, int $rowNumber): void
    {
        if (empty($itemsData)) {
            return;
        }

        // Parse items data (could be JSON string or array)
        $items = [];
        if (is_string($itemsData)) {
            $items = json_decode($itemsData, true);
            if (!$items) {
                throw new \Exception("Invalid JSON format for order items");
            }
        } else {
            $items = $itemsData;
        }

        // Clear existing items if updating
        if ($order->exists) {
            $order->items()->delete();
        }

        // Process each item
        foreach ($items as $index => $itemData) {
            $product = $this->findProduct($itemData['product_identifier'] ?? null, $rowNumber, $index);
            
            if (!$product) {
                continue;
            }

            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'quantity' => (int) ($itemData['quantity'] ?? 1),
                'price' => (float) ($itemData['price'] ?? $product->price),
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

    protected function processStatus($status): string
    {
        if (is_string($status)) {
            $status = strtolower($status);
            
            return match ($status) {
                'pending', 'new' => 'pending',
                'processing', 'in_progress' => 'processing',
                'completed', 'done', 'finished' => 'completed',
                'cancelled', 'canceled' => 'cancelled',
                default => 'pending',
            };
        }
        
        return 'pending';
    }

    public function rules(): array
    {
        return [
            'customer_identifier' => 'required|string|max:255',
            'total_price' => 'required|numeric|min:0',
            'discount_amount' => 'nullable|numeric|min:0',
            'status' => 'nullable|string|max:50',
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