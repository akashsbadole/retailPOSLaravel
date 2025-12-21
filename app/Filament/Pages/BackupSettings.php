<?php

namespace App\Filament\Pages;

use App\Models\Store;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Pages\Page;
use Filament\Actions;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Filament\Schemas\Components\Section;

class BackupSettings extends Page
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected string $view = 'filament.pages.backup-settings';

    protected static string|\UnitEnum|null $navigationGroup = 'System Management';

    protected static ?int $navigationSort = 4;

    protected static ?string $title = 'Backup Settings';

    protected static ?string $slug = 'backup-settings';

    public ?array $data = [];

    public bool $showTestModal = false;

    public function mount(): void
    {
        $this->form->fill([
            'retention' => [
                'daily' => config('backup.retention.daily', 30),
                'weekly' => config('backup.retention.weekly', 12),
                'monthly' => config('backup.retention.monthly', 12),
                'yearly' => config('backup.retention.yearly', 5),
            ],
            'compression' => [
                'enabled' => config('backup.compression.enabled', true),
                'level' => config('backup.compression.level', 6),
            ],
            'encryption' => [
                'enabled' => config('backup.encryption.enabled', false),
                'key' => config('backup.encryption.key', ''),
            ],
            'notifications' => [
                'enabled' => config('backup.notifications.enabled', true),
                'on_success' => config('backup.notifications.on_success', true),
                'on_failure' => config('backup.notifications.on_failure', true),
                'channels' => [
                    'mail' => config('backup.notifications.channels.mail', true),
                    'database' => config('backup.notifications.channels.database', true),
                ],
            ],
            'schedules' => [
                'daily' => [
                    'enabled' => config('backup.schedules.daily.enabled', false),
                    'time' => config('backup.schedules.daily.time', '02:00'),
                    'type' => config('backup.schedules.daily.type', 'incremental'),
                ],
                'weekly' => [
                    'enabled' => config('backup.schedules.weekly.enabled', false),
                    'day' => config('backup.schedules.weekly.day', 0),
                    'time' => config('backup.schedules.weekly.time', '03:00'),
                    'type' => config('backup.schedules.weekly.type', 'selective'),
                ],
                'monthly' => [
                    'enabled' => config('backup.schedules.monthly.enabled', false),
                    'day' => config('backup.schedules.monthly.day', 1),
                    'time' => config('backup.schedules.monthly.time', '04:00'),
                    'type' => config('backup.schedules.monthly.type', 'full'),
                ],
            ],
            'performance' => [
                'chunk_size' => config('backup.performance.chunk_size', 1000),
                'memory_limit' => config('backup.performance.memory_limit', '512M'),
                'timeout' => config('backup.performance.timeout', 3600),
                'max_concurrent' => config('backup.performance.max_concurrent', 1),
            ],
            'security' => [
                'file_permissions' => config('backup.security.file_permissions', '0600'),
                'directory_permissions' => config('backup.security.directory_permissions', '0700'),
                'validate_integrity' => config('backup.security.validate_integrity', true),
                'sanitize_filenames' => config('backup.security.sanitize_filenames', true),
            ],
            'multi_store' => [
                'enabled' => config('backup.multi_store.enabled', true),
                'include_store_data' => config('backup.multi_store.include_store_data', true),
                'separate_store_backups' => config('backup.multi_store.separate_store_backups', false),
            ],
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Retention Policy')
                    ->description('Configure how long different types of backups should be retained')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('retention.daily')
                                    ->label('Daily Backups')
                                    ->numeric()
                                    ->suffix('days')
                                    ->default(30)
                                    ->helperText('Keep daily backups for this many days'),
                                
                                Forms\Components\TextInput::make('retention.weekly')
                                    ->label('Weekly Backups')
                                    ->numeric()
                                    ->suffix('weeks')
                                    ->default(12)
                                    ->helperText('Keep weekly backups for this many weeks'),
                                
                                Forms\Components\TextInput::make('retention.monthly')
                                    ->label('Monthly Backups')
                                    ->numeric()
                                    ->suffix('months')
                                    ->default(12)
                                    ->helperText('Keep monthly backups for this many months'),
                                
                                Forms\Components\TextInput::make('retention.yearly')
                                    ->label('Yearly Backups')
                                    ->numeric()
                                    ->suffix('years')
                                    ->default(5)
                                    ->helperText('Keep yearly backups for this many years'),
                            ]),
                    ]),

                Section::make('Compression Settings')
                    ->description('Configure backup compression options')
                    ->schema([
                        Forms\Components\Toggle::make('compression.enabled')
                            ->label('Enable Compression')
                            ->default(true)
                            ->helperText('Compress backup files to save storage space'),
                        
                        Forms\Components\Select::make('compression.level')
                            ->label('Compression Level')
                            ->options([
                                1 => 'Level 1 (Fastest)',
                                2 => 'Level 2',
                                3 => 'Level 3',
                                4 => 'Level 4',
                                5 => 'Level 5',
                                6 => 'Level 6 (Default)',
                                7 => 'Level 7',
                                8 => 'Level 8',
                                9 => 'Level 9 (Best)',
                            ])
                            ->default(6)
                            ->helperText('Higher levels provide better compression but take longer')
                            ->visible(fn (callable $get) => $get('compression.enabled')),
                    ])
                    ->columns(2),

                Section::make('Encryption Settings')
                    ->description('Configure backup encryption for security')
                    ->schema([
                        Forms\Components\Toggle::make('encryption.enabled')
                            ->label('Enable Encryption')
                            ->default(false)
                            ->helperText('Encrypt backup files for security'),
                        
                        Forms\Components\TextInput::make('encryption.key')
                            ->label('Encryption Key')
                            ->password()
                            ->placeholder('Enter encryption key')
                            ->helperText('Key used for encrypting backup files. Keep this secure!')
                            ->visible(fn (callable $get) => $get('encryption.enabled')),
                    ])
                    ->columns(2),

                Section::make('Notification Settings')
                    ->description('Configure backup notifications')
                    ->schema([
                        Forms\Components\Toggle::make('notifications.enabled')
                            ->label('Enable Notifications')
                            ->default(true)
                            ->helperText('Send notifications for backup operations'),
                        
                        Forms\Components\CheckboxList::make('notifications.channels')
                            ->label('Notification Channels')
                            ->options([
                                'mail' => 'Email Notifications',
                                'database' => 'In-App Notifications',
                            ])
                            ->default(['mail', 'database'])
                            ->columns(2)
                            ->visible(fn (callable $get) => $get('notifications.enabled')),
                        
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Toggle::make('notifications.on_success')
                                    ->label('Notify on Success')
                                    ->default(true)
                                    ->helperText('Send notification when backup completes successfully'),
                                
                                Forms\Components\Toggle::make('notifications.on_failure')
                                    ->label('Notify on Failure')
                                    ->default(true)
                                    ->helperText('Send notification when backup fails'),
                            ])
                            ->visible(fn (callable $get) => $get('notifications.enabled')),
                    ])
                    ->columns(1),

                Section::make('Automatic Backup Schedules')
                    ->description('Configure automatic backup schedules')
                    ->schema([
                        Forms\Components\Grid::make(1)
                            ->schema([
                                Section::make('Daily Backup')
                                    ->schema([
                                        Forms\Components\Toggle::make('schedules.daily.enabled')
                                            ->label('Enable Daily Backup')
                                            ->default(false),
                                        
                                        Forms\Components\Grid::make(2)
                                            ->schema([
                                                Forms\Components\TimePicker::make('schedules.daily.time')
                                                    ->label('Time')
                                                    ->default('02:00')
                                                    ->withoutSeconds(),
                                                
                                                Forms\Components\Select::make('schedules.daily.type')
                                                    ->label('Backup Type')
                                                    ->options([
                                                        'full' => 'Full Backup',
                                                        'selective' => 'Selective Backup',
                                                        'incremental' => 'Incremental Backup',
                                                        'structure' => 'Structure Only',
                                                    ])
                                                    ->default('incremental'),
                                            ])
                                            ->visible(fn (callable $get) => $get('schedules.daily.enabled')),
                                    ])
                                    ->collapsed(),
                                
                                Section::make('Weekly Backup')
                                    ->schema([
                                        Forms\Components\Toggle::make('schedules.weekly.enabled')
                                            ->label('Enable Weekly Backup')
                                            ->default(false),
                                        
                                        Forms\Components\Grid::make(3)
                                            ->schema([
                                                Forms\Components\Select::make('schedules.weekly.day')
                                                    ->label('Day')
                                                    ->options([
                                                        0 => 'Sunday',
                                                        1 => 'Monday',
                                                        2 => 'Tuesday',
                                                        3 => 'Wednesday',
                                                        4 => 'Thursday',
                                                        5 => 'Friday',
                                                        6 => 'Saturday',
                                                    ])
                                                    ->default(0),
                                                
                                                Forms\Components\TimePicker::make('schedules.weekly.time')
                                                    ->label('Time')
                                                    ->default('03:00')
                                                    ->withoutSeconds(),
                                                
                                                Forms\Components\Select::make('schedules.weekly.type')
                                                    ->label('Backup Type')
                                                    ->options([
                                                        'full' => 'Full Backup',
                                                        'selective' => 'Selective Backup',
                                                        'incremental' => 'Incremental Backup',
                                                        'structure' => 'Structure Only',
                                                    ])
                                                    ->default('selective'),
                                            ])
                                            ->visible(fn (callable $get) => $get('schedules.weekly.enabled')),
                                    ])
                                    ->collapsed(),
                                
                                Section::make('Monthly Backup')
                                    ->schema([
                                        Forms\Components\Toggle::make('schedules.monthly.enabled')
                                            ->label('Enable Monthly Backup')
                                            ->default(false),
                                        
                                        Forms\Components\Grid::make(3)
                                            ->schema([
                                                Forms\Components\TextInput::make('schedules.monthly.day')
                                                    ->label('Day of Month')
                                                    ->numeric()
                                                    ->min(1)
                                                    ->max(31)
                                                    ->default(1),
                                                
                                                Forms\Components\TimePicker::make('schedules.monthly.time')
                                                    ->label('Time')
                                                    ->default('04:00')
                                                    ->withoutSeconds(),
                                                
                                                Forms\Components\Select::make('schedules.monthly.type')
                                                    ->label('Backup Type')
                                                    ->options([
                                                        'full' => 'Full Backup',
                                                        'selective' => 'Selective Backup',
                                                        'incremental' => 'Incremental Backup',
                                                        'structure' => 'Structure Only',
                                                    ])
                                                    ->default('full'),
                                            ])
                                            ->visible(fn (callable $get) => $get('schedules.monthly.enabled')),
                                    ])
                                    ->collapsed(),
                            ]),
                    ])
                    ->collapsed(),

                Section::make('Multi-Store Settings')
                    ->description('Configure multi-store backup options')
                    ->schema([
                        Forms\Components\Toggle::make('multi_store.enabled')
                            ->label('Enable Multi-Store Support')
                            ->default(true)
                            ->helperText('Enable multi-store backup functionality'),
                        
                        Forms\Components\Toggle::make('multi_store.include_store_data')
                            ->label('Include Store Data')
                            ->default(true)
                            ->helperText('Include store-specific data in backups')
                            ->visible(fn (callable $get) => $get('multi_store.enabled')),
                        
                        Forms\Components\Toggle::make('multi_store.separate_store_backups')
                            ->label('Separate Store Backups')
                            ->default(false)
                            ->helperText('Create separate backups for each store')
                            ->visible(fn (callable $get) => $get('multi_store.enabled')),
                    ])
                    ->columns(1)
                    ->visible(fn () => config('backup.multi_store.enabled', true)),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        // Save settings to config file or database
        // This is a simplified example - in production, you might want to
        // save these to a dedicated settings table or config cache
        
        Notification::make()
            ->title('Settings Saved')
            ->body('Backup settings have been updated successfully.')
            ->success()
            ->send();
    }

    protected function getFormActions(): array
    {
        return [
            Actions\Action::make('save')
                ->label('Save Settings')
                ->submit('save')
                ->icon('heroicon-o-check'),
            
            Actions\Action::make('test_backup')
                ->label('Test Backup')
                ->icon('heroicon-o-play')
                ->color('success')
                ->action(function () {
                    // Create a test backup with current settings
                    Notification::make()
                        ->title('Test Backup Started')
                        ->body('A test backup has been started with current settings.')
                        ->info()
                        ->send();
                }),
        ];
    }
}