<?php

namespace Pickem;

class Photo
{
    private const MAX_BYTES = 5 * 1024 * 1024; // 5MB
    private const SIZE = 400; // stored square, px

    public static function storageDir(): string
    {
        $dir = dirname(__DIR__) . '/storage/avatars';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return $dir;
    }

    /**
     * @throws \InvalidArgumentException on a bad upload the caller should show back to the user.
     * @return string relative path (just the filename) stored in participants.photo_path
     */
    public static function handleUpload(array $file, int $participantId): string
    {
        if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
            throw new \InvalidArgumentException('No file was uploaded.');
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new \InvalidArgumentException('Upload failed — try a smaller image.');
        }
        if ($file['size'] > self::MAX_BYTES) {
            throw new \InvalidArgumentException('Photo is too large — 5MB max.');
        }

        $info = @getimagesize($file['tmp_name']);
        if ($info === false) {
            throw new \InvalidArgumentException('That file doesn\'t look like an image.');
        }

        $src = match ($info['mime']) {
            'image/jpeg' => imagecreatefromjpeg($file['tmp_name']),
            'image/png' => imagecreatefrompng($file['tmp_name']),
            'image/webp' => imagecreatefromwebp($file['tmp_name']),
            default => null,
        };
        if ($src === null) {
            throw new \InvalidArgumentException('Use a JPEG, PNG, or WebP image.');
        }

        $srcW = imagesx($src);
        $srcH = imagesy($src);
        $cropSize = min($srcW, $srcH);
        $srcX = (int) (($srcW - $cropSize) / 2);
        $srcY = (int) (($srcH - $cropSize) / 2);

        $dst = imagecreatetruecolor(self::SIZE, self::SIZE);
        imagecopyresampled($dst, $src, 0, 0, $srcX, $srcY, self::SIZE, self::SIZE, $cropSize, $cropSize);

        $filename = $participantId . '_' . bin2hex(random_bytes(4)) . '.jpg';
        imagejpeg($dst, self::storageDir() . '/' . $filename, 85);

        imagedestroy($src);
        imagedestroy($dst);

        return $filename;
    }

    public static function path(string $filename): string
    {
        return self::storageDir() . '/' . $filename;
    }
}
