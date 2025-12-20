<?php

namespace App\Filament\Widgets;

use App\Filament\Actions\DownloadBackupAction;
use App\Filament\Actions\RestoreBackupAction;
use App\Models\Backup;
use Filament\Actions\Action;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\Auth;

class RecentBackupsWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected static ?string $pollingInterval = '30s';

    protected int | string | array $columnSpan = 'full';

    protected function getTableQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return Backup::query()
            ->with(['user', 'store'])
            ->latest()
            ->limit(10);
    }

    protected function getTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('name')
                ->label('Backup Name')
                ->searchable()
                ->sortable()
                ->limit(40)
                ->tooltip(fn (Backup $record): string => $record->name),

            Tables\Columns\BadgeColumn::make('type')
                ->colors([
                    'danger' => Backup::TYPE_FULL,
                    'warning' => Backup::TYPE_SELECTIVE,
                    'info' => Backup::TYPE_INCREMENTAL,
                    'gray' => Backup::TYPE_STRUCTURE,
                ])
                ->formatStateUsing(fn (string $state): string => match ($state) {
                    Backup::TYPE_FULL => 'Full',
                    Backup::TYPE_SELECTIVE => 'Selective',
                    Backup::TYPE_INCREMENTAL => 'Incremental',
                    Backup::TYPE_STRUCTURE => 'Structure',
                    default => $state,
                }),

            Tables\Columns\BadgeColumn::make('status')
                ->colors([
                    'warning' => Backup::STATUS_PENDING,
                    'info' => Backup::STATUS_PROCESSING,
                    'success' => Backup::STATUS_COMPLETED,
                    'danger' => Backup::STATUS_FAILED,
                ])
                ->formatStateUsing(fn (string $state): string => match ($state) {
                    Backup::STATUS_PENDING => 'Pending',
                    Backup::STATUS_PROCESSING => 'Processing',
                    Backup::STATUS_COMPLETED => 'Completed',
                    Backup::STATUS_FAILED => 'Failed',
                    default => $state,
                }),

            Tables\Columns\TextColumn::make('formatted_file_size')
                ->label('Size')
                ->sortable()
                ->alignEnd()
                ->default('N/A'),

            Tables\Columns\TextColumn::make('user.name')
                ->label('Created By')
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),

            Tables\Columns\TextColumn::make('created_at')
                ->label('Created')
                ->dateTime('M j, Y g:i A')
                ->sortable()
                ->description(fn (Backup $record): string => $record->created_at->diffForHumans()),
        ];
    }

    protected function getTableActions(): array
    {
        return [
            Action::make('view')
                ->label('View')
                ->icon('heroicon-o-eye')
                ->color('primary')
                ->modalContent(fn (Backup $record): string => 
                    view('filament.resources.backup-resource.view', ['record' => $record])->render()
                )
                ->modalWidth('2xl'),

            DownloadBackupAction::make('download_direct')
                ->visible(fn (Backup $record): bool => $record->isCompleted()),

            RestoreBackupAction::make('quick_restore')
                ->visible(fn (Backup $record): bool => $record->isCompleted()),
        ];
    }

    protected function getTableHeaderActions(): array
    {
        return [
            Action::make('create_backup')
                ->label('Create Backup')
                ->icon('heroicon-o-plus')
                ->color('primary')
                ->action(function () {
                    // Redirect to create backup page
                    return redirect()->route('filament.admin.resources.backups.create');
                }),
        ];
    }

    protected function getTableEmptyStateHeading(): ?string
    {
        return 'No backups found';
    }

    protected function getTableEmptyStateDescription(): ?string
    {
        return 'Create your first backup to get started with data protection.';
    }

    protected function getTableEmptyStateActions(): array
    {
        return [
            Action::make('create_first_backup')
                ->label('Create First Backup')
                ->icon('heroicon-o-archive-box-arrow-down')
                ->color('primary')
                ->action(function () {
                    return redirect()->route('filament.admin.resources.backups.create');
                }),
        ];
    }

    protected function isTablePaginationEnabled(): bool
    {
        return false;
    }

    public function getTableHeading(): string
    {
        return 'Recent Backups';
    }

    public function getTableDescription(): ?string
    {
        return 'Showing the 10 most recent backups';
    }

    protected function getTableRecordsPerPageSelectOptions(): array
    {
        return [];
    }
}