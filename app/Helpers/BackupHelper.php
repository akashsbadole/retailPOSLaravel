<?php

namespace App\Helpers;

use App\Models\Backup;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Exception;

class BackupHelper
{
    /**
     * Format bytes to human readable format
     *
     * @param int $bytes
     * @return string
     */
    public static function formatBytes(int $bytes): string
    {
        if ($bytes === 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $unitIndex = 0;

        while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
            $bytes /= 1024;
            $unitIndex++;
        }

        return round($bytes, 2) . ' ' . $units[$unitIndex];
    }

    /**
     * Calculate backup duration
     *
     * @param \DateTime|null $startedAt
     * @param \DateTime|null $completedAt
     * @return string|null
     */
    public static function calculateDuration(?\DateTime $startedAt, ?\DateTime $completedAt): ?string
    {
        if (!$startedAt) {
            return null;
        }

        $endTime = $completedAt ?: now();
        $duration = $startedAt->diffInSeconds($endTime);

        if ($duration < 60) {
            return $duration . ' seconds';
        }

        $minutes = floor($duration / 60);
        $seconds = $duration % 60;

        if ($minutes < 60) {
            return $minutes . 'm ' . $seconds . 's';
        }

        $hours = floor($minutes / 60);
        $minutes = $minutes % 60;

        return $hours . 'h ' . $minutes . 'm';
    }

    /**
     * Generate backup filename
     *
     * @param string $type
     * @param string $format
     * @param bool $compressed
     * @param bool $encrypted
     * @return string
     */
    public static function generateFilename(
        string $type,
        string $format = 'sql',
        bool $compressed = true,
        bool $encrypted = false
    ): string {
        $timestamp = now()->format('Y-m-d_H-i-s');
        $random = Str::random(6);
        $filename = "backup_{$type}_{$timestamp}_{$random}.{$format}";

        if ($compressed) {
            $filename .= '.gz';
        }

        if ($encrypted) {
            $filename .= '.enc';
        }

        return $filename;
    }

    /**
     * Sanitize filename
     *
     * @param string $filename
     * @return string
     */
    public static function sanitizeFilename(string $filename): string
    {
        // Remove special characters except dots, hyphens, and underscores
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        
        // Remove consecutive underscores
        $filename = preg_replace('/_+/', '_', $filename);
        
        // Remove leading/trailing underscores
        $filename = trim($filename, '_');
        
        // Ensure filename is not empty
        if (empty($filename)) {
            $filename = 'backup_' . time();
        }

        return $filename;
    }

    /**
     * Validate backup file integrity
     *
     * @param string $filePath
     * @param string $disk
     * @return bool
     */
    public static function validateFileIntegrity(string $filePath, string $disk = 'local'): bool
    {
        try {
            if (!Storage::disk($disk)->exists($filePath)) {
                return false;
            }

            $fileSize = Storage::disk($disk)->size($filePath);
            if ($fileSize === 0) {
                return false;
            }

            // For SQL files, check if they contain expected content
            if (str_ends_with($filePath, '.sql') || str_ends_with($filePath, '.sql.gz')) {
                $content = Storage::disk($disk)->get($filePath);
                
                // Decompress if needed
                if (str_ends_with($filePath, '.gz')) {
                    $content = gzdecode($content);
                    if ($content === false) {
                        return false;
                    }
                }
                
                // Check for SQL backup markers
                return strpos($content, '-- SuperMart POS Database Backup') !== false;
            }

            // For JSON files, check if they contain valid JSON
            if (str_ends_with($filePath, '.json') || str_ends_with($filePath, '.json.gz')) {
                $content = Storage::disk($disk)->get($filePath);
                
                // Decompress if needed
                if (str_ends_with($filePath, '.gz')) {
                    $content = gzdecode($content);
                    if ($content === false) {
                        return false;
                    }
                }
                
                json_decode($content);
                return json_last_error() === JSON_ERROR_NONE;
            }

            return true;

        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Get backup file information
     *
     * @param string $filePath
     * @param string $disk
     * @return array|null
     */
    public static function getFileInfo(string $filePath, string $disk = 'local'): ?array
    {
        try {
            if (!Storage::disk($disk)->exists($filePath)) {
                return null;
            }

            $fileSize = Storage::disk($disk)->size($filePath);
            $lastModified = Storage::disk($disk)->lastModified($filePath);

            return [
                'path' => $filePath,
                'size' => $fileSize,
                'formatted_size' => self::formatBytes($fileSize),
                'last_modified' => $lastModified,
                'last_modified_date' => date('Y-m-d H:i:s', $lastModified),
                'extension' => pathinfo($filePath, PATHINFO_EXTENSION),
                'basename' => basename($filePath),
                'dirname' => dirname($filePath),
                'is_compressed' => str_ends_with($filePath, '.gz'),
                'is_encrypted' => str_ends_with($filePath, '.enc'),
            ];

        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Get available disk space
     *
     * @param string $disk
     * @return array|null
     */
    public static function getDiskSpace(string $disk = 'local'): ?array
    {
        try {
            if ($disk === 'local') {
                $path = Storage::disk($disk)->path('/');
                $freeSpace = disk_free_space($path);
                $totalSpace = disk_total_space($path);
                $usedSpace = $totalSpace - $freeSpace;

                return [
                    'total' => $totalSpace,
                    'used' => $usedSpace,
                    'free' => $freeSpace,
                    'formatted_total' => self::formatBytes($totalSpace),
                    'formatted_used' => self::formatBytes($usedSpace),
                    'formatted_free' => self::formatBytes($freeSpace),
                    'usage_percentage' => round(($usedSpace / $totalSpace) * 100, 2),
                ];
            }

            // For non-local disks, we can't get accurate space information
            return null;

        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Check if backup directory has enough space
     *
     * @param int $estimatedSize
     * @param string $disk
     * @return bool
     */
    public static function hasEnoughSpace(int $estimatedSize, string $disk = 'local'): bool
    {
        $diskSpace = self::getDiskSpace($disk);
        
        if (!$diskSpace) {
            // If we can't determine space, assume there's enough
            return true;
        }

        return $diskSpace['free'] > $estimatedSize;
    }

    /**
     * Estimate backup size based on tables
     *
     * @param array $tables
     * @return int
     */
    public static function estimateBackupSize(array $tables): int
    {
        try {
            $totalSize = 0;

            foreach ($tables as $table) {
                // Get table size information
                $result = \Illuminate\Support\Facades\DB::select("
                    SELECT 
                        ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb
                    FROM information_schema.tables 
                    WHERE table_schema = DATABASE() 
                    AND table_name = ?
                ", [$table]);

                if (!empty($result)) {
                    $totalSize += $result[0]->size_mb * 1024 * 1024; // Convert to bytes
                }
            }

            // Add 20% buffer for compression overhead
            return (int) ($totalSize * 1.2);

        } catch (Exception $e) {
            // If we can't estimate, return a reasonable default
            return 100 * 1024 * 1024; // 100MB
        }
    }

    /**
     * Get backup statistics
     *
     * @return array
     */
    public static function getBackupStatistics(): array
    {
        try {
            $totalBackups = Backup::count();
            $completedBackups = Backup::completed()->count();
            $failedBackups = Backup::failed()->count();
            $processingBackups = Backup::processing()->count();

            $totalSize = Backup::completed()->sum('file_size');
            $avgSize = $completedBackups > 0 ? $totalSize / $completedBackups : 0;

            $lastBackup = Backup::latest()->first();
            $lastSuccessfulBackup = Backup::completed()->latest()->first();

            $backupsByType = Backup::selectRaw('type, COUNT(*) as count')
                ->groupBy('type')
                ->pluck('count', 'type')
                ->toArray();

            return [
                'total_backups' => $totalBackups,
                'completed_backups' => $completedBackups,
                'failed_backups' => $failedBackups,
                'processing_backups' => $processingBackups,
                'success_rate' => $totalBackups > 0 ? round(($completedBackups / $totalBackups) * 100, 2) : 0,
                'total_size' => $totalSize,
                'formatted_total_size' => self::formatBytes($totalSize),
                'average_size' => $avgSize,
                'formatted_average_size' => self::formatBytes($avgSize),
                'last_backup' => $lastBackup?->created_at,
                'last_successful_backup' => $lastSuccessfulBackup?->created_at,
                'backups_by_type' => $backupsByType,
            ];

        } catch (Exception $e) {
            return [
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Clean up old backup files
     *
     * @param int $days
     * @param string $disk
     * @return int Number of files deleted
     */
    public static function cleanupOldFiles(int $days, string $disk = 'local'): int
    {
        try {
            $path = config('backup.path', 'backups');
            $cutoffTime = now()->subDays($days)->timestamp;
            $deletedCount = 0;

            $files = Storage::disk($disk)->files($path);
            
            foreach ($files as $file) {
                $lastModified = Storage::disk($disk)->lastModified($file);
                
                if ($lastModified < $cutoffTime) {
                    Storage::disk($disk)->delete($file);
                    $deletedCount++;
                }
            }

            return $deletedCount;

        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Create backup directory if it doesn't exist
     *
     * @param string $disk
     * @return bool
     */
    public static function ensureBackupDirectory(string $disk = 'local'): bool
    {
        try {
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

            return true;

        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Get backup type options for forms
     *
     * @return array
     */
    public static function getBackupTypeOptions(): array
    {
        return [
            Backup::TYPE_FULL => 'Full Backup - Complete database backup',
            Backup::TYPE_SELECTIVE => 'Selective Backup - Specific tables only',
            Backup::TYPE_INCREMENTAL => 'Incremental Backup - Changes since last backup',
            Backup::TYPE_STRUCTURE => 'Structure Only - Database schema without data',
        ];
    }

    /**
     * Get backup format options for forms
     *
     * @return array
     */
    public static function getBackupFormatOptions(): array
    {
        return [
            Backup::FORMAT_SQL => 'SQL - Standard SQL dump file',
            Backup::FORMAT_JSON => 'JSON - Structured JSON format',
        ];
    }

    /**
     * Get available tables for backup
     *
     * @return array
     */
    public static function getAvailableTables(): array
    {
        try {
            $tables = \Illuminate\Support\Facades\DB::select('SHOW TABLES');
            return array_map('current', $tables);
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Check if backup is currently running
     *
     * @return bool
     */
    public static function isBackupRunning(): bool
    {
        return Backup::processing()->exists();
    }

    /**
     * Get running backup information
     *
     * @return Backup|null
     */
    public static function getRunningBackup(): ?Backup
    {
        return Backup::processing()->first();
    }
}