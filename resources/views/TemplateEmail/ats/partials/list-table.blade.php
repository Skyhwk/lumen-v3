<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%;border-collapse:separate;border-spacing:0;background-color:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;margin:0 0 20px 0;">
    @foreach($items as $item)
        <tr>
            <td style="padding:14px 16px;font-size:13px;line-height:1.6;color:#0f172a;border-top:1px solid #e2e8f0;">{{ $item }}</td>
        </tr>
    @endforeach
</table>
