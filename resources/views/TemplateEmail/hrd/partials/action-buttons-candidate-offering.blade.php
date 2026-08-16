<table role="presentation" align="center" cellspacing="0" cellpadding="0" style="margin:32px auto 0 auto;text-align:center;">
    <tr>
        @if(!empty($btn->approve))
            <td align="center" style="padding:0 10px;">
                <a href="{{ $btn->approve }}" target="_blank" title="Setujui Penawaran Gaji" style="display:inline-block;width:56px;height:56px;line-height:56px;background-color:#16a34a;color:#ffffff;text-decoration:none;font-size:28px;font-weight:700;border-radius:50%;box-shadow:0 4px 12px rgba(22,163,74,0.35);">
                    &#10003;
                </a>
                <div style="margin-top:8px;font-size:12px;font-weight:700;color:#15803d;">Setujui</div>
            </td>
        @endif
        @if(!empty($btn->reject))
            <td align="center" style="padding:0 10px;">
                <a href="{{ $btn->reject }}" target="_blank" title="Tolak Penawaran Gaji" style="display:inline-block;width:56px;height:56px;line-height:52px;background-color:#dc2626;color:#ffffff;text-decoration:none;font-size:28px;font-weight:700;border-radius:50%;box-shadow:0 4px 12px rgba(220,38,38,0.35);">
                    &#10007;
                </a>
                <div style="margin-top:8px;font-size:12px;font-weight:700;color:#b91c1c;">Tolak</div>
            </td>
        @endif
    </tr>
</table>

<p style="margin:18px 0 0 0;font-size:12px;line-height:1.6;color:#64748b;text-align:center;">
    Klik ikon di atas untuk memberikan keputusan Anda terhadap penawaran gaji ini.
</p>
