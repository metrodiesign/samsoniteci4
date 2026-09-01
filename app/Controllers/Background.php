<?php

namespace App\Controllers;

use App\Master\BackgroundStore;
use App\Master\BranchTypeImageStore;
use App\Presentation\LegacyViewRenderer;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use InvalidArgumentException;

final class Background extends BaseController
{
    private const DIRECTORY = WRITEPATH . 'uploads/backgrounds';

    private const LEGACY_IMAGES = [
        'image_track_laptop' => 'track_laptop.png',
        'image_track_mobile' => 'track_mobile.png',
        'image_trackstatus_laptop' => 'trackstatus_laptop.png',
        'image_trackstatus_mobile' => 'trackstatus_mobile.png',
        'image_contact_laptop' => 'contact_laptop.png',
        'image_contact_mobile' => 'contact_mobile.png',
    ];

    public function listing(): string
    {
        $this->assertAdmin();

        return $this->renderList();
    }

    public function legacyListing(): string
    {
        return $this->renderList(true);
    }

    public function add(): string
    {
        $this->assertAdmin();

        return $this->renderForm(null);
    }

    public function legacyAdd(): string
    {
        return $this->renderForm(null);
    }

    public function edit(string $rawId): string
    {
        $this->assertAdmin();
        $row = (new BackgroundStore(db_connect()))->find((int) $rawId);
        if ($row === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->renderForm($row);
    }

    public function legacyEditMissing(): RedirectResponse
    {
        return $this->legacyRedirect('/BackgroundListing');
    }

    public function legacyEdit(string $rawId): string
    {
        $row = (new BackgroundStore(db_connect()))->find((int) $rawId);

        return $this->renderForm($row === null ? [] : $this->legacyViewRow($row), true);
    }

    public function create(): RedirectResponse|ResponseInterface
    {
        $this->assertAdmin();

        return $this->save(null, '/backgrounds');
    }

    public function legacyCreate(): RedirectResponse|ResponseInterface
    {
        return $this->saveLegacy(null);
    }

    public function update(string $rawId): RedirectResponse|ResponseInterface
    {
        $this->assertAdmin();
        $id = preg_match('/\A[1-9][0-9]*\z/D', $rawId) === 1 ? (int) $rawId : 0;
        if ($id < 1) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->save($id, '/backgrounds');
    }

    public function legacyUpdate(): RedirectResponse|ResponseInterface
    {
        $id = $this->request->getPost('background_id');
        if (! is_string($id) || preg_match('/\A[1-9][0-9]*\z/D', $id) !== 1) {
            return $this->response->setStatusCode(422)->setJSON(['error' => 'invalid_background']);
        }

        return $this->saveLegacy((int) $id);
    }

    public function legacyDelete(): ResponseInterface
    {
        $rawId = $this->request->getPost('Backgroundid');
        $id = is_string($rawId) && preg_match('/\A[1-9][0-9]*\z/D', $rawId) === 1 ? (int) $rawId : 0;
        $row = (new BackgroundStore(db_connect()))->delete($id);
        if ($row !== null) {
            $files = new BranchTypeImageStore(self::DIRECTORY);
            foreach (BackgroundStore::FIELDS as $field) {
                $value = is_string($row[$field] ?? null) ? $row[$field] : null;
                $files->remove($value);
                if ($value !== null && preg_match('/\A[a-f0-9]{32}\.png\z/D', $value) === 1) {
                    @unlink(self::DIRECTORY . '/' . $value . '.legacy-extension');
                }
            }
        }

        $security = service('security');

        return \Config\Services::response(null, false)
            ->setBody(json_encode(['status' => $row !== null], JSON_THROW_ON_ERROR))
            ->setHeader('Content-Type', 'text/html; charset=UTF-8')
            ->setHeader($security->getHeaderName(), $security->getHash());
    }

    public function delete(string $rawId): ResponseInterface
    {
        $this->assertAdmin();
        $id = preg_match('/\A[1-9][0-9]*\z/D', $rawId) === 1 ? (int) $rawId : 0;
        $row = (new BackgroundStore(db_connect()))->delete($id);
        if ($row === null) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'background_not_found']);
        }
        $files = new BranchTypeImageStore(self::DIRECTORY);
        foreach (BackgroundStore::FIELDS as $field) {
            $value = is_string($row[$field] ?? null) ? $row[$field] : null;
            $files->remove($value);
            if ($value !== null && preg_match('/\A[a-f0-9]{32}\.png\z/D', $value) === 1) {
                @unlink(self::DIRECTORY . '/' . $value . '.legacy-extension');
            }
        }

        return $this->response->setStatusCode(204);
    }

    public function image(string $name): ResponseInterface
    {
        $path = self::DIRECTORY . '/' . $name;
        if (preg_match('/\A[a-f0-9]{32}\.png\z/D', $name) !== 1 || ! is_file($path)) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'background_not_found']);
        }

        return $this->response
            ->setHeader('Content-Type', 'image/png')
            ->setHeader('Cache-Control', 'public, max-age=86400, immutable')
            ->setBody((string) file_get_contents($path));
    }

    public function legacyImage(string $name): ResponseInterface
    {
        $known = false;
        foreach (self::LEGACY_IMAGES as $defaultName) {
            $base = pathinfo($defaultName, PATHINFO_FILENAME);
            if (preg_match('/\A' . preg_quote($base, '/') . '\.(?:png|jpe?g|gif|webp|bmp)\z/Di', $name) === 1) {
                $known = true;
                break;
            }
        }
        if (! $known) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'background_not_found']);
        }
        $privatePath = self::DIRECTORY . '/legacy-' . $name;
        $publicPath = FCPATH . 'uploads/web/' . $name;
        $path = is_file($privatePath) ? $privatePath : $publicPath;
        if (! is_file($path)) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'background_not_found']);
        }

        $contentType = match (strtolower(pathinfo($name, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'bmp' => 'image/bmp',
            default => 'image/png',
        };

        return $this->response
            ->setHeader('Content-Type', $contentType)
            ->setHeader('Cache-Control', 'public, max-age=86400')
            ->setBody((string) file_get_contents($path));
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function legacyViewRow(array $row): array
    {
        foreach (self::LEGACY_IMAGES as $field => $path) {
            if (! is_string($row[$field] ?? null)
                || preg_match('/\A[a-f0-9]{32}\.png\z/D', $row[$field]) !== 1) {
                continue;
            }
            $extensionPath = self::DIRECTORY . '/' . $row[$field] . '.legacy-extension';
            $extension = is_file($extensionPath) ? trim((string) file_get_contents($extensionPath)) : 'png';
            if (preg_match('/\A(?:png|jpe?g|gif|webp|bmp)\z/Di', $extension) !== 1) {
                $extension = 'png';
            }
            $row[$field] = 'uploads/web/' . pathinfo($path, PATHINFO_FILENAME) . '.' . $extension;
        }

        return $row;
    }

    private function renderList(bool $legacy = false): string
    {
        $store = new BackgroundStore(db_connect());
        $rows = $legacy ? array_map($this->legacyViewRow(...), $store->legacyAll()) : $store->all();
        $content = (new LegacyViewRenderer())->render('master/background_web', [
            'BackgroundRecords' => LegacyViewRenderer::escapedRecords($rows),
        ]);

        return $this->layout('Tracking : background web', $content, ['contentOwnsWrapper' => true]);
    }

    /** @param array<string, mixed>|null $row */
    private function renderForm(?array $row, ?bool $editing = null): string
    {
        $editing ??= $row !== null;
        $variables = ! $editing ? [] : [
            'BackgroundInfo' => $row === [] ? [] : LegacyViewRenderer::escapedRecords([$row]),
        ];
        $content = (new LegacyViewRenderer())->render(
            $editing ? 'master/edit_background' : 'master/add_background',
            $variables,
        );

        return $this->layout(
            $editing ? 'CodeInsect : Edit Background' : 'Tracking : Add New Background',
            $content,
            ['contentOwnsWrapper' => true],
        );
    }

    private function saveLegacy(?int $id): RedirectResponse|ResponseInterface
    {
        $store = new BackgroundStore(db_connect());
        $fileStore = new BranchTypeImageStore(self::DIRECTORY);
        $old = $id === null ? null : $store->find($id);
        $stored = [];
        $extensions = [];
        $databaseImages = [];
        try {
            foreach (array_keys(self::LEGACY_IMAGES) as $field) {
                $file = $this->request->getFile($field);
                if ($file === null || $file->getError() === UPLOAD_ERR_NO_FILE) {
                    continue;
                }
                $extension = $file->getClientExtension();
                $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file->getTempName());
                $allowedMimes = match (strtolower($extension)) {
                    'png' => ['image/png'],
                    'jpg', 'jpeg' => ['image/jpeg'],
                    'gif' => ['image/gif'],
                    'webp' => ['image/webp'],
                    'bmp' => ['image/bmp', 'image/x-ms-bmp'],
                    default => [],
                };
                if (! is_string($mime) || ! in_array($mime, $allowedMimes, true)) {
                    throw new InvalidArgumentException('Background image extension and MIME do not match');
                }
                $name = $fileStore->store($file);
                if ($name !== null) {
                    $stored[$field] = $name;
                    $extensions[$field] = $extension;
                    $databaseImages[$field] = 'uploads/web/'
                        . pathinfo(self::LEGACY_IMAGES[$field], PATHINFO_FILENAME)
                        . '.' . $extension;
                }
            }
        } catch (InvalidArgumentException) {
            foreach ($stored as $name) {
                $fileStore->remove($name);
            }

            return $this->response->setStatusCode(422)->setJSON(['error' => 'invalid_background_image']);
        }

        $result = $store->legacySave($id, $this->request->getPost('status'), $databaseImages);
        if (in_array($result, ['created', 'updated'], true)) {
            foreach ($stored as $field => $name) {
                $this->writeLegacyAlias($field, $name, $extensions[$field] ?? 'png');
                $fileStore->remove($name);
                if (is_string($old[$field] ?? null)) {
                    $fileStore->remove($old[$field]);
                    if (preg_match('/\A[a-f0-9]{32}\.png\z/D', $old[$field]) === 1) {
                        @unlink(self::DIRECTORY . '/' . $old[$field] . '.legacy-extension');
                    }
                }
            }
        } else {
            foreach ($stored as $name) {
                $fileStore->remove($name);
            }
        }

        if ($result === 'created') {
            service('session')->setFlashdata('success', 'New Background created successfully');

            return redirect()->to('/BackgroundListing');
        }
        if ($result === 'updated') {
            service('session')->setFlashdata('success', 'Background updated successfully');

            return redirect()->to('/BackgroundListing');
        }
        if ($result === 'failed') {
            service('session')->setFlashdata(
                'error',
                $id === null ? ' Background creation failed' : 'Background updation failed',
            );

            return redirect()->to($id === null ? '/BackgroundNew' : '/BackgroundListing');
        }

        return $this->response
            ->setStatusCode($result === 'not_found' ? 404 : 422)
            ->setJSON(['error' => $result === 'not_found' ? 'background_not_found' : 'invalid_background']);
    }

    private function writeLegacyAlias(string $field, string $name, string $extension): void
    {
        $defaultName = self::LEGACY_IMAGES[$field] ?? null;
        $source = self::DIRECTORY . '/' . $name;
        if ($defaultName === null
            || preg_match('/\A(?:png|jpe?g|gif|webp|bmp)\z/Di', $extension) !== 1
            || ! is_file($source)) {
            return;
        }
        $alias = pathinfo($defaultName, PATHINFO_FILENAME) . '.' . $extension;
        $target = self::DIRECTORY . '/legacy-' . $alias;
        $temporary = $target . '.' . bin2hex(random_bytes(8));
        $image = @imagecreatefrompng($source);
        if (! $image instanceof \GdImage) {
            return;
        }
        $written = match (strtolower($extension)) {
            'jpg', 'jpeg' => imagejpeg($image, $temporary, 90),
            'gif' => imagegif($image, $temporary),
            'webp' => imagewebp($image, $temporary, 90),
            'bmp' => imagebmp($image, $temporary, true),
            default => imagepng($image, $temporary, 6),
        };
        imagedestroy($image);
        if (! $written) {
            @unlink($temporary);
            return;
        }
        chmod($temporary, 0640);
        if (! @rename($temporary, $target)) {
            @unlink($temporary);
        }
    }

    private function save(?int $id, string $successPath): RedirectResponse|ResponseInterface
    {
        $db = db_connect();
        $store = new BackgroundStore($db);
        $fileStore = new BranchTypeImageStore(self::DIRECTORY);
        $old = $id === null ? null : $store->find($id);
        $stored = [];
        try {
            foreach (BackgroundStore::FIELDS as $field) {
                $file = $this->request->getFile($field);
                if ($file === null || $file->getError() === UPLOAD_ERR_NO_FILE) {
                    continue;
                }
                if (strtolower($file->getClientExtension()) !== 'png') {
                    throw new InvalidArgumentException('Background must use .png');
                }
                $name = $fileStore->store($file);
                if ($name !== null) {
                    $stored[$field] = $name;
                }
            }
        } catch (InvalidArgumentException) {
            foreach ($stored as $name) {
                $fileStore->remove($name);
            }

            return $this->response->setStatusCode(422)->setJSON(['error' => 'invalid_background_image']);
        }

        $result = $store->save($id, $this->request->getPost('status'), $stored);
        if (in_array($result, ['created', 'updated'], true)) {
            foreach ($stored as $field => $name) {
                if (is_string($old[$field] ?? null)) {
                    $fileStore->remove($old[$field]);
                }
            }
        } else {
            foreach ($stored as $name) {
                $fileStore->remove($name);
            }
        }

        return match ($result) {
            'created', 'updated' => redirect()->to($successPath),
            'invalid' => $this->response->setStatusCode(422)->setJSON(['error' => 'invalid_background']),
            'not_found' => $this->response->setStatusCode(404)->setJSON(['error' => 'background_not_found']),
            default => $this->response->setStatusCode(503)->setJSON(['error' => 'background_unavailable']),
        };
    }

    private function legacyRedirect(string $path): RedirectResponse
    {
        $status = strtoupper($this->request->getMethod()) === 'GET' ? 307 : 303;

        return redirect()->to($path)->setStatusCode($status);
    }

    private function assertAdmin(): void
    {
        if ((int) service('session')->get('role') !== 1) {
            throw PageNotFoundException::forPageNotFound();
        }
    }
}
