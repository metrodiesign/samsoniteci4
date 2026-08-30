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

    public function listing(): string
    {
        $this->assertAdmin();

        return $this->renderList();
    }

    public function legacyListing(): string
    {
        return $this->listing();
    }

    public function add(): string
    {
        $this->assertAdmin();

        return $this->renderForm(null);
    }

    public function legacyAdd(): string
    {
        return $this->add();
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
        $this->assertAdmin();

        return redirect()->to('/BackgroundListing');
    }

    public function legacyEdit(string $rawId): string
    {
        return $this->edit($rawId);
    }

    public function create(): RedirectResponse|ResponseInterface
    {
        $this->assertAdmin();

        return $this->save(null, '/backgrounds');
    }

    public function legacyCreate(): RedirectResponse|ResponseInterface
    {
        $this->assertAdmin();

        return $this->save(null, '/BackgroundListing');
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
        $this->assertAdmin();
        $id = $this->request->getPost('background_id');
        if (! is_string($id) || preg_match('/\A[1-9][0-9]*\z/D', $id) !== 1) {
            return $this->response->setStatusCode(422)->setJSON(['error' => 'invalid_background']);
        }

        return $this->save((int) $id, '/BackgroundListing');
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
            $files->remove(is_string($row[$field] ?? null) ? $row[$field] : null);
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

    private function renderList(): string
    {
        $content = (new LegacyViewRenderer())->render('master/background_web', [
            'BackgroundRecords' => LegacyViewRenderer::escapedRecords((new BackgroundStore(db_connect()))->all()),
        ]);

        return $this->layout('Tracking : background web', $content, ['contentOwnsWrapper' => true]);
    }

    /** @param array<string, mixed>|null $row */
    private function renderForm(?array $row): string
    {
        $variables = $row === null ? [] : [
            'BackgroundInfo' => LegacyViewRenderer::escapedRecords([$row]),
        ];
        $content = (new LegacyViewRenderer())->render(
            $row === null ? 'master/add_background' : 'master/edit_background',
            $variables,
        );

        return $this->layout(
            $row === null ? 'Tracking : Add New Background' : 'CodeInsect : Edit Background',
            $content,
            ['contentOwnsWrapper' => true],
        );
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

    private function assertAdmin(): void
    {
        if ((int) service('session')->get('role') !== 1) {
            throw PageNotFoundException::forPageNotFound();
        }
    }
}
