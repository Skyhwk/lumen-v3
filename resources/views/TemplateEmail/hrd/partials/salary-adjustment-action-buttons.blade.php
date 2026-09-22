<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:24px 0 8px 0;">
    <tr>
        <td align="center">
            @if(!empty($btn->approve))
                <a href="{{ $btn->approve }}" class="btn-stack"
                    style="display:inline-block;background-color:#15803d;color:#ffffff !important;text-decoration:none;font-size:14px;font-weight:700;padding:14px 32px;border:1px solid #166534;margin:0 6px 8px 6px;min-width:160px;">
                    Setujui
                </a>
            @endif
            @if(!empty($btn->reject))
                <a href="{{ $btn->reject }}" class="btn-stack"
                    style="display:inline-block;background-color:#ffffff;color:#b91c1c !important;text-decoration:none;font-size:14px;font-weight:700;padding:14px 32px;border:1px solid #b91c1c;margin:0 6px 8px 6px;min-width:160px;">
                    Tolak
                </a>
            @endif
        </td>
    </tr>
</table>

<p style="margin:0;font-size:11px;line-height:1.6;color:#71717a;text-align:center;">
    Tombol keputusan dapat dibuka di perangkat mobile maupun desktop. Link bersifat one-time use.
</p>
