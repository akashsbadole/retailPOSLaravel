<?php

namespace App\Filament\Actions;

use App\Models\Backup;
use App\Models\Store;
use App\Services\BackupService;
use App\Jobs\CreateBackupJob;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables\Actions\Action;
use Illuminate\Support\Facades\Auth;

class CreateBackupAction extends Action
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Create Backup')
            ->icon('heroicon-o-archive-box-arrow-down')
            ->color('primary')
            ->modalHeading('Create New Backup')
            ->modalDescription('Configure and create a new database backup')
            ->modalWidth(MaxWidth::TwoExtraLarge)
            ->form([
                Forms\Components\Section::make('Backup Configuration')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Backup Name')
                            ->required()
                            ->maxLength(255)
                            ->default(fn () => 'Backup - ' . now()->format('Y-m-d H:i:s'))
                            ->helperText('A descriptive name for this backup'),

                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->rows(2)
                            ->helperText('Optional description of what this backup contains'),

                        Forms\Components\Select::make('type')
                            ->label('Backup Type')
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
                            ->label('Backup Format')
                            ->options([
                                Backup::FORMAT_SQL => 'SQL - Standard SQL dump file',
                                Backup::FORMAT_JSON => 'JSON - Structured JSON format',
                            ])
                            ->default(Backup::FORMAT_SQL)
                            ->required()
                            ->helperText('Choose the backup file format'),

                        Forms\Components\Select::make('store_id')
                            ->label('Store')
                            ->placeholder('All Stores')
                            ->options(function () {
                                if (Auth::user()->hasRole('super_admin')) {
                                    return Store::pluck('name', 'id');
                                }
                                return Store::where('id', Auth::user()->store_id ?? 0)->pluck('name', 'id');
                            })
                            ->default(fn () => Auth::user()->store_id)
                            ->helperText('Create backup for specific store or all stores')
                            ->visible(fn () => config('backup.multi_store.enabled', true)),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Advanced Options')
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
            ->action(function (array $data) {
                // Prepare backup options
                $options = $data['options'] ?? [];
                
                // Create backup record
                $backupService = app(BackupService::class);
                $backup = $backupService->createBackup(
                    type: $data['type'],
                    format: $data['format'],
                    description: $data['description'] ?? null,
                    tables: $data['tables'] ?? null,
                    options: $options,
                    user: Auth::user(),
                    store: $data['store_id'] ? Store::find($data['store_id']) : null
                );

                // Update backup name if provided
                if (!empty($data['name'])) {
                    $backup->update(['name' => $data['name']]);
                }

                // Dispatch backup job
                CreateBackupJob::dispatch($backup->id);

                $this->successNotification()
                    ->title('Backup creation started')
                    ->body('The backup has been queued for creation. You will be notified when it completes.')
                    ->send();
            })
            ->successNotificationTitle('Backup creation started')
            ->successNotificationMessage('The backup has been queued for creation. You will be notified when it completes.');
    }

    public static function make(string $name = null): static
    {
        $static = parent::make($name);
        
        // Customize for different contexts
        if ($name === 'create_quick_backup') {
            $static->label('Quick Backup')
                ->icon('heroicon-o-bolt')
                ->color('success')
                ->modalHeading('Create Quick Backup')
                ->form([
                    Forms\Components\Select::make('type')
                        ->label('Backup Type')
                        ->options([
                            Backup::TYPE_FULL => 'Full Backup',
                            Backup::TYPE_INCREMENTAL => 'Incremental Backup',
                        ])
                        ->default(Backup::TYPE_INCREMENTAL)
                        ->required(),
                    
                    Forms\Components\Toggle::make('options.send_notification')
                        ->label('Send Notification')
                        ->default(true),
                ]);
        }
        
        return $static;
    }
}