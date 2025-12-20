<?php

namespace App\Jobs;

use App\Models\Backup;
use App\Services\BackupService;
use App\Notifications\BackupCompleted;
use App\Notifications\BackupFailed;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Exception;

class CreateBackupJob implements ShouldQueue
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
     * Create a new job instance.
     *
     * @param int $backupId
     * @return void
     */
    public function __construct(int $backupId)
    {
        $this->onQueue(config('backup.queue.queue', 'backups'));
        $this->connection = config('backup.queue.connection', 'default');
        
        // Store backup ID to avoid serialization issues
        $this->backupId = $backupId;
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
            
            // Check if backup is still pending
            if ($this->backup->status !== Backup::STATUS_PENDING) {
                Log::warning("Backup job skipped - backup status changed", [
                    'backup_id' => $this->backup->id,
                    'status' => $this->backup->status,
                ]);
                return;
            }

            // Set memory limit if configured
            $memoryLimit = config('backup.performance.memory_limit', '512M');
            if ($memoryLimit) {
                ini_set('memory_limit', $memoryLimit);
            }

            // Ensure backup directory exists
            $this->ensureBackupDirectory();

            // Perform backup based on type
            $success = match ($this->backup->type) {
                Backup::TYPE_FULL => $backupService->performFullBackup($this->backup),
                Backup::TYPE_SELECTIVE => $backupService->performSelectiveBackup(
                    $this->backup,
                    $this->backup->tables ?? []
                ),
                Backup::TYPE_INCREMENTAL => $backupService->performIncrementalBackup($this->backup),
                Backup::TYPE_STRUCTURE => $backupService->performStructureBackup($this->backup),
                default => throw new Exception("Unknown backup type: {$this->backup->type}"),
            };

            if ($success) {
                $this->handleSuccess();
            } else {
                $this->handleFailure('Backup service returned false');
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
            if (isset($this->backup)) {
                $this->backup->markAsFailed($exception->getMessage());
                $this->sendFailureNotification($exception);
            }
        } catch (Exception $e) {
            Log::error('Failed to handle backup job failure', [
                'backup_id' => $this->backupId ?? 'unknown',
                'error' => $e->getMessage(),
                'original_error' => $exception->getMessage(),
            ]);
        }

        Log::error('Backup job failed', [
            'backup_id' => $this->backupId ?? 'unknown',
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }

    /**
     * Handle successful backup completion.
     *
     * @return void
     */
    protected function handleSuccess(): void
    {
        try {
            // Validate backup file integrity if enabled
            if (config('backup.security.validate_integrity', true)) {
                $this->validateBackupIntegrity();
            }

            // Send success notification if enabled
            if (config('backup.notifications.on_success', true)) {
                $this->sendSuccessNotification();
            }

            Log::info('Backup completed successfully', [
                'backup_id' => $this->backup->id,
                'type' => $this->backup->type,
                'file_size' => $this->backup->file_size,
                'duration' => $this->backup->duration,
            ]);

        } catch (Exception $e) {
            Log::error('Failed to handle backup success', [
                'backup_id' => $this->backup->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle backup failure.
     *
     * @param string $message
     * @param \Throwable|null $exception
     * @return void
     */
    protected function handleFailure(string $message, ?\Throwable $exception = null): void
    {
        try {
            if (isset($this->backup)) {
                $this->backup->markAsFailed($message);
                $this->sendFailureNotification($exception ?? new Exception($message));
            }
        } catch (Exception $e) {
            Log::error('Failed to handle backup failure', [
                'backup_id' => $this->backupId ?? 'unknown',
                'error' => $e->getMessage(),
                'original_error' => $message,
            ]);
        }

        Log::error('Backup failed', [
            'backup_id' => $this->backupId ?? 'unknown',
            'error' => $message,
            'exception' => $exception?->getTraceAsString(),
        ]);
    }

    /**
     * Ensure backup directory exists.
     *
     * @return void
     */
    protected function ensureBackupDirectory(): void
    {
        $disk = $this->backup->disk;
        $path = config('backup.path', 'backups');

        if (!Storage::disk($disk)->exists($path)) {
            Storage::disk($disk)->makeDirectory($path);
            
            // Set directory permissions if local disk
            if ($disk === 'local') {
                $fullPath = Storage::disk($disk)->path($path);
                $permissions = config('backup.security.directory_permissions', '0700');
                chmod($fullPath, octdec($permissions));
            }
        }
    }

    /**
     * Validate backup file integrity.
     *
     * @return void
     * @throws Exception
     */
    protected function validateBackupIntegrity(): void
    {
        if (!$this->backup->file_path || !$this->backup->fileExists()) {
            throw new Exception('Backup file does not exist');
        }

        $disk = $this->backup->disk;
        $filePath = $this->backup->file_path;
        
        // Check file size
        $actualSize = Storage::disk($disk)->size($filePath);
        if ($actualSize !== $this->backup->file_size) {
            throw new Exception("File size mismatch: expected {$this->backup->file_size}, got {$actualSize}");
        }

        // For SQL files, check if they contain expected content
        if ($this->backup->format === Backup::FORMAT_SQL) {
            $content = Storage::disk($disk)->get($filePath);
            
            // Decompress if needed
            if (str_ends_with($filePath, '.gz')) {
                $content = gzdecode($content);
            }
            
            // Decrypt if needed
            if (str_ends_with($filePath, '.enc')) {
                $content = $this->decryptData($content);
            }
            
            if (empty($content) || strpos($content, '-- SuperMart POS Database Backup') === false) {
                throw new Exception('Backup file content is invalid');
            }
        }
    }

    /**
     * Send success notification.
     *
     * @return void
     */
    protected function sendSuccessNotification(): void
    {
        try {
            $user = $this->backup->user;
            if ($user && config('backup.notifications.enabled', true)) {
                $user->notify(new BackupCompleted($this->backup));
            }
        } catch (Exception $e) {
            Log::error('Failed to send backup success notification', [
                'backup_id' => $this->backup->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send failure notification.
     *
     * @param \Throwable $exception
     * @return void
     */
    protected function sendFailureNotification(\Throwable $exception): void
    {
        try {
            $user = $this->backup->user;
            if ($user && config('backup.notifications.enabled', true)) {
                $user->notify(new BackupFailed($this->backup, $exception));
            }
        } catch (Exception $e) {
            Log::error('Failed to send backup failure notification', [
                'backup_id' => $this->backup->id,
                'error' => $e->getMessage(),
            ]);
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
     * Get the tags that should be assigned to the job.
     *
     * @return array
     */
    public function tags(): array
    {
        return [
            'backup',
            'backup:' . ($this->backup->type ?? 'unknown'),
            'user:' . ($this->backup->user_id ?? 'unknown'),
        ];
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     *
     * @return int
     */
    public function retryUntil(): \DateTime
    {
        return now()->addHours(24);
    }

    /**
     * Determine the time at which the job should timeout.
     *
     * @return \DateTime
     */
    public function retryAfter(): \DateTime
    {
        return now()->addMinutes(5);
    }
}