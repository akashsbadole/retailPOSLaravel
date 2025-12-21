<?php

namespace App\Filament\Widgets;

use App\Models\Backup;
use Filament\Tables;
use Filament\Actions\Action;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class RecentBackupsWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected static ?string $pollingInterval = '30s';

    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Backup::query()
                    ->with(['user', 'store'])
                    ->latest()
                    ->limit(10)
            )
            ->columns([
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
            ])
            ->actions([
                Action::make('download')
                    ->label('Download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->visible(fn (Backup $record): bool => $record->isCompleted() && $record->file_path)
                    ->action(function (Backup $record) {
                        if (!$record->fileExists()) {
                            return;
                        }
                        $disk = $record->disk;
                        $filePath = $record->file_path;
                        $filename = $record->name . '.' . $record->format;
                        
                        return Storage::disk($disk)->download($filePath, $filename);
                    }),
            ])
            ->headerActions([
                Action::make('create_backup')
                    ->label('Create Backup')
                    ->icon('heroicon-o-plus')
                    ->color('primary')
                    ->url(route('filament.admin.resources.backups.create')),
            ])
            ->emptyStateHeading('No backups found')
            ->emptyStateDescription('Create your first backup to get started with data protection.')
            ->emptyStateActions([
                Action::make('create_first_backup')
                    ->label('Create First Backup')
                    ->icon('heroicon-o-archive-box-arrow-down')
                    ->color('primary')
                    ->url(route('filament.admin.resources.backups.create')),
            ])
            ->paginated(false)
            ->heading('Recent Backups')
            ->description('Showing the 10 most recent backups');
    }
}
