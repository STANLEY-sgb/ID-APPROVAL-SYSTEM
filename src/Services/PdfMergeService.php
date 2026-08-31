<?php
declare(strict_types=1);

namespace Mengo\IdApproval\Services;

use Mengo\IdApproval\Models\IdStatus;
use Mengo\IdApproval\Models\PrintBatch;
use Mengo\IdApproval\Models\PrintBatchItem;
use Mengo\IdApproval\Repositories\IdCardRepository;
use Mengo\IdApproval\Repositories\IdVersionRepository;
use Mengo\IdApproval\Repositories\PrintBatchRepository;
use Mengo\IdApproval\Support\Database;
use Mengo\IdApproval\Support\Timezone;
use RuntimeException;

class PdfMergeService
{
    private IdCardRepository $cardRepo;
    private IdVersionRepository $versionRepo;
    private PrintBatchRepository $batchRepo;
    private PdfService $pdfService;
    private string $tempDir;

    public function __construct(
        ?IdCardRepository $cardRepo = null,
        ?IdVersionRepository $versionRepo = null,
        ?PrintBatchRepository $batchRepo = null,
        ?PdfService $pdfService = null,
        ?string $tempDir = null
    ) {
        $this->cardRepo = $cardRepo ?? new IdCardRepository();
        $this->versionRepo = $versionRepo ?? new IdVersionRepository();
        $this->batchRepo = $batchRepo ?? new PrintBatchRepository();
        $this->pdfService = $pdfService ?? new PdfService();
        $this->tempDir = $tempDir ?? (dirname(__DIR__, 2) . '/storage/temp');

        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0755, true);
        }
    }

    /**
     * Validate an array of selected ID card IDs before creating or merging a batch
     */
    public function validateDocuments(array $idCardIds): array
    {
        $idCardIds = array_values(array_unique(array_filter(array_map('intval', $idCardIds))));
        if (empty($idCardIds)) {
            throw new RuntimeException("No ID cards were provided for validation.");
        }

        $validItems = [];
        $failedItems = [];
        $pageSizes = [];
        $totalEstimatedSize = 0;
        $totalPageCount = 0;

        foreach ($idCardIds as $seq => $cardId) {
            $card = $this->cardRepo->findById($cardId);
            if (!$card) {
                $failedItems[] = [
                    'id_card_id' => $cardId,
                    'employee_name' => "ID #{$cardId}",
                    'status' => PrintBatchItem::STATUS_MISSING,
                    'reason' => 'Database record not found'
                ];
                continue;
            }

            if ($card->current_status !== IdStatus::APPROVED) {
                $failedItems[] = [
                    'id_card_id' => $card->id,
                    'card_reference' => $card->card_reference,
                    'employee_name' => $card->employee_name,
                    'staff_id' => $card->staff_id,
                    'status' => PrintBatchItem::STATUS_INVALID,
                    'reason' => "Card status is '{$card->current_status}', must be 'APPROVED'"
                ];
                continue;
            }

            $version = null;
            if ($card->approved_version_id) {
                $version = $this->versionRepo->findById($card->approved_version_id);
            }
            if (!$version) {
                $version = $this->versionRepo->findByCardAndVersion($card->id, $card->current_version_number);
            }

            if (!$version) {
                $failedItems[] = [
                    'id_card_id' => $card->id,
                    'card_reference' => $card->card_reference,
                    'employee_name' => $card->employee_name,
                    'staff_id' => $card->staff_id,
                    'status' => PrintBatchItem::STATUS_MISSING,
                    'reason' => 'Approved PDF version record not found in system'
                ];
                continue;
            }

            $filePath = $this->pdfService->getAbsolutePath($version->file_path);
            if (!file_exists($filePath) || !is_readable($filePath)) {
                $failedItems[] = [
                    'id_card_id' => $card->id,
                    'card_reference' => $card->card_reference,
                    'employee_name' => $card->employee_name,
                    'staff_id' => $card->staff_id,
                    'version_id' => $version->id,
                    'status' => PrintBatchItem::STATUS_MISSING,
                    'reason' => 'Physical PDF file is missing from protected storage'
                ];
                continue;
            }

            if (!$this->pdfService->verifyIntegrity($filePath, $version->file_sha256)) {
                $failedItems[] = [
                    'id_card_id' => $card->id,
                    'card_reference' => $card->card_reference,
                    'employee_name' => $card->employee_name,
                    'staff_id' => $card->staff_id,
                    'version_id' => $version->id,
                    'status' => PrintBatchItem::STATUS_CORRUPTED,
                    'reason' => 'PDF SHA-256 hash mismatch (file altered or corrupted)'
                ];
                continue;
            }

            $fileSize = filesize($filePath);
            $totalEstimatedSize += $fileSize;

            // Extract basic PDF metadata
            $pdfMeta = $this->extractPdfPageInfo($filePath);
            $cardPageCount = $pdfMeta['page_count'] ?? 1;
            $totalPageCount += $cardPageCount;

            if (!empty($pdfMeta['page_size_label'])) {
                $pageSizes[] = $pdfMeta['page_size_label'];
            }

            $validItems[] = [
                'id_card_id' => $card->id,
                'approved_version_id' => $version->id,
                'employee_id' => $card->employee_id,
                'employee_name' => $card->employee_name,
                'staff_id' => $card->staff_id,
                'card_reference' => $card->card_reference,
                'department_name' => $card->department_name,
                'file_path' => $filePath,
                'file_sha256' => $version->file_sha256,
                'file_size' => $fileSize,
                'page_count' => $cardPageCount,
                'page_dimensions' => $pdfMeta['dimensions'] ?? 'Standard ID',
                'orientation' => $pdfMeta['orientation'] ?? 'Portrait',
                'sequence_number' => $seq + 1
            ];
        }

        $uniqueSizes = array_values(array_unique(array_filter($pageSizes)));
        $isMixed = count($uniqueSizes) > 1;

        return [
            'total_selected' => count($idCardIds),
            'valid_count' => count($validItems),
            'failed_count' => count($failedItems),
            'valid_items' => $validItems,
            'failed_items' => $failedItems,
            'total_pages' => $totalPageCount,
            'estimated_size' => $totalEstimatedSize,
            'page_sizes' => $uniqueSizes,
            'is_mixed_size' => $isMixed
        ];
    }

    /**
     * Merge multiple PDF files into one consolidated production PDF
     */
    public function mergeFiles(array $inputFiles, string $outputPath, string $orientation = 'ORIGINAL'): array
    {
        if (empty($inputFiles)) {
            throw new RuntimeException("No PDF files available to merge.");
        }

        $allObjects = [];
        $pageObjectIds = [];
        $nextObjectId = 1;
        $pageSizeTypes = [];

        foreach ($inputFiles as $filePath) {
            if (!file_exists($filePath) || !is_readable($filePath)) {
                throw new RuntimeException("Cannot read source PDF: " . basename($filePath));
            }

            $rawContent = file_get_contents($filePath);
            if (!str_starts_with($rawContent, '%PDF-')) {
                throw new RuntimeException("Invalid PDF header in file: " . basename($filePath));
            }

            $parsed = $this->parsePdfObjects($rawContent);
            $objects = $parsed['objects'];
            $rootId = $parsed['root_id'];

            if (!$rootId || !isset($objects[$rootId])) {
                foreach ($objects as $oid => $body) {
                    if (str_contains($body, '/Type /Catalog') || str_contains($body, '/Type/Catalog')) {
                        $rootId = $oid;
                        break;
                    }
                }
            }

            $filePageIds = $this->findPages($objects, $rootId);
            if (empty($filePageIds)) {
                foreach ($objects as $oid => $body) {
                    if (preg_match('/\/Type\s*\/Page\b/', $body) && !preg_match('/\/Type\s*\/Pages\b/', $body)) {
                        $filePageIds[] = $oid;
                    }
                }
            }

            // Remap IDs
            $idMap = [];
            foreach (array_keys($objects) as $oldOid) {
                $idMap[$oldOid] = $nextObjectId++;
            }

            foreach ($objects as $oldOid => $body) {
                $newOid = $idMap[$oldOid];
                $isPage = in_array($oldOid, $filePageIds, true);
                $remappedBody = $this->remapReferences($body, $idMap);

                if ($isPage) {
                    if (preg_match('/\/MediaBox\s*\[([0-9\.\s\-]+)\]/', $remappedBody, $m)) {
                        $pageSizeTypes[] = trim($m[1]);
                    }

                    // Optional forced orientation rotation
                    if ($orientation === 'LANDSCAPE') {
                        if (preg_match('/\/Rotate\s+[0-9]+/', $remappedBody)) {
                            $remappedBody = preg_replace('/\/Rotate\s+[0-9]+/', '/Rotate 90', $remappedBody);
                        } else {
                            $remappedBody = preg_replace('/(<<)/', '$1 /Rotate 90', $remappedBody, 1);
                        }
                    } elseif ($orientation === 'PORTRAIT') {
                        if (preg_match('/\/Rotate\s+[0-9]+/', $remappedBody)) {
                            $remappedBody = preg_replace('/\/Rotate\s+[0-9]+/', '/Rotate 0', $remappedBody);
                        } else {
                            $remappedBody = preg_replace('/(<<)/', '$1 /Rotate 0', $remappedBody, 1);
                        }
                    }

                    $pageObjectIds[] = $newOid;
                }

                $allObjects[$newOid] = $remappedBody;
            }
        }

        $pagesObjId = $nextObjectId++;
        $catalogObjId = $nextObjectId++;

        $kidsStr = '';
        foreach ($pageObjectIds as $pageOid) {
            $kidsStr .= "{$pageOid} 0 R ";
            $body = $allObjects[$pageOid];
            if (preg_match('/\/Parent\s+[0-9]+\s+[0-9]+\s+R/', $body)) {
                $body = preg_replace('/\/Parent\s+[0-9]+\s+[0-9]+\s+R/', "/Parent {$pagesObjId} 0 R", $body);
            } else {
                $body = preg_replace('/(<<)/', "$1 /Parent {$pagesObjId} 0 R", $body, 1);
            }
            $allObjects[$pageOid] = $body;
        }

        $pageCount = count($pageObjectIds);
        $allObjects[$pagesObjId] = "<< /Type /Pages /Kids [ {$kidsStr}] /Count {$pageCount} >>";
        $allObjects[$catalogObjId] = "<< /Type /Catalog /Pages {$pagesObjId} 0 R >>";

        $out = "%PDF-1.6\n%\xE2\xE3\xCF\xD3\n";
        $xref = [];
        $xref[0] = "0000000000 65535 f \n";

        ksort($allObjects);

        foreach ($allObjects as $oid => $body) {
            $offset = strlen($out);
            $xref[$oid] = sprintf("%010d 00000 n \n", $offset);
            $out .= "{$oid} 0 obj\n{$body}\nendobj\n";
        }

        $xrefOffset = strlen($out);
        $totalObjs = count($xref);
        $out .= "xref\n0 {$totalObjs}\n";
        for ($i = 0; $i < $totalObjs; $i++) {
            $out .= $xref[$i];
        }

        $out .= "trailer\n<< /Size {$totalObjs} /Root {$catalogObjId} 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF\n";

        $dir = dirname($outputPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($outputPath, $out);

        return [
            'output_path' => $outputPath,
            'file_size' => filesize($outputPath),
            'output_hash' => hash_file('sha256', $outputPath),
            'page_count' => $pageCount,
            'page_sizes' => array_values(array_unique($pageSizeTypes)),
            'is_consistent_size' => count(array_unique($pageSizeTypes)) <= 1
        ];
    }

    /**
     * Automatic cleanup of expired temporary merge files
     */
    public function cleanupExpiredBatches(int $expirationHours = 48): int
    {
        $expiredBatches = $this->batchRepo->getExpiredBatches($expirationHours);
        $cleaned = 0;

        foreach ($expiredBatches as $batch) {
            if (!empty($batch->output_path) && file_exists($batch->output_path)) {
                @unlink($batch->output_path);
            }
            $this->batchRepo->update($batch->id, [
                'status' => PrintBatch::STATUS_EXPIRED,
                'output_path' => null
            ]);
            $cleaned++;
        }

        // Also sweep any unreferenced files in storage/temp older than expiration
        $files = glob($this->tempDir . '/*.pdf');
        $cutoff = time() - ($expirationHours * 3600);
        foreach ($files as $f) {
            if (filemtime($f) < $cutoff) {
                @unlink($f);
            }
        }

        return $cleaned;
    }

    private function extractPdfPageInfo(string $filePath): array
    {
        $content = file_get_contents($filePath);
        $pageCount = 0;
        $dimensions = 'Standard ID (86 × 54 mm)';
        $orientation = 'Portrait';
        $label = 'Custom 86 × 54 mm';

        if (preg_match_all('/\/Type\s*\/Page\b/', $content, $m)) {
            // Exclude /Pages
            if (preg_match_all('/\/Type\s*\/Pages\b/', $content, $pm)) {
                $pageCount = count($m[0]) - count($pm[0]);
            } else {
                $pageCount = count($m[0]);
            }
        }
        $pageCount = max(1, $pageCount);

        if (preg_match('/\/MediaBox\s*\[\s*([0-9\.\-]+)\s+([0-9\.\-]+)\s+([0-9\.\-]+)\s+([0-9\.\-]+)\s*\]/', $content, $dim)) {
            $w = abs((float)$dim[3] - (float)$dim[1]);
            $h = abs((float)$dim[4] - (float)$dim[2]);

            if ($w > $h) {
                $orientation = 'Landscape';
            } else {
                $orientation = 'Portrait';
            }

            if (round($w) == 595 && round($h) == 842) {
                $label = 'A4 Portrait';
            } elseif (round($w) == 842 && round($h) == 595) {
                $label = 'A4 Landscape';
            } else {
                $label = sprintf("Custom %.0f × %.0f pt", $w, $h);
            }
            $dimensions = sprintf("%.0f × %.0f pt", $w, $h);
        }

        return [
            'page_count' => $pageCount,
            'dimensions' => $dimensions,
            'orientation' => $orientation,
            'page_size_label' => $label
        ];
    }

    private function parsePdfObjects(string $content): array
    {
        $objects = [];
        $rootId = null;

        if (preg_match('/trailer\s*<<.*?\/Root\s+([0-9]+)\s+([0-9]+)\s+R.*?>>/s', $content, $m)) {
            $rootId = (int)$m[1];
        }

        $offset = 0;
        while (preg_match('/([0-9]+)\s+([0-9]+)\s+obj\b/s', $content, $match, PREG_OFFSET_CAPTURE, $offset)) {
            $oid = (int)$match[1][0];
            $startObjPos = $match[0][1];
            $headerLen = strlen($match[0][0]);
            $bodyStart = $startObjPos + $headerLen;

            $endObjPos = strpos($content, 'endobj', $bodyStart);
            if ($endObjPos === false) {
                break;
            }

            $body = trim(substr($content, $bodyStart, $endObjPos - $bodyStart));
            $objects[$oid] = $body;

            $offset = $endObjPos + 6;
        }

        return [
            'objects' => $objects,
            'root_id' => $rootId
        ];
    }

    private function findPages(array $objects, ?int $rootId): array
    {
        if (!$rootId || !isset($objects[$rootId])) {
            return [];
        }

        $rootBody = $objects[$rootId];
        if (!preg_match('/\/Pages\s+([0-9]+)\s+[0-9]+\s+R/', $rootBody, $m)) {
            return [];
        }

        $pagesId = (int)$m[1];
        return $this->collectKids($objects, $pagesId);
    }

    private function collectKids(array $objects, int $nodeId): array
    {
        if (!isset($objects[$nodeId])) {
            return [];
        }

        $body = $objects[$nodeId];
        $isPage = preg_match('/\/Type\s*\/Page\b/', $body) && !preg_match('/\/Type\s*\/Pages\b/', $body);
        if ($isPage) {
            return [$nodeId];
        }

        $pages = [];
        if (preg_match('/\/Kids\s*\[(.*?)\]/s', $body, $km)) {
            if (preg_match_all('/([0-9]+)\s+[0-9]+\s+R/', $km[1], $rm)) {
                foreach ($rm[1] as $kidId) {
                    $kidId = (int)$kidId;
                    $pages = array_merge($pages, $this->collectKids($objects, $kidId));
                }
            }
        }

        return $pages;
    }

    private function remapReferences(string $body, array $idMap): string
    {
        return preg_replace_callback('/([0-9]+)\s+([0-9]+)\s+R\b/', function ($m) use ($idMap) {
            $oldId = (int)$m[1];
            $gen = $m[2];
            if (isset($idMap[$oldId])) {
                return "{$idMap[$oldId]} {$gen} R";
            }
            return $m[0];
        }, $body);
    }
}
