<?php
declare(strict_types=1);

namespace FFTicket;

final class UploadService
{
    private const ALLOWED = [
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'pdf' => 'application/pdf',
    ];

    public function saveOptionalAttachment(string $fieldName): ?array
    {
        if (!isset($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        $file = $_FILES[$fieldName];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            json_response('error', 'File upload failed.', null, 422);
        }

        $maxBytes = (int)env_value('MAX_UPLOAD_BYTES', '10485760');
        if ((int)$file['size'] > $maxBytes) {
            json_response('error', 'Attachment exceeds the 10 MB limit.', null, 422);
        }

        $originalName = basename((string)$file['name']);
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!array_key_exists($extension, self::ALLOWED)) {
            json_response('error', 'Unsupported attachment type.', null, 422);
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file((string)$file['tmp_name']);
        if ($mime !== self::ALLOWED[$extension]) {
            json_response('error', 'Attachment MIME type does not match the file extension.', null, 422);
        }

        $uploadDir = env_value('UPLOAD_DIR', 'storage/uploads');
        $absoluteDir = realpath(__DIR__ . '/../' . $uploadDir);
        if ($absoluteDir === false) {
            $absoluteDir = __DIR__ . '/../' . $uploadDir;
            if (!mkdir($absoluteDir, 0750, true) && !is_dir($absoluteDir)) {
                json_response('error', 'Upload directory is not writable.', null, 500);
            }
        }

        $storedName = bin2hex(random_bytes(16)) . '.' . $extension;
        $target = rtrim($absoluteDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $storedName;
        if (!move_uploaded_file((string)$file['tmp_name'], $target)) {
            json_response('error', 'Unable to store attachment.', null, 500);
        }

        return [
            'file_name' => $originalName,
            'file_path' => $storedName,
            'file_size' => (int)$file['size'],
            'file_type' => $mime,
        ];
    }
}
