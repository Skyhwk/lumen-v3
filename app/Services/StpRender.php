<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Mpdf;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Carbon\Carbon;

class StpRender
{
    // Maximum number of rows per table before splitting
    const MAX_ROWS_PER_TABLE = 50;
    
    public static function renderPdf($dataLoop, $date, $category, $stp)
    {
        // Get default font directories
        $defaultConfig = (new ConfigVariables())->getDefaults();
        $fontDirs = $defaultConfig['fontDir'];

        // Get default font data
        $defaultFontConfig = (new FontVariables())->getDefaults();
        $fontData = $defaultFontConfig['fontdata'];

        $mpdfConfig = array(
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 5,
            'margin_right' => 5,
            'margin_top' => 5,
            'margin_bottom' => 5,
            'margin_header' => 0,
            'margin_footer' => 0,
            'orientation' => 'P',
            'fontDir' => array_merge($fontDirs, [
                public_path('fonts'),
            ]),
            'fontdata' => $fontData + [
                'fontawesome' => [
                    'R' => 'fontawesome-webfont.ttf',
                ]
            ],
            'default_font' => 'arial',
            'setAutoTopMargin' => 'stretch',
            'setAutoBottomMargin' => 'stretch',
            // Adding these settings to ensure multi-page support
            'autoPageBreak' => true,
            'useSubstitutions' => true
        );

        $pdf = new Mpdf($mpdfConfig);
        $pdf->SetProtection(array('print'), '', 'skyhwk12');
        
        // Set document information which improves compatibility
        $pdf->SetTitle('Analyst Report');
        $pdf->SetAuthor('Analyst Render System');
        $pdf->SetCreator('Analyst Render System');
        
        // Important: Ensure all pages are processed without limits
        $pdf->SetDisplayMode('fullpage');

        // CSS styles - using table-based layout
        $html = '
        <style>
            body {
                font-family: "arial";
                font-size: 10px;
            }
            
            .layout-table {
                width: 100%; 
                border-collapse: separate;
                border-spacing: 5px;
                table-layout: fixed;
            }
            
            .layout-cell {
                width: 49%;
                vertical-align: top;
                padding: 0;
            }
            
            .data-table {
                width: 100%;
                border-collapse: collapse;
                text-align: center;
                table-layout: fixed;
                border: 2px solid #000;
                margin-bottom: 10px;
                break-inside: avoid;
            }
            
            .data-table th, .data-table td {
                border: 2px solid #000;
                padding: 4px 2px;
                font-size: 12px;
            }
            
            .data-table th {
                background-color: #f8f9fa;
            }
            
            .font-weight-bold {
                font-weight: bold;
            }
            
            .check {
                color: green;
                font-weight: bold;
            }
            
            .header {
                width: 100%;
                margin-bottom: 5px;
                font-weight: bold;
                display: flex;
                justify-content: space-between;
            }
            
            .continued {
                font-style: italic;
                font-size: 8px;
                text-align: right;
            }
            
            .pagebreak {
                page-break-before: always;
            }
        </style>';

        $pdf->WriteHTML($html);

        // Format date for display in PDF
        $displayDate = Carbon::parse($date)->format('d M Y');
        
        // Get category name from category string (format: "id-name")
        $categoryName = explode('-', $category)[1] ?? $category;

        // Prepare tables with splitting logic
        $processedTables = [];
        
        // Process dataLoop which is an object of arrays
        foreach ($dataLoop as $paramKey => $sampleItems) {
            if (!is_array($sampleItems)) {
                continue;
            }
            
            // Create a tableData object
            $tableData = (object) [
                'header' => $paramKey,
                'sampleData' => $sampleItems
            ];
            
            // If sample data needs splitting
            if (count($sampleItems) > self::MAX_ROWS_PER_TABLE) {
                $chunks = array_chunk($sampleItems, self::MAX_ROWS_PER_TABLE);
                
                foreach ($chunks as $index => $chunk) {
                    $newTable = clone $tableData;
                    $newTable->sampleData = $chunk;
                    
                    // Add part indicator
                    $newTable->partInfo = [
                        'current' => $index + 1,
                        'total' => count($chunks)
                    ];
                    
                    $processedTables[] = $newTable;
                }
            } else {
                $processedTables[] = $tableData;
            }
        }
        
        // Process tables in smaller batches to prevent memory issues
        // Reduced batch size to ensure we don't hit page limitations
        $batchSize = 4; // Process 4 tables (2 rows) at a time
        $tablesPerPage = 10; // Approximate number of tables that fit on a page
        $pageCount = 1;
        
        for ($batchStart = 0; $batchStart < count($processedTables); $batchStart += $batchSize) {
            // Add page break after each page (except the first)
            if ($batchStart > 0 && $batchStart % $tablesPerPage == 0) {
                $pdf->AddPage();
                $pageCount++;
            }
            
            $batchTables = array_slice($processedTables, $batchStart, $batchSize);
            
            // Content - using table with exactly 2 columns
            $tableHtml = '<table class="layout-table" cellpadding="0" cellspacing="5">';
            
            // Loop through items and distribute them alternately between left and right columns
            for ($i = 0; $i < count($batchTables); $i += 2) {
                $tableHtml .= '<tr>';
                
                // Left column
                $tableHtml .= '<td class="layout-cell">';
                if ($i < count($batchTables)) {
                    $item = $batchTables[$i];
                    $tableHtml .= self::generateDataTable($item, $date, $category);
                }
                $tableHtml .= '</td>';
                
                // Right column
                $tableHtml .= '<td class="layout-cell">';
                if ($i + 1 < count($batchTables)) {
                    $item = $batchTables[$i + 1];
                    $tableHtml .= self::generateDataTable($item, $date, $category);
                }
                $tableHtml .= '</td>';
                
                $tableHtml .= '</tr>';
            }
            
            $tableHtml .= '</table>';
            
            $pdf->WriteHTML($tableHtml);
        }

        // Save PDF
        $fileName = "rekap_stp.pdf";
        $filePath = public_path('analyst/' . $fileName);
        
        // Ensure directory exists
        if (!file_exists(public_path('analyst'))) {
            mkdir(public_path('analyst'), 0755, true);
        }
        
        $pdf->Output($filePath, \Mpdf\Output\Destination::FILE);
        
        return $fileName;
    }
    
    // Fixed helper function to generate individual data tables
    private static function generateDataTable($item, $date, $category)
    {
        $html = '<table class="data-table">';
        
        // Add part indicator in the title if applicable
        $partIndicator = '';
        if (isset($item->partInfo)) {
            $partIndicator = ' (Part ' . $item->partInfo['current'] . ' of ' . $item->partInfo['total'] . ')';
        }
        
        $html .= '<thead>
            <tr>
                <th width="5%"></th>
                <th colspan="2" class="font-weight-bold">' . htmlspecialchars($item->header ?? '') . $partIndicator . '</th>
            </tr>
            <tr>
                <th width="5%">No</th>
                <th width="45%">NO SAMPLE</th>';
        
        // Lab Sample column
        $html .= '<th width="50%">Lab Sample</th>';
        
        $html .= '</tr>
            </thead>
            <tbody>';

        $startIndex = 0;
        if (isset($item->partInfo) && $item->partInfo['current'] > 1) {
            $startIndex = ($item->partInfo['current'] - 1) * self::MAX_ROWS_PER_TABLE;
        }

        if (!empty($item->sampleData)) {
            foreach ($item->sampleData as $index => $sampleItem) {
                $displayIndex = $startIndex + $index + 1;
                $sampleItem = (object) $sampleItem;
                
                $html .= '<tr>
                    <td>' . $displayIndex . '</td>
                    <td class="font-weight-bold">' . htmlspecialchars($sampleItem->no_sampel ?? '') . '</td>';
                
                // Format the lab_sample time using Carbon if it exists
                $labSample = '-';
                if (isset($sampleItem->lab_sample) && $sampleItem->lab_sample !== '-' && $sampleItem->lab_sample !== null) {
                    try {
                        // This mimics the IndoTime function from the React component
                        $labSample = Carbon::parse($sampleItem->lab_sample)->format('d M Y H:i:s');
                    } catch (\Exception $e) {
                        $labSample = htmlspecialchars($sampleItem->lab_sample);
                    }
                }
                
                $html .= '<td>' . $labSample . '</td>';
                
                $html .= '</tr>';
            }
        }

        $html .= '</tbody></table>';
        
        // Add "continued" note if this is not the last part
        if (isset($item->partInfo) && $item->partInfo['current'] < $item->partInfo['total']) {
            $html .= '<div class="continued">Continued on next table...</div>';
        }
        
        return $html;
    }
}