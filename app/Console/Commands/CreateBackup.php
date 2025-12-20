<?php

namespace App\Console\Commands;

use App\Jobs\CreateBackupJob;
use App\Models\Backup;
use App\Models\User;
use App\Services\BackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CreateBackup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup:create 
                            {--type=full : Backup type (full, selective, incremental, structure)}
                            {--format=sql : Backup format (sql, json)}
                            {--tables= : Comma-separated list of tables for selective backup}
                            {--description= : Backup description}
                            {--user=1 : User ID to associate with backup}
                            {--store= : Store ID for multi-store backup}
                            {--queue : Process backup in queue}
                            {--compress : Enable compression}
                            {--encrypt : Enable encryption}
                            {--schedule= : Schedule type (daily, weekly, monthly)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a database backup';

    /**
     * Execute the console command.
     *
     * @param BackupService $backupService
     * @return int
     */
    public function handle(BackupService $backupService): int
    {
        try {
            $this->info('Starting backup creation...');

            // Validate input
            $this->validateInput();

            // Get options
            $type = $this->option('type');
            $format = $this->option('format');
            $tables = $this->getTables();
            $description = $this->option('description');
            $userId = $this->option('user');
            $storeId = $this->option('store');
            $useQueue = $this->option('queue');
            $schedule = $this->option('schedule');

            // Get user
            $user = User::find($userId);
            if (!$user) {
                $this->error("User with ID {$userId} not found.");
                return 1;
            }

            // Get store if specified
            $store = null;
            if ($storeId) {
                $store = \App\Models\Store::find($storeId);
                if (!$store) {
                    $this->error("Store with ID {$storeId} not found.");
                    return 1;
                }
            }

            // Prepare options
            $options = [
                'compress' => $this->option('compress'),
                'encrypt' => $this->option('encrypt'),
                'schedule' => $schedule,
                'command_line' => true,
            ];

            $this->info("Creating {$type} backup in {$format} format...");

            // Create backup
            $backup = $backupService->createBackup(
                type: $type,
                format: $format,
                description: $description,
                tables: $tables,
                options: $options,
                user: $user,
                store: $store
            );

            $this->info("Backup created with ID: {$backup->id}");

            // Process backup
            if ($useQueue) {
                $this->info("Dispatching backup job to queue...");
                CreateBackupJob::dispatch($backup->id);
                $this->info("Backup job dispatched. You can check status in the database.");
            } else {
                $this->info("Processing backup synchronously...");
                
                $success = match ($type) {
                    Backup::TYPE_FULL => $backupService->performFullBackup($backup),
                    Backup::TYPE_SELECTIVE => $backupService->performSelectiveBackup($backup, $tables ?? []),
                    Backup::TYPE_INCREMENTAL => $backupService->performIncrementalBackup($backup),
                    Backup::TYPE_STRUCTURE => $backupService->performStructureBackup($backup),
                    default => throw new \Exception("Unknown backup type: {$type}"),
                };

                if ($success) {
                    $this->info("Backup completed successfully!");
                    $this->info("File: {$backup->file_path}");
                    $this->info("Size: {$backup->formatted_file_size}");
                    $this->info("Duration: {$backup->duration}");
                } else {
                    $this->error("Backup failed!");
                    return 1;
                }
            }

            return 0;

        } catch (ValidationException $e) {
            $this->error('Validation failed: ' . $e->getMessage());
            return 1;
        } catch (\Exception $e) {
            $this->error('Backup creation failed: ' . $e->getMessage());
            $this->error('Stack trace: ' . $e->getTraceAsString());
            return 1;
        }
    }

    /**
     * Validate command input.
     *
     * @return void
     * @throws ValidationException
     */
    protected function validateInput(): void
    {
        $validator = Validator::make([
            'type' => $this->option('type'),
            'format' => $this->option('format'),
            'user' => $this->option('user'),
            'store' => $this->option('store'),
        ], [
            'type' => 'required|in:' . implode(',', [
                Backup::TYPE_FULL,
                Backup::TYPE_SELECTIVE,
                Backup::TYPE_INCREMENTAL,
                Backup::TYPE_STRUCTURE,
            ]),
            'format' => 'required|in:' . implode(',', [
                Backup::FORMAT_SQL,
                Backup::FORMAT_JSON,
            ]),
            'user' => 'required|exists:users,id',
            'store' => 'nullable|exists:stores,id',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        // Validate tables for selective backup
        if ($this->option('type') === Backup::TYPE_SELECTIVE && !$this->option('tables')) {
            throw new ValidationException(
                Validator::make([], [])
                    ->errors()
                    ->add('tables', 'Tables option is required for selective backup')
            );
        }
    }

    /**
     * Get tables from option.
     *
     * @return array|null
     */
    protected function getTables(): ?array
    {
        $tables = $this->option('tables');
        
        if (!$tables) {
            return null;
        }

        return array_map('trim', explode(',', $tables));
    }

    /**
     * Display available backup types.
     *
     * @return void
     */
    protected function showBackupTypes(): void
    {
        $this->info('Available backup types:');
        $this->info('  full       - Complete database backup');
        $this->info('  selective  - Backup specific tables');
        $this->info('  incremental - Changes since last backup');
        $this->info('  structure  - Database schema only');
    }

    /**
     * Display available backup formats.
     *
     * @return void
     */
    protected function showBackupFormats(): void
    {
        $this->info('Available backup formats:');
        $this->info('  sql  - SQL dump file');
        $this->info('  json - JSON format');
    }

    /**
     * Display available tables.
     *
     * @return void
     */
    protected function showAvailableTables(): void
    {
        $this->info('Available tables:');
        
        try {
            $tables = \Illuminate\Support\Facades\DB::select('SHOW TABLES');
            $tableNames = array_map('current', $tables);
            
            foreach ($tableNames as $table) {
                $this->info("  {$table}");
            }
        } catch (\Exception $e) {
            $this->error('Failed to retrieve table list: ' . $e->getMessage());
        }
    }
}