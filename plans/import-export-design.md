# SuperMart POS Import/Export System Design

## 1. Overall Architecture

### 1.1 System Overview

The import/export system will build upon the existing `pxlrbt/filament-excel` and `maatwebsite/excel` packages, enhancing them with:

- Queue-based processing for large datasets
- Comprehensive validation system
- Progress tracking and notifications
- Multi-store architecture support
- Relationship handling during imports
- File management for images and special fields
- Backup and restore functionality

### 1.2 Architecture Components

```
┌─────────────────────────────────────────────────────────────┐
│                    Filament UI Layer                      │
├─────────────────────────────────────────────────────────────┤
│                Import/Export Actions                       │
│  ┌─────────────────┐  ┌─────────────────┐              │
│  │   Import UI     │  │   Export UI     │              │
│  └─────────────────┘  └─────────────────┘              │
├─────────────────────────────────────────────────────────────┤
│                 Service Layer                             │
│  ┌─────────────────┐  ┌─────────────────┐              │
│  │ImportService    │  │ExportService    │              │
│  └─────────────────┘  └─────────────────┘              │
├─────────────────────────────────────────────────────────────┤
│                 Processing Layer                          │
│  ┌─────────────────┐  ┌─────────────────┐              │
│  │ImportProcessor │  │ExportProcessor  │              │
│  └─────────────────┘  └─────────────────┘              │
├─────────────────────────────────────────────────────────────┤
│                 Queue System                              │
│  ┌─────────────────┐  ┌─────────────────┐              │
│  │ImportJob       │  │ExportJob       │              │
│  └─────────────────┘  └─────────────────┘              │
├─────────────────────────────────────────────────────────────┤
│                 Validation Layer                          │
│  ┌─────────────────┐  ┌─────────────────┐              │
│  │ImportValidator │  │DataMapper      │              │
│  └─────────────────┘  └─────────────────┘              │
├─────────────────────────────────────────────────────────────┤
│                 Storage Layer                            │
│  ┌─────────────────┐  ┌─────────────────┐              │
│  │FileStorage     │  │BackupStorage   │              │
│  └─────────────────┘  └─────────────────┘              │
└─────────────────────────────────────────────────────────────┘
```

### 1.3 Core Classes Structure

```php
// Base Import/Export Classes
app/Services/ImportExport/
├── BaseImportService.php
├── BaseExportService.php
├── ImportProcessor.php
├── ExportProcessor.php
├── Validation/
│   ├── ImportValidator.php
│   └── ResourceValidators/
│       ├── ProductValidator.php
│       ├── CustomerValidator.php
│       ├── SupplierValidator.php
│       └── ...
├── Jobs/
│   ├── ImportJob.php
│   ├── ExportJob.php
│   └── BackupJob.php
├── Notifications/
│   ├── ImportCompleted.php
│   ├── ImportFailed.php
│   └── ExportCompleted.php
└── Traits/
    ├── Importable.php
    ├── Exportable.php
    └── QueueableImportExport.php
```

## 2. File Format Standards and Validation

### 2.1 Supported Formats

| Format | Import | Export | Use Case |
|--------|---------|---------|----------|
| CSV | ✓ | ✓ | Simple data, compatibility |
| XLSX | ✓ | ✓ | Complex data, formatting |
| JSON | ✓ | ✓ | API integration, complex structures |

### 2.2 File Structure Standards

#### 2.2.1 Standard Column Naming Convention
- Use snake_case for column names
- Include resource prefix for related data (e.g., `customer_name`, `product_sku`)
- Use consistent date formats (YYYY-MM-DD)
- Use ISO currency codes for monetary values

#### 2.2.2 Required Metadata
```csv
# First 3 rows for metadata (optional)
# Resource: Product
# Version: 1.0
# Export Date: 2024-01-15
# Store: Main Store
name,barcode,sku,price,cost_price,quantity,minimum_stock,tax,status
Product A,PROD001,SKU001,10.50,5.25,100,10,5.00,1
```

### 2.3 Validation Strategy

#### 2.3.1 Multi-Level Validation
1. **File Level Validation**
   - File format validation
   - Size limits (max 50MB)
   - Required columns check

2. **Row Level Validation**
   - Data type validation
   - Required field validation
   - Format validation (email, phone, etc.)

3. **Business Logic Validation**
   - Unique constraint checks
   - Relationship validation
   - Permission validation

#### 2.3.2 Error Handling
```php
class ImportError
{
    public function __construct(
        public int $row,
        public string $field,
        public string $message,
        public string $severity = 'error', // error, warning, info
        public mixed $value = null
    ) {}
}
```

## 3. Queue-Based Processing System

### 3.1 Queue Configuration

```php
// config/queue.php - Add new connection
'import_export' => [
    'driver' => 'redis',
    'connection' => 'default',
    'queue' => 'import_export',
    'retry_after' => 3600, // 1 hour
    'block_for' => null,
    'after_commit' => false,
],
```

### 3.2 Job Processing

#### 3.2.1 Import Job Structure
```php
class ImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
    public int $timeout = 3600; // 1 hour
    public int $tries = 3;
    public int $maxExceptions = 3;
    
    public function __construct(
        private string $filePath,
        private string $resourceClass,
        private int $userId,
        private array $options = []
    ) {}
    
    public function handle(ImportProcessor $processor): void
    {
        $processor->process($this->filePath, $this->resourceClass, $this->userId, $this->options);
    }
}
```

#### 3.2.2 Batch Processing Strategy
- Process files in chunks of 1000 records
- Track progress using database table
- Allow pause/resume functionality
- Memory-efficient processing for large files

### 3.3 Progress Tracking

```php
// Migration for import/export tracking
Schema::create('import_export_jobs', function (Blueprint $table) {
    $table->id();
    $table->string('type'); // import, export, backup
    $table->string('resource_type');
    $table->string('status'); // pending, processing, completed, failed
    $table->foreignId('user_id')->constrained();
    $table->string('file_path')->nullable();
    $table->integer('total_records')->default(0);
    $table->integer('processed_records')->default(0);
    $table->integer('failed_records')->default(0);
    $table->json('errors')->nullable();
    $table->json('options')->nullable();
    $table->timestamp('started_at')->nullable();
    $table->timestamp('completed_at')->nullable();
    $table->timestamps();
});
```

## 4. Resource Implementation Priority

### 4.1 Priority Order
1. **ProductResource** (enhance existing, add import)
2. **CustomerResource** (new import/export)
3. **SupplierResource** (new import/export)
4. **OrderResource** (enhance existing, add import)
5. **PurchaseResource** (new import/export)
6. **StockAdjustmentResource** (new import/export)
7. **StoreResource** (new import/export)
8. **UserResource** (new import/export)

### 4.2 Resource-Specific Considerations

#### 4.2.1 ProductResource
```php
class ProductImport extends BaseImport
{
    protected array $requiredColumns = ['name', 'price'];
    protected array $optionalColumns = ['barcode', 'sku', 'cost_price', 'quantity', 'minimum_stock', 'tax', 'status'];
    
    protected function processRow(array $row, int $rowNumber): array
    {
        return [
            'name' => $this->validateRequired($row, 'name', $rowNumber),
            'barcode' => $this->generateBarcodeIfMissing($row['barcode'] ?? null),
            'sku' => $row['sku'] ?? null,
            'price' => $this->validateDecimal($row, 'price', $rowNumber),
            'cost_price' => $this->validateDecimal($row, 'cost_price', $rowNumber, true),
            'quantity' => $this->validateInteger($row, 'quantity', $rowNumber, true, 0),
            'minimum_stock' => $this->validateInteger($row, 'minimum_stock', $rowNumber, true, 10),
            'tax' => $this->validateDecimal($row, 'tax', $rowNumber, true, 0),
            'status' => $this->validateBoolean($row, 'status', $rowNumber, true, true),
        ];
    }
    
    private function generateBarcodeIfMissing(?string $barcode): ?string
    {
        if (empty($barcode)) {
            $barcodeService = app(BarcodeService::class);
            return $barcodeService->generateUniqueBarcode('PROD');
        }
        return $barcode;
    }
}
```

#### 4.2.2 CustomerResource
```php
class CustomerImport extends BaseImport
{
    protected array $requiredColumns = ['first_name'];
    protected array $optionalColumns = ['last_name', 'email', 'phone', 'address'];
    
    protected function processRow(array $row, int $rowNumber): array
    {
        return [
            'first_name' => $this->validateRequired($row, 'first_name', $rowNumber),
            'last_name' => $row['last_name'] ?? null,
            'email' => $this->validateEmail($row, 'email', $rowNumber, true),
            'phone' => $this->validatePhone($row, 'phone', $rowNumber, true),
            'address' => $row['address'] ?? null,
        ];
    }
}
```

#### 4.2.3 OrderResource (Complex Import)
```php
class OrderImport extends BaseImport
{
    protected function processRow(array $row, int $rowNumber): array
    {
        // Handle complex order structure with items
        $orderData = [
            'customer_id' => $this->findOrCreateCustomer($row, $rowNumber),
            'total_price' => $this->validateDecimal($row, 'total_price', $rowNumber),
            'status' => $row['status'] ?? 'pending',
            'discount_amount' => $this->validateDecimal($row, 'discount_amount', $rowNumber, true, 0),
        ];
        
        // Process order items if present
        if (isset($row['items'])) {
            $orderData['items'] = $this->processOrderItems($row['items'], $rowNumber);
        }
        
        return $orderData;
    }
    
    private function processOrderItems(string $itemsJson, int $rowNumber): array
    {
        $items = json_decode($itemsJson, true);
        if (!$items) {
            $this->addError($rowNumber, 'items', 'Invalid JSON format for order items');
            return [];
        }
        
        $processedItems = [];
        foreach ($items as $index => $item) {
            $product = $this->findProduct($item['product_identifier'] ?? null);
            if (!$product) {
                $this->addError($rowNumber, "items.{$index}", 'Product not found');
                continue;
            }
            
            $processedItems[] = [
                'product_id' => $product->id,
                'quantity' => $item['quantity'] ?? 1,
                'price' => $item['price'] ?? $product->price,
            ];
        }
        
        return $processedItems;
    }
}
```

## 5. Relationship Handling During Import

### 5.1 Relationship Resolution Strategy

#### 5.1.1 Reference Resolution
```php
class RelationshipResolver
{
    public function resolveCustomer($identifier): ?Customer
    {
        // Try multiple identifiers
        if (is_numeric($identifier)) {
            return Customer::find($identifier);
        }
        
        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            return Customer::where('email', $identifier)->first();
        }
        
        // Try phone number
        return Customer::where('phone', $identifier)->first();
    }
    
    public function resolveProduct($identifier): ?Product
    {
        // Try barcode first
        $product = Product::where('barcode', $identifier)->first();
        if ($product) return $product;
        
        // Try SKU
        $product = Product::where('sku', $identifier)->first();
        if ($product) return $product;
        
        // Try name
        return Product::where('name', $identifier)->first();
    }
}
```

#### 5.1.2 Import Modes
```php
enum ImportMode: string
{
    case CREATE_ONLY = 'create_only';
    case UPDATE_ONLY = 'update_only';
    case CREATE_UPDATE = 'create_update';
    case SKIP_DUPLICATES = 'skip_duplicates';
}
```

### 5.2 Dependency Management

#### 5.2.1 Import Order for Related Resources
1. **Stores** (if referenced)
2. **Suppliers** (for purchases)
3. **Products** (for orders)
4. **Customers** (for orders)
5. **Orders** (depends on products and customers)
6. **Purchases** (depends on suppliers and products)

#### 5.2.2 Circular Reference Handling
```php
class CircularReferenceHandler
{
    public function handleCircularReferences(array $data): array
    {
        // Create placeholder records for circular references
        // Resolve after all records are processed
        // Update foreign keys in second pass
    }
}
```

## 6. Progress Tracking and Notifications

### 6.1 Real-time Progress Updates

#### 6.1.1 WebSocket Integration
```php
class ImportProgressTracker
{
    public function updateProgress(int $jobId, int $processed, int $total): void
    {
        $progress = ($processed / $total) * 100;
        
        broadcast(new ImportProgressUpdated([
            'job_id' => $jobId,
            'progress' => round($progress, 2),
            'processed' => $processed,
            'total' => $total,
            'status' => 'processing',
        ]))->toOthers();
    }
}
```

#### 6.1.2 Database Progress Tracking
```php
class ImportExportJob extends Model
{
    protected $casts = [
        'errors' => 'array',
        'options' => 'array',
    ];
    
    public function updateProgress(int $processed, int $total): void
    {
        $this->update([
            'processed_records' => $processed,
            'progress_percentage' => ($processed / $total) * 100,
        ]);
    }
    
    public function addError(ImportError $error): void
    {
        $errors = $this->errors ?? [];
        $errors[] = $error->toArray();
        $this->update([
            'errors' => $errors,
            'failed_records' => $this->failed_records + 1,
        ]);
    }
}
```

### 6.2 Notification System

#### 6.2.1 Notification Types
```php
class ImportCompleted extends Notification
{
    public function __construct(
        private ImportExportJob $job
    ) {}
    
    public function via($notifiable): array
    {
        return ['database', 'mail'];
    }
    
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Import Completed Successfully')
            ->greeting('Hello ' . $notifiable->name)
            ->line("Your {$this->job->resource_type} import has been completed.")
            ->line("Processed: {$this->job->processed_records} records")
            ->line("Failed: {$this->job->failed_records} records")
            ->action('View Details', route('filament.admin.import-exports.show', $this->job->id));
    }
}
```

#### 6.2.2 In-App Notifications
```php
class ImportFailed extends Notification
{
    public function toDatabase($notifiable): array
    {
        return [
            'title' => 'Import Failed',
            'message' => "Your {$this->job->resource_type} import failed.",
            'type' => 'error',
            'job_id' => $this->job->id,
            'error_count' => count($this->job->errors ?? []),
        ];
    }
}
```

## 7. Multi-Store Architecture Support

### 7.1 Store Context Handling

#### 7.1.1 Store-Aware Imports
```php
class StoreAwareImport extends BaseImport
{
    protected ?Store $store;
    
    public function __construct(?Store $store = null)
    {
        $this->store = $store ?? auth()->user()->store;
    }
    
    protected function processRow(array $row, int $rowNumber): array
    {
        $data = parent::processRow($row, $rowNumber);
        
        // Add store context if applicable
        if ($this->store && $this->shouldAddStoreContext()) {
            $data['store_id'] = $this->store->id;
        }
        
        return $data;
    }
    
    protected function shouldAddStoreContext(): bool
    {
        return in_array($this->resourceType, ['order', 'stock_adjustment', 'cashier_shift']);
    }
}
```

#### 7.1.2 Store-Specific Exports
```php
class StoreAwareExport extends BaseExport
{
    protected ?Store $store;
    
    public function __construct(?Store $store = null)
    {
        $this->store = $store ?? auth()->user()->store;
    }
    
    public function query()
    {
        $query = parent::query();
        
        if ($this->store && $this->shouldFilterByStore()) {
            $query->where('store_id', $this->store->id);
        }
        
        return $query;
    }
}
```

### 7.2 Cross-Store Data Handling

#### 7.2.1 Store Mapping
```php
class StoreMapper
{
    public function mapProductToStore(Product $product, Store $store): StoreStock
    {
        return StoreStock::updateOrCreate(
            ['store_id' => $store->id, 'product_id' => $product->id],
            [
                'quantity' => $product->quantity,
                'minimum_stock' => $product->minimum_stock,
                'location' => null,
            ]
        );
    }
    
    public function mapUserToStore(User $user, Store $store): void
    {
        $user->update(['store_id' => $store->id]);
    }
}
```

## 8. File Handling for Images and Special Fields

### 8.1 Image Import/Export

#### 8.1.1 Image Import Strategy
```php
class ImageImportHandler
{
    public function handleImageImport(string $imagePath, string $destinationDisk): ?string
    {
        try {
            // Handle different image sources
            if (str_starts_with($imagePath, 'http')) {
                // Download from URL
                $imageContents = file_get_contents($imagePath);
                $filename = uniqid() . '.' . pathinfo($imagePath, PATHINFO_EXTENSION);
                Storage::disk($destinationDisk)->put($filename, $imageContents);
                return $filename;
            }
            
            if (Storage::disk('temp')->exists($imagePath)) {
                // Move from temp storage
                $filename = uniqid() . '.' . pathinfo($imagePath, PATHINFO_EXTENSION);
                Storage::disk($destinationDisk)->move($imagePath, $filename);
                return $filename;
            }
            
            return null;
        } catch (Exception $e) {
            Log::error('Image import failed: ' . $e->getMessage());
            return null;
        }
    }
}
```

#### 8.1.2 Image Export Strategy
```php
class ImageExportHandler
{
    public function handleImageExport(?string $imagePath, string $sourceDisk): ?string
    {
        if (!$imagePath) {
            return null;
        }
        
        // Return full URL for exports
        return Storage::disk($sourceDisk)->url($imagePath);
    }
}
```

### 8.2 Special Field Handling

#### 8.2.1 JSON Fields
```php
class JsonFieldHandler
{
    public function importJsonField(mixed $value): array
    {
        if (is_string($value)) {
            return json_decode($value, true) ?? [];
        }
        
        if (is_array($value)) {
            return $value;
        }
        
        return [];
    }
    
    public function exportJsonField(array $value): string
    {
        return json_encode($value, JSON_PRETTY_PRINT);
    }
}
```

#### 8.2.2 Password Hashing for Users
```php
class UserPasswordHandler
{
    public function handlePasswordImport(?string $password): ?string
    {
        if (empty($password)) {
            // Generate random password if not provided
            return Str::random(12);
        }
        
        return Hash::make($password);
    }
}
```

## 9. Backup System Design

### 9.1 Backup Architecture

#### 9.1.1 Backup Types
```php
enum BackupType: string
{
    case FULL = 'full';           // All data
    case SELECTIVE = 'selective'; // Specific resources
    case INCREMENTAL = 'incremental'; // Changes since last backup
    case STRUCTURE = 'structure';   // Schema only
}
```

#### 9.1.2 Backup Manager
```php
class BackupManager
{
    public function createBackup(BackupType $type, array $options = []): Backup
    {
        $backup = Backup::create([
            'type' => $type->value,
            'status' => 'pending',
            'user_id' => auth()->id(),
            'options' => $options,
        ]);
        
        BackupJob::dispatch($backup->id, $type, $options);
        
        return $backup;
    }
    
    public function restoreBackup(int $backupId, array $options = []): void
    {
        $backup = Backup::findOrFail($backupId);
        
        RestoreJob::dispatch($backupId, $options);
    }
}
```

### 9.2 Backup Storage

#### 9.2.1 Storage Configuration
```php
// config/filesystems.php - Add backup disk
'backups' => [
    'driver' => 'local',
    'root' => storage_path('app/backups'),
    'throw' => false,
],

// For cloud storage
'backups_s3' => [
    'driver' => 's3',
    'bucket' => env('AWS_BACKUP_BUCKET'),
    'key' => env('AWS_ACCESS_KEY_ID'),
    'secret' => env('AWS_SECRET_ACCESS_KEY'),
    'region' => env('AWS_DEFAULT_REGION'),
    'root' => 'backups',
],
```

#### 9.2.2 Backup Retention Policy
```php
class BackupRetentionPolicy
{
    public function cleanupOldBackups(): void
    {
        // Keep daily backups for 30 days
        $this->deleteBackupsOlderThan(30, BackupType::DAILY);
        
        // Keep weekly backups for 12 weeks
        $this->deleteBackupsOlderThan(84, BackupType::WEEKLY);
        
        // Keep monthly backups for 12 months
        $this->deleteBackupsOlderThan(365, BackupType::MONTHLY);
        
        // Keep yearly backups indefinitely
    }
}
```

### 9.3 Restore Functionality

#### 9.3.1 Restore Process
```php
class RestoreManager
{
    public function restoreFromBackup(Backup $backup, array $options = []): void
    {
        // Validate backup integrity
        if (!$this->validateBackup($backup)) {
            throw new InvalidBackupException('Backup file is corrupted or invalid');
        }
        
        // Create restore point
        $this->createRestorePoint();
        
        try {
            // Disable foreign key checks
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            
            // Restore data in correct order
            $this->restoreData($backup, $options);
            
            // Re-enable foreign key checks
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
            
            // Update system settings if needed
            $this->updateSystemSettings($backup, $options);
            
        } catch (Exception $e) {
            // Rollback to restore point
            $this->rollbackToRestorePoint();
            throw $e;
        }
    }
}
```

## 10. User Interface Design

### 10.1 Import Interface

#### 10.1.1 Import Form Component
```php
class ImportForm extends Component
{
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Import Configuration')
                    ->schema([
                        Select::make('resource_type')
                            ->label('Resource Type')
                            ->options([
                                'products' => 'Products',
                                'customers' => 'Customers',
                                'suppliers' => 'Suppliers',
                                'orders' => 'Orders',
                                // ... other resources
                            ])
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(fn ($state, callable $set) => 
                                $set('template_url', $this->getTemplateUrl($state))
                            ),
                        
                        FileUpload::make('file')
                            ->label('Import File')
                            ->disk('temp')
                            ->directory('imports')
                            ->acceptedFileTypes(['text/csv', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                            ->helperText('Supported formats: CSV, XLS, XLSX')
                            ->required(),
                        
                        TextInput::make('template_url')
                            ->label('Download Template')
                            ->disabled()
                            ->formatStateUsing(fn ($state) => 
                                $state ? "<a href='{$state}' target='_blank'>Download Template</a>" : null
                            ),
                        
                        Select::make('import_mode')
                            ->label('Import Mode')
                            ->options([
                                'create_update' => 'Create New & Update Existing',
                                'create_only' => 'Create New Only',
                                'update_only' => 'Update Existing Only',
                                'skip_duplicates' => 'Skip Duplicates',
                            ])
                            ->default('create_update')
                            ->required(),
                        
                        Toggle::make('validate_only')
                            ->label('Validate Only (No Import)')
                            ->helperText('Check data validity without importing')
                            ->default(false),
                        
                        Toggle::make('send_notifications')
                            ->label('Send Email Notifications')
                            ->default(true),
                    ])
                    ->columns(2),
            ]);
    }
}
```

#### 10.1.2 Import Progress Component
```php
class ImportProgress extends Component
{
    public ?ImportExportJob $job;
    
    public function mount(int $jobId): void
    {
        $this->job = ImportExportJob::findOrFail($jobId);
    }
    
    public function getProgressProperty(): int
    {
        if (!$this->job || $this->job->total_records === 0) {
            return 0;
        }
        
        return ($this->job->processed_records / $this->job->total_records) * 100;
    }
    
    public function pollProgress(): void
    {
        $this->job->refresh();
    }
    
    public function render(): View
    {
        return view('filament.components.import-progress', [
            'job' => $this->job,
            'progress' => $this->progress,
        ]);
    }
}
```

### 10.2 Export Interface

#### 10.2.1 Export Form Component
```php
class ExportForm extends Component
{
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Export Configuration')
                    ->schema([
                        Select::make('resource_type')
                            ->label('Resource Type')
                            ->options([
                                'products' => 'Products',
                                'customers' => 'Customers',
                                'suppliers' => 'Suppliers',
                                'orders' => 'Orders',
                                // ... other resources
                            ])
                            ->required()
                            ->reactive(),
                        
                        Select::make('format')
                            ->label('Export Format')
                            ->options([
                                'csv' => 'CSV',
                                'xlsx' => 'Excel (XLSX)',
                                'json' => 'JSON',
                            ])
                            ->default('xlsx')
                            ->required(),
                        
                        Select::make('store_id')
                            ->label('Store')
                            ->options(Store::pluck('name', 'id'))
                            ->placeholder('All Stores')
                            ->visible(fn ($get) => $this->resourceSupportsStores($get('resource_type'))),
                        
                        DatePicker::make('date_from')
                            ->label('Date From')
                            ->visible(fn ($get) => $this->resourceSupportsDateRange($get('resource_type'))),
                        
                        DatePicker::make('date_to')
                            ->label('Date To')
                            ->visible(fn ($get) => $this->resourceSupportsDateRange($get('resource_type'))),
                        
                        CheckboxList::make('columns')
                            ->label('Columns to Export')
                            ->options(fn ($get) => $this->getAvailableColumns($get('resource_type')))
                            ->columns(3),
                        
                        Toggle::make('include_relations')
                            ->label('Include Related Data')
                            ->helperText('Export related records (e.g., order items, customer details)')
                            ->default(false),
                        
                        Toggle::make('send_email')
                            ->label('Send Download Link via Email')
                            ->default(false),
                    ])
                    ->columns(2),
            ]);
    }
}
```

### 10.3 Backup Management Interface

#### 10.3.1 Backup List Page
```php
class ListBackups extends Page
{
    protected static string $view = 'filament.pages.backups.list';
    
    public function table(Table $table): Table
    {
        return $table
            ->query(Backup::query()->latest())
            ->columns([
                TextColumn::make('type')
                    ->badge()
                    ->color(fn ($record) => match ($record->type) {
                        'full' => 'danger',
                        'selective' => 'warning',
                        'incremental' => 'info',
                        'structure' => 'gray',
                    }),
                
                TextColumn::make('status')
                    ->badge()
                    ->color(fn ($record) => match ($record->status) {
                        'completed' => 'success',
                        'pending' => 'warning',
                        'failed' => 'danger',
                    }),
                
                TextColumn::make('file_size')
                    ->formatStateUsing(fn ($state) => $this->formatBytes($state)),
                
                TextColumn::make('created_at')
                    ->dateTime(),
                
                TextColumn::make('user.name')
                    ->label('Created By'),
            ])
            ->actions([
                Action::make('download')
                    ->label('Download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn ($record) => $record->download_url)
                    ->visible(fn ($record) => $record->status === 'completed'),
                
                Action::make('restore')
                    ->label('Restore')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->action(fn ($record) => $this->restoreBackup($record))
                    ->requiresConfirmation()
                    ->visible(fn ($record) => $record->status === 'completed'),
                
                DeleteAction::make(),
            ]);
    }
}
```

## 11. Implementation Roadmap

### 11.1 Phase 1: Foundation (Week 1-2)
1. **Base Infrastructure**
   - Create base import/export classes
   - Set up queue system configuration
   - Implement progress tracking database table
   - Create notification system

2. **Core Services**
   - ImportProcessor and ExportProcessor
   - Validation framework
   - Error handling system
   - File management utilities

### 11.2 Phase 2: Priority Resources (Week 3-4)
1. **ProductResource Enhancement**
   - Enhance existing export functionality
   - Implement comprehensive import with barcode generation
   - Add image handling
   - Create validation rules

2. **CustomerResource Import/Export**
   - Full import/export functionality
   - Email/phone validation
   - Avatar image handling

3. **SupplierResource Import/Export**
   - Complete import/export implementation
   - GST number validation
   - Contact information handling

### 11.3 Phase 3: Complex Resources (Week 5-6)
1. **OrderResource Enhancement**
   - Complex import with order items
   - Customer relationship handling
   - Payment method mapping

2. **PurchaseResource Import/Export**
   - Supplier relationship handling
   - Purchase items processing
   - Payment status management

### 11.4 Phase 4: Advanced Features (Week 7-8)
1. **StockAdjustmentResource**
   - Import with approval workflow
   - Reason validation
   - Quantity calculations

2. **StoreResource Import/Export**
   - Settings JSON handling
   - Multi-store support
   - Store-specific data

3. **UserResource Import/Export**
   - Password hashing
   - Role assignment
   - Permission handling

### 11.5 Phase 5: Backup System (Week 9-10)
1. **Backup Infrastructure**
   - Backup manager implementation
   - Storage configuration
   - Retention policies

2. **Restore Functionality**
   - Restore manager
   - Rollback capabilities
   - Validation checks

### 11.6 Phase 6: UI Polish & Testing (Week 11-12)
1. **User Interface Enhancement**
   - Progress indicators
   - Error reporting
   - Template downloads

2. **Testing & Documentation**
   - Unit tests for all components
   - Integration tests
   - User documentation

## 12. Security Considerations

### 12.1 File Upload Security
- Validate file types and sizes
- Scan uploaded files for malware
- Store files in secure directories
- Implement file access controls

### 12.2 Data Validation Security
- Sanitize all input data
- Validate against SQL injection
- Implement rate limiting for imports
- Check user permissions for resources

### 12.3 Queue Security
- Validate job parameters
- Implement job authentication
- Monitor queue for suspicious activity
- Log all import/export activities

## 13. Performance Optimization

### 13.1 Memory Management
- Process files in chunks
- Use generators for large datasets
- Implement memory limits
- Clear unused variables

### 13.2 Database Optimization
- Use database transactions
- Implement batch inserts
- Optimize queries with indexes
- Use query caching where appropriate

### 13.3 Queue Optimization
- Prioritize critical jobs
- Implement job batching
- Monitor queue performance
- Scale workers as needed

This comprehensive design provides a robust foundation for implementing import/export functionality across all SuperMart POS resources while maintaining data integrity, performance, and user experience.