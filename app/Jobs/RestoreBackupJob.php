<?php

namespace App\Jobs;

use App\Models\Backup;
use App\Models\User;
use App\Notifications\RestoreCompleted;
use App\Notifications\RestoreFailed;
use App\Services\BackupService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Exception;

class RestoreBackupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public int $tries = 3;

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     *
     * @var int
     */
    public int $maxExceptions = 3;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public int $timeout = 3600; // 1 hour

    /**
     * Indicate if the job should be marked as failed on timeout.
     *
     * @var bool
     */
    public bool $failOnTimeout = true;

    /**
     * The backup instance.
     *
     * @var Backup
     */
    protected Backup $backup;

    /**
     * The user who initiated the restore.
     *
     * @var int
     */
    protected int $userId;

    /**
     * Restore options.
     *
     * @var array
     */
    protected array $options;

    /**
     * Create a new job instance.
     *
     * @param int $backupId
     * @param int $userId
     * @param array $options
     * @return void
     */
    public function __construct(int $backupId, int $userId, array $options = [])
    {
        $this->backupId = $backupId;
        $this->userId = $userId;
        $this->options = $options;
    }

    /**
     * Execute the job.
     *
     * @param BackupService $backupService
     * @return void
     */
    public function handle(BackupService $backupService): void
    {
        try {
            // Load backup fresh from database
            $this->backup = Backup::findOrFail($this->backupId);
            
            // Check if backup is completed
            if (!$this->backup->isCompleted()) {
                Log::warning("Restore job skipped - backup not completed", [
                    'backup_id' => $this->backup->id,
                    'status' => $this->backup->status,
                ]);
                return;
            }

            // Check if backup file exists
            if (!$this->backup->fileExists()) {
                throw new Exception('Backup file does not exist on storage');
            }

            // Set memory limit if configured
            $memoryLimit = config('backup.performance.memory_limit', '512M');
            if ($memoryLimit) {
                ini_set('memory_limit', $memoryLimit);
            }

            // Create restore point if enabled
            if ($this->options['create_restore_point'] ?? true) {
                $this->createRestorePoint();
            }

            // Perform restore based on format
            $success = match ($this->backup->format) {
                Backup::FORMAT_SQL => $this->restoreFromSql($backupService),
                Backup::FORMAT_JSON => $this->restoreFromJson($backupService),
                default => throw new Exception("Unknown backup format: {$this->backup->format}"),
            };

            if ($success) {
                $this->handleSuccess();
            } else {
                $this->handleFailure('Restore service returned false');
            }

        } catch (Exception $e) {
            $this->handleFailure($e->getMessage(), $e);
        }
    }

    /**
     * Handle a job failure.
     *
     * @param \Throwable $exception
     * @return void
     */
    public function failed(\Throwable $exception): void
    {
        try {
            $user = User::find($this->userId);
            if ($user) {
                $user->notify(new RestoreFailed($this->backup ?? null, $exception));
            }
        } catch (Exception $e) {
            Log::error('Failed to handle restore job failure', [
                'backup_id' => $this->backupId ?? 'unknown',
                'error' => $e->getMessage(),
                'original_error' => $exception->getMessage(),
            ]);
        }

        Log::error('Restore job failed', [
            'backup_id' => $this->backupId ?? 'unknown',
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }

    /**
     * Create a restore point before restoring.
     *
     * @return void
     */
    protected function createRestorePoint(): void
    {
        try {
            $backupService = app(BackupService::class);
            $restorePoint = $backupService->createBackup(
                type: Backup::TYPE_SELECTIVE,
                format: Backup::FORMAT_SQL,
                description: "Restore point before restoring backup #{$this->backup->id}",
                tables: ['users', 'stores', 'products', 'customers', 'orders'],
                options: ['is_restore_point' => true],
                user: User::find($this->userId),
            );

            Log::info('Restore point created', [
                'backup_id' => $this->backup->id,
                'restore_point_id' => $restorePoint->id,
            ]);
        } catch (Exception $e) {
            Log::warning('Failed to create restore point', [
                'backup_id' => $this->backup->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Restore from SQL backup file.
     *
     * @param BackupService $backupService
     * @return bool
     */
    protected function restoreFromSql(BackupService $backupService): bool
    {
        try {
            $disk = $this->backup->disk;
            $filePath = $this->backup->file_path;
            
            // Get SQL content
            $sql = Storage::disk($disk)->get($filePath);
            
            // Decompress if needed
            if (str_ends_with($filePath, '.gz')) {
                $sql = gzdecode($sql);
            }
            
            // Decrypt if needed
            if (str_ends_with($filePath, '.enc')) {
                $sql = $this->decryptData($sql);
            }
            
            // Disable foreign key checks if enabled
            if ($this->options['disable_foreign_keys'] ?? true) {
                DB::statement('SET FOREIGN_KEY_CHECKS=0');
            }
            
            // Truncate tables if enabled
            if ($this->options['truncate_tables'] ?? true) {
                $this->truncateTables();
            }
            
            // Execute SQL statements
            $statements = array_filter(array_map('trim', explode(';', $sql)));
            
            foreach ($statements as $statement) {
                if (!empty($statement) && !str_starts_with($statement, '--')) {
                    try {
                        DB::statement($statement);
                    } catch (Exception $e) {
                        Log::warning('SQL statement failed during restore', [
                            'statement' => substr($statement, 0, 100) . '...',
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }
            
            // Re-enable foreign key checks if disabled
            if ($this->options['disable_foreign_keys'] ?? true) {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            }
            
            return true;
            
        } catch (Exception $e) {
            Log::error('SQL restore failed', [
                'backup_id' => $this->backup->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return false;
        }
    }

    /**
     * Restore from JSON backup file.
     *
     * @param BackupService $backupService
     * @return bool
     */
    protected function restoreFromJson(BackupService $backupService): bool
    {
        try {
            $disk = $this->backup->disk;
            $filePath = $this->backup->file_path;
            
            // Get JSON content
            $json = Storage::disk($disk)->get($filePath);
            
            // Decompress if needed
            if (str_ends_with($filePath, '.gz')) {
                $json = gzdecode($json);
            }
            
            // Decrypt if needed
            if (str_ends_with($filePath, '.enc')) {
                $json = $this->decryptData($json);
            }
            
            $data = json_decode($json, true);
            
            if (!$data || !isset($data['tables'])) {
                throw new Exception('Invalid JSON backup format');
            }
            
            // Disable foreign key checks if enabled
            if ($this->options['disable_foreign_keys'] ?? true) {
                DB::statement('SET FOREIGN_KEY_CHECKS=0');
            }
            
            // Truncate tables if enabled
            if ($this->options['truncate_tables'] ?? true) {
                $this->truncateTables();
            }
            
            // Restore each table
            $tables = $this->options['tables'] ?? array_keys($data['tables']);
            
            foreach ($tables as $table) {
                if (!isset($data['tables'][$table])) {
                    continue;
                }
                
                try {
                    $tableData = $data['tables'][$table];
                    
                    // Handle structure-only backups
                    if (isset($tableData['structure'])) {
                        $this->restoreTableStructure($table, $tableData['structure']);
                    }
                    
                    // Handle data backups
                    if (isset($tableData['data'])) {
                        $this->restoreTableData($table, $tableData['data']);
                    }
                    
                } catch (Exception $e) {
                    Log::error('Failed to restore table', [
                        'table' => $table,
                        'backup_id' => $this->backup->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            
            // Re-enable foreign key checks if disabled
            if ($this->options['disable_foreign_keys'] ?? true) {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            }
            
            return true;
            
        } catch (Exception $e) {
            Log::error('JSON restore failed', [
                'backup_id' => $this->backup->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return false;
        }
    }

    /**
     * Truncate tables before restore.
     *
     * @return void
     */
    protected function truncateTables(): void
    {
        $tables = $this->options['tables'] ?? $this->backup->tables ?? [];
        
        foreach ($tables as $table) {
            try {
                DB::table($table)->truncate();
            } catch (Exception $e) {
                Log::warning('Failed to truncate table', [
                    'table' => $table,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Restore table structure.
     *
     * @param string $table
     * @param array $structure
     * @return void
     */
    protected function restoreTableStructure(string $table, array $structure): void
    {
        // This is a simplified implementation
        // In a real implementation, you would need to parse the structure
        // and recreate the table with proper SQL statements
        Log::info('Restoring table structure', ['table' => $table]);
    }

    /**
     * Restore table data.
     *
     * @param string $table
     * @param array $data
     * @return void
     */
    protected function restoreTableData(string $table, array $data): void
    {
        if (empty($data)) {
            return;
        }
        
        // Insert data in chunks to avoid memory issues
        $chunks = array_chunk($data, 1000);
        
        foreach ($chunks as $chunk) {
            DB::table($table)->insert($chunk);
        }
    }

    /**
     * Decrypt data if encryption is enabled.
     *
     * @param string $data
     * @return string
     * @throws Exception
     */
    protected function decryptData(string $data): string
    {
        $key = config('backup.encryption.key');
        
        if (!$key) {
            throw new Exception('Encryption key not configured');
        }
        
        $decoded = base64_decode($data);
        $ivLength = openssl_cipher_iv_length('aes-256-cbc');
        $iv = substr($decoded, 0, $ivLength);
        $encrypted = substr($decoded, $ivLength);
        
        return openssl_decrypt($encrypted, 'aes-256-cbc', $key, 0, $iv);
    }

    /**
     * Handle successful restore completion.
     *
     * @return void
     */
    protected function handleSuccess(): void
    {
        try {
            $user = User::find($this->userId);
            if ($user) {
                $user->notify(new RestoreCompleted($this->backup, $this->options));
            }

            Log::info('Restore completed successfully', [
                'backup_id' => $this->backup->id,
                'user_id' => $this->userId,
                'options' => $this->options,
            ]);

        } catch (Exception $e) {
            Log::error('Failed to handle restore success', [
                'backup_id' => $this->backup->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle restore failure.
     *
     * @param string $message
     * @param \Throwable|null $exception
     * @return void
     */
    protected function handleFailure(string $message, ?\Throwable $exception = null): void
    {
        try {
            $user = User::find($this->userId);
            if ($user) {
                $user->notify(new RestoreFailed($this->backup, $exception ?? new Exception($message)));
            }
        } catch (Exception $e) {
            Log::error('Failed to handle restore failure', [
                'backup_id' => $this->backupId ?? 'unknown',
                'error' => $e->getMessage(),
                'original_error' => $message,
            ]);
        }

        Log::error('Restore failed', [
            'backup_id' => $this->backupId ?? 'unknown',
            'error' => $message,
            'exception' => $exception?->getTraceAsString(),
        ]);
    }

    /**
     * Get tags that should be assigned to the job.
     *
     * @return array
     */
    public function tags(): array
    {
        return [
            'restore',
            'backup:' . ($this->backup->type ?? 'unknown'),
            'user:' . $this->userId,
        ];
    }

    /**
     * Calculate number of seconds to wait before retrying the job.
     *
     * @return int
     */
    public function retryAfter(): \DateTime
    {
        return now()->addMinutes(5);
    }
}