@php
    /**
     * Horizontal 0–100% bars. 50% = netral (score 0).
     * Formula: (value + scale) / (2 * scale) * 100
     *
     * Expects: $scores, $scale
     */
    $scale = max(1, (float) ($scale ?? 8));
    $shades = ['D' => '#2b2b2b', 'I' => '#4a4a4a', 'S' => '#6e6e6e', 'C' => '#8f8f8f'];

    $rows = [];
    $strongestIndex = 0;
    $strongestAbs = -1;

    foreach (array_values($scores) as $index => $score) {
        $value = (float) ($score['value'] ?? 0);
        $clamped = max(-$scale, min($scale, $value));
        $mapped = (int) round(($clamped + $scale) / (2 * $scale) * 100);
        $mapped = max(0, min(100, $mapped));

        $leftFill = (int) round(min(50, $mapped) / 50 * 100);
        $rightFill = (int) round(max(0, $mapped - 50) / 50 * 100);
        $leftFill = max(0, min(100, $leftFill));
        $rightFill = max(0, min(100, $rightFill));

        if ($mapped >= 75) {
            $level = 'Sangat tinggi';
        } elseif ($mapped >= 63) {
            $level = 'Tinggi';
        } elseif ($mapped > 37) {
            $level = 'Seimbang';
        } elseif ($mapped > 25) {
            $level = 'Rendah';
        } else {
            $level = 'Sangat rendah';
        }

        $rows[] = [
            'key' => (string) ($score['key'] ?? ''),
            'label' => (string) ($score['label'] ?? ''),
            'mapped' => $mapped,
            'left_fill' => $leftFill,
            'left_rest' => 100 - $leftFill,
            'right_fill' => $rightFill,
            'right_rest' => 100 - $rightFill,
            'level' => $level,
            'fill' => $shades[$score['key'] ?? ''] ?? '#555555',
        ];

        if (abs($clamped) > $strongestAbs) {
            $strongestAbs = abs($clamped);
            $strongestIndex = $index;
        }
    }
@endphp

<table class="hbar-table" width="100%" cellpadding="0" cellspacing="0">
    <tr>
        <td class="hbar-spacer">&nbsp;</td>
        <td class="hbar-scale">
            <table class="hbar-ruler" width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td class="hbar-ruler-left">0%</td>
                    <td class="hbar-ruler-mid">50% netral</td>
                    <td class="hbar-ruler-right">100%</td>
                </tr>
            </table>
        </td>
        <td class="hbar-spacer" colspan="2">&nbsp;</td>
    </tr>
    @foreach($rows as $index => $row)
        @php $strongClass = $index === $strongestIndex ? 'hbar-strong' : ''; @endphp
        <tr>
            <td class="hbar-label {{ $strongClass }}">
                <strong>{{ $row['key'] }}</strong> {{ $row['label'] }}
            </td>
            <td class="hbar-track {{ $strongClass }}">
                <table class="hbar-bar" width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td class="hbar-half hbar-mid" width="50%">
                            <table width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    @if($row['left_fill'] > 0)
                                        <td width="{{ $row['left_fill'] }}%" height="12" bgcolor="{{ $row['fill'] }}" style="font-size:1px;line-height:1px;">&nbsp;</td>
                                    @endif
                                    @if($row['left_rest'] > 0)
                                        <td width="{{ $row['left_rest'] }}%" height="12" bgcolor="#ececec" style="font-size:1px;line-height:1px;">&nbsp;</td>
                                    @endif
                                </tr>
                            </table>
                        </td>
                        <td class="hbar-half" width="50%">
                            <table width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    @if($row['right_fill'] > 0)
                                        <td width="{{ $row['right_fill'] }}%" height="12" bgcolor="{{ $row['fill'] }}" style="font-size:1px;line-height:1px;">&nbsp;</td>
                                    @endif
                                    @if($row['right_rest'] > 0)
                                        <td width="{{ $row['right_rest'] }}%" height="12" bgcolor="#ececec" style="font-size:1px;line-height:1px;">&nbsp;</td>
                                    @endif
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
            <td class="hbar-pct {{ $strongClass }}">{{ $row['mapped'] }}%</td>
            <td class="hbar-meta {{ $strongClass }}">{{ $row['level'] }}</td>
        </tr>
    @endforeach
</table>
