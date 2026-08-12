@php
    $showHold = ($mark ?? '') === 'Ibu Boss';
@endphp

<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:28px 0 0 0;">
    <tr>
        <td align="center">
            @if(!empty($btn->approve))
                <a href="{{ $btn->approve }}" class="btn-stack" style="display:inline-block;background-color:#16a34a;color:#ffffff;text-decoration:none;font-size:14px;font-weight:600;padding:14px 22px;border-radius:10px;margin:0 6px 8px 6px;min-width:140px;">
                    Approve Kandidat
                </a>
            @endif
            @if(!empty($btn->reject))
                <a href="{{ $btn->reject }}" class="btn-stack" style="display:inline-block;background-color:#dc2626;color:#ffffff;text-decoration:none;font-size:14px;font-weight:600;padding:14px 22px;border-radius:10px;margin:0 6px 8px 6px;min-width:140px;">
                    Reject Kandidat
                </a>
            @endif
            @if($showHold && !empty($btn->keep))
                <a href="{{ $btn->keep }}" class="btn-stack" style="display:inline-block;background-color:#ea580c;color:#ffffff;text-decoration:none;font-size:14px;font-weight:600;padding:14px 22px;border-radius:10px;margin:0 6px 8px 6px;min-width:140px;">
                    Hold +7 Hari
                </a>
            @endif
        </td>
    </tr>
</table>

<p style="margin:16px 0 0 0;font-size:12px;line-height:1.6;color:#64748b;text-align:center;">
    Klik tombol di atas untuk memberikan keputusan langsung melalui sistem.
</p>
