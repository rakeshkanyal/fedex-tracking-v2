<?php
/**
 * Core Functions Library
 * Contains all business logic functions
 */

require_once 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use GuzzleHttp\Client;
use setasign\Fpdi\Fpdi;

/**
 * Send SSE (Server-Sent Events) message
 */
function sendSSE($event, $data) {
    echo "event: $event\n";
    echo "data: " . json_encode($data) . "\n\n";
    if (ob_get_level() > 0) {
        ob_flush();
    }
    flush();
}

/**
 * Log and send status update
 */
function logStatus($message, $type = 'info', $progress = null) {
    $data = [
        'message' => $message,
        'type' => $type,
        'timestamp' => date('H:i:s'),
    ];
    
    if ($progress !== null) {
        $data['progress'] = $progress;
    }
    
    sendSSE('status', $data);
    error_log("[FedEx Tracker] $message");
}

/**
 * Get FedEx access token
 */
function getFedexAccessToken($for = "") {
    $client = new Client();

    $response = $client->post(FEDEX_AUTH_URL, [
        'form_params' => [
            'grant_type' => 'client_credentials',
            'client_id' => $for == "AV" ? FEDEX_API_KEY_AV:FEDEX_API_KEY,
            'client_secret' => $for == "AV" ? FEDEX_SECRET_KEY_AV : FEDEX_SECRET_KEY,
        ],
        'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
    ]);
    
    $data = json_decode($response->getBody(), true);
    if (!empty($data['access_token'])) {
        return $data['access_token'];
    }
    throw new Exception("Failed to get FedEx access token.");
}

/**
 * Get tracking status for a single tracking number
 */
function getTrackingStatus($trackingNumber, $accessToken, $shipmentDate = null) {
    $client = new Client();
    $payload = [
        "trackingInfo" => [
            [
                "trackingNumberInfo" => ["trackingNumber" => $trackingNumber]
            ]
        ],
        "includeDetailedScans" => true // we need scan events to get exceptionDescription
    ];

    $attempt = 0;
    while ($attempt < MAX_RETRIES) {
        try {
            $response = $client->post(FEDEX_TRACKING_URL, [
                'headers' => [
                    "Authorization" => "Bearer $accessToken",
                    "Content-Type" => "application/json"
                ],
                'json' => $payload,
                'timeout' => 30
            ]);

            $data = json_decode($response->getBody(), true);
            $result = $data['output']['completeTrackResults'][0] ?? null;
            
            if (!$result) {
                return ['status' => 'NOT_FOUND', 'reason' => 'Tracking number not found'];
            }

            $trackResult = $result['trackResults'][0] ?? [];
            $code = $trackResult['latestStatusDetail']['derivedCode'] ?? '';
            //$desc = $trackResult['latestStatusDetail']['description'] ?? '';
            $desc = "";
            // ✅ Extract non-empty exceptionDescription values from scanEvents
            $exceptionDescriptions = [];
            if (!empty($trackResult['scanEvents']) && is_array($trackResult['scanEvents'])) {
                foreach ($trackResult['scanEvents'] as $event) {
                    $exceptionDesc = trim($event['exceptionDescription'] ?? '');
                    if ($exceptionDesc !== '' && strtolower($exceptionDesc) != "return tracking number") {
                        $exceptionDescriptions[] = $exceptionDesc;
                    }
                }
            }

            // Combine all non-empty exception descriptions into a comma-separated string
            if (!empty($exceptionDescriptions)) {
                $desc = implode(' -> ', array_reverse(array_unique($exceptionDescriptions)));
            }

            return [
                'status' => $code,
                'reason' => $code != 'DL' ? ucfirst($desc) : ''
            ];

        } catch (Exception $e) {
            $attempt++;
            logStatus(
                "⚠ Tracking API failed for $trackingNumber (Attempt $attempt/" . MAX_RETRIES . "): " . $e->getMessage(),
                'warning'
            );
            
            if ($attempt < MAX_RETRIES) {
                $delay = RETRY_DELAY_SECONDS * $attempt;
                sleep($delay);
            } else {
                return ['status' => 'ERROR', 'reason' => 'API error: ' . $e->getMessage()];
            }
        }
    }
    
    return ['status' => 'ERROR', 'reason' => 'Failed after ' . MAX_RETRIES . ' attempts'];
}

/**
 * Download POD for a single tracking number
 */
function downloadPodDocument($trackingNumber, $accessToken, $shipmentDate = null) {
    $client = new Client();
    
    $specification = [
        "trackingNumberInfo" => ["trackingNumber" => $trackingNumber],
        "accountNumber" => FEDEX_ACCOUNT_NUMBER,
    ];
    
    // Add shipment date filter if provided
    if ($shipmentDate) {
        $specification["shipDateBegin"] = $shipmentDate;
        $specification["shipDateEnd"] = $shipmentDate;
    }
    
    $payload = [
        "trackDocumentDetail" => [
            "documentType" => "SIGNATURE_PROOF_OF_DELIVERY"
        ],
        "trackDocumentSpecification" => [$specification]
    ];

    $attempt = 0;
    while ($attempt < MAX_RETRIES) {
        try {
            logStatus("Downloading POD for $trackingNumber...", 'info');
            
            $response = $client->post(POD_DOCUMENT_URL, [
                'headers' => [
                    "Authorization" => "Bearer $accessToken",
                    "Content-Type" => "application/json",
                ],
                'json' => $payload,
            ]);

            $data = json_decode($response->getBody(), true);
            $document = $data['output']['documents'][0] ?? null;
            
            if ($document) {
                $pdfBytes = base64_decode($document);
                $pdfPath = POD_FOLDER . "/{$trackingNumber}.pdf";
                file_put_contents($pdfPath, $pdfBytes);
                logStatus("✓ Downloaded POD: {$trackingNumber}.pdf", 'success');
                return $pdfPath;
            }
            
            logStatus("⚠ No POD document available for $trackingNumber", 'warning');
            return null;

        } catch (Exception $e) {
            $attempt++;
            logStatus(
                "⚠ POD download failed for $trackingNumber (Attempt $attempt/" . MAX_RETRIES . "): " . $e->getMessage(),
                'warning'
            );
            
            if ($attempt < MAX_RETRIES) {
                $delay = RETRY_DELAY_SECONDS * $attempt;
                sleep($delay);
            }
        }
    }
    
    logStatus("✗ Failed to download POD for $trackingNumber after " . MAX_RETRIES . " attempts", 'error');
    return null;
}

/**
 * Decompress PDF using Ghostscript
 */
function decompressPdf($inputPath, $outputPath) {
    try {
        exec("which gs 2>&1", $output, $returnCode);
        if ($returnCode !== 0) {
            return false;
        }
        
        $command = sprintf(
            'gs -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dNOPAUSE -dQUIET -dBATCH -sOutputFile=%s %s 2>&1',
            escapeshellarg($outputPath),
            escapeshellarg($inputPath)
        );
        
        exec($command, $output, $returnCode);
        return $returnCode === 0 && file_exists($outputPath) && filesize($outputPath) > 0;
        
    } catch (Exception $e) {
        error_log("Ghostscript decompression failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Merge PDFs using Ghostscript with Batch Processing
 */
function mergePdfsWithGhostscriptBatch($pdfFiles, $outputPath, $batchSize = 100) {
    try {
        exec("which gs 2>&1", $output, $returnCode);
        if ($returnCode !== 0) {
            return false;
        }
        
        $totalFiles = count($pdfFiles);
        
        // If files are less than batch size, merge directly
        if ($totalFiles <= $batchSize) {
            logStatus("Merging {$totalFiles} files directly...", 'info');
            
            $fileList = array_map('escapeshellarg', $pdfFiles);
            $command = sprintf(
                'gs -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dNOPAUSE -dQUIET -dBATCH -sOutputFile=%s %s 2>&1',
                escapeshellarg($outputPath),
                implode(' ', $fileList)
            );
            
            exec($command, $output, $returnCode);
            
            if ($returnCode === 0 && file_exists($outputPath) && filesize($outputPath) > 0) {
                $fileSize = round(filesize($outputPath) / 1024 / 1024, 2);
                logStatus("✓ Successfully merged {$totalFiles} PDFs ({$fileSize} MB)", 'success');
                return true;
            }
            return false;
        }
        
        // Batch processing for large number of files
        logStatus("Large file count detected ({$totalFiles} files). Using batch merge...", 'info');
        
        $batches = array_chunk($pdfFiles, $batchSize);
        $totalBatches = count($batches);
        $batchFiles = [];
        $tempDir = sys_get_temp_dir();
        
        logStatus("Creating {$totalBatches} batches ({$batchSize} files each)...", 'info');
        
        // Step 1: Merge each batch
        foreach ($batches as $batchIndex => $batchPdfs) {
            $batchNum = $batchIndex + 1;
            $batchOutput = $tempDir . '/batch_' . uniqid() . '_' . $batchNum . '.pdf';
            
            logStatus("Merging batch {$batchNum}/{$totalBatches} (" . count($batchPdfs) . " files)...", 'info');
            
            $fileList = array_map('escapeshellarg', $batchPdfs);
            $command = sprintf(
                'gs -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dNOPAUSE -dQUIET -dBATCH -sOutputFile=%s %s 2>&1',
                escapeshellarg($batchOutput),
                implode(' ', $fileList)
            );
            
            exec($command, $output, $returnCode);
            
            if ($returnCode === 0 && file_exists($batchOutput) && filesize($batchOutput) > 0) {
                $batchFiles[] = $batchOutput;
                $batchSize = round(filesize($batchOutput) / 1024 / 1024, 2);
                logStatus("✓ Batch {$batchNum}/{$totalBatches} complete ({$batchSize} MB)", 'success');
            } else {
                logStatus("⚠ Batch {$batchNum} failed, skipping...", 'warning');
            }
        }
        
        if (empty($batchFiles)) {
            logStatus("✗ All batches failed", 'error');
            return false;
        }
        
        // Step 2: Merge all batch files into final PDF
        logStatus("Merging {$totalBatches} batches into final PDF...", 'info');
        
        $fileList = array_map('escapeshellarg', $batchFiles);
        $command = sprintf(
            'gs -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dNOPAUSE -dQUIET -dBATCH -sOutputFile=%s %s 2>&1',
            escapeshellarg($outputPath),
            implode(' ', $fileList)
        );
        
        exec($command, $output, $returnCode);
        
        // Clean up batch files
        foreach ($batchFiles as $batchFile) {
            if (file_exists($batchFile)) {
                @unlink($batchFile);
            }
        }
        
        if ($returnCode === 0 && file_exists($outputPath) && filesize($outputPath) > 0) {
            $fileSize = round(filesize($outputPath) / 1024 / 1024, 2);
            logStatus("✓ Successfully merged {$totalFiles} PDFs into final file ({$fileSize} MB)", 'success');
            return true;
        }
        
        logStatus("✗ Final merge failed", 'error');
        return false;
        
    } catch (Exception $e) {
        error_log("Ghostscript batch merge failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Merge PDFs using FPDI (non-batch, for small file counts)
 */
function mergePdfsWithFpdi($pdfFiles, $outputPath) {
    $pdf = new Fpdi();
    $processedFiles = 0;
    $skippedFiles = [];
    $fileCount = count($pdfFiles);
    
    foreach ($pdfFiles as $file) {
        try {
            $tempFile = null;
            $fileToUse = $file;
            
            exec("which gs 2>&1", $output, $returnCode);
            if ($returnCode === 0) {
                $tempFile = sys_get_temp_dir() . '/' . uniqid('pdf_') . '.pdf';
                if (decompressPdf($file, $tempFile)) {
                    $fileToUse = $tempFile;
                }
            }
            
            $pageCount = $pdf->setSourceFile($fileToUse);
            
            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                $tpl = $pdf->importPage($pageNo);
                $size = $pdf->getTemplateSize($tpl);
                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($tpl);
            }
            
            $processedFiles++;
            
            if ($tempFile && file_exists($tempFile)) {
                @unlink($tempFile);
            }
            
            if ($processedFiles % 5 === 0 || $processedFiles === $fileCount) {
                logStatus("Merging progress: {$processedFiles}/{$fileCount} files", 'info');
            }
            
        } catch (Exception $e) {
            if (isset($tempFile) && $tempFile && file_exists($tempFile)) {
                @unlink($tempFile);
            }
            
            if (strpos($e->getMessage(), 'compression') !== false) {
                logStatus("⚠ Skipping " . basename($file) . " (unsupported compression)", 'warning');
                $skippedFiles[] = basename($file);
            } else {
                logStatus("⚠ Could not merge " . basename($file), 'warning');
            }
        }
    }
    
    if ($processedFiles === 0) {
        logStatus("⚠ No files could be merged", 'warning');
        return false;
    }
    
    $pdf->Output($outputPath, 'F');
    
    $fileSize = round(filesize($outputPath) / 1024 / 1024, 2);
    logStatus("✓ Merged {$processedFiles} PDFs ({$fileSize} MB)", 'success');
    
    return true;
}

/**
 * Merge PDFs using FPDI with Batch Processing
 */
function mergePdfsWithFpdiBatch($pdfFiles, $outputPath, $batchSize = 50) {
    $totalFiles = count($pdfFiles);
    
    // If files are less than batch size, merge directly
    if ($totalFiles <= $batchSize) {
        return mergePdfsWithFpdi($pdfFiles, $outputPath);
    }
    
    logStatus("Using FPDI with batch processing for {$totalFiles} files...", 'info');
    
    $batches = array_chunk($pdfFiles, $batchSize);
    $totalBatches = count($batches);
    $batchFiles = [];
    $tempDir = sys_get_temp_dir();
    
    // Step 1: Merge each batch
    foreach ($batches as $batchIndex => $batchPdfs) {
        $batchNum = $batchIndex + 1;
        $batchOutput = $tempDir . '/fpdi_batch_' . uniqid() . '_' . $batchNum . '.pdf';
        
        logStatus("FPDI: Merging batch {$batchNum}/{$totalBatches} (" . count($batchPdfs) . " files)...", 'info');
        
        $pdf = new Fpdi();
        $processedFiles = 0;
        
        foreach ($batchPdfs as $file) {
            try {
                $tempFile = null;
                $fileToUse = $file;
                
                exec("which gs 2>&1", $output, $returnCode);
                if ($returnCode === 0) {
                    $tempFile = sys_get_temp_dir() . '/' . uniqid('pdf_') . '.pdf';
                    if (decompressPdf($file, $tempFile)) {
                        $fileToUse = $tempFile;
                    }
                }
                
                $pageCount = $pdf->setSourceFile($fileToUse);
                
                for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                    $tpl = $pdf->importPage($pageNo);
                    $size = $pdf->getTemplateSize($tpl);
                    $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                    $pdf->useTemplate($tpl);
                }
                
                $processedFiles++;
                
                if ($tempFile && file_exists($tempFile)) {
                    @unlink($tempFile);
                }
                
            } catch (Exception $e) {
                if (isset($tempFile) && $tempFile && file_exists($tempFile)) {
                    @unlink($tempFile);
                }
                logStatus("⚠ Skipping " . basename($file), 'warning');
            }
        }
        
        if ($processedFiles > 0) {
            $pdf->Output($batchOutput, 'F');
            $batchFiles[] = $batchOutput;
            logStatus("✓ FPDI Batch {$batchNum}/{$totalBatches} complete ({$processedFiles} files)", 'success');
        } else {
            logStatus("⚠ FPDI Batch {$batchNum} had no processable files", 'warning');
        }
        
        unset($pdf); // Free memory
    }
    
    if (empty($batchFiles)) {
        logStatus("✗ All FPDI batches failed", 'error');
        return false;
    }
    
    // Step 2: Merge all batch files
    logStatus("FPDI: Merging {$totalBatches} batch files into final PDF...", 'info');
    
    $finalPdf = new Fpdi();
    
    foreach ($batchFiles as $batchFile) {
        try {
            $pageCount = $finalPdf->setSourceFile($batchFile);
            
            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                $tpl = $finalPdf->importPage($pageNo);
                $size = $finalPdf->getTemplateSize($tpl);
                $finalPdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $finalPdf->useTemplate($tpl);
            }
        } catch (Exception $e) {
            logStatus("⚠ Could not merge batch: " . basename($batchFile), 'warning');
        }
    }
    
    $finalPdf->Output($outputPath, 'F');
    
    // Clean up batch files
    foreach ($batchFiles as $batchFile) {
        if (file_exists($batchFile)) {
            @unlink($batchFile);
        }
    }
    
    $fileSize = round(filesize($outputPath) / 1024 / 1024, 2);
    logStatus("✓ FPDI batch merge complete ({$fileSize} MB)", 'success');
    
    return true;
}

/**
 * Merge multiple PDF files with automatic batch processing
 */
function mergePodPdfs($pdfFiles, $outputPath) {
    try {
        $validFiles = array_filter($pdfFiles, fn($f) => is_readable($f));
        $fileCount = count($validFiles);
        
        if ($fileCount === 0) {
            logStatus("✗ No valid PDF files to merge", 'error');
            return false;
        }
        
        logStatus("Starting merge of {$fileCount} PDF files...", 'info');
        
        // Try Ghostscript with batch processing (handles large file counts)
        if (mergePdfsWithGhostscriptBatch($validFiles, $outputPath, 100)) {
            return true;
        }
        
        // Fallback to FPDI with batch processing
        logStatus("ℹ Ghostscript not available, using FPDI with batch processing...", 'info');
        return mergePdfsWithFpdiBatch($validFiles, $outputPath, 50);
        
    } catch (Exception $e) {
        logStatus("✗ Merge error: " . $e->getMessage(), 'error');
        logStatus("ℹ Individual PODs are still available for download", 'info');
        error_log("Merge error: " . $e->getMessage());
        return false;
    }
}

/**
 * Handle file uploads and combine multiple Excel files
 */
function handleFileUploads($uploadedFiles) {
    $combinedData = [];
    $isFirstFile = true;
    $baseFileName = '';

    foreach ($uploadedFiles['tmp_name'] as $index => $tmpName) {
        $originalName = basename($uploadedFiles['name'][$index]);
        if ($originalName === '') continue;

        $targetPath = UPLOAD_FOLDER . '/' . $originalName;

        if (move_uploaded_file($tmpName, $targetPath)) {
            $spreadsheet = IOFactory::load($targetPath);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();

            if ($isFirstFile) {
                $combinedData = $rows;
                $isFirstFile = false;
                $baseFileName = pathinfo($originalName, PATHINFO_FILENAME);
            } else {
                $dataRows = array_slice($rows, 1);
                $combinedData = array_merge($combinedData, $dataRows);
            }
        }
    }

    if (empty($combinedData)) {
        throw new Exception("No valid Excel data found in uploaded files.");
    }

    $combinedFilePath = UPLOAD_FOLDER . '/' . $baseFileName . '_combined.xlsx';

    $combinedSpreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $combinedSpreadsheet->getActiveSheet();

    foreach ($combinedData as $rowIndex => $rowValues) {
        foreach ($rowValues as $colIndex => $cellValue) {
            $cellCoordinate = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 1)
                . ($rowIndex + 1);
            $sheet->setCellValue($cellCoordinate, $cellValue);
        }
    }

    $writer = IOFactory::createWriter($combinedSpreadsheet, 'Xlsx');
    $writer->save($combinedFilePath);

    return $combinedFilePath;
}

/**
 * Process tracking numbers from Excel file
 */
function processTrackingFile($filePath, $accessToken, $projectNumber, $shipmentDate = null) {
    logStatus("Loading Excel file...", 'info', 15);
    
    // Show project number and shipment date if provided
    if ($shipmentDate) {
        $displayDate = date('m/d/Y', strtotime($shipmentDate));
        logStatus("Project: $projectNumber | Shipment Date: $displayDate", 'info');
    } else {
        logStatus("Project: $projectNumber | No date filter", 'info');
    }
    
    $spreadsheet = IOFactory::load($filePath);
    $sheet = $spreadsheet->getActiveSheet();
    $rows = $sheet->toArray();

    // Find tracking number column
    $headers = array_map('strtolower', $rows[0]);
    $trackingColIndex = null;
    foreach ($headers as $idx => $header) {
        if (in_array($header, ['tracking number', 'tracking'])) {
            $trackingColIndex = $idx;
            break;
        }
    }
    
    if ($trackingColIndex === null) {
        throw new Exception("Tracking Number column not found");
    }

    // Add "Reason" column to header
    $rows[0][] = "Reason";

    // Collect all tracking numbers
    $trackingNumbers = [];
    for ($i = 1; $i < count($rows); $i++) {
        $tn = trim($rows[$i][$trackingColIndex]);
        if ($tn !== '') $trackingNumbers[] = $tn;
    }

    $totalTracking = count($trackingNumbers);
    logStatus("Found $totalTracking tracking numbers to process", 'info', 20);

    // Process each tracking number individually
    $statuses = [];
    $reasons = [];
    $downloadedPdfs = [];
    
    foreach ($trackingNumbers as $index => $tn) {
        $currentProgress = 20 + (($index + 1) / $totalTracking * 60);
        logStatus("Processing (" . ($index + 1) . "/$totalTracking): $tn", 'info', (int)$currentProgress);
        
        // Get tracking status
        $trackingResult = getTrackingStatus($tn, $accessToken, $shipmentDate);
        $statuses[$tn] = $trackingResult['status'];
        $reasons[$tn] = $trackingResult['reason'];
        
        logStatus("✓ Status for $tn: " . $trackingResult['status'], 'success');
        
        // Download POD if delivered
        if ($trackingResult['status'] === 'DL') {
            $podPath = downloadPodDocument($tn, $accessToken, $shipmentDate);
            if ($podPath) {
                $downloadedPdfs[] = $podPath;
            }
        }
        
        // Sleep between API calls
        if ($index < $totalTracking - 1) {
            usleep(API_SLEEP_SECONDS * 1000000);
        }
    }

    // Build Excel output
    logStatus("Generating Excel report...", 'info', 85);
    
    $newSpreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $newSheet = $newSpreadsheet->getActiveSheet();

    for ($i = 0; $i < count($rows); $i++) {
        $rowValues = $rows[$i];
        $rowIndex = $i + 1;

        if ($i > 0) {
            $tn = trim($rows[$i][$trackingColIndex]);
            $reason = $reasons[$tn] ?? '';
            $rowValues[] = $reason;
        }

        foreach ($rowValues as $colIndex => $cellValue) {
            $cellCoordinate = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 1) . $rowIndex;
            $newSheet->setCellValueExplicit($cellCoordinate, $cellValue, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        }

        if ($i > 0) {
            $tn = trim($rows[$i][$trackingColIndex]);
            $status = $statuses[$tn] ?? '';
            $fillColor = ($status == 'DL') ? 'FFFF00' : 'FF0000';
            $newSheet->getStyle("A{$rowIndex}:" .
                \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($rowValues)) . "{$rowIndex}"
            )->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB($fillColor);
        }
    }

    // Use project number in filename
    $excelPath = RESULTS_FOLDER . "/{$projectNumber}-Tracking.xlsx";
    $writer = IOFactory::createWriter($newSpreadsheet, 'Xlsx');
    $writer->save($excelPath);
    
    logStatus("✓ Excel report generated: {$projectNumber}-Tracking.xlsx", 'success', 90);

    // Merge PODs if any
    $mergedPdfPath = null;
    if (!empty($downloadedPdfs)) {
        $podCount = count($downloadedPdfs);
        logStatus("Merging {$podCount} POD documents...", 'info', 95);
        
        // Use project number in filename
        $mergedPdfPath = RESULTS_FOLDER . "/{$projectNumber}-POD.pdf";
        
        if (mergePodPdfs($downloadedPdfs, $mergedPdfPath)) {
            logStatus("✓ Successfully merged {$podCount} PODs: {$projectNumber}-POD.pdf", 'success', 98);
        } else {
            logStatus("⚠ Failed to merge PDFs", 'warning', 98);
        }
    } else {
        logStatus("ℹ No delivered shipments - no PODs to merge", 'info', 98);
    }

    return [
        'mergedPdf' => $mergedPdfPath,
        'individualPdfs' => $downloadedPdfs,
        'excel' => $excelPath,
        'projectNumber' => $projectNumber
    ];
}