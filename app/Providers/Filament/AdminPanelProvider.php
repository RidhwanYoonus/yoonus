<?php

namespace App\Providers\Filament;

use App\Support\Settings;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationItem;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->profile()
            ->brandName(fn () => Settings::get('store_name').' · Admin')
            ->brandLogo(fn () => rescue(fn () => Settings::imageUrl('brand_logo'), null, false))
            ->darkModeBrandLogo(fn () => rescue(fn () => Settings::imageUrl('brand_logo_dark') ?? Settings::imageUrl('brand_logo'), null, false))
            ->brandLogoHeight('2.5rem')
            ->favicon(fn () => rescue(fn () => Settings::imageUrl('brand_favicon'), null, false) ?? asset('favicon.ico'))
            ->colors(['primary' => Color::hex('#b8954a'), 'gray' => Color::Stone])
            ->font('Inter')
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])
            ->sidebarCollapsibleOnDesktop()
            ->navigationGroups(['Sales', 'Gold rates', 'Catalogue', 'Offers', 'Customers', 'Affiliates', 'Marketing', 'Settings'])
            ->navigationItems([
                NavigationItem::make('View shop')->url(fn () => url('/'), shouldOpenInNewTab: true)->icon('heroicon-o-arrow-top-right-on-square')->sort(99),
            ])
            ->renderHook(PanelsRenderHook::STYLES_AFTER, fn () => self::textSizeStyle())
            ->renderHook(PanelsRenderHook::STYLES_AFTER, fn () => '<style>
                .affiliate-order-row{background:rgba(184,149,74,.10)}
                .affiliate-order-row:hover{background:rgba(184,149,74,.16)!important}
                .affiliate-order-row>td:first-child{box-shadow:inset 4px 0 0 #b8954a}
                .affiliate-order-section{border:1px solid rgba(184,149,74,.55)!important;background:rgba(184,149,74,.06)}
                @media (max-width:639px){
                    .fi-main{padding-left:.75rem!important;padding-right:.75rem!important}
                    .fi-section-content{padding:1rem!important}
                    .fi-header-heading{font-size:1.5rem!important;line-height:2rem!important}
                    .fi-ac{flex-wrap:wrap}
                    .fi-ta-header-toolbar{flex-wrap:wrap;gap:.5rem}
                    .fi-ta-text{white-space:normal}
                }
            </style>')
            // Phones: the side menu starts closed on every page instead of covering it.
            ->renderHook(PanelsRenderHook::BODY_END, fn () => '<script>
                (function () {
                    const close = () => { if (window.innerWidth < 1024 && window.Alpine && Alpine.store("sidebar")) Alpine.store("sidebar").close(); };
                    document.addEventListener("alpine:initialized", close);
                    document.addEventListener("livewire:navigated", close);
                })();
            </script>')
            ->renderHook(PanelsRenderHook::BODY_START, fn () => view('filament.database-update-banner')->render().view('filament.stale-rate-banner')->render())
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([Pages\Dashboard::class])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
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
            ->authMiddleware([Authenticate::class, \App\Http\Middleware\AdminSecurity::class], isPersistent: true);
    }

    /** Filament sizes everything in rem, so the root font size scales the whole panel. */
    public static function textSizeStyle(): string
    {
        return match (rescue(fn () => Settings::get('admin_text_size', 'auto'), 'auto', false)) {
            'normal' => '',
            'large' => '<style>html{font-size:112.5%}</style>',
            'xlarge' => '<style>html{font-size:125%}</style>',
            default => '<style>@media (min-width:1440px){html{font-size:106.25%}}@media (min-width:1680px){html{font-size:112.5%}}@media (min-width:2200px){html{font-size:125%}}</style>',
        };
    }
}
