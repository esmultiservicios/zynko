<?php
declare(strict_types=1);

/** Detect from bytes, never from a client MIME header or filename. */
final class AttachmentMime
{
    public static function file(string $path): string
    {
        if (!is_file($path) || !is_readable($path)) return 'application/octet-stream';
        if (class_exists('finfo')) {
            return (new finfo(FILEINFO_MIME_TYPE))->file($path) ?: 'application/octet-stream';
        }
        $size = filesize($path);
        if ($size === false || $size < 1 || $size > 20 * 1024 * 1024) return 'application/octet-stream';
        $bytes = file_get_contents($path);
        return $bytes === false ? 'application/octet-stream' : self::fallback($bytes);
    }

    public static function buffer(string $bytes): string
    {
        if ($bytes === '') return 'application/octet-stream';
        if (class_exists('finfo')) {
            return (new finfo(FILEINFO_MIME_TYPE))->buffer($bytes) ?: 'application/octet-stream';
        }
        return self::fallback($bytes);
    }

    private static function fallback(string $bytes): string
    {
        $length = strlen($bytes);
        if ($length < 12 || $length > 20 * 1024 * 1024) return 'application/octet-stream';
        // Reject unknown formats instead of trusting extensions when Fileinfo is disabled.
        if (preg_match('/^%PDF-[12]\.[0-9](?:\r\n|\n|\r)/D', $bytes)
            && preg_match('/%%EOF[\x00\x09\x0A\x0C\x0D\x20]*$/D', substr($bytes, -1024))) {
            return 'application/pdf';
        }
        $image = @getimagesizefromstring($bytes);
        if (!$image || empty($image[0]) || empty($image[1])) return 'application/octet-stream';
        $mime = $image['mime'] ?? '';
        if ($mime === 'image/png' && str_starts_with($bytes, "\x89PNG\r\n\x1a\n") && substr($bytes,-12) === "\x00\x00\x00\x00IEND\xae\x42\x60\x82") return $mime;
        if ($mime === 'image/jpeg' && str_starts_with($bytes, "\xff\xd8\xff") && str_ends_with($bytes, "\xff\xd9")) return $mime;
        if ($mime === 'image/webp' && substr($bytes,0,4)==='RIFF' && substr($bytes,8,4)==='WEBP' && unpack('V',substr($bytes,4,4))[1]+8===$length) return $mime;
        if ($mime === 'image/gif' && (str_starts_with($bytes,'GIF87a') || str_starts_with($bytes,'GIF89a')) && str_ends_with($bytes,";")) return $mime;
        return 'application/octet-stream';
    }
}
