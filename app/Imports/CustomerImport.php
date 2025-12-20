<?php

namespace App\Imports;

use App\Models\Customer;
use App\Helpers\ImportExportHelper;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;

class CustomerImport implements ToCollection, WithHeadingRow, WithValidation, WithBatchInserts, WithChunkReading
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
                
                \Log::error("Customer import failed at row {$rowNumber}: " . $e->getMessage());
            }
        }
    }

    protected function processRow(array $row, int $rowNumber): void
    {
        // Validate required fields
        $validator = Validator::make($row, [
            'first_name' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        // Clean and prepare data
        $data = $this->prepareData($row, $rowNumber);

        // Find existing customer
        $existingCustomer = null;
        if (!empty($data['email'])) {
            $existingCustomer = Customer::where('email', $data['email'])->first();
        } elseif (!empty($data['phone'])) {
            $existingCustomer = Customer::where('phone', $data['phone'])->first();
        } else {
            $existingCustomer = Customer::where('first_name', $data['first_name'])
                ->where('last_name', $data['last_name'] ?? '')
                ->first();
        }

        // Handle based on import mode
        if ($existingCustomer) {
            if ($this->importMode === 'create_only') {
                throw new \Exception("Customer already exists and import mode is 'create only'");
            }
            
            if ($this->importMode === 'skip_duplicates') {
                return; // Skip this record
            }
            
            if ($this->importMode === 'update_only' || $this->importMode === 'create_update') {
                if (!$this->validateOnly) {
                    $existingCustomer->update($data);
                }
            }
        } else {
            if ($this->importMode === 'update_only') {
                throw new \Exception("Customer not found and import mode is 'update only'");
            }
            
            if ($this->importMode === 'create_only' || $this->importMode === 'create_update') {
                if (!$this->validateOnly) {
                    Customer::create($data);
                }
            }
        }
    }

    protected function prepareData(array $row, int $rowNumber): array
    {
        $data = [
            'first_name' => trim($row['first_name'] ?? ''),
            'last_name' => trim($row['last_name'] ?? ''),
            'email' => $this->processEmail($row['email'] ?? null),
            'phone' => $this->processPhone($row['phone'] ?? null),
            'address' => trim($row['address'] ?? ''),
        ];

        // Handle avatar
        if (!empty($row['avatar'])) {
            $data['avatar'] = ImportExportHelper::handleImageImport($row['avatar']);
        }

        // Validate email uniqueness
        if (!empty($data['email'])) {
            $existingCustomer = Customer::where('email', $data['email'])->first();
            if ($existingCustomer && ($existingCustomer->first_name !== $data['first_name'] || $existingCustomer->last_name !== $data['last_name'])) {
                throw new \Exception("Email '{$data['email']}' already exists for another customer");
            }
        }

        // Validate phone uniqueness
        if (!empty($data['phone'])) {
            $existingCustomer = Customer::where('phone', $data['phone'])->first();
            if ($existingCustomer && ($existingCustomer->first_name !== $data['first_name'] || $existingCustomer->last_name !== $data['last_name'])) {
                throw new \Exception("Phone '{$data['phone']}' already exists for another customer");
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

    public function rules(): array
    {
        return [
            'first_name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:1000',
            'avatar' => 'nullable|string',
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