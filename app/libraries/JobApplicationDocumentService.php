<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class JobApplicationDocumentService
{
    private string $storageDir;

    public function __construct(?string $storageDir = null)
    {
        $this->storageDir = $storageDir ?? ROOT_DIR . 'runtime/private/job-applications';
    }

    public function store(array $upload): array
    {
        if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($upload['tmp_name']) || !is_uploaded_file($upload['tmp_name'])) {
            throw new InvalidArgumentException('Upload a clear image or PDF of your ID.');
        }
        $size = (int)($upload['size'] ?? 0);
        if ($size < 1 || $size > 8 * 1024 * 1024) {
            throw new InvalidArgumentException('ID file must be 8 MB or smaller.');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($upload['tmp_name']);
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf'];
        if (!isset($allowed[$mime])) {
            throw new InvalidArgumentException('ID file must be a JPEG, PNG, WebP, or PDF.');
        }
        if (str_starts_with($mime, 'image/')) {
            $image = @getimagesize($upload['tmp_name']);
            if (!$image || ($image['mime'] ?? '') !== $mime || $image[0] > 7000 || $image[1] > 7000 || $image[0] * $image[1] > 30000000) {
                throw new InvalidArgumentException('ID image could not be validated.');
            }
        } elseif (file_get_contents($upload['tmp_name'], false, null, 0, 5) !== '%PDF-') {
            throw new InvalidArgumentException('ID PDF could not be validated.');
        }

        if (!is_dir($this->storageDir) && !@mkdir($this->storageDir, 0700, true) && !is_dir($this->storageDir)) {
            throw new RuntimeException('Private application storage is unavailable.');
        }
        @chmod($this->storageDir, 0700);
        $key = bin2hex(random_bytes(32)) . '.' . $allowed[$mime];
        $fileData = file_get_contents($upload['tmp_name']);
        if (!move_uploaded_file($upload['tmp_name'], $this->storageDir . DIRECTORY_SEPARATOR . $key)) {
            throw new RuntimeException('Could not securely store ID file.');
        }
        @chmod($this->storageDir . DIRECTORY_SEPARATOR . $key, 0600);

        return [
            'storage_key' => $key,
            'mime_type' => $mime,
            'original_name' => preg_replace('/[^A-Za-z0-9._-]/', '_', basename((string)($upload['name'] ?? 'id-document'))),
            'file_data' => $fileData,
        ];
    }

    public function restoreFromData(string $storageKey, string $fileData): ?string
    {
        if (!preg_match('/\A[a-f0-9]{64}\.(jpg|png|webp|pdf)\z/', $storageKey)) {
            return null;
        }
        if (!is_dir($this->storageDir) && !@mkdir($this->storageDir, 0700, true) && !is_dir($this->storageDir)) {
            return null;
        }
        $path = $this->storageDir . DIRECTORY_SEPARATOR . $storageKey;
        if (file_put_contents($path, $fileData) !== false) {
            @chmod($path, 0600);
            return $path;
        }
        return null;
    }

    public function readPath(string $storageKey): ?string
    {
        if (!preg_match('/\A[a-f0-9]{64}\.(jpg|png|webp|pdf)\z/', $storageKey)) {
            return null;
        }
        $path = $this->storageDir . DIRECTORY_SEPARATOR . $storageKey;
        return is_file($path) ? $path : null;
    }

    public function delete(string $storageKey): void
    {
        $path = $this->readPath($storageKey);
        if ($path !== null) {
            @unlink($path);
        }
    }
}
