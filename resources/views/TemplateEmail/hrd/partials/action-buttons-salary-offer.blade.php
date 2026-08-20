@php
    $showHold = ($mark ?? '') === 'Ibu Boss';
@endphp

<table role="presentation" align="center" cellspacing="0" cellpadding="0" style="margin:24px auto 8px auto;text-align:center;width:100%;">
    <tr>
        <td align="center" style="padding:0;">
            <table role="presentation" cellspacing="0" cellpadding="0" style="margin:0 auto;">
                <tr>
                    @if(!empty($btn->approve))
                        <td align="center" style="padding:4px 6px;">
                            <a href="{{ $btn->approve }}" target="_blank" style="display:inline-block;background:linear-gradient(135deg, #16a34a 0%, #15803d 100%);color:#ffffff;text-decoration:none;font-size:13px;font-weight:700;padding:12px 24px;border-radius:24px;white-space:nowrap;box-shadow:0 4px 12px rgba(22,163,74,0.3);letter-spacing:0.3px;">
                                &#10003; Setujui Penawaran
                            </a>
                        </td>
                    @endif
                    @if(!empty($btn->reject))
                        <td align="center" style="padding:4px 6px;">
                            <a href="{{ $btn->reject }}" target="_blank" style="display:inline-block;background:linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);color:#ffffff;text-decoration:none;font-size:13px;font-weight:700;padding:12px 24px;border-radius:24px;white-space:nowrap;box-shadow:0 4px 12px rgba(220,38,38,0.3);letter-spacing:0.3px;">
                                &#10007; Tolak Penawaran
                            </a>
                        </td>
                    @endif
                    @if(!empty($btn->negotiate))
                        <td align="center" style="padding:4px 6px;">
                            <a href="{{ $btn->negotiate }}" target="_blank" style="display:inline-block;background:linear-gradient(135deg, #0284c7 0%, #0369a1 100%);color:#ffffff;text-decoration:none;font-size:13px;font-weight:700;padding:12px 24px;border-radius:24px;white-space:nowrap;box-shadow:0 4px 12px rgba(2,132,199,0.3);letter-spacing:0.3px;">
                                Negotiate Gaji
                            </a>
                        </td>
                    @endif
                    @if($showHold && !empty($btn->keep))
                        <td align="center" style="padding:4px 6px;">
                            <a href="{{ $btn->keep }}" target="_blank" style="display:inline-block;background:linear-gradient(135deg, #ea580c 0%, #c2410c 100%);color:#ffffff;text-decoration:none;font-size:13px;font-weight:700;padding:12px 24px;border-radius:24px;white-space:nowrap;box-shadow:0 4px 12px rgba(234,88,12,0.3);letter-spacing:0.3px;">
                                Hold +7 Hari
                            </a>
                        </td>
                    @endif
                </tr>
            </table>
        </td>
    </tr>
</table>

<p style="margin:12px 0 0 0;font-size:12px;line-height:1.5;color:#64748b;text-align:center;">
    Klik salah satu tombol di atas untuk memberikan konfirmasi keputusan Anda secara langsung melalui sistem.
</p>
