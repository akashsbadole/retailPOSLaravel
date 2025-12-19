<?php

namespace App\Filament\Pages;

use App\Models\CashierShift;
use App\Models\Setting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class ShiftManagement extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected static string|\UnitEnum|null $navigationGroup = 'Shift Management';

    protected string $view = 'filament.pages.shift-management';

    protected static ?string $navigationLabel = 'Shift Management';

    protected static ?string $title = 'Shift Management';

    protected static ?int $navigationSort = 2;

    public ?CashierShift $currentShift = null;

    public function mount(): void
    {
        $this->currentShift = Auth::user()?->currentShift();
    }

    public function openShiftForm(): Form
    {
        return Forms\Form::make([
            Forms\Components\Section::make('Open New Shift')
                ->description('Start a new shift by entering the opening cash amount')
                ->schema([
                    Forms\Components\TextInput::make('opening_cash')
                        ->label('Opening Cash Amount (₹)')
                        ->numeric()
                        ->prefix('₹')
                        ->step(0.01)
                        ->default(0)
                        ->required()
                        ->helperText('Enter the amount of cash in the drawer before starting sales'),

                    Forms\Components\Textarea::make('notes')
                        ->label('Opening Notes')
                        ->rows(2)
                        ->placeholder('Enter any notes about this shift opening...'),
                ])
                ->columns(1),
        ]);
    }

    public function closeShiftForm(): Form
    {
        return Forms\Form::make([
            Forms\Components\Section::make('Close Current Shift')
                ->description('Close your shift by reconciling cash and sales')
                ->schema([
                    Forms\Components\Placeholder::make('shift_summary')
                        ->label('Shift Summary')
                        ->content(function () {
                            if (!$this->currentShift) {
                                return 'No active shift';
                            }

                            return "
                                <div class='space-y-2'>
                                    <div><strong>Start Time:</strong> {$this->currentShift->start_time->format('M j, Y g:i A')}</div>
                                    <div><strong>Duration:</strong> {$this->currentShift->duration}</div>
                                    <div><strong>Total Sales:</strong> ₹" . number_format($this->currentShift->total_sales, 2) . "</div>
                                    <div><strong>Cash Sales:</strong> ₹" . number_format($this->currentShift->cash_collected, 2) . "</div>
                                    <div><strong>Expected Cash:</strong> ₹" . number_format($this->currentShift->expected_cash, 2) . "</div>
                                </div>
                            ";
                        }),

                    Forms\Components\TextInput::make('closing_cash')
                        ->label('Actual Cash in Drawer (₹)')
                        ->numeric()
                        ->prefix('₹')
                        ->step(0.01)
                        ->required()
                        ->helperText('Count all cash in the drawer and enter the actual amount'),

                    Forms\Components\Textarea::make('notes')
                        ->label('Closing Notes')
                        ->rows(3)
                        ->placeholder('Enter any notes about cash discrepancies or shift issues...'),
                ])
                ->columns(1),
        ]);
    }

    public function openShift(array $data): void
    {
        $user = Auth::user();

        // Check if user already has an open shift
        if ($user->currentShift()) {
            Notification::make()
                ->title('Shift Already Open')
                ->body('You already have an active shift. Please close it before opening a new one.')
                ->warning()
                ->send();
            return;
        }

        // Check if shift opening is required
        $requireShiftOpening = Setting::where('key', 'require_shift_opening')->first()?->value ?? '1';
        if ($requireShiftOpening === '0') {
            Notification::make()
                ->title('Shift Opening Not Required')
                ->body('Shift opening is not required according to system settings.')
                ->info()
                ->send();
            return;
        }

        // Create new shift
        $shift = CashierShift::openShift($user, $data['opening_cash']);

        if (!empty($data['notes'])) {
            $shift->update(['notes' => $data['notes']]);
        }

        Notification::make()
            ->title('Shift Opened')
            ->body('Your shift has been opened successfully. You can now start taking orders.')
            ->success()
            ->send();

        $this->currentShift = $shift;
    }

    public function closeShift(array $data): void
    {
        if (!$this->currentShift) {
            Notification::make()
                ->title('No Active Shift')
                ->body('You do not have an active shift to close.')
                ->warning()
                ->send();
            return;
        }

        // Check cash discrepancy tolerance
        $tolerance = (float) (Setting::where('key', 'cash_discrepancy_tolerance')->first()?->value ?? 50.00);
        $discrepancy = $data['closing_cash'] - $this->currentShift->expected_cash;

        if (abs($discrepancy) > $tolerance) {
            Notification::make()
                ->title('Large Cash Discrepancy')
                ->body("Cash discrepancy of ₹" . number_format(abs($discrepancy), 2) . " exceeds tolerance of ₹" . number_format($tolerance, 2) . ". Please verify your count.")
                ->warning()
                ->send();
        }

        // Close the shift
        $this->currentShift->closeShift($data['closing_cash'], $data['notes'] ?? null);

        Notification::make()
            ->title('Shift Closed')
            ->body('Your shift has been closed successfully. Thank you for your service.')
            ->success()
            ->send();

        $this->currentShift = null;
    }

    public function getFormActions(): array
    {
        return [
            Action::make('open_shift')
                ->label('Open New Shift')
                ->icon('heroicon-o-play')
                ->color('success')
                ->visible(fn() => !$this->currentShift && auth()->user()->can('cashier-shift.create'))
                ->form($this->openShiftForm())
                ->action('openShift'),

            Action::make('close_current_shift')
                ->label('Close Shift')
                ->icon('heroicon-o-stop')
                ->color('danger')
                ->visible(fn() => $this->currentShift && auth()->user()->can('cashier-shift.close'))
                ->form($this->closeShiftForm())
                ->action('closeShift'),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()->hasRole(['cashier', 'manager', 'admin']);
    }
}