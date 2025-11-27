<?php
/**
 * Address Validation Functions - CUSTOM LOGIC VERSION
 * No API calls, no SSE, custom validation rules
 */

require_once 'vendor/autoload.php';

/**
 * Validate a single address using CUSTOM LOGIC (no API calls)
 * 
 * CUSTOMIZE THIS FUNCTION with your own validation rules
 */
function validateAddress($address, $accessToken = null) {
    // ========================================
    // YOUR CUSTOM VALIDATION LOGIC HERE
    // ========================================
    
    // Extract address components
    $company = strtoupper(trim($address['Company'] ?? ''));
    $add1 = strtoupper(trim($address['Add1'] ?? ''));
    $add2 = strtoupper(trim($address['Add2'] ?? ''));
    $add3 = strtoupper(trim($address['Add3'] ?? ''));
    $city = strtoupper(trim($address['City'] ?? ''));
    $state = strtoupper(trim($address['St'] ?? ''));
    $zip = preg_replace('/[^0-9]/', '', $address['Zip'] ?? '');
    $zip5 = substr($zip, 0, 5);
    
    // ===========================================
    // RULE 1: PO Box Detection (All variations)
    // ===========================================
    // Matches: P.O. Box, PO Box, P O Box, POB, Post Office Box, etc.
    $poBoxPatterns = [
        '/\bP\.?\s*O\.?\s*BOX\b/i',           // P.O. Box, PO Box, P O Box
        '/\bPOST\s+OFFICE\s+BOX\b/i',         // Post Office Box
        '/\bP\s*O\s*B\b/i',                   // POB
        '/\bBOX\s+\d+\b/i',                   // Box 123
        '/\b(PO|P\.O\.)\s*#?\s*\d+\b/i',     // PO 123, P.O. #123
    ];
    
    // Check all address lines for PO Box
    $allAddressLines = $add1 . ' ' . $add2 . ' ' . $add3 . ' ' . $company;
    foreach ($poBoxPatterns as $pattern) {
        if (preg_match($pattern, $allAddressLines)) {
            return [
                'deliverable' => false,
                'reason' => 'PO Box address - Use USPS',
                'resolvedAddress' => null
            ];
        }
    }
    
    // ===========================================
    // RULE 2: Military & Territory STATES - Send to USPS
    // ===========================================
    // AE = Armed Forces Europe/Africa/Middle East
    // AP = Armed Forces Pacific  
    // AA = Armed Forces Americas
    // MP = Northern Mariana Islands (Military)
    // GU = Guam
    // PR = Puerto Rico
    // VI = Virgin Islands
    // AS = American Samoa
    $militaryTerritoryStates = ['AE', 'AP', 'AA', 'MP', 'GU', 'PR', 'VI', 'AS', 'APO', 'FPO', 'DPO'];
    
    if (in_array($state, $militaryTerritoryStates)) {
        return [
            'deliverable' => false,
            'reason' => "Military/Territory state ($state) - Use USPS",
            'resolvedAddress' => null
        ];
    }
    
    // ===========================================
    // RULE 3: Military & Territory CITIES - Send to USPS
    // ===========================================
    // These cities indicate military or territory addresses
    // Can appear with ANY state code
    $militaryTerritoryCities = [
        'APO',          // Army Post Office
        'FPO',          // Fleet Post Office
        'DPO',          // Diplomatic Post Office
        'SAIPAN',       // Northern Mariana Islands capital
        'LANDSTUHL',    // Germany - Ramstein Air Base area
        'LAANDSTUHL',   // Alternate spelling
    ];
    
    if (in_array($city, $militaryTerritoryCities)) {
        return [
            'deliverable' => false,
            'reason' => "Military/Territory city ($city) - Use USPS",
            'resolvedAddress' => null
        ];
    }
    
    // ===========================================
    // RULE 4: DELAWARE (DE) Validation
    // ===========================================
    // Ensure DE means Delaware (USA), NOT Deutschland (Germany)
    // Special check for Landstuhl, Germany incorrectly marked as DE
    if ($state === 'DE') {
        // If city is Landstuhl with DE state, it's Germany (not Delaware)
        if (in_array($city, ['LANDSTUHL', 'LAANDSTUHL'])) {
            return [
                'deliverable' => false,
                'reason' => 'Germany address (Landstuhl) with DE state code - Use USPS',
                'resolvedAddress' => null
            ];
        }
        
        // Delaware ZIP codes must start with 19 (19xxx)
        if (strlen($zip5) === 5 && !preg_match('/^19\d{3}$/', $zip5)) {
            return [
                'deliverable' => false,
                'reason' => 'Invalid ZIP for Delaware (must be 19xxx) - Possible Germany address - Use USPS',
                'resolvedAddress' => null
            ];
        }
        
    }
    
 
    return [
        'deliverable' => true,
        'reason' => 'Valid',
        'resolvedAddress' => [
            'streetLines' => [$add1],
            'city' => $city,
            'stateOrProvinceCode' => $state,
            'postalCode' => $zip5
        ]
    ];
}

/**
 * Process CSV file for address validation (NO SSE VERSION)
 */
function processAddressValidation($filePath, $accessToken, $projectNumber) {
    // Read CSV file
    $csvData = [];
    if (($handle = fopen($filePath, 'r')) !== FALSE) {
        while (($row = fgetcsv($handle)) !== FALSE) {
            $csvData[] = $row;
        }
        fclose($handle);
    } else {
        throw new Exception("Failed to open CSV file");
    }
    
    if (empty($csvData)) {
        throw new Exception("CSV file is empty");
    }
    
    // Get headers
    $headers = $csvData[0];
    $totalAddresses = count($csvData) - 1; // Exclude header row
    
    // Prepare output arrays
    $deliverableAddresses = [$headers]; // Start with headers
    $undeliverableAddresses = [$headers]; // Start with headers
    
    $deliverableCount = 0;
    $undeliverableCount = 0;
    
    // Process each address
    for ($i = 1; $i < count($csvData); $i++) {
        $row = $csvData[$i];
        
        if (empty($row) || count($row) < 9) {
            continue; // Skip invalid rows
        }
        
        // Map CSV columns to address structure
        $address = [
            'SAP_ID' => $row[0],
            'ATTN' => $row[1],
            'Company' => $row[2],
            'Add1' => $row[3],
            'Add2' => $row[4],
            'Add3' => $row[5],
            'City' => $row[6],
            'St' => $row[7],
            'Zip' => $row[8]
        ];
        
        // Validate address using custom logic (no API call)
        $validationResult = validateAddress($address);
        
        if ($validationResult['deliverable']) {
            $deliverableAddresses[] = $row;
            $deliverableCount++;
        } else {
            $undeliverableAddresses[] = $row;
            $undeliverableCount++;
        }
    }
    
    // Sort both arrays by SAP_ID (first column) in ascending order
    $deliverableHeader = array_shift($deliverableAddresses);
    $undeliverableHeader = array_shift($undeliverableAddresses);
    
    // Sort by SAP_ID (column 0) numerically in ascending order
    usort($deliverableAddresses, function($a, $b) {
        return intval($a[0]) - intval($b[0]);
    });
    
    usort($undeliverableAddresses, function($a, $b) {
        return intval($a[0]) - intval($b[0]);
    });
    
    // Put headers back at the beginning
    array_unshift($deliverableAddresses, $deliverableHeader);
    array_unshift($undeliverableAddresses, $undeliverableHeader);
    
    // Save results to CSV files
    $fedexFile = RESULTS_FOLDER . "/{$projectNumber}-FedEx-ListResult.csv";
    $uspsFile = RESULTS_FOLDER . "/{$projectNumber}-USPS-ListResult.csv";
    
    // Write FedEx deliverable addresses
    if ($deliverableCount > 0) {
        $fp = fopen($fedexFile, 'w');
        foreach ($deliverableAddresses as $row) {
            fputcsv($fp, $row);
        }
        fclose($fp);
    }
    
    // Write USPS addresses
    if ($undeliverableCount > 0) {
        $fp = fopen($uspsFile, 'w');
        foreach ($undeliverableAddresses as $row) {
            fputcsv($fp, $row);
        }
        fclose($fp);
    }
    
    // Create FedEx Break Result file
    $breakResultFile = null;
    if ($deliverableCount > 0) {
        try {
            $breakResultFile = createFedexBreakResult($fedexFile, $projectNumber);
        } catch (Exception $e) {
            error_log("Failed to create break result file: " . $e->getMessage());
        }
    }
    
    return [
        'fedexFile' => $deliverableCount > 0 ? $fedexFile : null,
        'uspsFile' => $undeliverableCount > 0 ? $uspsFile : null,
        'breakResultFile' => $breakResultFile,
        'fedexCount' => $deliverableCount,
        'uspsCount' => $undeliverableCount,
        'totalCount' => $totalAddresses,
        'projectNumber' => $projectNumber
    ];
}

/**
 * Create FedEx List Break Result - First and last of each chunk of 100 with alternating yellow background
 */
function createFedexBreakResult($fedexCsvFile, $projectNumber) {
    if (!file_exists($fedexCsvFile)) {
        return null;
    }
    
    // Read FedEx CSV file
    $csvData = [];
    if (($handle = fopen($fedexCsvFile, 'r')) !== FALSE) {
        while (($row = fgetcsv($handle)) !== FALSE) {
            $csvData[] = $row;
        }
        fclose($handle);
    }
    
    if (empty($csvData) || count($csvData) <= 1) {
        return null; // No data or only headers
    }
    
    // Remove header row
    $headers = array_shift($csvData);
    
    // Find City and State columns
    $cityIndex = array_search('City', $headers);
    $stateIndex = array_search('St', $headers);
    
    if ($cityIndex === false || $stateIndex === false) {
        throw new Exception("City or St column not found in FedEx CSV");
    }
    
    // Create Excel file
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // Set headers
    $sheet->setCellValue('A1', 'Helper');
    $sheet->setCellValue('B1', 'Count');
    $sheet->setCellValue('C1', 'City');
    $sheet->setCellValue('D1', 'St');
    
    // Border style
    $borderStyle = [
        'borders' => [
            'allBorders' => [
                'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                'color' => ['rgb' => '000000']
            ]
        ]
    ];
    
    // Style header row (gray background with borders)
    $headerStyle = [
        'font' => ['bold' => true],
        'fill' => [
            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'D3D3D3']
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                'color' => ['rgb' => '000000']
            ]
        ]
    ];
    $sheet->getStyle('A1:D1')->applyFromArray($headerStyle);
    
    // Yellow background style with borders
    $yellowStyle = [
        'fill' => [
            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'FFFF00']
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                'color' => ['rgb' => '000000']
            ]
        ]
    ];
    
    // White background style with borders
    $whiteStyle = [
        'fill' => [
            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'FFFFFF']
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                'color' => ['rgb' => '000000']
            ]
        ]
    ];
    
    // Add only first and last row of each chunk of 100
    $totalRows = count($csvData);
    $excelRow = 2; // Start at row 2 (after header)
    
    for ($i = 0; $i < $totalRows; $i++) {
        $count = $i + 1;    // Row count (1, 2, 3, ...)
        $helperValue = $i % 100; // MOD value (0-99)
        
        // Include first row of chunk (Helper = 0) OR last row of chunk (Helper = 99) OR last row overall
        if ($helperValue === 0 || $helperValue === 99 || $i === $totalRows - 1) {
            // Set Helper value
            $sheet->setCellValue('A' . $excelRow, $helperValue);
            
            // Set Count (row number)
            $sheet->setCellValue('B' . $excelRow, $count);
            
            // Set City and State (uppercase)
            $sheet->setCellValue('C' . $excelRow, strtoupper($csvData[$i][$cityIndex]));
            $sheet->setCellValue('D' . $excelRow, strtoupper($csvData[$i][$stateIndex]));
            
            // Apply yellow or white background to every 2 rows with borders
            // Pattern: rows 2-3 yellow, 4-5 white, 6-7 yellow, 8-9 white, ...
            $groupNumber = floor(($excelRow - 2) / 2); // Which pair of rows (0, 1, 2, ...)
            if ($groupNumber % 2 === 0) {
                // Apply yellow background with borders
                $sheet->getStyle('A' . $excelRow . ':D' . $excelRow)->applyFromArray($yellowStyle);
            } else {
                // Apply white background with borders
                $sheet->getStyle('A' . $excelRow . ':D' . $excelRow)->applyFromArray($whiteStyle);
            }
            
            $excelRow++;
        }
    }
    
    // Auto-size only columns A-D
    foreach (range('A', 'D') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
    
    // Set print area to only columns A-D
    $lastRow = $excelRow - 1;
    $sheet->getPageSetup()->setPrintArea('A1:D' . $lastRow);
    
    // Hide all columns after D
    $sheet->getColumnDimension('E')->setVisible(false);
    
    // Optionally, set a specific width for columns to prevent auto-expansion
    $sheet->getColumnDimension('E')->setWidth(0);
    
    // Save Excel file
    $excelFile = RESULTS_FOLDER . "/{$projectNumber}-FedEx-ListBreakResult.xlsx";
    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
    $writer->save($excelFile);
    
    return $excelFile;
}