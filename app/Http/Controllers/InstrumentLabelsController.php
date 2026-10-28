<?php

namespace App\Http\Controllers;

use App\Models\InstrumentUnit;
use App\Services\ChoraleIdentity;
use Illuminate\Http\Request;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;
use Picqer\Barcode\Renderers\SvgRenderer;
use Picqer\Barcode\Types\TypeCode128;

final class InstrumentLabelsController extends Controller
{
    public function __invoke(Request $request)
    {
        $data = $request->validate(['units' => 'required|array|min:1|max:240', 'units.*' => 'required|integer|distinct', 'start' => 'required|integer|between:1,24']);
        $units = InstrumentUnit::with('instrument:id,name')->whereHas('instrument')->whereNull('retired_at')->whereKey($data['units'])->orderBy('instrument_id')->orderBy('ordinal')->get();
        if ($units->count() !== count($data['units'])) {
            throw ValidationException::withMessages(['units' => 'Sélectionnez uniquement des articles en inventaire dans votre chorale.']);
        }
        $generator = new TypeCode128;
        $renderer = (new SvgRenderer)->setSvgType(SvgRenderer::TYPE_SVG_INLINE);
        $labels = $units->map(function ($unit) use ($generator, $renderer) {
            $barcode = $generator->getBarcode($unit->inventory_code);
            $svg = $renderer->render($barcode, $barcode->getWidth() * 2, 48);

            // Only the barcode library's SVG for our immutable inventory code is trusted HTML.
            return ['unit' => $unit, 'svg' => new HtmlString(str_replace('<svg ', '<svg preserveAspectRatio="none" ', $svg))];
        });
        $slots = collect(array_fill(0, (int) $data['start'] - 1, null))->concat($labels);

        $identity = app(ChoraleIdentity::class);

        return response()->view('instruments.labels', [
            'pages' => $slots->chunk(24),
            'chorale' => $identity->name(),
            'logo' => $identity->logoUrl(),
        ])
            ->header('Cache-Control', 'private, no-store')->header('X-Content-Type-Options', 'nosniff');
    }
}
