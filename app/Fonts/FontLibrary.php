<?php

namespace App\Fonts;

use App\Domain\Typesetting\TypesettingFailed;
use App\Domain\Typesetting\Typst\TypstRunner;
use App\Models\Font;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Fuentes disponibles para un usuario: las incluidas en Typst, las del
 * catálogo y las que el usuario ha subido.
 */
final class FontLibrary
{
    /** Familias que Typst trae incorporadas (sin archivo propio). */
    public const array BUILTIN = ['Libertinus Serif'];

    public const string SYSTEM_DIRECTORY = 'fonts/catalogo';

    public function __construct(
        private readonly FontInspector $inspector,
        private readonly TypstRunner $typst,
    ) {}

    /**
     * @return list<string>
     */
    public function families(User $user): array
    {
        $families = array_filter(
            Font::query()->availableTo($user)->distinct()->orderBy('family')->pluck('family')->all(),
            is_string(...),
        );

        return array_values(array_unique([...self::BUILTIN, ...$families]));
    }

    /**
     * Familias con sus variantes, para elegir fuente y cargarla en el navegador.
     *
     * @return list<array{family: string, label: string, category: string, own: bool, faces: list<array{id: int, weight: int, italic: bool, url: string}>}>
     */
    public function catalogFor(User $user): array
    {
        $families = [];
        foreach (self::BUILTIN as $family) {
            $families[$family] = ['family' => $family, 'label' => $family, 'category' => 'Serif', 'own' => false, 'faces' => []];
        }

        $fonts = Font::query()->availableTo($user)->orderBy('family')->orderBy('weight')->orderBy('italic')->get();
        foreach ($fonts as $font) {
            $families[$font->family] ??= [
                'family' => $font->family,
                'label' => $font->catalog_family ?? $font->family,
                'category' => $font->user_id === null ? ($font->category ?? 'Otras') : 'Mis fuentes',
                'own' => $font->user_id !== null,
                'faces' => [],
            ];
            $families[$font->family]['faces'][] = [
                'id' => $font->id,
                'weight' => $font->weight,
                'italic' => $font->italic,
                'url' => route('fonts.file', $font),
            ];
        }

        $list = array_values($families);
        usort($list, fn (array $a, array $b): int => [$a['own'] ? 0 : 1, $a['label']] <=> [$b['own'] ? 0 : 1, $b['label']]);

        return $list;
    }

    public function has(User $user, string $family): bool
    {
        return in_array($family, self::BUILTIN, true)
            || Font::query()->availableTo($user)->where('family', $family)->exists();
    }

    /**
     * Directorios que Typst debe ver al componer para este usuario.
     *
     * @return list<string>
     */
    public function typstPaths(User $user): array
    {
        $disk = Storage::disk('local');

        return array_values(array_filter(
            [$disk->path(self::SYSTEM_DIRECTORY), $disk->path($this->userDirectory($user))],
            is_dir(...),
        ));
    }

    /**
     * Guarda y registra una variante de fuente. Si ya existe la misma familia,
     * peso y estilo para el mismo dueño, la reemplaza.
     */
    public function register(string $sourcePath, string $originalName, ?User $owner = null, ?string $category = null, ?string $catalogFamily = null): Font
    {
        $face = $this->inspector->inspect($sourcePath);
        $family = $this->typstFamily($sourcePath, $face) ?? $face->family;
        $slug = Str::slug($family) ?: 'fuente';
        $directory = $owner === null ? self::SYSTEM_DIRECTORY."/{$slug}" : $this->userDirectory($owner);
        $path = "{$directory}/{$slug}-{$face->weight}".($face->italic ? 'i' : '').".{$face->extension}";

        $stream = fopen($sourcePath, 'r') ?: throw new InvalidFont('No se pudo leer el archivo.');
        Storage::disk('local')->put($path, $stream);
        if (is_resource($stream)) {
            fclose($stream);
        }

        $font = Font::query()
            ->where('user_id', $owner?->id)
            ->where('family', $family)
            ->where('weight', $face->weight)
            ->where('italic', $face->italic)
            ->first() ?? new Font;

        if ($font->exists && $font->path !== $path) {
            Storage::disk('local')->delete($font->path);
        }

        $font->fill([
            'family' => $family,
            'weight' => $face->weight,
            'italic' => $face->italic,
            'category' => $category ?? $font->category,
            'catalog_family' => $catalogFamily ?? $font->catalog_family,
            'path' => $path,
            'original_name' => $originalName,
            'sha256' => (string) hash_file('sha256', $sourcePath),
        ]);
        $font->user_id = $owner?->id;
        $font->save();

        return $font;
    }

    /**
     * Nombre con el que Typst reconoce la fuente: puede diferir del que declara
     * el archivo (Typst interpreta «Light», «Bold»… como pesos).
     */
    private function typstFamily(string $sourcePath, FontFace $face): ?string
    {
        $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'noctua-fuente-'.bin2hex(random_bytes(6));
        mkdir($dir);

        try {
            copy($sourcePath, $dir.DIRECTORY_SEPARATOR."fuente.{$face->extension}");

            return $this->typst->fontFamilies($dir)[0] ?? null;
        } catch (TypesettingFailed) {
            return null;
        } finally {
            array_map(unlink(...), glob($dir.DIRECTORY_SEPARATOR.'*') ?: []);
            rmdir($dir);
        }
    }

    public function userDirectory(User $user): string
    {
        return "fonts/usuarios/{$user->id}";
    }
}
