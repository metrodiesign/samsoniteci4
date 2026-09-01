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

    public function preview(string $kind, bool $legacyContract = false): string|ResponseInterface
    {
        $this->assertKind($kind);
        $file = $this->request->getFile('file');
        $path = $file?->getTempName() ?? '';
        $mime = is_file($path) ? (new \finfo(FILEINFO_MIME_TYPE))->file($path) : false;
        $clientExtension = $file !== null ? $file->getClientExtension() : '';
        $extension = strtolower($clientExtension);
        $allowed = match ($extension) {
            'xlsx' => ['application/zip', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            'xls' => ['application/vnd.ms-excel'],
            default => [],
        };
        if ($file === null || $file->getError() !== UPLOAD_ERR_OK || ! in_array($mime, $allowed, true)
            || ($legacyContract && ! in_array($clientExtension, ['xls', 'xlsx'], true))) {
            return $legacyContract
                ? $this->legacyPreviewError($kind)
                : $this->response->setStatusCode(422)->setJSON(['error' => 'invalid_xlsx']);
        }
        $session = service('session');
        try {
            $preview = (new ImportWorkflow(db_connect(), service('encrypter')))->preview(
                $kind,
                (int) $session->get('userId'),
                $session->get('BranchID') === null ? null : (int) $session->get('BranchID'),
                $path,
                $extension,
                $legacyContract,
            );
        } catch (InvalidArgumentException) {
            return $legacyContract
                ? $this->legacyPreviewError($kind)
                : $this->response->setStatusCode(422)->setJSON(['error' => 'invalid_import']);
        } catch (Throwable $exception) {
            log_message('error', 'Import preview unavailable: {exception}', ['exception' => $exception::class]);

            return $this->response->setStatusCode(503)->setJSON(['error' => 'import_unavailable']);
        }

        $template = [
            'status' => 'tracking/show_upload_excel',
            'price' => 'tracking/show_price_upload_excel',
            'new-order' => 'tracking/show_upload_neworder_excel',
        ][$kind];
        $legacyRows = array_map(function (array $row) use ($kind): array {
            $data = $row['data'];
            $trackId = is_scalar($data['_track_id'] ?? null) ? (string) $data['_track_id'] : '';
            $details = match ($kind) {
                'status' => $this->legacyStatusOrderDetails(
                    $trackId,
                    is_scalar($data['order_id'] ?? null) ? (string) $data['order_id'] : '',
                    is_scalar($data['telephone'] ?? null) ? (string) $data['telephone'] : '',
                ),
                'price' => $this->legacyPriceOrderDetails(
                    is_scalar($data['number_cmg'] ?? null) ? (string) $data['number_cmg'] : '',
                ),
                'new-order' => $this->legacyNewOrderDetails(
                    is_scalar($data['order_id'] ?? null) ? (string) $data['order_id'] : '',
                    is_scalar($data['telephone'] ?? null) ? (string) $data['telephone'] : '',
                ),
                default => [
                    'trackID' => $trackId, 'action_status' => 0, 'countorderID' => $trackId === '' ? 0 : 1,
                    'branch_name' => '', 'customerFullname' => '', 'requestDate' => '', 'status_name_th' => '',
                ],
            };

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
                'temp_pic' => self::legacyPreviewPrice($data['repair_price'] ?? ''),
                ...$details,
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

    /** @return array{trackID: string, action_status: int, countorderID: int, branch_name: string, customerFullname: string, requestDate: string, status_name_th: string} */
    private function legacyStatusOrderDetails(string $trackId, string $orderId, string $telephone): array
    {
        $details = [
            'trackID' => $trackId, 'action_status' => 0, 'countorderID' => 0,
            'branch_name' => '', 'customerFullname' => '', 'requestDate' => '', 'status_name_th' => '',
        ];
        if ($trackId === '' && ($orderId === '' || $telephone === '')) {
            return $details;
        }

        $db = db_connect();
        $builder = $db->table('request_order')
            ->select('trackID, customerFullname, requestDate, branchID, action_status');
        if ($trackId !== '') {
            $builder->where('trackID', $trackId);
        } else {
            $builder->where('orderIDShow', $orderId)->where('customerTel', $telephone);
        }
        $sessionBranch = service('session')->get('BranchID');
        if ($sessionBranch !== null && (int) $sessionBranch > 0) {
            $builder->where('branchID', (int) $sessionBranch);
        }
        $countBuilder = clone $builder;
        $details['countorderID'] = $countBuilder->countAllResults();
        $order = $builder->get()->getRowArray();
        if ($order === null) {
            return $details;
        }

        $details['trackID'] = is_string($order['trackID'] ?? null) ? $order['trackID'] : '';
        $details['action_status'] = is_numeric($order['action_status'] ?? null) ? (int) $order['action_status'] : 0;
        $details['customerFullname'] = is_string($order['customerFullname'] ?? null)
            ? $order['customerFullname']
            : '';
        $details['requestDate'] = is_string($order['requestDate'] ?? null) ? $order['requestDate'] : '';
        if ($db->fieldExists('branch_name', 'branch')) {
            $name = $db->table('branch')->where('branch_id', $order['branchID'] ?? 0)->get()->getRow('branch_name');
            $details['branch_name'] = is_string($name) ? $name : '';
        }
        if ($db->tableExists('statusaction') && $db->fieldExists('status_name_th', 'statusaction')) {
            $status = $db->table('statusaction')
                ->where('status_id', $order['action_status'] ?? 0)
                ->get()
                ->getRow('status_name_th');
            $details['status_name_th'] = is_string($status) ? $status : '';
        }

        return $details;
    }

    /** @return array{trackID: string, action_status: int, countorderID: int, branch_name: string, customerFullname: string, requestDate: string, status_name_th: string} */
    private function legacyPriceOrderDetails(string $numberCmg): array
    {
        $details = [
            'trackID' => '', 'action_status' => 0, 'countorderID' => 0,
            'branch_name' => '', 'customerFullname' => '', 'requestDate' => '', 'status_name_th' => '',
        ];
        if ($numberCmg === '') {
            return $details;
        }

        $db = db_connect();
        $builder = $db->table('request_order')
            ->select('trackID, customerFullname, requestDate, branchID, action_status')
            ->where('number_cmg', $numberCmg);
        $sessionBranch = service('session')->get('BranchID');
        if ($sessionBranch !== null && (int) $sessionBranch > 0) {
            $builder->where('branchID', (int) $sessionBranch);
        }
        $countBuilder = clone $builder;
        $details['countorderID'] = $countBuilder->countAllResults();
        $order = $builder->get()->getRowArray();
        if ($order === null) {
            return $details;
        }

        $details['trackID'] = is_string($order['trackID'] ?? null) ? $order['trackID'] : '';
        $details['action_status'] = is_numeric($order['action_status'] ?? null) ? (int) $order['action_status'] : 0;
        $details['customerFullname'] = is_string($order['customerFullname'] ?? null)
            ? $order['customerFullname']
            : '';
        $details['requestDate'] = is_string($order['requestDate'] ?? null) ? $order['requestDate'] : '';
        if ($db->fieldExists('branch_name', 'branch')) {
            $name = $db->table('branch')->where('branch_id', $order['branchID'] ?? 0)->get()->getRow('branch_name');
            $details['branch_name'] = is_string($name) ? $name : '';
        }
        if ($db->tableExists('statusaction') && $db->fieldExists('status_name_th', 'statusaction')) {
            $status = $db->table('statusaction')
                ->where('status_id', $order['action_status'] ?? 0)
                ->get()
                ->getRow('status_name_th');
            $details['status_name_th'] = is_string($status) ? $status : '';
        }

        return $details;
    }

    /** @return array{trackID: string, action_status: int, countorderID: int, branch_name: string, customerFullname: string, requestDate: string, status_name_th: string} */
    private function legacyNewOrderDetails(string $orderId, string $telephone): array
    {
        $duplicate = $orderId !== '' && $telephone !== ''
            && db_connect()->table('request_order')
                ->where('orderIDShow', $orderId)
                ->where('customerTel', $telephone)
                ->countAllResults() > 0;

        return [
            'trackID' => $duplicate ? 'duplicate' : '',
            'action_status' => 0,
            'countorderID' => $duplicate ? 1 : 0,
            'branch_name' => '',
            'customerFullname' => '',
            'requestDate' => '',
            'status_name_th' => '',
        ];
    }

    private static function legacyPreviewPrice(mixed $value): mixed
    {
        return is_string($value) && preg_match('/^-?[0-9]+\.0$/D', $value) === 1
            ? substr($value, 0, -2)
            : $value;
    }

    public function legacyPreview(string $kind): string|ResponseInterface
    {
        return $this->preview($kind, true);
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
        $this->assertKind($kind);
        $batchId = $this->request->getPost('batch_id');
        $count = $this->request->getPost('count_ex');
        if (! is_scalar($count) || ! is_numeric((string) $count) || (float) $count <= 0 || ! is_string($batchId)) {
            return $this->legacyConfirmRedirect($kind, false);
        }

        $session = service('session');
        try {
            $result = (new ImportWorkflow(db_connect(), service('encrypter')))->confirm(
                $kind,
                $batchId,
                (int) $session->get('userId'),
                $session->get('BranchID') === null ? null : (int) $session->get('BranchID'),
            );
        } catch (Throwable $exception) {
            log_message('error', 'Legacy import confirm unavailable: {exception}', [
                'exception' => $exception::class,
            ]);

            return $this->legacyConfirmRedirect($kind, false);
        }

        return $this->legacyConfirmRedirect($kind, in_array($result, ['confirmed', 'replayed'], true));
    }

    private function legacyConfirmRedirect(string $kind, bool $success): ResponseInterface
    {
        service('session')->setFlashdata(
            $success ? 'success' : 'error',
            $success ? 'Upload updated successfully' : 'กรุณาตรวจสอบข้อมูลอีกครั้งค่ะ',
        );

        $status = strtoupper($this->request->getMethod()) === 'GET' ? 307 : 303;
        $listing = match ($kind) {
            'price' => '/UploadexcelpriceListing',
            'new-order' => '/UploadneworderexcelListing',
            default => '/UploadexcelListing',
        };

        return redirect()->to($listing)->setStatusCode($status);
    }

    private function legacyPreviewError(string $kind): string
    {
        service('session')->setFlashdata('error', 'กรุณาตรวจสอบข้อมูลค่ะ');

        return $this->listing($kind);
    }

    private function assertKind(string $kind): void
    {
        if (! in_array($kind, ['status', 'price', 'new-order'], true)) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
    }
}
