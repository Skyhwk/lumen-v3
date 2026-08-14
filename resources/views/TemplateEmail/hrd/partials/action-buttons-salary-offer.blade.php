@php
    $showHold = ($mark ?? '') === 'Ibu Boss';
@endphp

<table role="presentation" align="center" cellspacing="0" cellpadding="0" style="margin:28px auto 0 auto;text-align:center;">
    <tr>
        @if(!empty($btn->approve))
            <td align="center" style="padding:0 4px;">
                <a href="{{ $btn->approve }}" target="_blank" style="display:inline-block;background-color:#16a34a;color:#ffffff;text-decoration:none;font-size:13px;font-weight:600;padding:12px 18px;border-radius:8px;white-space:nowrap;box-shadow:0 2px 4px rgba(22,163,74,0.2);">
                    Approve Kandidat
                </a>
            </td>
        @endif
        @if(!empty($btn->reject))
            <td align="center" style="padding:0 4px;">
                <a href="{{ $btn->reject }}" target="_blank" style="display:inline-block;background-color:#dc2626;color:#ffffff;text-decoration:none;font-size:13px;font-weight:600;padding:12px 18px;border-radius:8px;white-space:nowrap;box-shadow:0 2px 4px rgba(220,38,38,0.2);">
                    Reject Kandidat
                </a>
            </td>
        @endif
        @if(!empty($btn->negotiate))
            <td align="center" style="padding:0 4px;">
                <a href="{{ $btn->negotiate }}" target="_blank" style="display:inline-block;background-color:#0284c7;color:#ffffff;text-decoration:none;font-size:13px;font-weight:600;padding:12px 18px;border-radius:8px;white-space:nowrap;box-shadow:0 2px 4px rgba(2,132,199,0.2);">
                    Negotiate Gaji
                </a>
            </td>
        @endif
        @if($showHold && !empty($btn->keep))
            <td align="center" style="padding:0 4px;">
                <a href="{{ $btn->keep }}" target="_blank" style="display:inline-block;background-color:#ea580c;color:#ffffff;text-decoration:none;font-size:13px;font-weight:600;padding:12px 18px;border-radius:8px;white-space:nowrap;box-shadow:0 2px 4px rgba(234,88,12,0.2);">
                    Hold +7 Hari
                </a>
            </td>
        @endif
    </tr>
</table>

<p style="margin:16px 0 0 0;font-size:12px;line-height:1.6;color:#64748b;text-align:center;">
    Klik tombol di atas untuk memberikan keputusan langsung melalui sistem.
</p>
