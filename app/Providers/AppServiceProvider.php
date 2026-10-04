<?php

namespace App\Providers;

use App\Actions\Book\ExtractPageText;
use App\Actions\Book\RenderPageImage;
use App\Actions\Book\RenderThumbnails;
use App\Actions\Book\TextlessPage;
use App\Domain\Typesetting\Typst\BorderPreview;
use App\Domain\Typesetting\Typst\PageLayers;
use App\Domain\Typesetting\Typst\TypstRunner;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
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
