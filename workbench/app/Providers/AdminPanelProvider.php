<?php

declare(strict_types=1);

namespace Workbench\App\Providers;

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Orion\NotifyRules\NotifyRulesPlugin;
use Workbench\App\Filament\Resources\Orders\OrderResource;
use Workbench\App\Http\Middleware\SetLocale;
use Workbench\App\Models\Order;
use Workbench\App\Models\User;

final class AdminPanelProvider extends PanelProvider
{
    public function register(): void
    {
        parent::register();

        config()->set('auth.providers.users.model', User::class);
        config()->set('notify-rules.user_model', User::class);
    }

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->colors([
                'primary' => Color::Sky,
            ])
            ->pages([
                Dashboard::class,
            ])
            ->resources([
                OrderResource::class,
            ])
            ->plugin(
                NotifyRulesPlugin::make()
                    ->models([Order::class])
                    ->navigationGroup('Notifications')
                    ->deliveryLog()
                    ->connectTelegramInUserMenu()
                    ->canManageRules(fn (User $user): bool => $user->is_admin),
            )
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
            // Persistent, so Livewire requests (forms, tables, actions) use the chosen language too.
            ->middleware([SetLocale::class], isPersistent: true)
            ->routes(function (): void {
                Route::get('locale/{locale}', function (string $locale): RedirectResponse {
                    session()->put('locale', $locale);

                    return redirect()->back();
                })
                    ->whereIn('locale', array_keys(SetLocale::LOCALES))
                    ->name('locale');

                // Local only: logs in and opens a page in one request, for headless screenshots of the README.
                if (app()->environment('local')) {
                    Route::get('screenshot-login', function (Request $request): RedirectResponse {
                        $user = User::query()->where('email', $request->query('email', 'admin@example.com'))->firstOrFail();
                        Filament::auth()->login($user);

                        if (array_key_exists((string) $request->query('locale'), SetLocale::LOCALES)) {
                            session()->put('locale', $request->query('locale'));
                        }

                        $to = (string) $request->query('to', '/admin');

                        return redirect(str_starts_with($to, '/admin') ? $to : '/admin');
                    })->name('screenshot-login');
                }
            })
            ->userMenuItems([
                Action::make('workbench-locale')
                    ->label(fn (): string => SetLocale::LOCALES[$this->otherLocale()])
                    ->icon(Heroicon::OutlinedLanguage)
                    ->url(fn (): string => route('filament.admin.locale', ['locale' => $this->otherLocale()])),
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }

    private function otherLocale(): string
    {
        return app()->getLocale() === 'ru' ? 'en' : 'ru';
    }
}
