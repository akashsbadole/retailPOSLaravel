<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BackupResource\Pages;
use App\Filament\Actions\CreateBackupAction;
use App\Filament\Actions\DownloadBackupAction;
use App\Filament\Actions\RestoreBackupAction;
use App\Filament\Actions\DeleteBackupAction;
use App\Models\Backup;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Storage;

class BackupResource extends Resource
{
    protected static ?string $model = Backup::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-archive-box-arrow-down';

    protected static string|\UnitEnum|null $navigationGroup = 'System Management';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\Section::make('Backup Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->helperText('A descriptive name for this backup'),

                        Forms\Components\Textarea::make('description')
                            ->rows(3)
                            ->helperText('Optional description of what this backup contains'),

                        Forms\Components\Select::make('type')
                            ->options([
                                Backup::TYPE_FULL => 'Full Backup - Complete database backup',
                                Backup::TYPE_SELECTIVE => 'Selective Backup - Specific tables only',
                                Backup::TYPE_INCREMENTAL => 'Incremental Backup - Changes since last backup',
                                Backup::TYPE_STRUCTURE => 'Structure Only - Database schema without data',
                            ])
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(fn ($state, callable $set) => 
                                $set('tables', $state === Backup::TYPE_SELECTIVE ? [] : null)
                            )
                            ->helperText('Select the type of backup to create'),

                        Forms\Components\CheckboxList::make('tables')
                            ->label('Tables to Include')
                            ->options(function () {
                                $tables = config('backup.tables.include.selective', []);
                                return array_combine($tables, $tables);
                            })
                            ->columns(3)
                            ->required()
                            ->visible(fn (callable $get) => $get('type') === Backup::TYPE_SELECTIVE)
                            ->helperText('Select which tables to include in the backup'),

                        Forms\Components\Select::make('format')
                            ->options([
                                Backup::FORMAT_SQL => 'SQL - Standard SQL dump file',
                                Backup::FORMAT_JSON => 'JSON - Structured JSON format',
                            ])
                            ->default(Backup::FORMAT_SQL)
                            ->required()
                            ->helperText('Choose the backup file format'),

                        Forms\Components\Select::make('store_id')
                            ->label('Store')
                            ->relationship('store', 'name')
                            ->placeholder('All Stores')
                            ->helperText('Create backup for specific store or all stores')
                            ->visible(fn () => config('backup.multi_store.enabled', true)),

                        Forms\Components\Section::make('Backup Options')
                            ->schema([
                                Forms\Components\Toggle::make('options.compression')
                                    ->label('Enable Compression')
                                    ->default(config('backup.compression.enabled', true))
                                    ->helperText('Compress the backup file to save storage space'),

                                Forms\Components\Toggle::make('options.encryption')
                                    ->label('Enable Encryption')
                                    ->default(config('backup.encryption.enabled', false))
                                    ->helperText('Encrypt the backup file for security')
                                    ->visible(fn () => config('backup.encryption.key', false)),

                                Forms\Components\Toggle::make('options.send_notification')
                                    ->label('Send Notification')
                                    ->default(config('backup.notifications.on_success', true))
                                    ->helperText('Send notification when backup completes'),
                            ])
                            ->columns(1),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->limit(50)
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

                Tables\Columns\TextColumn::make('format')
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('formatted_file_size')
                    ->label('File Size')
                    ->sortable()
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('total_records')
                    ->label('Records')
                    ->sortable()
                    ->alignEnd()
                    ->formatStateUsing(fn ($state) => number_format($state)),

                Tables\Columns\TextColumn::make('duration')
                    ->label('Duration')
                    ->sortable()
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Created By')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('store.name')
                    ->label('Store')
                    ->sortable()
                    ->toggleable()
                    ->visible(fn () => config('backup.multi_store.enabled', true)),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('completed_at')
                    ->label('Completed')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        Backup::TYPE_FULL => 'Full Backup',
                        Backup::TYPE_SELECTIVE => 'Selective Backup',
                        Backup::TYPE_INCREMENTAL => 'Incremental Backup',
                        Backup::TYPE_STRUCTURE => 'Structure Only',
                    ]),

                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        Backup::STATUS_PENDING => 'Pending',
                        Backup::STATUS_PROCESSING => 'Processing',
                        Backup::STATUS_COMPLETED => 'Completed',
                        Backup::STATUS_FAILED => 'Failed',
                    ]),

                Tables\Filters\SelectFilter::make('format')
                    ->options([
                        Backup::FORMAT_SQL => 'SQL',
                        Backup::FORMAT_JSON => 'JSON',
                    ]),

                Tables\Filters\SelectFilter::make('user_id')
                    ->label('Created By')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('store_id')
                    ->label('Store')
                    ->relationship('store', 'name')
                    ->searchable()
                    ->preload()
                    ->visible(fn () => config('backup.multi_store.enabled', true)),

                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label('Created From'),
                        Forms\Components\DatePicker::make('created_until')
                            ->label('Created Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['created_from'] ?? null) {
                            $indicators['created_from'] = 'Created from ' . $data['created_from'];
                        }
                        if ($data['created_until'] ?? null) {
                            $indicators['created_until'] = 'Created until ' . $data['created_until'];
                        }
                        return $indicators;
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->modalContent(fn (Backup $record): string => 
                        view('filament.resources.backup-resource.view', ['record' => $record])->render()
                    ),

                DownloadBackupAction::make()
                    ->visible(fn (Backup $record): bool => $record->isCompleted()),

                RestoreBackupAction::make()
                    ->visible(fn (Backup $record): bool => $record->isCompleted()),

                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation()
                    ->modalHeading('Delete Backup')
                    ->modalDescription('Are you sure you want to delete this backup? This action cannot be undone.')
                    ->modalSubmitActionLabel('Yes, Delete'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->requiresConfirmation()
                        ->modalHeading('Delete Selected Backups')
                        ->modalDescription('Are you sure you want to delete the selected backups? This action cannot be undone.')
                        ->modalSubmitActionLabel('Yes, Delete All'),
                ]),
            ])
            ->emptyStateActions([
                CreateBackupAction::make(),
            ])
            ->emptyStateHeading('No backups found')
            ->emptyStateDescription('Create your first backup to get started.')
            ->emptyStateIcon('heroicon-o-archive-box-arrow-down');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBackups::route('/'),
            'create' => Pages\CreateBackup::route('/create'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', Backup::STATUS_PROCESSING)->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'type', 'status'];
    }
}