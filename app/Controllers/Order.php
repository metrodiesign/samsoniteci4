<?php

namespace App\Controllers;

use App\Authorization\AuthorizationPolicy;
use App\Orders\OrderStore;
use App\Orders\OrderCreationWorkflow;
use App\Orders\OrderImageStore;
use App\Orders\OrderTransitionWorkflow;
use App\Reporting\TrackingReport;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\Files\UploadedFile;
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
     * `title`, `subtitle`, `list_title`, `add_new`, `to_date` and `headers` mirror the CI3
     * queue views one for one, including CI3's inconsistent header casing between queues
     * (`TrackID` on queue 1 and 5, `trackID` on 2-4, `Track Id` on 7). Do not "tidy" them:
     * the visual parity comparison reads these strings verbatim.
     *
     * @var array<int, array{title: string, subtitle: string, list_title: string, add_new: bool, to_date: bool, headers: list<string>, bulk_endpoint: ?string, statuses: list<int>, row_action: ?string}>
     */
    public const PROFILES = [
        1 => [
            'title' => 'NEW REQUEST REPAIR', 'subtitle' => 'Add, Edit, Delete',
            'list_title' => 'Request order List', 'add_new' => true, 'to_date' => true,
            'headers' => ['Id', 'TrackID', 'OrderID', 'Fullname', 'Tel', 'Email', 'RequestDate', 'Action status'],
            'bulk_endpoint' => '/sendorderUpdate', 'statuses' => [], 'row_action' => null,
        ],
        2 => [
            'title' => 'TRANSPORTING', 'subtitle' => '',
            'list_title' => 'TRANSPORTING List', 'add_new' => false, 'to_date' => false,
            'headers' => ['Id', 'trackID', 'orderID', 'Fullname', 'Tel', 'Email', 'RequestDate', 'Action status', 'status Update'],
            'bulk_endpoint' => '/sendorderUpdateStatus', 'statuses' => [3, 4], 'row_action' => null,
        ],
        3 => [
            'title' => 'STATUS REPAIR', 'subtitle' => '',
            'list_title' => 'STATUS REPAIR List', 'add_new' => false, 'to_date' => false,
            'headers' => ['Id', 'trackID', 'orderID', 'Fullname', 'Tel', 'Email', 'RequestDate', 'Action Status', 'Status Update'],
            'bulk_endpoint' => '/sendorderUpdateStatus', 'statuses' => [4], 'row_action' => null,
        ],
        4 => [
            'title' => 'DELIVER TO CUSTOMER', 'subtitle' => '',
            'list_title' => 'DELIVER TO CUSTOMER List', 'add_new' => false, 'to_date' => false,
            'headers' => ['Id', 'trackID', 'orderID', 'Fullname', 'Tel', 'Email', 'RequestDate', 'Action Status', 'Status Update'],
            'bulk_endpoint' => '/sendorder_deliver', 'statuses' => [5], 'row_action' => null,
        ],
        5 => [
            'title' => 'COMPLETE FEEDBACK', 'subtitle' => '',
            'list_title' => '', 'add_new' => false, 'to_date' => false,
            'headers' => ['Id', 'TrackID', 'OrderID', 'Fullname', 'Tel', 'Email', 'RequestDate', 'Action Status', 'Status Update'],
            'bulk_endpoint' => '/sendorderUpdateStatus', 'statuses' => [7], 'row_action' => 'rate',
        ],
        7 => [
            'title' => 'COMPLETED JOB', 'subtitle' => '',
            'list_title' => '', 'add_new' => false, 'to_date' => false,
            'headers' => ['Id', 'Track Id', 'Order Id', 'Full Name', 'Tel', 'Email', 'Request Date', 'Completed Date', 'Action Status', 'Status Update'],
            'bulk_endpoint' => null, 'statuses' => [], 'row_action' => null,
        ],
    ];

    public function listing(?string $fixedStatus = null): string|ResponseInterface
    {
        $rawStatus = $fixedStatus ?? $this->request->getGet('status');
        $rawPage = $this->request->getGet('page');
        $rawSearch = $this->request->getGet('search');
        $rawSdate = $this->request->getGet('sdate');
        $rawEdate = $this->request->getGet('edate');
        $status = is_string($rawStatus) && preg_match('/\A[1-8]\z/D', $rawStatus) === 1 ? (int) $rawStatus : null;
        $page = $rawPage === null ? 1 : (is_string($rawPage) && preg_match('/\A[1-9][0-9]*\z/D', $rawPage) === 1 ? (int) $rawPage : 0);
        $search = is_string($rawSearch) && mb_strlen($rawSearch) <= 128 ? trim($rawSearch) : '';
        $sdate = is_string($rawSdate) ? $rawSdate : '';
        $edate = is_string($rawEdate) ? $rawEdate : '';
        if ($status === null || $page < 1) {
            throw PageNotFoundException::forPageNotFound();
        }
        $session = service('session');
        // Single choke point for the TRANSPORTING (2) / STATUS REPAIR (3) queues: every entry that
        // resolves $status here — /TrackingListing, /TrackingcloseListing and /orders?status= — is
        // denied to any branch user (BranchID not null), so the route filter cannot be side-stepped
        // with a guessable query string. The route-level `branchless` filter stays as a second layer.
        if (in_array($status, [2, 3], true) && $session->get('BranchID') !== null) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'forbidden']);
        }
        $branchId = (int) $session->get('role') === 1 ? null : (int) $session->get('BranchID');
        $db = db_connect();
        $store = new OrderStore($db);
        $rows = $store->listing($status, $branchId, $search, $page, $sdate, $edate);
        $statusUpdates = $store->latestStatusUpdates(array_map(
            static fn (array $row): array => ['orderID' => (string) $row['orderID'], 'customerTel' => (string) $row['customerTel']],
            $rows,
        ));

        $profile = self::PROFILES[$status] ?? null;

        return $this->layout((self::PROFILES[$status]['title'] ?? ('Orders — status ' . $status)), view('orders', [
            'rows' => $rows,
            'status' => $status, 'page' => $page, 'search' => $search, 'sdate' => $sdate, 'edate' => $edate,
            'profile' => $profile,
            'statusUpdates' => $statusUpdates,
            'canWrite' => in_array((int) $session->get('role'), [1, 2], true),
            'providers' => $db->table('provider')
                ->select('provider_id, provider_name')
                ->orderBy('provider_id', 'ASC')
                ->get()
                ->getResultArray(),
        ]), [
            'subtitle' => $profile['subtitle'] ?? '',
            'actions' => ($profile['add_new'] ?? false) ? $this->actionLink('/orders/new', 'Add New') : '',
        ]);
    }

    public function newOrder(): string
    {
        return $this->layout('NEW REQUEST REPAIR', view('order_new', [
            'submissionId' => bin2hex(random_bytes(16)),
            'caption' => 'Enter Request order Details',
            // CI3 Order::add() prefills the readonly field with date('d/m/Y'); the stored value
            // still comes from the server clock inside OrderCreationWorkflow.
            'requestDate' => date('d/m/Y'),
        ] + $this->formMasterData()), profile: 'order');
    }

    public function create(): \CodeIgniter\HTTP\RedirectResponse|ResponseInterface
    {
        $files = new OrderImageStore(WRITEPATH . 'uploads/orders');
        // The controller owns the stored-name list end to end (design §5.2): every failure path,
        // in Phase A or the workflow's Phase B, removes exactly these names so no orphan is left.
        $stored = [];
        try {
            $uploads = array_values(array_filter(
                $this->request->getFileMultiple('detail_image') ?? [],
                static fn (UploadedFile $file): bool => $file->getError() !== UPLOAD_ERR_NO_FILE,
            ));
            if (count($uploads) > OrderImageStore::MAX_FILES) {
                throw new InvalidArgumentException('Too many order images');
            }
            foreach ($uploads as $upload) {
                $stored[] = $files->store($upload);
            }
            $session = service('session');
            $trackId = (new OrderCreationWorkflow(db_connect(), service('encrypter')))->create(
                (int) $session->get('userId'),
                (int) $session->get('role'),
                $session->get('BranchID') === null ? null : (int) $session->get('BranchID'),
                $this->orderInput(),
                $stored,
            );

            return redirect()->to('/orders/new?created=' . rawurlencode($trackId));
        } catch (DomainException) {
            $files->removeAll($stored);

            return $this->response->setStatusCode(409)->setJSON(['error' => 'duplicate_order']);
        } catch (InvalidArgumentException) {
            $files->removeAll($stored);

            return $this->response->setStatusCode(422)->setJSON(['error' => 'invalid_order']);
        } catch (Throwable $exception) {
            $files->removeAll($stored);
            log_message('error', 'Order creation unavailable: {exception}', ['exception' => $exception::class]);

            return $this->response->setStatusCode(503)->setJSON(['error' => 'order_unavailable']);
        }
    }

    public function previewUpload(string $submissionId): ResponseInterface
    {
        $csrf = ['csrf_token' => csrf_token(), 'csrf_hash' => csrf_hash()];
        try {
            if (preg_match('/\A[a-f0-9]{32}\z/D', $submissionId) !== 1) {
                throw new InvalidArgumentException('Invalid order upload token');
            }
            $file = $this->request->getFile('upl');
            if (! $file instanceof UploadedFile) {
                throw new InvalidArgumentException('Invalid order image');
            }
            (new OrderImageStore(WRITEPATH . 'uploads/orders'))->validate($file);

            return $this->response->setJSON(['status' => 'success'] + $csrf);
        } catch (InvalidArgumentException) {
            return $this->response->setStatusCode(422)->setJSON(['status' => 'error'] + $csrf);
        } catch (Throwable $exception) {
            log_message('error', 'Order image preview validation unavailable: {exception}', ['exception' => $exception::class]);

            return $this->response->setStatusCode(503)->setJSON(['status' => 'error'] + $csrf);
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
            'forbidden' => $this->response->setStatusCode(403)->setJSON(['error' => 'forbidden']),
            'conflict' => $this->response->setStatusCode(409)->setJSON(['error' => 'invalid_order_state']),
            default => $this->response->setStatusCode(503)->setJSON(['error' => 'transition_unavailable']),
        };
    }

    public function editForm(string $rawId): string
    {
        $row = $this->accessibleOrder($rawId);

        // CI3 keeps the queue-1 title on the edit screen too; the track id already shows in the
        // REQUEST ID / TRACK ID fields.
        return $this->layout('NEW REQUEST REPAIR', view('order_edit', [
            'row' => $row,
            'submissionId' => bin2hex(random_bytes(16)),
            'caption' => 'Enter Request order Details',
        ] + $this->formMasterData()), profile: 'order');
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

    /** @return array<string, mixed> */
    private function orderInput(): array
    {
        $input = $this->request->getPost();
        foreach ([
            'bookshort' => 'book_id',
            'customerFullname' => 'customer_name',
            'customerTel' => 'customer_tel',
            'email' => 'customer_email',
            'detailTypeId' => 'type_id',
            'detailBrandId' => 'brand_id',
        ] as $legacy => $canonical) {
            if (! array_key_exists($canonical, $input) && array_key_exists($legacy, $input)) {
                $input[$canonical] = $input[$legacy];
            }
        }

        return $input;
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
     * Master lists the create and edit forms need to render their dropdowns and checkbox groups:
     * the type/brand/branch catalogues plus the condition/estimateprice/fixed checkbox catalogues.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private function formMasterData(): array
    {
        $db = db_connect();
        $session = service('session');
        $books = $db->table('book')->select('book_id, book_detail, branch_id')->where('status', 1);
        if ((int) $session->get('role') !== 1) {
            $books->where('branch_id', (int) $session->get('BranchID'));
        }

        return [
            'books' => $books->orderBy('branch_id', 'ASC')->orderBy('book_id', 'ASC')->get()->getResultArray(),
            'types' => $db->table('type')->select('type_id, type_details')
                ->orderBy('type_id', 'ASC')->get()->getResultArray(),
            'brands' => $db->table('brand')->select('brand_id, brand_details')
                ->orderBy('brand_id', 'ASC')->get()->getResultArray(),
            // branch_type and default_suffix ride along so the form can show CI3's `Branch Type`
            // and `branch short` fields. Both stay display-only: the create workflow re-derives
            // them from the chosen branch, so a tampered POST changes nothing.
            'branches' => $db->table('branch')->select('branch_id, branch_name, branch_type, default_suffix')
                ->orderBy('branch_id', 'ASC')->get()->getResultArray(),
            'branchTypes' => $db->table('branch_type')->select('branch_type_id, branch_type_details')
                ->orderBy('branch_type_id', 'ASC')->get()->getResultArray(),
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
        $files = new OrderImageStore(WRITEPATH . 'uploads/orders');
        // The controller owns only the newly stored names: every failed edit removes those files.
        // A successful replacement changes detailImage but deliberately keeps prior files on disk,
        // while no-upload and failed updates keep both the prior association and file untouched.
        $stored = [];
        try {
            $uploads = array_values(array_filter(
                $this->request->getFileMultiple('detail_image') ?? [],
                static fn (UploadedFile $file): bool => $file->getError() !== UPLOAD_ERR_NO_FILE,
            ));
            if (count($uploads) > OrderImageStore::MAX_FILES) {
                throw new InvalidArgumentException('Too many order images');
            }
            foreach ($uploads as $upload) {
                $stored[] = $files->store($upload);
            }
            $result = (new OrderStore(db_connect()))->edit(
                (int) $session->get('role'),
                $session->get('BranchID') === null ? null : (int) $session->get('BranchID'),
                $id,
                $this->orderInput(),
                $stored,
            );
            if ($result !== 'updated') {
                $files->removeAll($stored);
            }

            return match ($result) {
                'updated' => redirect()->to('/orders?status=1'),
                'invalid' => $this->response->setStatusCode(422)->setJSON(['error' => 'invalid_order']),
                'not_found' => $this->response->setStatusCode(404)->setJSON(['error' => 'order_not_found']),
                default => $this->response->setStatusCode(503)->setJSON(['error' => 'order_unavailable']),
            };
        } catch (InvalidArgumentException) {
            $files->removeAll($stored);

            return $this->response->setStatusCode(422)->setJSON(['error' => 'invalid_order']);
        } catch (Throwable $exception) {
            $files->removeAll($stored);
            log_message('error', 'Order edit unavailable: {exception}', ['exception' => $exception::class]);

            return $this->response->setStatusCode(503)->setJSON(['error' => 'order_unavailable']);
        }
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

        $response = match ($result) {
            'deleted' => $this->response->setStatusCode(204),
            'not_found' => $this->response->setStatusCode(404)->setJSON(['error' => 'order_not_found']),
            'forbidden' => $this->response->setStatusCode(403)->setJSON(['error' => 'forbidden']),
            'conflict' => $this->response->setStatusCode(409)->setJSON(['error' => 'order_state_conflict']),
            default => $this->response->setStatusCode(503)->setJSON(['error' => 'order_unavailable']),
        };
        // The listing deletes over fetch, so every outcome has to hand back a fresh token
        // or the second delete in a row is rejected on a stale one (same fix as Users).
        $security = service('security');

        return $response->setHeader($security->getHeaderName(), $security->getHash());
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
            'showCmg'          => service('session')->get('BranchID') === null,
            'startDate'        => is_string($startDate) ? $startDate : '',
            'endDate'          => is_string($endDate) ? $endDate : '',
            'statuses'         => $report->statuses(),
        ]);
        $html = $this->layout('Report Tracking', $content);

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
