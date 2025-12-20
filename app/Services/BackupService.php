<?php

namespace App\Services;

use App\Models\Backup;
use App\Models\User;
use App\Models\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Exception;

class BackupService
{
    /**
     * Create a new backup record
     */
    public function createBackup(
        string $type,
        string $format = 'sql',
        ?string $description = null,
        ?array $tables = null,
        ?array $options = null,
        ?User $user = null,
        ?Store $store = null
    ): Backup {
        $user = $user ?? auth()->user();
        
        $backup = Backup::create([
            'name' => $this->generateBackupName($type),
            'type' => $type,
            'status' => Backup::STATUS_PENDING,
            'format' => $format,
            'description' => $description,
            'tables' => $tables,
            'options' => $options,
            'user_id' => $user->id,
            'store_id' => $store?->id,
        ]);

        return $backup;
    }

    /**
     * Perform a full database backup
     */
    public function performFullBackup(Backup $backup): bool
    {
        try {
            $backup->markAsProcessing();
            
            $tables = $this->getTablesForBackup('full');
            $backup->update(['tables' => $tables]);
            
            $filename = Backup::generateFilename('full', $backup->format);
            $filePath = config('backup.path') . '/' . $filename;
            
            if ($backup->format === Backup::FORMAT_SQL) {
                $result = $this->createSqlBackup($backup, $tables, $filePath);
            } else {
                $result = $this->createJsonBackup($backup, $tables, $filePath);
            }
            
            if ($result) {
                $fileSize = Storage::disk($backup->disk)->size($filePath);
                
                return $backup->markAsCompleted([
                    'file_path' => $filePath,
                    'file_size' => $fileSize,
                    'total_records' => $this->getTotalRecordsCount($tables),
                ]);
            }
            
            return $backup->markAsFailed('Failed to create backup file');
            
        } catch (Exception $e) {
            Log::error('Full backup failed', [
                'backup_id' => $backup->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return $backup->markAsFailed($e->getMessage());
        }
    }

    /**
     * Perform a selective backup of specific tables
     */
    public function performSelectiveBackup(Backup $backup, array $tables): bool
    {
        try {
            $backup->markAsProcessing();
            
            // Validate tables exist
            $validTables = $this->validateTables($tables);
            $backup->update(['tables' => $validTables]);
            
            $filename = Backup::generateFilename('selective', $backup->format);
            $filePath = config('backup.path') . '/' . $filename;
            
            if ($backup->format === Backup::FORMAT_SQL) {
                $result = $this->createSqlBackup($backup, $validTables, $filePath);
            } else {
                $result = $this->createJsonBackup($backup, $validTables, $filePath);
            }
            
            if ($result) {
                $fileSize = Storage::disk($backup->disk)->size($filePath);
                
                return $backup->markAsCompleted([
                    'file_path' => $filePath,
                    'file_size' => $fileSize,
                    'total_records' => $this->getTotalRecordsCount($validTables),
                ]);
            }
            
            return $backup->markAsFailed('Failed to create selective backup');
            
        } catch (Exception $e) {
            Log::error('Selective backup failed', [
                'backup_id' => $backup->id,
                'tables' => $tables,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return $backup->markAsFailed($e->getMessage());
        }
    }

    /**
     * Perform an incremental backup (changes since last backup)
     */
    public function performIncrementalBackup(Backup $backup): bool
    {
        try {
            $backup->markAsProcessing();
            
            $lastBackup = $this->getLastSuccessfulBackup($backup->type);
            $tables = $this->getTablesForBackup('incremental');
            
            if ($lastBackup) {
                $tables = $this->getChangedTables($tables, $lastBackup->created_at);
            }
            
            $backup->update(['tables' => $tables]);
            
            $filename = Backup::generateFilename('incremental', $backup->format);
            $filePath = config('backup.path') . '/' . $filename;
            
            if ($backup->format === Backup::FORMAT_SQL) {
                $result = $this->createSqlBackup($backup, $tables, $filePath, $lastBackup?->created_at);
            } else {
                $result = $this->createJsonBackup($backup, $tables, $filePath, $lastBackup?->created_at);
            }
            
            if ($result) {
                $fileSize = Storage::disk($backup->disk)->size($filePath);
                
                return $backup->markAsCompleted([
                    'file_path' => $filePath,
                    'file_size' => $fileSize,
                    'total_records' => $this->getTotalRecordsCount($tables),
                ]);
            }
            
            return $backup->markAsFailed('Failed to create incremental backup');
            
        } catch (Exception $e) {
            Log::error('Incremental backup failed', [
                'backup_id' => $backup->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return $backup->markAsFailed($e->getMessage());
        }
    }

    /**
     * Perform a structure-only backup
     */
    public function performStructureBackup(Backup $backup): bool
    {
        try {
            $backup->markAsProcessing();
            
            $tables = $this->getTablesForBackup('structure');
            $backup->update(['tables' => $tables]);
            
            $filename = Backup::generateFilename('structure', $backup->format);
            $filePath = config('backup.path') . '/' . $filename;
            
            if ($backup->format === Backup::FORMAT_SQL) {
                $result = $this->createStructureSqlBackup($backup, $tables, $filePath);
            } else {
                $result = $this->createStructureJsonBackup($backup, $tables, $filePath);
            }
            
            if ($result) {
                $fileSize = Storage::disk($backup->disk)->size($filePath);
                
                return $backup->markAsCompleted([
                    'file_path' => $filePath,
                    'file_size' => $fileSize,
                    'total_records' => 0, // Structure backup has no records
                ]);
            }
            
            return $backup->markAsFailed('Failed to create structure backup');
            
        } catch (Exception $e) {
            Log::error('Structure backup failed', [
                'backup_id' => $backup->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return $backup->markAsFailed($e->getMessage());
        }
    }

    /**
     * Create SQL backup file
     */
    protected function createSqlBackup(Backup $backup, array $tables, string $filePath, ?\DateTime $since = null): bool
    {
        $disk = $backup->disk;
        
        // Create backup header
        $sql = $this->createSqlHeader($backup);
        
        foreach ($tables as $table) {
            try {
                $sql .= $this->createTableSqlDump($table, $since);
                $backup->updateProgress($backup->processed_records + 1);
            } catch (Exception $e) {
                $backup->addError("Failed to backup table {$table}: " . $e->getMessage());
                Log::error("Table backup failed", [
                    'table' => $table,
                    'backup_id' => $backup->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
        
        $sql .= $this->createSqlFooter($backup);
        
        // Compress if enabled
        if (config('backup.compression.enabled', true)) {
            $sql = gzencode($sql, config('backup.compression.level', 6));
            $filePath .= '.gz';
        }
        
        // Encrypt if enabled
        if (config('backup.encryption.enabled', false)) {
            $sql = $this->encryptData($sql);
            $filePath .= '.enc';
        }
        
        return Storage::disk($disk)->put($filePath, $sql);
    }

    /**
     * Create JSON backup file
     */
    protected function createJsonBackup(Backup $backup, array $tables, string $filePath, ?\DateTime $since = null): bool
    {
        $disk = $backup->disk;
        $data = [
            'metadata' => $this->createJsonMetadata($backup),
            'tables' => [],
        ];
        
        foreach ($tables as $table) {
            try {
                $data['tables'][$table] = $this->getTableJsonData($table, $since);
                $backup->updateProgress($backup->processed_records + 1);
            } catch (Exception $e) {
                $backup->addError("Failed to backup table {$table}: " . $e->getMessage());
                Log::error("Table backup failed", [
                    'table' => $table,
                    'backup_id' => $backup->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
        
        $json = json_encode($data, JSON_PRETTY_PRINT);
        
        // Compress if enabled
        if (config('backup.compression.enabled', true)) {
            $json = gzencode($json, config('backup.compression.level', 6));
            $filePath .= '.gz';
        }
        
        // Encrypt if enabled
        if (config('backup.encryption.enabled', false)) {
            $json = $this->encryptData($json);
            $filePath .= '.enc';
        }
        
        return Storage::disk($disk)->put($filePath, $json);
    }

    /**
     * Create structure-only SQL backup
     */
    protected function createStructureSqlBackup(Backup $backup, array $tables, string $filePath): bool
    {
        $disk = $backup->disk;
        $sql = $this->createSqlHeader($backup);
        
        foreach ($tables as $table) {
            try {
                $sql .= $this->getTableStructureSql($table);
                $backup->updateProgress($backup->processed_records + 1);
            } catch (Exception $e) {
                $backup->addError("Failed to backup structure for table {$table}: " . $e->getMessage());
                Log::error("Table structure backup failed", [
                    'table' => $table,
                    'backup_id' => $backup->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
        
        $sql .= $this->createSqlFooter($backup);
        
        return Storage::disk($disk)->put($filePath, $sql);
    }

    /**
     * Create structure-only JSON backup
     */
    protected function createStructureJsonBackup(Backup $backup, array $tables, string $filePath): bool
    {
        $disk = $backup->disk;
        $data = [
            'metadata' => $this->createJsonMetadata($backup),
            'tables' => [],
        ];
        
        foreach ($tables as $table) {
            try {
                $data['tables'][$table] = [
                    'structure' => $this->getTableStructure($table),
                ];
                $backup->updateProgress($backup->processed_records + 1);
            } catch (Exception $e) {
                $backup->addError("Failed to backup structure for table {$table}: " . $e->getMessage());
                Log::error("Table structure backup failed", [
                    'table' => $table,
                    'backup_id' => $backup->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
        
        $json = json_encode($data, JSON_PRETTY_PRINT);
        
        return Storage::disk($disk)->put($filePath, $json);
    }

    /**
     * Get tables to backup based on type
     */
    protected function getTablesForBackup(string $type): array
    {
        $includeTables = config("backup.tables.include.{$type}", []);
        $excludeTables = config('backup.tables.exclude.all', []);
        
        if ($type === 'incremental') {
            $excludeTables = array_merge($excludeTables, config('backup.tables.exclude.incremental', []));
        }
        
        return array_diff($includeTables, $excludeTables);
    }

    /**
     * Validate that tables exist in the database
     */
    protected function validateTables(array $tables): array
    {
        $dbTables = DB::select('SHOW TABLES');
        $dbTableNames = array_map('current', $dbTables);
        
        return array_intersect($tables, $dbTableNames);
    }

    /**
     * Get the last successful backup of the same type
     */
    protected function getLastSuccessfulBackup(string $type): ?Backup
    {
        return Backup::byType($type)
            ->completed()
            ->latest()
            ->first();
    }

    /**
     * Get tables that have changed since the last backup
     */
    protected function getChangedTables(array $tables, \DateTime $since): array
    {
        $changedTables = [];
        
        foreach ($tables as $table) {
            $count = DB::table($table)
                ->where('updated_at', '>', $since)
                ->orWhere('created_at', '>', $since)
                ->count();
                
            if ($count > 0) {
                $changedTables[] = $table;
            }
        }
        
        return $changedTables;
    }

    /**
     * Get total records count for tables
     */
    protected function getTotalRecordsCount(array $tables): int
    {
        $total = 0;
        
        foreach ($tables as $table) {
            try {
                $total += DB::table($table)->count();
            } catch (Exception $e) {
                Log::warning("Could not count records for table {$table}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }
        
        return $total;
    }

    /**
     * Generate backup name
     */
    protected function generateBackupName(string $type): string
    {
        $timestamp = now()->format('Y-m-d H:i:s');
        $typeLabel = ucfirst($type);
        
        return "{$typeLabel} Backup - {$timestamp}";
    }

    /**
     * Create SQL backup header
     */
    protected function createSqlHeader(Backup $backup): string
    {
        $date = now()->toDateTimeString();
        $user = $backup->user->name ?? 'System';
        
        return "-- SuperMart POS Database Backup\n" .
               "-- Backup Type: {$backup->type}\n" .
               "-- Created: {$date}\n" .
               "-- Created By: {$user}\n" .
               "-- Database: " . DB::connection()->getDatabaseName() . "\n\n" .
               "SET FOREIGN_KEY_CHECKS=0;\n\n";
    }

    /**
     * Create SQL backup footer
     */
    protected function createSqlFooter(Backup $backup): string
    {
        return "\n\nSET FOREIGN_KEY_CHECKS=1;\n" .
               "-- Backup completed at: " . now()->toDateTimeString() . "\n";
    }

    /**
     * Create table SQL dump
     */
    protected function createTableSqlDump(string $table, ?\DateTime $since = null): string
    {
        $sql = "-- Table: {$table}\n";
        
        // Get table structure
        $sql .= $this->getTableStructureSql($table);
        
        // Get table data
        $query = DB::table($table);
        
        if ($since) {
            $query->where('updated_at', '>', $since)
                  ->orWhere('created_at', '>', $since);
        }
        
        $records = $query->get();
        
        if ($records->isNotEmpty()) {
            $sql .= "\n-- Data for table: {$table}\n";
            
            foreach ($records as $record) {
                $values = [];
                foreach ((array) $record as $value) {
                    $values[] = $value === null ? 'NULL' : "'" . addslashes($value) . "'";
                }
                $sql .= "INSERT INTO `{$table}` VALUES (" . implode(', ', $values) . ");\n";
            }
        }
        
        $sql .= "\n";
        
        return $sql;
    }

    /**
     * Get table structure as SQL
     */
    protected function getTableStructureSql(string $table): string
    {
        $structure = DB::select("SHOW CREATE TABLE `{$table}`");
        return $structure[0]->{'Create Table'} . ";\n\n";
    }

    /**
     * Get table structure as array
     */
    protected function getTableStructure(string $table): array
    {
        $columns = DB::select("SHOW COLUMNS FROM `{$table}`");
        $indexes = DB::select("SHOW INDEX FROM `{$table}`");
        
        return [
            'columns' => $columns,
            'indexes' => $indexes,
        ];
    }

    /**
     * Get table data as JSON
     */
    protected function getTableJsonData(string $table, ?\DateTime $since = null): array
    {
        $query = DB::table($table);
        
        if ($since) {
            $query->where('updated_at', '>', $since)
                  ->orWhere('created_at', '>', $since);
        }
        
        return $query->get()->toArray();
    }

    /**
     * Create JSON metadata
     */
    protected function createJsonMetadata(Backup $backup): array
    {
        return [
            'backup_type' => $backup->type,
            'backup_format' => $backup->format,
            'created_at' => $backup->created_at->toIso8601String(),
            'created_by' => $backup->user->name ?? 'System',
            'database' => DB::connection()->getDatabaseName(),
            'version' => config('app.version', '1.0.0'),
            'description' => $backup->description,
        ];
    }

    /**
     * Encrypt data if encryption is enabled
     */
    protected function encryptData(string $data): string
    {
        $key = config('backup.encryption.key');
        
        if (!$key) {
            throw new Exception('Encryption key not configured');
        }
        
        $iv = random_bytes(openssl_cipher_iv_length('aes-256-cbc'));
        $encrypted = openssl_encrypt($data, 'aes-256-cbc', $key, 0, $iv);
        
        return base64_encode($iv . $encrypted);
    }

    /**
     * Delete old backups based on retention policy
     */
    public function cleanupOldBackups(): int
    {
        $deletedCount = 0;
        $retention = config('backup.retention', []);
        
        // Delete old daily backups
        if (isset($retention['daily'])) {
            $deletedCount += $this->deleteBackupsOlderThan($retention['daily'], 'daily');
        }
        
        // Delete old weekly backups
        if (isset($retention['weekly'])) {
            $deletedCount += $this->deleteBackupsOlderThan($retention['weekly'], 'weekly');
        }
        
        // Delete old monthly backups
        if (isset($retention['monthly'])) {
            $deletedCount += $this->deleteBackupsOlderThan($retention['monthly'], 'monthly');
        }
        
        // Delete old yearly backups
        if (isset($retention['yearly'])) {
            $deletedCount += $this->deleteBackupsOlderThan($retention['yearly'], 'yearly');
        }
        
        return $deletedCount;
    }

    /**
     * Delete backups older than specified days
     */
    protected function deleteBackupsOlderThan(int $days, string $period): int
    {
        $cutoffDate = now()->subDays($days);
        
        return Backup::where('created_at', '<', $cutoffDate)
            ->where('options->period', $period)
            ->delete();
    }
}