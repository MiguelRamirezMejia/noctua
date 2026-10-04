<?php

namespace App\Console\Commands;

use App\Fonts\FontLibrary;
use App\Fonts\InvalidFont;
use App\Models\Font;
use Illuminate\Console\Command;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Support\Facades\Http;

class InstallFontCatalogCommand extends Command
{
    protected $signature = 'noctua:fonts:install
        {--family=* : Instalar solo estas familias}
        {--force : Volver a descargar las variantes ya instaladas}';

    protected $description = 'Descarga e instala el catálogo de fuentes libres (Google Fonts, licencia OFL)';

    /** Variantes a intentar: [cursiva, peso]. */
    private const array STYLES = [[0, 400], [0, 700], [1, 400], [1, 700]];

    public function handle(FontLibrary $library): int
    {
        $catalog = $this->catalog();
        $only = array_filter((array) $this->option('family'), is_string(...));

        if ($only !== []) {
            $catalog = array_intersect_key($catalog, array_flip($only));
        }

        $installed = 0;
        foreach ($catalog as $family => $category) {
            $styles = [];

            foreach (self::STYLES as [$italic, $weight]) {
                $exists = Font::query()->whereNull('publisher_id')->where('catalog_family', $family)->where('weight', $weight)->where('italic', (bool) $italic)->exists();

                if ($exists && ! $this->option('force')) {
                    $styles[] = $this->label($italic, $weight);

                    continue;
                }

                try {
                    $url = $this->fontUrl($family, $italic, $weight);
                } catch (HttpClientException $e) {
                    $this->components->warn("{$family} ({$this->label($italic, $weight)}): sin conexión, se reintentará en la próxima ejecución.");

                    continue;
                }

                if ($url === null) {
                    continue;
                }

                $tmp = tempnam(sys_get_temp_dir(), 'noctua-font-') ?: throw new \RuntimeException('No se pudo crear un archivo temporal.');

                try {
                    Http::timeout(60)->connectTimeout(20)->retry(3, 2000)->sink($tmp)->get($url)->throw();
                    $library->register($tmp, basename(parse_url($url, PHP_URL_PATH) ?: 'fuente.ttf'), category: $category, catalogFamily: $family);
                    $styles[] = $this->label($italic, $weight);
                    $installed++;
                } catch (InvalidFont $e) {
                    $this->components->warn("{$family}: {$e->getMessage()}");
                } catch (HttpClientException $e) {
                    $this->components->warn("{$family} ({$this->label($italic, $weight)}): no se pudo descargar, se reintentará en la próxima ejecución.");
                } finally {
                    @unlink($tmp);
                }
            }

            $this->components->twoColumnDetail($family, $styles === [] ? '<fg=red>no disponible</>' : implode(', ', $styles));
        }

        $this->components->info("Variantes nuevas instaladas: {$installed}.");

        return self::SUCCESS;
    }

    /**
     * URL del TTF estático de una variante, o null si la familia no la tiene.
     */
    private function fontUrl(string $family, int $italic, int $weight): ?string
    {
        // Con un User-Agent antiguo, la API devuelve TTF en lugar de WOFF2.
        $response = Http::timeout(30)
            ->connectTimeout(20)
            ->retry(3, 2000, throw: false)
            ->withHeaders(['User-Agent' => 'Mozilla/4.0'])
            ->get('https://fonts.googleapis.com/css2', ['family' => "{$family}:ital,wght@{$italic},{$weight}"]);

        if (! $response->successful() || ! preg_match('/url\((https:[^)]+\.ttf)\)/', $response->body(), $m)) {
            return null;
        }

        return $m[1];
    }

    private function label(int $italic, int $weight): string
    {
        return match ([$italic, $weight]) {
            [0, 400] => 'regular',
            [0, 700] => 'negrita',
            [1, 400] => 'cursiva',
            default => 'negrita cursiva',
        };
    }

    /**
     * @return array<string, string>
     */
    private function catalog(): array
    {
        $catalog = [];
        foreach (config()->array('noctua.font_catalog') as $family => $category) {
            if (is_string($family) && is_string($category)) {
                $catalog[$family] = $category;
            }
        }

        return $catalog;
    }
}
