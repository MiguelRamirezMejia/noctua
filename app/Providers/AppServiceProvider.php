<?php

namespace App\Providers;

use App\Actions\Book\ExtractPageText;
use App\Actions\Book\RenderPageImage;
use App\Actions\Book\RenderThumbnails;
use App\Actions\Book\TextlessPage;
use App\Actions\Demo\PurgeDemoAccount;
use App\Activity\ActivityLog;
use App\Activity\ActivityType;
use App\Domain\Typesetting\Typst\BorderPreview;
use App\Domain\Typesetting\Typst\PageLayers;
use App\Domain\Typesetting\Typst\TypstRunner;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TypstRunner::class, fn (): TypstRunner => new TypstRunner(
            config()->string('noctua.binaries.typst'),
            config()->integer('noctua.process_timeout'),
        ));

        $this->app->singleton(PageLayers::class, fn (Application $app): PageLayers => new PageLayers(
            $app->make(TypstRunner::class),
            resource_path('typst/capas.typ'),
        ));

        $this->app->singleton(BorderPreview::class, fn (Application $app): BorderPreview => new BorderPreview(
            $app->make(TypstRunner::class),
            resource_path('typst/marco-vista.typ'),
            storage_path('framework/cache/noctua-marcos'),
        ));

        $this->app->singleton(TextlessPage::class, fn (): TextlessPage => new TextlessPage(
            config()->string('noctua.binaries.ghostscript'),
            config()->string('noctua.binaries.pdftotext'),
            config()->string('noctua.binaries.pdftoppm'),
            config()->integer('noctua.process_timeout'),
        ));

        $this->app->singleton(ExtractPageText::class, fn (): ExtractPageText => new ExtractPageText(
            config()->string('noctua.binaries.pdftotext'),
            config()->string('noctua.binaries.pdftohtml'),
            config()->integer('noctua.process_timeout'),
        ));

        $this->app->singleton(RenderPageImage::class, fn (Application $app): RenderPageImage => new RenderPageImage(
            $app->make(TextlessPage::class),
            config()->string('noctua.binaries.pdftoppm'),
            config()->integer('noctua.process_timeout'),
        ));

        $this->app->singleton(RenderThumbnails::class, fn (): RenderThumbnails => new RenderThumbnails(
            config()->string('noctua.binaries.pdftoppm'),
            config()->integer('noctua.process_timeout'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        $this->recordAuthentication();

        // Solo quien administra la instalación ve el registro de actividad.
        Gate::define('view-activity', fn (User $user): bool => ! $user->isDemo()
            && in_array(strtolower($user->email), config()->array('noctua.admins'), true));
    }

    /**
     * Entradas, salidas, registros e intentos fallidos van al registro de
     * actividad. Al salir del modo de prueba se borra todo lo de esa cuenta.
     */
    private function recordAuthentication(): void
    {
        Event::listen(function (Login $event): void {
            if ($event->user instanceof User && ! $event->user->isDemo()) {
                app(ActivityLog::class)->record(ActivityType::LoggedIn, 'Inició sesión.', user: $event->user);
            }
        });

        Event::listen(function (Registered $event): void {
            if ($event->user instanceof User) {
                app(ActivityLog::class)->record(ActivityType::Registered, 'Creó su cuenta ('.$event->user->email.').', user: $event->user);
            }
        });

        Event::listen(function (Failed $event): void {
            $email = is_string($event->credentials['email'] ?? null) ? $event->credentials['email'] : '';
            app(ActivityLog::class)->record(ActivityType::LoginFailed, "Intento fallido de inicio de sesión con «{$email}».", ['correo' => $email]);
        });

        Event::listen(function (Logout $event): void {
            if (! $event->user instanceof User) {
                return;
            }

            if ($event->user->isDemo()) {
                app(ActivityLog::class)->record(ActivityType::DemoDeleted, 'Salió del modo de prueba: se borró su cuenta con todos sus libros.', user: $event->user);
                app(PurgeDemoAccount::class)->handle($event->user);

                return;
            }

            app(ActivityLog::class)->record(ActivityType::LoggedOut, 'Cerró sesión.', user: $event->user);
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
