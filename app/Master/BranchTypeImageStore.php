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
        if ($file->getError() !== UPLOAD_ERR_OK || $size < 1 || $size > self::MAX_BYTES
            || $mime !== 'image/png' || $info === false || ($info[2] ?? null) !== IMAGETYPE_PNG
            || $info[0] < 1 || $info[0] > 4096 || $info[1] < 1 || $info[1] > 4096) {
            throw new InvalidArgumentException('Invalid branch-type PNG');
        }
        if (! is_dir($this->directory) && ! mkdir($this->directory, 0750, true) && ! is_dir($this->directory)) {
            throw new InvalidArgumentException('Branch-type image storage unavailable');
        }

        $name = bin2hex(random_bytes(16)) . '.png';
        $target = $this->directory . '/' . $name;
        $source = fopen($path, 'rb');
        $destination = fopen($target, 'xb');
        if ($source === false || $destination === false || stream_copy_to_stream($source, $destination) !== $size) {
            is_resource($source) && fclose($source);
            is_resource($destination) && fclose($destination);
            @unlink($target);
            throw new InvalidArgumentException('Branch-type image storage unavailable');
        }
        fclose($source);
        fclose($destination);
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
