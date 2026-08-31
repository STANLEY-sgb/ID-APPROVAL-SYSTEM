<?php
declare(strict_types=1);

namespace Mengo\IdApproval\Services;

use finfo;
use Mengo\IdApproval\Security\Sanitizer;
use Mengo\IdApproval\Support\Config;
use RuntimeException;

class PdfService
{
    private string $storagePath;

    public function __construct(?string $storagePath = null)
    {
        $base = dirname(__DIR__, 2);
        $configured = $storagePath ?? (string)Config::get('STORAGE_PROTECTED_PATH', 'storage/uploads/protected');
        $this->storagePath = str_starts_with($configured, '/') || preg_match('/^[A-Za-z]:/', $configured)
            ? $configured
            : $base . '/' . ltrim($configured, '/\\');

        if (!is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0750, true);
        }
    }

    public function getStoragePath(): string
    {
        return $this->storagePath;
    }

    public function validateAndStoreUpload(array $file): array
    {
        if (!isset($file['error']) || is_array($file['error'])) {
            throw new RuntimeException("Invalid file upload parameters.");
        }

        switch ($file['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                throw new RuntimeException("File exceeds maximum allowed upload size limit.");
            case UPLOAD_ERR_PARTIAL:
                throw new RuntimeException("File was only partially uploaded.");
            case UPLOAD_ERR_NO_FILE:
                throw new RuntimeException("No file was uploaded.");
            default:
                throw new RuntimeException("File upload failed with error code: {$file['error']}");
        }

        $maxSize = (int)Config::get('MAX_UPLOAD_SIZE', 31457280); // 30 MB
        if ($file['size'] > $maxSize) {
            $mb = round($maxSize / (1024 * 1024));
            throw new RuntimeException("Uploaded file exceeds the maximum limit of {$mb} MB.");
        }

        $tmpPath = $file['tmp_name'];
        if (!is_uploaded_file($tmpPath) && !file_exists($tmpPath)) {
            throw new RuntimeException("Invalid uploaded file path.");
        }

        return $this->processAndStoreFile($tmpPath, $file['name']);
    }

    public function processAndStoreFile(string $sourcePath, string $originalFilename): array
    {
        // 1. Check MIME type via FileInfo
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($sourcePath);
        if ($mime !== 'application/pdf') {
            throw new RuntimeException("Invalid file type: '{$mime}'. Only authentic PDF documents are allowed.");
        }

        // 2. Check Magic Header Signature
        $fp = fopen($sourcePath, 'rb');
        $header = fread($fp, 1024);
        fclose($fp);

        if (!str_starts_with((string)$header, '%PDF-')) {
            throw new RuntimeException("Corrupted or invalid PDF signature. The file is not a valid PDF.");
        }

        // 3. Compute SHA-256 Hash
        $hash = hash_file('sha256', $sourcePath);
        $filesize = filesize($sourcePath);

        // 4. Generate Secure Filename and Copy/Move
        $cleanOriginalName = Sanitizer::cleanFilename($originalFilename);
        $safeFilename = substr($hash, 0, 16) . '_' . bin2hex(random_bytes(8)) . '.pdf';
        $destinationPath = $this->storagePath . '/' . $safeFilename;

        if (is_uploaded_file($sourcePath)) {
            if (!move_uploaded_file($sourcePath, $destinationPath)) {
                throw new RuntimeException("Failed to move uploaded file to protected storage.");
            }
        } else {
            if (!copy($sourcePath, $destinationPath)) {
                throw new RuntimeException("Failed to copy PDF file to protected storage.");
            }
        }

        return [
            'relative_path' => $safeFilename,
            'full_path' => $destinationPath,
            'original_filename' => $cleanOriginalName,
            'file_size' => $filesize,
            'file_sha256' => $hash,
            'mime_type' => 'application/pdf'
        ];
    }

    public function getAbsolutePath(string $relativePath): string
    {
        $safeName = basename($relativePath);
        return $this->storagePath . '/' . $safeName;
    }

    public function verifyIntegrity(string $relativePath, string $expectedSha256): bool
    {
        $fullPath = $this->getAbsolutePath($relativePath);
        if (!file_exists($fullPath)) {
            return false;
        }

        $currentHash = hash_file('sha256', $fullPath);
        return hash_equals($expectedSha256, $currentHash);
    }
}
