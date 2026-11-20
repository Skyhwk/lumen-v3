@foreach($lampiranImages as $index => $imageSrc)
    <div style="text-align:center; margin:0 0 6mm;{{ $index > 0 ? ' page-break-before:always;' : '' }}">
        @if($index === 0)
            <p style="font-family:calibri,sans-serif; font-weight:bold; font-size:10.5pt; margin:0 0 4mm; text-align:left;">Lampiran</p>
        @endif
        <img src="{{ $imageSrc }}" alt="Lampiran" style="max-width:170mm; height:auto;">
    </div>
@endforeach