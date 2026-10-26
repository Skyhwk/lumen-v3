<p>Best Regards,</p>
<br />
<table class="MsoNormalTable" border="0" cellspacing="0" cellpadding="0" align="default" style="width: 494px">
    <tbody>
        <tr>
            <td style="padding: 0 0 3.75pt; width: 493px">{{ $namaLengkap }}</td>
            @if(!empty($qrImageSrc))
                <td rowspan="5">
                    <div>
                        <img src="{{ $qrImageSrc }}" border="0" width="141" height="141" style="max-height: 90px; max-width: 90px" />
                    </div>
                </td>
            @endif
        </tr>
        <tr>
            <td style="padding: 0 !important; margin: 0 !important; width: 493px" height="20px" class="MsoNormal">
                <b>
                    <span style="font-size: 10pt; color: #3c3c3b">{{ $namaJabatan }}</span>
                </b>
            </td>
        </tr>
        <tr>
            <td style="padding: 0 0 0.75pt; width: 493px" class="MsoNormal">
                <b>
                    <span style="font-size: 9pt; color: #3c3c3b">T:</span>
                </b>
                <span style="font-size: 9pt; color: #3c3c3b">{{ $teleponPerusahaan }}</span>
            </td>
        </tr>
        <tr>
            <td style="padding: 0 0 0.75pt; width: 493px" class="MsoNormal">
                <b>
                    <span style="font-size: 9pt; color: #3c3c3b">E:</span>
                </b>
                <font face="Arial" style="font-size: 12px" size="1" color="#0000ff">
                    <a href="mailto:{{ $emailPerusahaan }}">{{ $emailPerusahaan }}</a>| <a href="http://{{ $websitePerusahaan }}">{{ $websitePerusahaan }}</a>
                </font>
            </td>
        </tr>
        <tr>
            <td style="padding: 0 0 0.75pt; width: 493px" class="MsoNormal">
                <b>
                    <span style="font-size: 9pt; font-family: Arial; color: #3c3c3b">PT Inti Surya Laboratorium</span>
                </b>
                <span style="font-size: 9pt; font-family: Arial; color: #3c3c3b">
                    <br />Ruko Icon Business Park Blok O No. 5 - 6 BSD City | Sampora, Cisauk, Kab. Tangerang
                </span>
            </td>
        </tr>
        <tr>
            <td valign="bottom" style="padding: 7.5pt 0 0; width: 493px" colspan="2" class="MsoNormal">
                <div class="se-component se-image-container __se__float-left">
                    <figure style="margin: 0; width: 20px; display: inline-flex">
                        <a href="https://www.facebook.com/profile.php?id=100073378609952">
                            <img src="https://www.mail-signatures.com/signature-generator/img/templates/medium-banner/fb.png" alt="Facebook icon" style="width: 20px; height: 20px" />
                        </a>
                        <a href="https://www.linkedin.com/company/pt-inti-surya-laboratorium" style="margin-left: 2px">
                            <img src="https://www.mail-signatures.com/signature-generator/img/templates/medium-banner/ln.png" alt="LinkedIn icon" style="width: 20px; height: 20px" />
                        </a>
                        <a href="https://twitter.com/Intisuryalab_" style="margin-left: 2px">
                            <img src="https://www.mail-signatures.com/signature-generator/img/templates/medium-banner/tt.png" alt="Twitter icon" style="width: 20px; height: 20px" />
                        </a>
                        <a href="https://youtube.com/channel/UC7AhVfw7VnUeXPL1xUfA_AA" style="margin-left: 2px">
                            <img src="https://www.mail-signatures.com/signature-generator/img/templates/medium-banner/yt.png" alt="Youtube icon" style="width: 20px; height: 20px" />
                        </a>
                        <a href="https://www.instagram.com/invites/contact/?i=am6kow40jkrg&amp;utm_content=mk5q3sx" style="margin-left: 2px">
                            <img src="https://www.mail-signatures.com/signature-generator/img/templates/medium-banner/it.png" alt="Instagramicon" style="width: 20px; height: 20px" />
                        </a>
                    </figure>
                </div>
            </td>
        </tr>
        <tr>
            <td colspan="2">
                <div class="se-component se-image-container __se__float-">
                    <figure style="margin: 0; width: 220px; display: inline-flex">
                        @foreach($footerLogoSrcs as $logo)
                            <img src="{{ $logo['src'] }}" alt="" style="width:{{ $logo['width'] }}px;height:{{ $logo['height'] }}px" />
                        @endforeach
                    </figure>
                </div>
            </td>
        </tr>
    </tbody>
</table>
