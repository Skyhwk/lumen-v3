<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%;border-collapse:separate;border-spacing:0;background-color:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;margin:0 0 20px 0;">
    @foreach($rows as $label => $value)
        <tr>
            <td class="info-label" style="padding:10px 16px;width:38%;font-size:13px;color:#64748b;{{ $loop->first ? '' : 'border-top:1px solid #e2e8f0;' }}vertical-align:top;">{{ $label }}</td>
            <td style="padding:10px 16px;font-size:13px;color:#0f172a;font-weight:600;{{ $loop->first ? '' : 'border-top:1px solid #e2e8f0;' }}vertical-align:top;">{{ $value }}</td>
        </tr>
    @endforeach
</table>
