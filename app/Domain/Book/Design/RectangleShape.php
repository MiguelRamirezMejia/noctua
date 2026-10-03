<?php

namespace App\Domain\Book\Design;

final readonly class RectangleShape implements Element
{
    public function __construct(
        public string $id,
        public Box $box,
        public Color $fill,
        public float $opacity = 1.0,
    ) {
        if ($opacity < 0 || $opacity > 1) {
            throw new InvalidPageDesign('La opacidad debe estar entre 0 y 1.');
        }
    }

    public function id(): string
    {
        return $this->id;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => 'rect',
            'x' => $this->box->x,
            'y' => $this->box->y,
            'width' => $this->box->width,
            'height' => $this->box->height,
            'fill' => $this->fill->hex,
            'opacity' => $this->opacity,
        ];
    }
}
