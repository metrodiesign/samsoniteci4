<?php

namespace App\Controllers;

use App\Imports\ImportWorkflow;
use CodeIgniter\HTTP\ResponseInterface;
use InvalidArgumentException;
use Throwable;

final class Imports extends BaseController
{
    public function listing(string $kind): string
    {
        $this->assertKind($kind);

        return view('layout', ['title' => 'Import ' . $kind, 'content' => view('import_form', ['kind' => $kind])]);
    }

    public function preview(string $kind): string|ResponseInterface
    {
        $this->assertKind($kind);
        $file = $this->request->getFile('file');
        $path = $file?->getTempName() ?? '';
        $mime = is_file($path) ? (new \finfo(FILEINFO_MIME_TYPE))->file($path) : false;
        if ($file === null || $file->getError() !== UPLOAD_ERR_OK || strtolower($file->getClientExtension()) !== 'xlsx'
            || ! in_array($mime, ['application/zip', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'], true)) {
            return $this->response->setStatusCode(422)->setJSON(['error' => 'invalid_xlsx']);
        }
        $session = service('session');
        try {
            $preview = (new ImportWorkflow(db_connect(), service('encrypter')))->preview(
                $kind,
                (int) $session->get('userId'),
                $session->get('BranchID') === null ? null : (int) $session->get('BranchID'),
                $path,
            );
        } catch (InvalidArgumentException) {
            return $this->response->setStatusCode(422)->setJSON(['error' => 'invalid_import']);
        } catch (Throwable $exception) {
            log_message('error', 'Import preview unavailable: {exception}', ['exception' => $exception::class]);

            return $this->response->setStatusCode(503)->setJSON(['error' => 'import_unavailable']);
        }

        return view('layout', [
            'title' => 'Import preview',
            'content' => view('import_preview', ['kind' => $kind, ...$preview]),
        ]);
    }

    public function confirm(string $kind, string $batchId): ResponseInterface
    {
        $this->assertKind($kind);
        $session = service('session');
        try {
            $result = (new ImportWorkflow(db_connect(), service('encrypter')))->confirm(
                $kind,
                $batchId,
                (int) $session->get('userId'),
                $session->get('BranchID') === null ? null : (int) $session->get('BranchID'),
            );
        } catch (Throwable $exception) {
            log_message('error', 'Import confirm unavailable: {exception}', ['exception' => $exception::class]);

            return $this->response->setStatusCode(503)->setJSON(['error' => 'import_unavailable']);
        }

        return match ($result) {
            'confirmed', 'replayed' => redirect()->to('/imports/' . $kind . '?confirmed=1'),
            'not_found' => $this->response->setStatusCode(404)->setJSON(['error' => 'batch_not_found']),
            'conflict' => $this->response->setStatusCode(409)->setJSON(['error' => 'batch_conflict']),
            default => $this->response->setStatusCode(422)->setJSON(['error' => 'invalid_batch']),
        };
    }

    public function legacyConfirm(string $kind): ResponseInterface
    {
        $batchId = $this->request->getPost('batch_id');

        return $this->confirm($kind, is_string($batchId) ? $batchId : '');
    }

    private function assertKind(string $kind): void
    {
        if (! in_array($kind, ['status', 'price', 'new-order'], true)) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
    }
}
