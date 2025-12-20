<?php

namespace App\Providers\Filament;

use Filament\Pages\Dashboard;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Filament\Navigation\NavigationItem;
use Filament\Navigation\MenuItem;
use Illuminate\Support\Facades\Auth;
use App\Filament\Components\StoreSelector;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->brandName('SuperMart')
            ->profile()
            ->id('admin')
            ->path('admin')
            ->login()
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Dashboard::class,
                \App\Filament\Pages\ShiftManagement::class,
                \App\Filament\Pages\ShiftReports::class,
                \App\Filament\Pages\ProductSalesReport::class,
                \App\Filament\Pages\CashierPerformanceReport::class,
                \App\Filament\Pages\ProfitReport::class,
                \App\Filament\Pages\BackupSettings::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                \App\Filament\Widgets\BackupStatsWidget::class,
                \App\Filament\Widgets\RecentBackupsWidget::class,
                // Widgets\AccountWidget::class,
                // Widgets\FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->sidebarWidth('13rem')
            ->sidebarCollapsibleOnDesktop()
            ->userMenuItems([
                MenuItem::make('My Profile')
                    ->label('My Profile')
                    ->url(fn(): string => route('filament.admin.auth.profile'))
                    ->icon('heroicon-o-user-circle'),
            ])
            ->renderHook(
                'panels::user-menu.before',
                fn (): string => view('filament.user-role-badge', ['user' => Auth::user()])->render()
            )
            ->renderHook(
                'panels::topbar.start',
                fn (): string => view('filament.components.store-selector')->render()
            )
            ->navigationItems([
                NavigationItem::make('POS')
                    ->url('/pos')
                    ->icon('heroicon-o-shopping-cart')
                    ->visible(fn(): bool => Auth::user()?->can('order.create')),
            ])
            ->viteTheme('resources/css/app.css');
    }
}
