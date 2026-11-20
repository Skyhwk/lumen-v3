@php
    $box = static function (bool $on): string {
        if ($on) {
            return '<svg xmlns="http://www.w3.org/2000/svg" width="3.3mm" height="3.3mm" viewBox="0 0 12 12" style="vertical-align:middle;"><rect x="0.7" y="0.7" width="10.6" height="10.6" fill="#ffffff" stroke="#000000" stroke-width="1.1"/><polyline points="2.3,6.2 4.9,8.9 9.7,2.7" fill="none" stroke="#000000" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>';
        }
        return '<svg xmlns="http://www.w3.org/2000/svg" width="3.3mm" height="3.3mm" viewBox="0 0 12 12" style="vertical-align:middle;"><rect x="0.7" y="0.7" width="10.6" height="10.6" fill="#ffffff" stroke="#000000" stroke-width="1.1"/></svg>';
    };
    $caseEsc = htmlspecialchars($caseCode ?? '', ENT_QUOTES, 'UTF-8');
    $flags = $statusCaseFlags ?? ['dscTs' => false, 'km' => false, 'dscDs' => false];
@endphp
<table width="100%" style="font-family:calibri,sans-serif;border-collapse:collapse;color:#000;table-layout:fixed;">
    <tr>
        <td style="width:54%;vertical-align:bottom;padding:0 0 1.2mm 0;">
            <div style="font-size:15.5pt;font-weight:bold;line-height:1.15;">PT Inti Surya Laboratorium</div>
        </td>
        <td style="width:46%;vertical-align:top;padding:0;">
            <table width="100%" style="border-collapse:collapse;table-layout:fixed;margin-bottom:0.8mm;">
                <tr>
                    <td style="border:none;padding:0;">&nbsp;</td>
                    <td style="width:11mm;border:0.35mm solid #000;padding:0.7mm 0;font-size:13pt;font-weight:bold;text-align:center;line-height:1.05;">F1</td>
                </tr>
            </table>
            <table width="100%" style="border-collapse:collapse;font-size:9.5pt;line-height:1.25;">
                <tr>
                    <td style="width:21mm;white-space:nowrap;padding:0.3mm 0;"><i>Case Code</i></td>
                    <td style="width:3.5mm;text-align:center;padding:0.3mm 0;">:</td>
                    <td colspan="2" style="border-bottom:0.25mm dotted #000;font-weight:bold;padding:0.3mm 1mm;">@if($caseEsc !== ''){{ $caseEsc }}@else&nbsp;@endif</td>
                </tr>
                <tr>
                    <td style="white-space:nowrap;padding:0.8mm 0 0 0;vertical-align:top;">Status <i>Case</i></td>
                    <td style="text-align:center;padding:0.8mm 0 0 0;vertical-align:top;">:</td>
                    <td style="width:24mm;white-space:nowrap;padding:0.8mm 1mm 0 1mm;vertical-align:top;">{!! $box(!empty($flags['dscTs'])) !!}&nbsp;DSC-TS</td>
                    <td style="white-space:nowrap;padding:0.8mm 0 0 1mm;vertical-align:top;">{!! $box(!empty($flags['km'])) !!}&nbsp;KM</td>
                </tr>
                <tr>
                    <td colspan="2" style="padding:0;"></td>
                    <td colspan="2" style="white-space:nowrap;padding:0.6mm 0 0 1mm;vertical-align:top;">{!! $box(!empty($flags['dscDs'])) !!}&nbsp;DSC-DS</td>
                </tr>
            </table>
        </td>
    </tr>
</table>
