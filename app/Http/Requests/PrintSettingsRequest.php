<?php

namespace App\Http\Requests;

use App\Domain\Imposition\ImpositionSettings;
use App\Domain\Imposition\ImpositionSpec;
use App\Domain\Printing\Offset;
use App\Domain\Printing\PageFit;
use App\Domain\Printing\PrinterProfile;
use App\Domain\Printing\PrintOptions;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PrintSettingsRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'sheets_per_signature' => ['required', 'integer', 'between:'.ImpositionSpec::MIN_SHEETS_PER_SIGNATURE.','.ImpositionSpec::MAX_SHEETS_PER_SIGNATURE],
            'short_last_signature' => ['boolean'],
            'creep_mm' => ['required', 'numeric', 'between:0,2'],
            'front_offset_x' => ['required', 'numeric', 'between:-10,10'],
            'front_offset_y' => ['required', 'numeric', 'between:-10,10'],
            'back_offset_x' => ['required', 'numeric', 'between:-10,10'],
            'back_offset_y' => ['required', 'numeric', 'between:-10,10'],
            'unprintable_margin_mm' => ['required', 'numeric', 'between:0,20'],
            'fold_marks' => ['boolean'],
            'page_fit' => ['required', Rule::enum(PageFit::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'sheets_per_signature' => 'hojas por cuadernillo',
            'creep_mm' => 'creep',
            'front_offset_x' => 'offset X del frente',
            'front_offset_y' => 'offset Y del frente',
            'back_offset_x' => 'offset X del dorso',
            'back_offset_y' => 'offset Y del dorso',
            'unprintable_margin_mm' => 'zona no imprimible',
            'page_fit' => 'ajuste de página',
        ];
    }

    public function settings(): ImpositionSettings
    {
        return new ImpositionSettings(
            sheetsPerSignature: $this->integer('sheets_per_signature'),
            shortLastSignature: $this->boolean('short_last_signature'),
            creepMmPerSheet: $this->float('creep_mm'),
        );
    }

    public function printer(): PrinterProfile
    {
        return new PrinterProfile(
            frontOffset: new Offset($this->float('front_offset_x'), $this->float('front_offset_y')),
            backOffset: new Offset($this->float('back_offset_x'), $this->float('back_offset_y')),
            unprintableMarginMm: $this->float('unprintable_margin_mm'),
        );
    }

    /**
     * Valores con su tipo real, para recordarlos en la sesión y rellenar los
     * formularios (los datos validados llegan como texto).
     *
     * @return array<string, int|float|bool|string>
     */
    public function defaults(): array
    {
        return [
            'sheets_per_signature' => $this->integer('sheets_per_signature'),
            'short_last_signature' => $this->boolean('short_last_signature'),
            'creep_mm' => $this->float('creep_mm'),
            'front_offset_x' => $this->float('front_offset_x'),
            'front_offset_y' => $this->float('front_offset_y'),
            'back_offset_x' => $this->float('back_offset_x'),
            'back_offset_y' => $this->float('back_offset_y'),
            'unprintable_margin_mm' => $this->float('unprintable_margin_mm'),
            'fold_marks' => $this->boolean('fold_marks'),
            'page_fit' => ($this->enum('page_fit', PageFit::class) ?? PageFit::Actual)->value,
        ];
    }

    public function options(): PrintOptions
    {
        return new PrintOptions(
            foldMarks: $this->boolean('fold_marks'),
            pageFit: $this->enum('page_fit', PageFit::class) ?? PageFit::Actual,
        );
    }
}
