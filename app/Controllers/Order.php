<?php

namespace App\Controllers;

use App\Authorization\AuthorizationPolicy;
use App\Orders\OrderStore;
use App\Orders\OrderCreationWorkflow;
use App\Orders\OrderTransitionWorkflow;
use App\Master\BranchTypeImageStore;
use App\Reporting\TrackingReport;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;
use InvalidArgumentException;
use DomainException;
use Throwable;

final class Order extends BaseController
{
    /**
     * Listing profile per status (design §2.2). Fields beyond `title` are consumed by later
     * tasks whose file scope cannot reach this controller: `bulk_endpoint`/`statuses` (T4 bulk
     * form, view-only), `row_action` (T5 rating modal, view-only). status 1 bulk provider
     * (T7 bulk form, view-only); status 6/8 stay generic.
     *
     * @var array<int, array{title: string, bulk_endpoint: ?string, statuses: list<int>, row_action: ?string}>
     */
    public const PROFILES = [
        1 => ['title' => 'NEW ORDER', 'bulk_endpoint' => '/sendorderUpdate', 'statuses' => [], 'row_action' => null],
        2 => ['title' => 'TRANSPORTING', 'bulk_endpoint' => '/sendorderUpdateStatus', 'statuses' => [3, 4], 'row_action' => null],
        3 => ['title' => 'STATUS REPAIR', 'bulk_endpoint' => '/sendorderUpdateStatus', 'statuses' => [4], 'row_action' => null],
        4 => ['title' => 'DELIVER TO CUSTOMER', 'bulk_endpoint' => '/sendorder_deliver', 'statuses' => [5], 'row_action' => null],
        5 => ['title' => 'COMPLETE', 'bulk_endpoint' => '/sendorderUpdateStatus', 'statuses' => [7], 'row_action' => 'rate'],
        7 => ['title' => 'COMPLETED', 'bulk_endpoint' => null, 'statuses' => [], 'row_action' => null],
    ];

    public function listing(?string $fixedStatus = null): string
    {
        $rawStatus = $fixedStatus ?? $this->request->getGet('status');
        $rawPage = $this->request->getGet('page');
        $rawSearch = $this->request->getGet('search');
        $rawSdate = $this->request->getGet('sdate');
        $status = is_string($rawStatus) && preg_match('/\A[1-8]\z/D', $rawStatus) === 1 ? (int) $rawStatus : null;
        $page = $rawPage === null ? 1 : (is_string($rawPage) && preg_match('/\A[1-9][0-9]*\z/D', $rawPage) === 1 ? (int) $rawPage : 0);
        $search = is_string($rawSearch) && mb_strlen($rawSearch) <= 128 ? trim($rawSearch) : '';
        $sdate = is_string($rawSdate) ? $rawSdate : '';
        if ($status === null || $page < 1) {
            throw PageNotFoundException::forPageNotFound();
        }
        $session = service('session');
        $branchId = (int) $session->get('role') === 1 ? null : (int) $session->get('BranchID');
        $db = db_connect();
        $store = new OrderStore($db);
        $rows = $store->listing($status, $branchId, $search, $page, $sdate);
        $statusUpdates = $store->latestStatusUpdates(array_map(
            static fn (array $row): array => ['orderID' => (string) $row['orderID'], 'customerTel' => (string) $row['customerTel']],
            $rows,
        ));

        return view('layout', [
            'title' => (self::PROFILES[$status]['title'] ?? ('Orders status ' . $status)),
            'content' => view('orders', [
                'rows' => $rows,
                'status' => $status, 'page' => $page, 'search' => $search,
                'profile' => self::PROFILES[$status] ?? null,
                'statusUpdates' => $statusUpdates,
                'canWrite' => in_array((int) $session->get('role'), [1, 2], true),
                'providers' => $db->table('provider')
                    ->select('provider_id, provider_name')
                    ->orderBy('provider_id', 'ASC')
                    ->get()
                    ->getResultArray(),
            ]),
        ]);
    }

    public function newOrder(): string
    {
        return view('layout', [
            'title' => 'New repair order',
            'content' => view('order_new', ['submissionId' => bin2hex(random_bytes(16))] + $this->checkboxCatalogues()),
        ]);
    }

    public function create(): \CodeIgniter\HTTP\RedirectResponse|ResponseInterface
    {
        $files = new BranchTypeImageStore(WRITEPATH . 'uploads/orders');
        $imageName = null;
        try {
            $image = $this->request->getFile('detail_image');
            if ($image !== null && $image->getError() !== UPLOAD_ERR_NO_FILE) {
                if (strtolower($image->getClientExtension()) !== 'png') {
                    throw new InvalidArgumentException('Order image must use .png');
                }
                $imageName = $files->store($image);
            }
            $session = service('session');
            $trackId = (new OrderCreationWorkflow(db_connect(), service('encrypter')))->create(
                (int) $session->get('userId'),
                (int) $session->get('role'),
                $session->get('BranchID') === null ? null : (int) $session->get('BranchID'),
                $this->request->getPost(),
                $imageName,
            );

            return redirect()->to('/orders/new?created=' . rawurlencode($trackId));
        } catch (DomainException) {
            $files->remove($imageName);

            return $this->response->setStatusCode(409)->setJSON(['error' => 'duplicate_order']);
        } catch (InvalidArgumentException) {
            $files->remove($imageName);

            return $this->response->setStatusCode(422)->setJSON(['error' => 'invalid_order']);
        } catch (Throwable $exception) {
            $files->remove($imageName);
            log_message('error', 'Order creation unavailable: {exception}', ['exception' => $exception::class]);

            return $this->response->setStatusCode(503)->setJSON(['error' => 'order_unavailable']);
        }
    }

    public function sendToProvider(): \CodeIgniter\HTTP\RedirectResponse|ResponseInterface
    {
        return $this->transition('provider', $this->request->getPost('provider_id'), '/sendorderListing');
    }

    public function updateStatus(): \CodeIgniter\HTTP\RedirectResponse|ResponseInterface
    {
        return $this->transition('status', $this->request->getPost('status_id'), '/ReportTrackingListing');
    }

    public function deliver(): \CodeIgniter\HTTP\RedirectResponse|ResponseInterface
    {
        return $this->transition('deliver', $this->request->getPost('status_id'), '/TrackingreturnListing');
    }

    private function transition(string $mode, mixed $value, string $redirect): \CodeIgniter\HTTP\RedirectResponse|ResponseInterface
    {
        $session = service('session');
        $result = (new OrderTransitionWorkflow(db_connect(), service('encrypter')))->transition(
            (int) $session->get('role'),
            $session->get('BranchID') === null ? null : (int) $session->get('BranchID'),
            $this->request->getPost('select_list_id'),
            $mode,
            $value,
        );

        return match ($result) {
            'updated' => redirect()->to($redirect),
            'invalid' => $this->response->setStatusCode(422)->setJSON(['error' => 'invalid_transition']),
            'not_found' => $this->response->setStatusCode(404)->setJSON(['error' => 'order_not_found']),
            'conflict' => $this->response->setStatusCode(409)->setJSON(['error' => 'invalid_order_state']),
            default => $this->response->setStatusCode(503)->setJSON(['error' => 'transition_unavailable']),
        };
    }

    public function editForm(string $rawId): string
    {
        $row = $this->accessibleOrder($rawId);

        return view('layout', ['title' => 'Edit order', 'content' => view('order_edit', ['row' => $row])]);
    }

    public function print(string $rawId): string
    {
        $row = $this->accessibleOrder($rawId);

        return view('order_print', ['row' => $row] + $this->printMasterData($row));
    }

    public function image(string $name): ResponseInterface
    {
        $path = WRITEPATH . 'uploads/orders/' . $name;
        if (preg_match('/\A[a-f0-9]{32}\.png\z/D', $name) !== 1 || ! is_file($path)) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'order_image_not_found']);
        }

        return $this->response
            ->setHeader('Content-Type', 'image/png')
            ->setHeader('Cache-Control', 'private, max-age=86400, immutable')
            ->setBody((string) file_get_contents($path));
    }

    /**
     * Master lookups the print view needs beyond the order row: the resolved branch/type/brand
     * names plus the full condition/estimateprice/fixed catalogues rendered as checkbox lists.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function printMasterData(array $row): array
    {
        $db = db_connect();

        return [
            'branchName' => (string) ($db->table('branch')->select('branch_name')
                ->where('branch_id', (int) ($row['branchID'] ?? 0))->get()->getRow('branch_name') ?? ''),
            'typeName' => (string) ($db->table('type')->select('type_details')
                ->where('type_id', (int) ($row['detailTypeId'] ?? 0))->get()->getRow('type_details') ?? ''),
            'brandName' => (string) ($db->table('brand')->select('brand_details')
                ->where('brand_id', (int) ($row['detailBrandId'] ?? 0))->get()->getRow('brand_details') ?? ''),
        ] + $this->checkboxCatalogues();
    }

    /**
     * The condition/estimateprice/fixed catalogues rendered as checkbox lists by both the print view
     * and the create form.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private function checkboxCatalogues(): array
    {
        $db = db_connect();

        return [
            'conditions' => $db->table('condition')->select('condition_id, condition_details')
                ->orderBy('condition_id', 'ASC')->get()->getResultArray(),
            'estimatePrices' => $db->table('estimateprice')->select('estimateprice_id, estimateprice_details')
                ->orderBy('estimateprice_id', 'ASC')->get()->getResultArray(),
            'fixedItems' => $db->table('fixed')->select('fixed_id, fixed_details')
                ->orderBy('fixed_id', 'ASC')->get()->getResultArray(),
        ];
    }

    public function edit(string $rawId): \CodeIgniter\HTTP\RedirectResponse|ResponseInterface
    {
        $id = preg_match('/\A[1-9][0-9]*\z/D', $rawId) === 1 ? (int) $rawId : 0;
        $session = service('session');
        $result = (new OrderStore(db_connect()))->edit(
            (int) $session->get('role'),
            $session->get('BranchID') === null ? null : (int) $session->get('BranchID'),
            $id,
            $this->request->getPost(),
        );

        return match ($result) {
            'updated' => redirect()->to('/orders?status=1'),
            'invalid' => $this->response->setStatusCode(422)->setJSON(['error' => 'invalid_order']),
            'not_found' => $this->response->setStatusCode(404)->setJSON(['error' => 'order_not_found']),
            default => $this->response->setStatusCode(503)->setJSON(['error' => 'order_unavailable']),
        };
    }

    public function delete(string $rawId): ResponseInterface
    {
        $id = preg_match('/\A[1-9][0-9]*\z/D', $rawId) === 1 ? (int) $rawId : 0;
        $session = service('session');
        $result = (new OrderStore(db_connect()))->softDelete(
            (int) $session->get('role'),
            $session->get('BranchID') === null ? null : (int) $session->get('BranchID'),
            $id,
        );

        return match ($result) {
            'deleted' => $this->response->setStatusCode(204),
            'not_found' => $this->response->setStatusCode(404)->setJSON(['error' => 'order_not_found']),
            'conflict' => $this->response->setStatusCode(409)->setJSON(['error' => 'order_state_conflict']),
            default => $this->response->setStatusCode(503)->setJSON(['error' => 'order_unavailable']),
        };
    }

    /** @return array<string, mixed> */
    private function accessibleOrder(string $rawId): array
    {
        $id = preg_match('/\A[1-9][0-9]*\z/D', $rawId) === 1 ? (int) $rawId : 0;
        $session = service('session');
        $row = (new OrderStore(db_connect()))->find(
            (int) $session->get('role'),
            $session->get('BranchID') === null ? null : (int) $session->get('BranchID'),
            $id,
        );
        if ($row === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $row;
    }

    public function reportTrackingListing(string $page = '0', ?string $routeBranchId = null): string|ResponseInterface
    {
        if (preg_match('/^[0-9]+$/D', $page) !== 1) {
            throw PageNotFoundException::forPageNotFound();
        }

        $pageNumber = filter_var($page, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        $branchId   = $this->routeBranchId($routeBranchId);
        if ($pageNumber === false) {
            throw PageNotFoundException::forPageNotFound();
        }

        $session       = service('session');
        $role          = (int) $session->get('role');
        $sessionBranch = $session->get('BranchID');
        $sessionBranch = $sessionBranch === null ? null : (int) $sessionBranch;
        if ($role !== 1) {
            if ($branchId !== null) {
                (new AuthorizationPolicy())->assertBranchAccess($role, $sessionBranch, $branchId);
            }
            $branchId = $sessionBranch;
        }

        $report       = new TrackingReport(db_connect());
        $searchText   = $this->request->getPost('searchText');
        $startDate    = $this->request->getPost('sdate');
        $endDate      = $this->request->getPost('edate');
        $rawStatusIds = $this->request->getPost('status_id');
        $error        = null;

        try {
            $rows = $report->rows($searchText, $startDate, $endDate, $rawStatusIds, $branchId);
        } catch (InvalidArgumentException $exception) {
            $rows  = [];
            $error = $exception->getMessage();
        }

        $content = view('reports/tracking', [
            'branchId'         => $branchId,
            'error'            => $error,
            'page'             => (int) $pageNumber,
            'rows'             => $rows,
            'searchText'       => is_string($searchText) ? $searchText : '',
            'selectedStatusIds' => $report->parseStatusIds($rawStatusIds),
            'startDate'        => is_string($startDate) ? $startDate : '',
            'endDate'          => is_string($endDate) ? $endDate : '',
            'statuses'         => $report->statuses(),
        ]);
        $html = view('layout', [
            'title'   => 'Report Tracking',
            'content' => $content,
        ]);

        return $error === null
            ? $html
            : $this->response->setStatusCode(422)->setBody($html);
    }

    private function routeBranchId(?string $branchId): ?int
    {
        if ($branchId === null) {
            return null;
        }
        if (preg_match('/^[1-9][0-9]*$/D', $branchId) !== 1) {
            throw PageNotFoundException::forPageNotFound();
        }

        $validated = filter_var($branchId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($validated === false) {
            throw PageNotFoundException::forPageNotFound();
        }

        return (int) $validated;
    }
}
