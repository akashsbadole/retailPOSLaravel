<?php

namespace App\Filament\Widgets;

use App\Models\Backup;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class BackupStatsWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        $totalBackups = Backup::count();
        $completedBackups = Backup::completed()->count();
        $failedBackups = Backup::failed()->count();
        $processingBackups = Backup::processing()->count();

        // Calculate total storage used by backups
        $totalStorage = Backup::completed()->sum('file_size') ?? 0;
        $formattedStorage = $this->formatBytes($totalStorage);

        // Get last backup date
        $lastBackup = Backup::latest()->first();
        $lastBackupDate = $lastBackup?->created_at?->diffForHumans() ?? 'Never';

        // Get storage disk usage
        $diskUsage = $this->getDiskUsage();

        return [
            Stat::make('Total Backups', $totalBackups)
                ->description($completedBackups . ' completed')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('primary')
                ->chart([7, 2, 10, 3, 15, 4, 17]),

            Stat::make('Storage Used', $formattedStorage)
                ->description($diskUsage['percentage'] . '% of disk')
                ->descriptionIcon($diskUsage['percentage'] > 80 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-server')
                ->color($diskUsage['percentage'] > 80 ? 'danger' : 'success'),

            Stat::make('Last Backup', $lastBackupDate)
                ->description($processingBackups > 0 ? 'Processing...' : 'Completed')
                ->descriptionIcon($processingBackups > 0 ? 'heroicon-m-arrow-path' : 'heroicon-m-check-circle')
                ->color($processingBackups > 0 ? 'warning' : 'success'),

            Stat::make('Success Rate', $completedBackups . '/' . $totalBackups)
                ->description($totalBackups > 0 ? round(($completedBackups / $totalBackups) * 100, 1) . '%' : 'N/A')
                ->descriptionIcon($failedBackups > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($failedBackups > 0 ? 'warning' : 'success'),
        ];
    }

    protected function getDiskUsage(): array
    {
        try {
            $disk = config('backup.disk', 'local');
            $path = config('backup.path', 'backups');
            
            if ($disk === 'local') {
                $backupPath = storage_path('app/' . $path);
                
                if (is_dir($backupPath)) {
                    $totalSpace = disk_total_space($backupPath);
                    $freeSpace = disk_free_space($backupPath);
                    $usedSpace = $totalSpace - $freeSpace;
                    
                    $percentage = $totalSpace > 0 ? round(($usedSpace / $totalSpace) * 100, 1) : 0;
                    
                    return [
                        'total' => $this->formatBytes($totalSpace),
                        'used' => $this->formatBytes($usedSpace),
                        'free' => $this->formatBytes($freeSpace),
                        'percentage' => $percentage,
                    ];
                }
            }
            
            // For non-local disks, we can't easily get disk usage
            return [
                'total' => 'N/A',
                'used' => 'N/A',
                'free' => 'N/A',
                'percentage' => 0,
            ];
            
        } catch (\Exception $e) {
            return [
                'total' => 'N/A',
                'used' => 'N/A',
                'free' => 'N/A',
                'percentage' => 0,
            ];
        }
    }

    protected function formatBytes(int $bytes): string
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

    protected function getColumns(): int
    {
        return 4;
    }
}