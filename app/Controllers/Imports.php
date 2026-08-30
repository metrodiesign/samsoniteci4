<?php

namespace App\Controllers;

use App\Imports\ImportWorkflow;
use App\Presentation\LegacyViewRenderer;
use CodeIgniter\HTTP\ResponseInterface;
use InvalidArgumentException;
use Throwable;

final class Imports extends BaseController
{
    public function listing(string $kind): string
    {
        $this->assertKind($kind);

        $template = [
            'status' => 'tracking/upload_excel',
            'price' => 'tracking/upload_price_excel',
            'new-order' => 'tracking/upload_neworder_excel',
        ][$kind];
        $content = (new LegacyViewRenderer())->render($template);

        $pageTitle = [
            'status' => 'Tracking : branch Listing',
            'price' => 'Tracking : branch Listing',
            'new-order' => 'Tracking : Upload NEW REQUEST Listing',
        ][$kind];

        return $this->layout($pageTitle, $content, ['contentOwnsWrapper' => true]);
    }

    public function preview(string $kind): string|ResponseInterface
    {
        $this->assertKind($kind);
        $file = $this->request->getFile('file');
        $path = $file?->getTempName() ?? '';
        $mime = is_file($path) ? (new \finfo(FILEINFO_MIME_TYPE))->file($path) : false;
        $extension = $file !== null ? strtolower($file->getClientExtension()) : '';
        $allowed = match ($extension) {
            'xlsx' => ['application/zip', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            'xls' => ['application/vnd.ms-excel'],
            default => [],
        };
        if ($file === null || $file->getError() !== UPLOAD_ERR_OK || ! in_array($mime, $allowed, true)) {
            return $this->response->setStatusCode(422)->setJSON(['error' => 'invalid_xlsx']);
        }
        $session = service('session');
        try {
            $preview = (new ImportWorkflow(db_connect(), service('encrypter')))->preview(
                $kind,
                (int) $session->get('userId'),
                $session->get('BranchID') === null ? null : (int) $session->get('BranchID'),
                $path,
                $extension,
            );
        } catch (InvalidArgumentException) {
            return $this->response->setStatusCode(422)->setJSON(['error' => 'invalid_import']);
        } catch (Throwable $exception) {
            log_message('error', 'Import preview unavailable: {exception}', ['exception' => $exception::class]);

            return $this->response->setStatusCode(503)->setJSON(['error' => 'import_unavailable']);
        }

        $template = [
            'status' => 'tracking/show_upload_excel',
            'price' => 'tracking/show_price_upload_excel',
            'new-order' => 'tracking/show_upload_neworder_excel',
        ][$kind];
        $legacyRows = array_map(static function (array $row) use ($kind): array {
            $data = $row['data'];
            $trackId = is_scalar($data['_track_id'] ?? null) ? (string) $data['_track_id'] : '';

            return [
                'trackID' => $trackId,
                'action_status' => is_numeric($data['_action_status'] ?? null) ? (int) $data['_action_status'] : 0,
                'countorderID' => $trackId === '' ? 0 : 1,
                'temp_waranty_cmg' => $kind === 'price' ? '' : ($data['warranty'] ?? ''),
                'temp_orderIDShow' => $data['order_id'] ?? '', 'temp_Status' => $data['status'] ?? '',
                'temp_Update' => $data['updated_at'] ?? '', 'temp_recripUpdate' => $data['repair_started_at'] ?? '',
                'temp_number_cmg' => $data['number_cmg'] ?? '',
                'temp_customerFullname' => $data['customer_name'] ?? '', 'temp_customerTel' => $data['telephone'] ?? '',
                'temp_orderID' => str_replace('/', '', is_scalar($data['order_id'] ?? null) ? (string) $data['order_id'] : ''),
                'temp_pic' => $data['repair_price'] ?? '',
                'branch_name' => '', 'customerFullname' => '', 'requestDate' => '', 'status_name_th' => '',
                'preview_row' => $row['row'], 'preview_error' => $row['error'],
            ];
        }, $preview['rows']);
        $content = (new LegacyViewRenderer())->render(
            $template,
            ['sheet_data' => LegacyViewRenderer::escapedRecords($legacyRows)],
            ['batch_id' => $preview['batch_id']],
        );

        $pageTitle = $kind === 'new-order'
            ? 'Tracking : Upload New REQUEST'
            : 'Tracking : branch Listing';

        return $this->layout($pageTitle, $content, ['contentOwnsWrapper' => true]);
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

    public function download(string $name): ResponseInterface
    {
        $path = WRITEPATH . 'uploads/imports/' . $name;
        if (preg_match('/\A[a-f0-9]{64}\.(xlsx|xls)\z/D', $name) !== 1 || ! is_file($path)) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'import_file_not_found']);
        }

        return $this->response
            ->setHeader('Content-Type', str_ends_with($name, '.xls')
                ? 'application/vnd.ms-excel'
                : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $name . '"')
            ->setBody((string) file_get_contents($path));
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
