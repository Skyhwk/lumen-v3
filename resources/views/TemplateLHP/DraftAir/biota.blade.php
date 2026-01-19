@php
    if (!function_exists('formatSpeciesName')) {
        function formatSpeciesName($name) {
            $showedName = str_replace('_', ' ', $name);
            if (strpos($name, 'nauplius') !== false) {
                $showedName = str_replace('nauplius', '( nauplius )', $showedName);
            } elseif (strpos($name, 'calcar avis') !== false) {
                $showedName = str_replace('calcar avis', 'calcar - avis', $showedName);
            }
            return ucfirst($showedName);
        }

        function formatCategoryName($name) {
            return ucwords(str_replace('_', ' ', $name));
        }

        function extractSpeciesData($mainData) {
            $species = [];
            $currentNumber = 1;

            if (empty($mainData) || empty($mainData['data'])) {
                return $species;
            }

            foreach ($mainData['data'] as $category) {
                // Level 0 Category
                $species[] = [
                    'number' => '',
                    'category' => formatCategoryName($category['name']),
                    'species' => '',
                    'hasilUji' => '',
                    'hasilPerkalian' => '',
                    'isCategory' => true,
                    'categoryLevel' => 0,
                ];

                if (!empty($category['data'])) {
                    if (is_array($category['data']) && isset($category['data'][0])) {
                        // Array of sub-categories
                        foreach ($category['data'] as $subCategory) {
                            // Level 1 Category
                            $species[] = [
                                'number' => '',
                                'category' => formatCategoryName($subCategory['name']),
                                'species' => '',
                                'hasilUji' => '',
                                'hasilPerkalian' => '',
                                'isCategory' => true,
                                'categoryLevel' => 1,
                            ];

                            if (!empty($subCategory['data'])) {
                                if (is_array($subCategory['data']) && isset($subCategory['data'][0])) {
                                    // Nested Level 2
                                    foreach ($subCategory['data'] as $nestedCategory) {
                                        // Level 2 Category
                                        $species[] = [
                                            'number' => '',
                                            'category' => formatCategoryName($nestedCategory['name']),
                                            'species' => '',
                                            'hasilUji' => '',
                                            'hasilPerkalian' => '',
                                            'isCategory' => true,
                                            'categoryLevel' => 2,
                                        ];

                                        if (!empty($nestedCategory['data'])) {
                                            foreach ($nestedCategory['data'] as $speciesName => $speciesData) {
                                                $species[] = [
                                                    'number' => $currentNumber++,
                                                    'category' => '',
                                                    'species' => formatSpeciesName($speciesName),
                                                    'hasilUji' => $speciesData['hasil_uji'] ?? 0,
                                                    'hasilPerkalian' => $speciesData['hasil_perkalian'] ?? '',
                                                    'isCategory' => false,
                                                    'categoryLevel' => 0,
                                                ];
                                            }
                                        }
                                    }
                                } else {
                                    // Direct species object
                                    foreach ($subCategory['data'] as $speciesName => $speciesData) {
                                        $species[] = [
                                            'number' => $currentNumber++,
                                            'category' => '',
                                            'species' => formatSpeciesName($speciesName),
                                            'hasilUji' => $speciesData['hasil_uji'] ?? 0,
                                            'hasilPerkalian' => $speciesData['hasil_perkalian'] ?? '',
                                            'isCategory' => false,
                                            'categoryLevel' => 0,
                                        ];
                                    }
                                }
                            }
                        }
                    } else {
                        // Direct species object at category level
                        foreach ($category['data'] as $speciesName => $speciesData) {
                            $species[] = [
                                'number' => $currentNumber++,
                                'category' => '',
                                'species' => formatSpeciesName($speciesName),
                                'hasilUji' => $speciesData['hasil_uji'] ?? 0,
                                'hasilPerkalian' => $speciesData['hasil_perkalian'] ?? '',
                                'isCategory' => false,
                                'categoryLevel' => 0,
                            ];
                        }
                    }
                }
            }

            return $species;
        }
    }
    // Main processing
    $hasilJson = !empty($value->hasil_uji_json) ? json_decode($value->hasil_uji_json, true) : [];
    $type = $value['type'] ?? null;
@endphp

@if (!empty($hasilJson))
    <div class="left"
     @if(!($isJustBiota && $isFirst))
        style="page-break-before: always;"
     @endif
     >
        @php
            $total_data = count($hasilJson);
        @endphp
        @if($total_data > 1)
            @foreach ($hasilJson as $itemIndex => $item)
            
            <div class="{{$itemIndex == 0 ? 'leftMiddle' : 'rightMiddle'}}">
                <table width="100%" style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif;">
                            @php
                                $detailRows = extractSpeciesData($item);
                                $summary = $item['result'] ?? [];
                                $totalRows = count($detailRows);
                            @endphp
                            <thead>
                                <tr>
                                    <th width="25" class="pd-5-solid-top-center">NO</th>
                                    <th width="280" class="pd-5-solid-top-center">JENIS</th>
                                    <th width="80" class="pd-5-solid-top-center">HASIL UJI</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($detailRows as $rowIndex => $row)
                                    @if ($row['isCategory'])
                                        {{-- Category Row --}}
                                        <tr>
                                            @php
                                                $margin = $row['categoryLevel'] - 2 == 0 ? 10 : ($row['categoryLevel'] * 30);
                                            @endphp
                                            <td class="pd-5-dot-center"></td>
                                            <td class="{{ $row['categoryLevel'] === 0 ? 'pd-5-dot-center' : 'pd-5-dot-left' }}" 
                                                style="padding-left: {{ $margin }}px;">
                                                <b>{{ strtoupper($row['category']) }}</b>
                                            </td>
                                            <td class="pd-5-dot-center"></td>
                                        </tr>
                                    @else
                                        {{-- Species Data Row --}}
                                        <tr>
                                            <td class="pd-5-dot-center">{{ $row['number'] }}</td>
                                            <td class="pd-5-dot-left">{{ $row['species'] }}</td>
                                            <td class="pd-5-dot-center">
                                                <b>{{ str_replace('.', ',', $row['hasilUji']) }}</b>
                                            </td>
                                        </tr>
                                    @endif
                                @endforeach
                                <tr>
                                    <td class="pd-5-dot-center"></td>
                                    <td class="pd-5-dot-center">
                                        <b>Jumlah individu/ ml</b>
                                    </td>
                                    <td class="pd-5-dot-center">
                                        <b>{{ str_replace('.', ',', $summary['individu'] ?? 0) }}</b>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="pd-5-dot-center"></td>
                                    <td class="pd-5-dot-center">
                                        <b>Jumlah Taxa</b>
                                    </td>
                                    <td class="pd-5-dot-center">
                                        <b>{{ str_replace('.', ',', $summary['taxa'] ?? 0) }}</b>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="pd-5-dot-center"></td>
                                    <td class="pd-5-dot-center">
                                        <b>Indeks Diversitas H' = -Σ Pi log2pi</b>
                                    </td>
                                    <td class="pd-5-dot-center">
                                        <b>{{ str_replace('.', ',', $summary['diversitas'] ?? 0) }}</b>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="pd-5-dot-center"></td>
                                    <td class="pd-5-dot-center">
                                        <b>H-max = Log2S</b>
                                    </td>
                                    <td class="pd-5-dot-center">
                                        <b>{{ str_replace('.', ',', $summary['h_max'] ?? 0) }}</b>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="pd-5-solid-center"></td>
                                    <td class="pd-5-solid-center">
                                        <b>Equitabilitas (E) = H'/H-max</b>
                                    </td>
                                    <td class="pd-5-solid-center">
                                        <b>{{ str_replace('.', ',', $summary['equitabilitas'] ?? 0) }}</b>
                                    </td>
                                </tr>
                            </tbody>
                </table>
                
            </div>
            @endforeach
        @else
            @foreach ($hasilJson as $itemIndex => $item)
                <table width="100%" style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif;">
                            @php
                                $detailRows = extractSpeciesData($item);
                                $summary = $item['result'] ?? [];
                                $totalRows = count($detailRows);
                            @endphp
                            <thead>
                                <tr>
                                    <th width="25" class="pd-5-solid-top-center">NO</th>
                                    <th width="280" class="pd-5-solid-top-center">JENIS</th>
                                    <th width="80" class="pd-5-solid-top-center">HASIL UJI</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($detailRows as $rowIndex => $row)
                                    @if ($row['isCategory'])
                                        {{-- Category Row --}}
                                        <tr>
                                            @php
                                                $margin = $row['categoryLevel'] - 2 == 0 ? 10 : ($row['categoryLevel'] * 30);
                                            @endphp
                                            <td class="pd-5-dot-center"></td>
                                            <td class="{{ $row['categoryLevel'] === 0 ? 'pd-5-dot-center' : 'pd-5-dot-left' }}" 
                                                style="padding-left: {{ $margin }}px;">
                                                <b>{{ strtoupper($row['category']) }}</b>
                                            </td>
                                            <td class="pd-5-dot-center"></td>
                                        </tr>
                                    @else
                                        {{-- Species Data Row --}}
                                        <tr>
                                            <td class="pd-5-dot-center">{{ $row['number'] }}</td>
                                            <td class="pd-5-dot-left">{{ $row['species'] }}</td>
                                            <td class="pd-5-dot-center">
                                                <b>{{ str_replace('.', ',', $row['hasilUji']) }}</b>
                                            </td>
                                        </tr>
                                    @endif
                                @endforeach
                                <tr>
                                    <td class="pd-5-dot-center"></td>
                                    <td class="pd-5-dot-center">
                                        <b>Jumlah individu/ ml</b>
                                    </td>
                                    <td class="pd-5-dot-center">
                                        <b>{{ str_replace('.', ',', $summary['individu'] ?? 0) }}</b>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="pd-5-dot-center"></td>
                                    <td class="pd-5-dot-center">
                                        <b>Jumlah Taxa</b>
                                    </td>
                                    <td class="pd-5-dot-center">
                                        <b>{{ str_replace('.', ',', $summary['taxa'] ?? 0) }}</b>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="pd-5-dot-center"></td>
                                    <td class="pd-5-dot-center">
                                        <b>Indeks Diversitas H' = -Σ Pi log2pi</b>
                                    </td>
                                    <td class="pd-5-dot-center">
                                        <b>{{ str_replace('.', ',', $summary['diversitas'] ?? 0) }}</b>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="pd-5-dot-center"></td>
                                    <td class="pd-5-dot-center">
                                        <b>H-max = Log2S</b>
                                    </td>
                                    <td class="pd-5-dot-center">
                                        <b>{{ str_replace('.', ',', $summary['h_max'] ?? 0) }}</b>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="pd-5-solid-center"></td>
                                    <td class="pd-5-solid-center">
                                        <b>Equitabilitas (E) = H'/H-max</b>
                                    </td>
                                    <td class="pd-5-solid-center">
                                        <b>{{ str_replace('.', ',', $summary['equitabilitas'] ?? 0) }}</b>
                                    </td>
                                </tr>
                            </tbody>
                </table>
            @endforeach
        @endif
    </div>
@else
    <div class="left">
        <p style="text-align: center; color: #6c757d; font-family: Arial, Helvetica, sans-serif;">
            Tidak ada data untuk ditampilkan.
        </p>
    </div>
@endif