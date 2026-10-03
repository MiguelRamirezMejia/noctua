<?php

namespace App\Domain\Book;

use App\Domain\Book\Design\PageDesign;

/**
 * Una página del libro: una página del PDF de origen o una página en blanco.
 */
final readonly class PageItem
{
    /**
     * @param  string  $id  Identificador estable para la interfaz (reordenar sin perder la identidad).
     * @param  int|null  $sourcePage  Página del PDF de origen (1..n) o null si es un blanco.
     * @param  bool  $showFolio  Si esta página muestra su número cuando le corresponde.
     */
    public function __construct(
        public string $id,
        public ?int $sourcePage,
        public bool $showFolio = true,
        /** Fondo y elementos que Noctua añade a la página. */
        public PageDesign $design = new PageDesign,
    ) {}

    public function isBlank(): bool
    {
        return $this->sourcePage === null;
    }
}
