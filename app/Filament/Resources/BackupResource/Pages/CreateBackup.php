<?php

namespace App\Filament\Resources\BackupResource\Pages;

use App\Filament\Resources\BackupResource;
use App\Models\Backup;
use App\Models\Store;
use App\Services\BackupService;
use App\Jobs\CreateBackupJob;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class CreateBackup extends CreateRecord
{
    protected static string $resource = BackupResource::class;

    protected ?string $heading = 'Create New Backup';

    protected ?string $subheading = 'Configure and create a new database backup';

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\Section::make('Backup Configuration')
                    ->description('Configure the basic settings for your backup')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Backup Name')
                            ->required()
                            ->maxLength(255)
                            ->default(fn () => 'Backup - ' . now()->format('Y-m-d H:i:s'))
                            ->helperText('A descriptive name for this backup'),

                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->rows(3)
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
                    ->description('Configure additional backup options')
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

                        Forms\Components\Toggle::make('options.validate_integrity')
                            ->label('Validate Integrity')
                            ->default(config('backup.security.validate_integrity', true))
                            ->helperText('Validate backup file integrity after creation'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Storage Settings')
                    ->description('Configure where the backup will be stored')
                    ->schema([
                        Forms\Components\Select::make('disk')
                            ->label('Storage Disk')
                            ->options(function () {
                                $disks = config('filesystems.disks', []);
                                $availableDisks = [];
                                
                                foreach ($disks as $key => $config) {
                                    if (isset($config['driver'])) {
                                        $availableDisks[$key] = ucfirst($key) . ' (' . $config['driver'] . ')';
                                    }
                                }
                                
                                return $availableDisks;
                            })
                            ->default(config('backup.disk', 'local'))
                            ->required()
                            ->helperText('Select the storage disk for this backup'),

                        Forms\Components\TextInput::make('custom_path')
                            ->label('Custom Path (Optional)')
                            ->placeholder('backups/custom-folder')
                            ->helperText('Custom path within the selected disk. Leave empty for default.'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Preview')
                    ->description('Review your backup configuration before creating')
                    ->schema([
                        Forms\Components\Placeholder::make('preview')
                            ->label('Backup Summary')
                            ->content(function (callable $get) {
                                $type = $get('type');
                                $format = $get('format');
                                $compression = $get('options.compression') ? 'Enabled' : 'Disabled';
                                $encryption = $get('options.encryption') ? 'Enabled' : 'Disabled';
                                $disk = $get('disk') ?: 'Default';
                                
                                $typeLabels = [
                                    Backup::TYPE_FULL => 'Full Backup',
                                    Backup::TYPE_SELECTIVE => 'Selective Backup',
                                    Backup::TYPE_INCREMENTAL => 'Incremental Backup',
                                    Backup::TYPE_STRUCTURE => 'Structure Only',
                                ];
                                
                                $typeLabel = $typeLabels[$type] ?? 'Unknown';
                                
                                return "
                                    <div class='space-y-2'>
                                        <div><strong>Type:</strong> {$typeLabel}</div>
                                        <div><strong>Format:</strong> " . strtoupper($format) . "</div>
                                        <div><strong>Compression:</strong> {$compression}</div>
                                        <div><strong>Encryption:</strong> {$encryption}</div>
                                        <div><strong>Storage:</strong> {$disk}</div>
                                    </div>
                                ";
                            }),
                    ])
                    ->visible(fn (callable $get) => $get('type')),
            ]);
    }

    protected function handleRecordCreation(array $data): Backup
    {
        // Prepare backup options
        $options = $data['options'] ?? [];
        $options['custom_path'] = $data['custom_path'] ?? null;
        
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

        // Update disk if provided
        if (!empty($data['disk'])) {
            $backup->update(['disk' => $data['disk']]);
        }

        // Dispatch backup job
        CreateBackupJob::dispatch($backup->id);

        return $backup;
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Backup creation started';
    }

    protected function getCreatedNotificationMessage(): ?string
    {
        return 'The backup has been queued for creation. You will be notified when it completes.';
    }

    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction()
                ->label('Create Backup')
                ->icon('heroicon-o-archive-box-arrow-down'),
            
            $this->getCancelFormAction()
                ->label('Cancel'),
        ];
    }
}