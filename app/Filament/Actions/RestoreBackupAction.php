<?php

namespace App\Filament\Actions;

use App\Models\Backup;
use App\Jobs\RestoreBackupJob;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Actions\Action;
use Illuminate\Support\Facades\Auth;

class RestoreBackupAction extends Action
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Restore')
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('warning')
            ->tooltip('Restore from this backup')
            ->requiresConfirmation()
            ->modalHeading('Restore Backup')
            ->modalDescription('Are you sure you want to restore from this backup? This action will replace current data and cannot be undone.')
            ->modalSubmitActionLabel('Yes, Restore')
            ->modalCancelActionLabel('Cancel')
            ->form([
                Section::make('Restore Options')
                    ->description('Configure how the backup should be restored')
                    ->schema([
                        Toggle::make('create_restore_point')
                            ->label('Create Restore Point')
                            ->default(true)
                            ->helperText('Create a restore point before restoring this backup'),

                        Toggle::make('disable_foreign_keys')
                            ->label('Disable Foreign Key Checks')
                            ->default(true)
                            ->helperText('Temporarily disable foreign key checks during restore'),

                        Toggle::make('truncate_tables')
                            ->label('Truncate Tables First')
                            ->default(true)
                            ->helperText('Remove existing data before restoring'),

                        CheckboxList::make('tables')
                            ->label('Tables to Restore')
                            ->options(function (Backup $record) {
                                $tables = $record->tables ?? [];
                                return array_combine($tables, $tables);
                            })
                            ->columns(3)
                            ->default(function (Backup $record) {
                                return $record->tables ?? [];
                            })
                            ->helperText('Select which tables to restore. Leave all selected to restore everything.')
                            ->visible(fn (Backup $record) => $record->type === Backup::TYPE_SELECTIVE),

                        Textarea::make('notes')
                            ->label('Restore Notes')
                            ->rows(2)
                            ->helperText('Optional notes about this restore operation'),
                    ])
                    ->columns(1),

                Section::make('Warning')
                    ->description('Please read this warning carefully before proceeding')
                    ->schema([
                        Placeholder::make('warning')
                            ->label('⚠️ Important Warning')
                            ->content(function (Backup $record) {
                                $backupDate = $record->created_at->format('F j, Y \a\t g:i A');
                                $backupSize = $record->formatted_file_size;
                                
                                return "
                                    <div class='space-y-2 text-sm'>
                                        <p><strong>This action will replace your current data with data from:</strong></p>
                                        <ul class='list-disc list-inside ml-4 space-y-1'>
                                            <li>Backup created on: {$backupDate}</li>
                                            <li>Backup size: {$backupSize}</li>
                                            <li>Backup type: {$record->type_label}</li>
                                        </ul>
                                        <p class='text-red-600 font-semibold mt-3'>
                                            <strong>This action cannot be undone!</strong><br>
                                            All current data will be permanently replaced.
                                        </p>
                                        <p class='text-amber-600 mt-2'>
                                            It's recommended to create a backup of your current data before proceeding.
                                        </p>
                                    </div>
                                ";
                            }),
                    ]),
            ])
            ->action(function (Backup $record, array $data) {
                try {
                    // Prepare restore options
                    $options = [
                        'create_restore_point' => $data['create_restore_point'] ?? true,
                        'disable_foreign_keys' => $data['disable_foreign_keys'] ?? true,
                        'truncate_tables' => $data['truncate_tables'] ?? true,
                        'notes' => $data['notes'] ?? null,
                    ];

                    // Add selected tables if selective restore
                    if ($record->type === Backup::TYPE_SELECTIVE && isset($data['tables'])) {
                        $options['tables'] = $data['tables'];
                    }

                    // Dispatch restore job
                    RestoreBackupJob::dispatch($record->id, Auth::id(), $options);

                    $this->successNotification()
                        ->title('Restore Started')
                        ->body('The restore process has been started. You will be notified when it completes.')
                        ->send();

                } catch (\Exception $e) {
                    $this->failureNotification()
                        ->title('Restore Failed')
                        ->body('Failed to start restore process: ' . $e->getMessage())
                        ->send();
                }
            })
            ->successNotificationTitle('Restore Started')
            ->successNotificationMessage('The restore process has been started. You will be notified when it completes.')
            ->visible(fn (Backup $record): bool => $record->isCompleted() && $record->fileExists());
    }

    public static function make(string $name = null): static
    {
        $name = $name ?? 'restore_backup';
        $static = parent::make($name);
        
        // Customize for different contexts
        if ($name === 'quick_restore') {
            $static->label('Quick Restore')
                ->icon('heroicon-bolt')
                ->color('danger')
                ->modalHeading('Quick Restore')
                ->modalDescription('Quick restore from this backup with default options')
                ->form([
                    Placeholder::make('warning')
                        ->label('⚠️ Warning')
                        ->content(function (Backup $record) {
                            $backupDate = $record->created_at->format('F j, Y \a\t g:i A');
                            return "
                                <div class='text-sm'>
                                    <p>This will restore data from backup created on <strong>{$backupDate}</strong>.</p>
                                    <p class='text-red-600 font-semibold mt-2'>This action cannot be undone!</p>
                                </div>
                            ";
                        }),
                ])
                ->action(function (Backup $record) {
                    try {
                        // Quick restore with default options
                        $options = [
                            'create_restore_point' => true,
                            'disable_foreign_keys' => true,
                            'truncate_tables' => true,
                        ];

                        RestoreBackupJob::dispatch($record->id, Auth::id(), $options);

                        $this->successNotification()
                            ->title('Quick Restore Started')
                            ->body('The restore process has been started with default options.')
                            ->send();

                    } catch (\Exception $e) {
                        $this->failureNotification()
                            ->title('Restore Failed')
                            ->body('Failed to start restore process: ' . $e->getMessage())
                            ->send();
                    }
                });
        }
        
        return $static;
    }
}