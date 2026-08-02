<?php

namespace App\Providers\Filament;

use App\Http\Middleware\AuthenticateStaffPanel;
use App\Http\Middleware\AuthenticateStaffSession;
use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

abstract class HotelPanelProvider extends PanelProvider
{
    /**
     * @param  array<class-string>  $resources
     * @param  array<class-string>  $pages
     * @param  array<class-string>  $widgets
     * @param  array<string>  $navigationGroups
     */
    protected function configureHotelPanel(
        Panel $panel,
        string $id,
        string $path,
        string $roleLabel,
        array $resources,
        array $pages,
        array $widgets,
        array $navigationGroups,
    ): Panel {
        return $panel
            ->id($id)
            ->path($path)
            ->brandName("M.A Hotels | {$roleLabel}")
            ->brandLogo(asset('MALogo.png'))
            ->darkModeBrandLogo(asset('MALogo.png'))
            ->brandLogoHeight('3rem')
            ->favicon(asset('MALogo.png'))
            ->font('Poppins', provider: LocalFontProvider::class)
            ->viteTheme('resources/css/filament/hotel/theme.css')
            ->colors([
                'primary' => Color::hex('#16233f'),
            ])
            ->resources($resources)
            ->pages($pages)
            ->widgets($widgets)
            ->navigationGroups($navigationGroups)
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateStaffSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                AuthenticateStaffPanel::class,
            ]);
    }
}
