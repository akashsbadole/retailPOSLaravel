<?php

namespace App\Filament\Actions;

use App\Models\Backup;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Components\Section;
use Filament\Actions\Action;
use Illuminate\Support\Facades\Storage;

class DeleteBackupAction extends Action
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Delete')
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->tooltip('Delete backup')
            ->requiresConfirmation()
            ->modalHeading('Delete Backup')
            ->modalDescription('Are you sure you want to delete this backup? This action cannot be undone.')
            ->modalSubmitActionLabel('Yes, Delete')
            ->modalCancelActionLabel('Cancel')
            ->form([
                Section::make('Backup Information')
                    ->schema([
                        Placeholder::make('backup_info')
                            ->label('Backup Details')
                            ->content(function (Backup $record) {
                                $backupDate = $record->created_at->format('F j, Y \a\t g:i A');
                                $backupSize = $record->formatted_file_size;
                                $backupType = $record->type_label;
                                $backupStatus = $record->status_label;
                                
                                return "
                                    <div class='space-y-2 text-sm'>
                                        <div><strong>Name:</strong> {$record->name}</div>
                                        <div><strong>Type:</strong> {$backupType}</div>
                                        <div><strong>Status:</strong> {$backupStatus}</div>
                                        <div><strong>Created:</strong> {$backupDate}</div>
                                        <div><strong>Size:</strong> {$backupSize}</div>
                                    </div>
                                ";
                            }),
                    ]),
                
                Section::make('Warning')
                    ->description('Please read this warning carefully before proceeding')
                    ->schema([
                        Placeholder::make('warning')
                            ->label('⚠️ Important Warning')
                            ->content(function (Backup $record) {
                                $isCompleted = $record->isCompleted();
                                $hasFile = $record->fileExists();
                                
                                $warningMessage = $isCompleted 
                                    ? "This will permanently delete the backup file and all associated data."
                                    : "This will permanently delete this backup record.";
                                
                                if ($hasFile) {
                                    $warningMessage .= " The backup file will be removed from storage.";
                                }
                                
                                return "
                                    <div class='space-y-2 text-sm'>
                                        <p class='text-red-600 font-semibold'>{$warningMessage}</p>
                                        <p class='text-amber-600'>This action cannot be undone!</p>
                                    </div>
                                ";
                            }),
                    ]),
                
                Checkbox::make('confirm_delete')
                    ->label('I understand that this action cannot be undone')
                    ->required()
                    ->validationMessages([
                        'required' => 'You must confirm that you understand this action cannot be undone.',
                    ]),
            ])
            ->action(function (Backup $record, array $data) {
                try {
                    if (!($data['confirm_delete'] ?? false)) {
                        $this->failureNotification()
                            ->title('Deletion Cancelled')
                            ->body('You must confirm that you understand this action cannot be undone.')
                            ->send();
                        return;
                    }

                    if ($record->isProcessing()) {
                        $this->failureNotification()
                            ->title('Cannot Delete')
                            ->body('Cannot delete a backup that is currently being processed.')
                            ->send();
                        return;
                    }

                    $record->delete();

                    $this->successNotification()
                        ->title('Backup Deleted')
                        ->body('The backup has been successfully deleted.')
                        ->send();

                } catch (\Exception $e) {
                    $this->failureNotification()
                        ->title('Deletion Failed')
                        ->body('Failed to delete backup: ' . $e->getMessage())
                        ->send();
                }
            })
            ->successNotificationTitle('Backup Deleted')
            ->successNotificationMessage('The backup has been successfully deleted.');
    }

    public static function make(string $name = null): static
    {
        $name = $name ?? 'delete_backup';
        $static = parent::make($name);
        
        if ($name === 'bulk_delete') {
            $static->label('Delete Selected')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Delete Selected Backups')
                ->modalDescription('Are you sure you want to delete the selected backups? This action cannot be undone.')
                ->modalSubmitActionLabel('Yes, Delete All')
                ->modalCancelActionLabel('Cancel')
                ->form([
                    Checkbox::make('confirm_bulk_delete')
                        ->label('I understand that this action cannot be undone')
                        ->required(),
                ])
                ->action(function (array $data) {
                    try {
                        if (!($data['confirm_bulk_delete'] ?? false)) {
                            $this->failureNotification()
                                ->title('Deletion Cancelled')
                                ->body('You must confirm that you understand this action cannot be undone.')
                                ->send();
                            return;
                        }

                        $records = $this->getSelectedRecords();

                        $processingCount = $records->filter(fn ($record) => $record->isProcessing())->count();
                        if ($processingCount > 0) {
                            $this->failureNotification()
                                ->title('Cannot Delete')
                                ->body("Cannot delete {$processingCount} backup(s) that are currently being processed.")
                                ->send();
                            return;
                        }

                        $deletedCount = 0;
                        foreach ($records as $record) {
                            $record->delete();
                            $deletedCount++;
                        }

                        $this->successNotification()
                            ->title('Backups Deleted')
                            ->body("Successfully deleted {$deletedCount} backup(s).")
                            ->send();

                    } catch (\Exception $e) {
                        $this->failureNotification()
                            ->title('Deletion Failed')
                            ->body('Failed to delete backups: ' . $e->getMessage())
                            ->send();
                    }
                });
        }
        
        return $static;
    }
}
