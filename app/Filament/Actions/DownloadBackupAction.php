<?php

namespace App\Filament\Actions;

use App\Models\Backup;
use Filament\Actions\Action;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadBackupAction extends Action
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Download')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('success')
            ->tooltip('Download backup file')
            ->action(function (Backup $record) {
                if (!$record->isCompleted()) {
                    $this->failureNotification()
                        ->title('Download Failed')
                        ->body('Cannot download a backup that has not completed.')
                        ->send();
                    return null;
                }

                if (!$record->file_path) {
                    $this->failureNotification()
                        ->title('Download Failed')
                        ->body('Backup file not found.')
                        ->send();
                    return null;
                }

                if (!$record->fileExists()) {
                    $this->failureNotification()
                        ->title('Download Failed')
                        ->body('Backup file does not exist on storage.')
                        ->send();
                    return null;
                }

                try {
                    $disk = $record->disk;
                    $filePath = $record->file_path;
                    
                    // Generate download filename
                    $filename = $record->name . '.' . $record->format;
                    
                    // Add compression extension if applicable
                    if (str_ends_with($filePath, '.gz')) {
                        $filename .= '.gz';
                    }
                    
                    // Add encryption extension if applicable
                    if (str_ends_with($filePath, '.enc')) {
                        $filename .= '.enc';
                    }

                    return Storage::disk($disk)->download($filePath, $filename);
                } catch (\Exception $e) {
                    $this->failureNotification()
                        ->title('Download Failed')
                        ->body('Failed to download backup file: ' . $e->getMessage())
                        ->send();
                    return null;
                }
            })
            ->visible(fn (Backup $record): bool => $record->isCompleted() && $record->file_path)
            ->disabled(fn (Backup $record): bool => !$record->fileExists());
    }

    public static function make(string $name = null): static
    {
        $name = $name ?? 'download_backup';
        $static = parent::make($name);
        
        // Customize for different contexts
        if ($name === 'download_direct') {
            $static->label('Download')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->size('sm')
                ->tooltip('Download backup file')
                ->action(function (Backup $record) {
                    if (!$record->isCompleted() || !$record->file_path || !$record->fileExists()) {
                        return null;
                    }

                    $disk = $record->disk;
                    $filePath = $record->file_path;
                    
                    // Generate download filename
                    $filename = $record->name . '.' . $record->format;
                    
                    // Add compression extension if applicable
                    if (str_ends_with($filePath, '.gz')) {
                        $filename .= '.gz';
                    }
                    
                    // Add encryption extension if applicable
                    if (str_ends_with($filePath, '.enc')) {
                        $filename .= '.enc';
                    }

                    return Storage::disk($disk)->download($filePath, $filename);
                });
        }
        
        return $static;
    }
}