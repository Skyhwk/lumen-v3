@if (!empty($custom))
    @foreach ($data as $key => $value)
        <div class="left" style="page-break-before: always;">
            <table style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; font-size: 10px;">
                <thead>
                    <tr>
                        <th width="25" class="custom">NO</th>
                        <th width="170" class="custom">PARAMETER</th>
                        <th width="210" class="custom">HASIL UJI </th>
                        <th width="50" class="custom">NAB **</th>
                        <th width="50" class="custom">SATUAN</th>
                        <th width="120" class="custom">SPESIFIKASI METODE</th>
                    </tr>
                </thead>
                <tbody>
                    @php 
                        $totdat = count($value); 
                    @endphp
                    @foreach ($value as $kk => $yy)
                        @php
                            $p = $kk + 1;
                        @endphp
                        <tr>
                            <td class="pd-5-solid-center">{{$p}}</td>
                            <td class="pd-5-solid-left">{{$yy['parameter']}}</td>
                            <td class="pd-5-solid-center">{{$yy['hasil']}}</td>
                            <td class="pd-5-solid-center">{{$yy['nab']}}</td>
                            <td class="pd-5-solid-center">{{$yy['satuan']}}</td>
                            <td class="pd-5-solid-center">{{$yy['methode']}}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endforeach

@endif



