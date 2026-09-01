<?php

namespace App\Master;

use CodeIgniter\HTTP\Files\UploadedFile;
use InvalidArgumentException;

final class BranchTypeImageStore
{
    private const MAX_BYTES = 2_097_152;

    public function __construct(private string $directory = WRITEPATH . 'uploads/branch-types')
    {
    }

    public function store(?UploadedFile $file): ?string
    {
        if ($file === null || $file->getError() === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        $path = $file->getTempName();
        $size = $file->getSize();
        $info = is_file($path) ? @getimagesize($path) : false;
        $mime = is_file($path) ? (new \finfo(FILEINFO_MIME_TYPE))->file($path) : false;
        $allowedMimes = ['image/png', 'image/jpeg', 'image/gif', 'image/webp', 'image/bmp'];
        if ($file->getError() !== UPLOAD_ERR_OK || $size < 1 || $size > self::MAX_BYTES
            || ! in_array($mime, $allowedMimes, true) || $info === false
            || $info[0] < 1 || $info[0] > 4096 || $info[1] < 1 || $info[1] > 4096) {
            throw new InvalidArgumentException('Invalid branch-type image');
        }
        $bytes = file_get_contents($path);
        $image = is_string($bytes) ? @imagecreatefromstring($bytes) : false;
        if (! $image instanceof \GdImage) {
            throw new InvalidArgumentException('Invalid branch-type image');
        }
        if (! is_dir($this->directory) && ! mkdir($this->directory, 0750, true) && ! is_dir($this->directory)) {
            imagedestroy($image);
            throw new InvalidArgumentException('Branch-type image storage unavailable');
        }

        $name = bin2hex(random_bytes(16)) . '.png';
        $target = $this->directory . '/' . $name;
        $destination = fopen($target, 'xb');
        imagesavealpha($image, true);
        $written = is_resource($destination) && imagepng($image, $destination, 6);
        imagedestroy($image);
        if (is_resource($destination)) {
            fclose($destination);
        }
        if (! $written) {
            @unlink($target);
            throw new InvalidArgumentException('Branch-type image storage unavailable');
        }
        chmod($target, 0640);

        return $name;
    }

    public function remove(?string $name): void
    {
        if (is_string($name) && preg_match('/\A[a-f0-9]{32}\.png\z/D', $name) === 1) {
            @unlink($this->directory . '/' . $name);
        }
    }
}
