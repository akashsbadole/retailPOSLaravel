<?php

namespace App\Imports;

use App\Models\Supplier;
use App\Helpers\ImportExportHelper;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;

class SupplierImport implements ToCollection, WithHeadingRow, WithValidation, WithBatchInserts, WithChunkReading
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
                
                \Log::error("Supplier import failed at row {$rowNumber}: " . $e->getMessage());
            }
        }
    }

    protected function processRow(array $row, int $rowNumber): void
    {
        // Validate required fields
        $validator = Validator::make($row, [
            'name' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        // Clean and prepare data
        $data = $this->prepareData($row, $rowNumber);

        // Find existing supplier
        $existingSupplier = null;
        if (!empty($data['email'])) {
            $existingSupplier = Supplier::where('email', $data['email'])->first();
        } elseif (!empty($data['phone'])) {
            $existingSupplier = Supplier::where('phone', $data['phone'])->first();
        } else {
            $existingSupplier = Supplier::where('name', $data['name'])->first();
        }

        // Handle based on import mode
        if ($existingSupplier) {
            if ($this->importMode === 'create_only') {
                throw new \Exception("Supplier already exists and import mode is 'create only'");
            }
            
            if ($this->importMode === 'skip_duplicates') {
                return; // Skip this record
            }
            
            if ($this->importMode === 'update_only' || $this->importMode === 'create_update') {
                if (!$this->validateOnly) {
                    $existingSupplier->update($data);
                }
            }
        } else {
            if ($this->importMode === 'update_only') {
                throw new \Exception("Supplier not found and import mode is 'update only'");
            }
            
            if ($this->importMode === 'create_only' || $this->importMode === 'create_update') {
                if (!$this->validateOnly) {
                    Supplier::create($data);
                }
            }
        }
    }

    protected function prepareData(array $row, int $rowNumber): array
    {
        $data = [
            'name' => trim($row['name'] ?? ''),
            'contact_person' => trim($row['contact_person'] ?? ''),
            'email' => $this->processEmail($row['email'] ?? null),
            'phone' => $this->processPhone($row['phone'] ?? null),
            'address' => trim($row['address'] ?? ''),
            'gst_number' => $this->processGST($row['gst_number'] ?? null),
            'status' => $this->processStatus($row['status'] ?? true),
        ];

        // Validate email uniqueness
        if (!empty($data['email'])) {
            $existingSupplier = Supplier::where('email', $data['email'])->first();
            if ($existingSupplier && $existingSupplier->name !== $data['name']) {
                throw new \Exception("Email '{$data['email']}' already exists for another supplier");
            }
        }

        // Validate phone uniqueness
        if (!empty($data['phone'])) {
            $existingSupplier = Supplier::where('phone', $data['phone'])->first();
            if ($existingSupplier && $existingSupplier->name !== $data['name']) {
                throw new \Exception("Phone '{$data['phone']}' already exists for another supplier");
            }
        }

        // Validate GST uniqueness
        if (!empty($data['gst_number'])) {
            $existingSupplier = Supplier::where('gst_number', $data['gst_number'])->first();
            if ($existingSupplier && $existingSupplier->name !== $data['name']) {
                throw new \Exception("GST Number '{$data['gst_number']}' already exists for another supplier");
            }
        }

        return $data;
    }

    protected function processEmail(?string $email): ?string
    {
        if (empty($email)) {
            return null;
        }
        
        $email = trim(strtolower($email));
        
        if (!ImportExportHelper::validateEmail($email)) {
            throw new \Exception("Invalid email format: {$email}");
        }
        
        return $email;
    }

    protected function processPhone(?string $phone): ?string
    {
        if (empty($phone)) {
            return null;
        }
        
        $phone = ImportExportHelper::formatPhone($phone);
        
        if (!ImportExportHelper::validatePhone($phone)) {
            throw new \Exception("Invalid phone format: {$phone}");
        }
        
        return $phone;
    }

    protected function processGST(?string $gst): ?string
    {
        if (empty($gst)) {
            return null;
        }
        
        $gst = trim(strtoupper($gst));
        
        if (!ImportExportHelper::validateGST($gst)) {
            throw new \Exception("Invalid GST number format: {$gst}");
        }
        
        return $gst;
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
            'contact_person' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:1000',
            'gst_number' => 'nullable|string|max:15',
            'status' => 'nullable|boolean',
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