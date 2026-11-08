@props(['header' => null, 'widthMm' => 210, 'identity'])
@if($header)
    <img class="document-letterhead" src="{{ $header }}" alt="En-tête officiel de la chorale"
        style="display:block;width:{{ $widthMm }}mm;max-width:100%;height:auto;margin:0 auto;object-fit:contain;object-position:top center;break-inside:avoid;" />
@else
    <header class="fallback document-letterhead-fallback" style="text-align:center;border-bottom:2px solid #334155;padding:8mm 10mm 5mm;break-inside:avoid;">
        <h1 style="margin:0;font-size:22px">{{ $identity->name() }}</h1>
        <p style="margin:2mm 0 0;color:#475569;font-size:11px">{{ implode(' · ', array_filter([$identity->address(), $identity->phone(), $identity->email(), $identity->website()])) }}</p>
    </header>
@endif
