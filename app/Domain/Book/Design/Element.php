<?php

namespace App\Domain\Book\Design;

/**
 * Elemento colocado sobre la página, en mm desde la esquina superior izquierda
 * de la página de origen.
 */
interface Element
{
    public function id(): string;

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
