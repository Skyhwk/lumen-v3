<div class="doc-content">
<br></br>
    @foreach($sections as $section)
        <p class="section-title"><strong><u>{{ strtoupper($section['title'] ?? '') }}</u></strong></p>
        <div class="section-body">{!! $section['content'] ?? '<p>-</p>' !!}</div>
        <br></br>
    @endforeach
</div>
