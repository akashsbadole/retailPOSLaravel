<?php

namespace App\Filament\Resources\BackupResource\Pages;

use App\Filament\Resources\BackupResource;
use App\Filament\Actions\CreateBackupAction;
use App\Models\Backup;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ListBackups extends ListRecords
{
    protected static string $resource = BackupResource::class;

    protected ?string $heading = 'Backups';

    protected ?string $subheading = 'Manage database backups and restore points';

    protected function getHeaderActions(): array
    {
        return [
            CreateBackupAction::make(),
            Actions\Action::make('cleanup')
                ->label('Cleanup Old Backups')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->action(function () {
                    $backupService = app(\App\Services\BackupService::class);
                    $deletedCount = $backupService->cleanupOldBackups();
                    
                    $this->notify('success', "Deleted {$deletedCount} old backups based on retention policy.");
                })
                ->requiresConfirmation()
                ->modalHeading('Cleanup Old Backups')
                ->modalDescription('This will delete old backups based on the retention policy. Are you sure?')
                ->modalSubmitActionLabel('Yes, Clean Up'),
        ];
    }

    protected function getTableRecordsPerPageSelectOptions(): array
    {
        return [10, 25, 50, 100];
    }

    public function table(Table $table): Table
    {
        return parent::table($table)
            ->actions([
                Action::make('view_details')
                    ->label('View Details')
                    ->icon('heroicon-o-eye')
                    ->color('primary')
                    ->modalContent(fn (Backup $record): string => 
                        view('filament.resources.backup-resource.view', ['record' => $record])->render()
                    )
                    ->modalWidth('2xl')
                    ->modalFooterActions(fn (Backup $record): array => [
                        Action::make('download')
                            ->label('Download')
                            ->icon('heroicon-o-arrow-down-tray')
                            ->url(fn () => $record->download_url)
                            ->visible(fn () => $record->isCompleted()),
                        Action::make('restore')
                            ->label('Restore')
                            ->icon('heroicon-o-arrow-uturn-left')
                            ->color('warning')
                            ->action(fn () => $this->restoreBackup($record))
                            ->requiresConfirmation()
                            ->modalHeading('Restore Backup')
                            ->modalDescription('Are you sure you want to restore from this backup? This will replace current data.')
                            ->modalSubmitActionLabel('Yes, Restore')
                            ->visible(fn () => $record->isCompleted()),
                    ]),
            ]);
    }

    protected function restoreBackup(Backup $backup): void
    {
        try {
            // Create a restore job
            \App\Jobs\RestoreBackupJob::dispatch($backup->id, auth()->id());
            
            $this->notify('success', 'Restore job has been queued. You will be notified when it completes.');
        } catch (\Exception $e) {
            $this->notify('danger', 'Failed to start restore: ' . $e->getMessage());
        }
    }

    public function getTabs(): array
    {
        return [
            'all' => \Filament\Resources\Components\Tab::make('All Backups')
                ->modifyQueryUsing(fn (Builder $query) => $query),
            
            'processing' => \Filament\Resources\Components\Tab::make('Processing')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', Backup::STATUS_PROCESSING))
                ->badge(Backup::where('status', Backup::STATUS_PROCESSING)->count()),
            
            'completed' => \Filament\Resources\Components\Tab::make('Completed')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', Backup::STATUS_COMPLETED))
                ->badge(Backup::where('status', Backup::STATUS_COMPLETED)->count()),
            
            'failed' => \Filament\Resources\Components\Tab::make('Failed')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', Backup::STATUS_FAILED))
                ->badge(Backup::where('status', Backup::STATUS_FAILED)->count()),
            
            'recent' => \Filament\Resources\Components\Tab::make('Recent (7 days)')
                ->modifyQueryUsing(fn (Builder $query) => $query->recent(7)),
        ];
    }

    protected function getTableQuery(): Builder
    {
        return parent::getTableQuery()->with(['user', 'store']);
    }
}