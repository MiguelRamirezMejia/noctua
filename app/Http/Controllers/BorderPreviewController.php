<?php

namespace App\Http\Controllers;

use App\Domain\Book\BorderStyle;
use App\Domain\Book\Design\Color;
use App\Domain\Book\PageBorder;
use App\Domain\Printing\Size;
use App\Domain\Typesetting\Typst\BorderPreview;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Marco decorativo en SVG para superponerlo en el editor y en las miniaturas.
 */
class BorderPreviewController extends Controller
{
    public function __invoke(Request $request, BorderPreview $preview): BinaryFileResponse
    {
        $request->validate([
            'style' => ['required', Rule::enum(BorderStyle::class)],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'width_pt' => ['required', 'numeric', 'between:'.PageBorder::MIN_WIDTH_PT.','.PageBorder::MAX_WIDTH_PT],
            'inset_mm' => ['required', 'numeric', 'between:'.PageBorder::MIN_INSET_MM.','.PageBorder::MAX_INSET_MM],
            'unit_mm' => ['required', 'numeric', 'between:'.PageBorder::MIN_UNIT_MM.','.PageBorder::MAX_UNIT_MM],
            'page_width_mm' => ['required', 'numeric', 'between:20,1000'],
            'page_height_mm' => ['required', 'numeric', 'between:20,1000'],
            'print_scale' => ['required', 'numeric', 'between:0.1,5'],
        ]);

        $border = new PageBorder(
            $request->enum('style', BorderStyle::class) ?? BorderStyle::Line,
            new Color($request->string('color')->toString()),
            $request->float('width_pt'),
            $request->float('inset_mm'),
            $request->float('unit_mm'),
        );

        return response()->file($preview->svg(
            $border,
            new Size($request->float('page_width_mm'), $request->float('page_height_mm')),
            $request->float('print_scale'),
        ), [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'private, max-age=604800',
        ]);
    }
}
