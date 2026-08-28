<?php

namespace App\Orders;

use CodeIgniter\HTTP\Files\UploadedFile;
use InvalidArgumentException;

final class OrderImageStore
{
    public const MAX_FILES = 5;

    private const MAX_BYTES = 2_097_152;

    /** @var array<string, int> finfo mime => IMAGETYPE_* the file must also report */
    private const ACCEPTED = [
        'image/png'  => IMAGETYPE_PNG,
        'image/jpeg' => IMAGETYPE_JPEG,
        'image/gif'  => IMAGETYPE_GIF,
    ];

    public function __construct(private string $directory = WRITEPATH . 'uploads/orders')
    {
    }

    public function validate(UploadedFile $file): void
    {
        imagedestroy($this->validatedImage($file));
    }

    /**
     * Validate one uploaded image, re-encode it to PNG, and store it under a random 32hex name.
     * The client extension is never trusted: finfo mime and getimagesize must agree on png/jpg/gif
     * before the bytes reach gd, and nothing is written to disk until every check passes.
     */
    public function store(UploadedFile $file): string
    {
        $image = $this->validatedImage($file);
        if (! is_dir($this->directory) && ! mkdir($this->directory, 0750, true) && ! is_dir($this->directory)) {
            imagedestroy($image);
            throw new InvalidArgumentException('Order image storage unavailable');
        }
        imagealphablending($image, false);
        imagesavealpha($image, true);
        $name   = bin2hex(random_bytes(16)) . '.png';
        $target = $this->directory . '/' . $name;
        $stored = imagepng($image, $target);
        imagedestroy($image);
        if ($stored !== true || ! is_file($target)) {
            @unlink($target);
            throw new InvalidArgumentException('Order image storage unavailable');
        }
        chmod($target, 0640);

        return $name;
    }

    private function validatedImage(UploadedFile $file): \GdImage
    {
        $path = $file->getTempName();
        $size = $file->getSize();
        if ($file->getError() !== UPLOAD_ERR_OK || $path === '' || ! is_file($path)
            || $size < 1 || $size > self::MAX_BYTES) {
            throw new InvalidArgumentException('Invalid order image');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        $info = @getimagesize($path);
        if (! is_string($mime) || ! isset(self::ACCEPTED[$mime]) || $info === false
            || $info[2] !== self::ACCEPTED[$mime]
            || $info[0] < 1 || $info[0] > 4096 || $info[1] < 1 || $info[1] > 4096) {
            throw new InvalidArgumentException('Invalid order image');
        }
        $image = match ($info[2]) {
            IMAGETYPE_PNG  => @imagecreatefrompng($path),
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_GIF  => @imagecreatefromgif($path),
        };
        if ($image === false) {
            throw new InvalidArgumentException('Invalid order image');
        }

        return $image;
    }

    /** @param list<string> $names */
    public function removeAll(array $names): void
    {
        foreach ($names as $name) {
            if (preg_match('/\A[a-f0-9]{32}\.png\z/D', $name) === 1) {
                @unlink($this->directory . '/' . $name);
            }
        }
    }
}
