<?php

namespace App\Controllers;

use App\Authorization\AuthorizationPolicy;
use App\Orders\OrderStore;
use App\Orders\OrderCreationWorkflow;
use App\Orders\OrderImageStore;
use App\Orders\OrderTransitionWorkflow;
use App\Presentation\LegacyViewRenderer;
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

    public function listing(?string $fixedStatus = null, ?string $legacyOffset = null): string|ResponseInterface
    {
        $rawStatus = $fixedStatus ?? $this->request->getGet('status');
        $legacyCompletedContract = $fixedStatus === '7';
        $rawPage = $legacyOffset === null && ! $legacyCompletedContract ? $this->request->getGet('page') : null;
        $isPost = $this->request->getMethod() === 'POST';
        if ($legacyCompletedContract) {
            $rawSearch = $isPost ? $this->request->getPost('searchText') : null;
            $rawSdate = $isPost ? $this->request->getPost('sdate') : null;
            $rawEdate = null;
        } else {
            $rawSearch = $isPost ? $this->request->getPost('searchText') : $this->request->getGet('search');
            $rawSdate = $isPost ? $this->request->getPost('sdate') : $this->request->getGet('sdate');
            $rawEdate = $isPost ? $this->request->getPost('edate') : $this->request->getGet('edate');
        }
        $status = is_string($rawStatus) && preg_match('/\A[1-8]\z/D', $rawStatus) === 1 ? (int) $rawStatus : null;
        if ($legacyOffset !== null) {
            $offset = preg_match('/\A(?:0|[1-9][0-9]*)\z/D', $legacyOffset) === 1 ? (int) $legacyOffset : -1;
            $page = $offset >= 0 ? intdiv($offset, 50) + 1 : 0;
        } else {
            $page = $rawPage === null ? 1 : (is_string($rawPage) && preg_match('/\A[1-9][0-9]*\z/D', $rawPage) === 1 ? (int) $rawPage : 0);
            $offset = ($page - 1) * 50;
        }
        $search = $legacyCompletedContract
            ? (is_string($rawSearch) ? $rawSearch : '')
            : (is_string($rawSearch) && mb_strlen($rawSearch) <= 128 ? trim($rawSearch) : '');
        $sdate = $legacyCompletedContract
            ? (is_string($rawSdate) && ! empty($rawSdate) ? $rawSdate : '')
            : (is_string($rawSdate) ? $rawSdate : '');
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
        $rows = $store->listing($status, $branchId, $search, $offset, $sdate, $edate, $legacyCompletedContract);
        $statusUpdates = $store->latestStatusUpdates(array_map(
            static fn (array $row): array => ['orderID' => (string) $row['orderID'], 'customerTel' => (string) $row['customerTel']],
            $rows,
        ));

        $profile = self::PROFILES[$status] ?? null;
        $path = strtolower(trim($this->request->getUri()->getPath(), '/'));
        $template = match ($status) {
            1 => $path === 'sendorderlisting' ? 'tracking/send_order' : 'tracking/order',
            2 => 'tracking/tracking',
            3 => 'tracking/trackingrepair',
            4 => 'tracking/trackingreturn',
            5 => 'tracking/trackingclose',
            7 => 'tracking/tracking_completed',
            default => null,
        };
        if ($template === null) {
            return $this->layout('Orders — status ' . $status, view('orders', [
                'rows' => $rows, 'status' => $status, 'page' => $page, 'search' => $search,
                'sdate' => $sdate, 'edate' => $edate, 'profile' => null,
                'statusUpdates' => $statusUpdates, 'canWrite' => false, 'providers' => [],
            ]));
        }
        if ($profile === null) {
            throw PageNotFoundException::forPageNotFound();
        }
        $statuses = $db->table('statusaction')
            ->select('status_id, status_name')
            ->orderBy('status_id', 'ASC')
            ->get()
            ->getResultArray();
        $providers = $db->table('provider')
            ->select('provider_id, provider_name')
            ->orderBy('provider_id', 'ASC')
            ->get()
            ->getResultArray();
        $paginationBase = match ($status) {
            1 => $path === 'sendorderlisting' ? 'sendorderListing' : 'ordersListing',
            2 => 'TrackingListing', 3 => 'TrackingcloseListing', 4 => 'TrackingreturnListing',
            5 => 'TrackingcompleteListing', 7 => 'TrackingCompletedListing',
        };
        $pagination = $this->legacyListingPagination(
            $paginationBase,
            $offset,
            $store->listingCount($status, $branchId, $search, $sdate, $edate, $legacyCompletedContract),
        );
        $content = (new LegacyViewRenderer(pagination: $pagination, statusUpdates: $statusUpdates))->render($template, [
            'OrdersRecords' => LegacyViewRenderer::escapedRecords($rows),
            'Status' => LegacyViewRenderer::escapedRecords($statuses),
            'Providers' => LegacyViewRenderer::escapedRecords($providers),
            'searchText' => esc($search), 'sdate' => esc($sdate), 'edate' => esc($edate),
            'page' => $offset, 'BranchID' => $session->get('BranchID'),
        ]);

        $pageTitle = $status === 1 && $path === 'orderslisting'
            ? 'Tracking : branch Listing'
            : 'Tracking : Listing';

        return $this->layout($pageTitle, $content, ['contentOwnsWrapper' => true]);
    }

    private function legacyListingPagination(string $base, int $offset, int $total): string
    {
        $perPage = 50;
        $numberOfLinks = 5;
        $pages = (int) ceil($total / $perPage);
        if ($pages <= 1) {
            return '';
        }

        if ($offset > $total) {
            $offset = ($pages - 1) * $perPage;
        }
        $uriOffset = $offset;
        $currentPage = (int) floor($offset / $perPage) + 1;
        $start = ($currentPage - $numberOfLinks) > 0 ? $currentPage - ($numberOfLinks - 1) : 1;
        $end = ($currentPage + $numberOfLinks) < $pages ? $currentPage + $numberOfLinks : $pages;
        $baseUrl = rtrim(base_url($base), '/') . '/';
        $firstUrl = $baseUrl;
        $startRelationUsed = false;
        $links = '<nav><ul class="pagination">';

        if ($currentPage > ($numberOfLinks + 1)) {
            $links .= '<li class="arrow"><a href="' . $firstUrl
                . '" data-ci-pagination-page="1" rel="start">First</a></li>';
            $startRelationUsed = true;
        }
        if ($currentPage !== 1) {
            $previousOffset = $uriOffset - $perPage;
            $previousUrl = $previousOffset === 0 ? $firstUrl : $baseUrl . $previousOffset;
            $links .= '<li class="arrow"><a href="' . $previousUrl . '" data-ci-pagination-page="'
                . ($currentPage - 1) . '" rel="prev">Previous</a></li>';
        }
        for ($number = $start; $number <= $end; $number++) {
            $pageOffset = ($number - 1) * $perPage;
            if ($number === $currentPage) {
                $links .= '<li class="active"><a href="#">' . $number . '</a></li>';
            } elseif ($pageOffset === 0) {
                $relation = $startRelationUsed ? '' : ' rel="start"';
                $links .= '<li><a href="' . $firstUrl . '" data-ci-pagination-page="' . $number . '"'
                    . $relation . '>' . $number . '</a></li>';
                $startRelationUsed = true;
            } else {
                $links .= '<li><a href="' . $baseUrl . $pageOffset . '" data-ci-pagination-page="'
                    . $number . '">' . $number . '</a></li>';
            }
        }
        if ($currentPage < $pages) {
            $nextOffset = $currentPage * $perPage;
            $links .= '<li class="arrow"><a href="' . $baseUrl . $nextOffset
                . '" data-ci-pagination-page="' . ($currentPage + 1) . '" rel="next">Next</a></li>';
        }
        if (($currentPage + $numberOfLinks) < $pages) {
            $lastOffset = ($pages - 1) * $perPage;
            $links .= '<li class="arrow"><a href="' . $baseUrl . $lastOffset
                . '" data-ci-pagination-page="' . $pages . '">Last</a></li>';
        }

        return $links . '</ul></nav>';
    }

    public function newOrder(): string
    {
        $catalogues = $this->legacyOrderCatalogues($this->formMasterData());
        $session = service('session');
        $branchId = $session->get('BranchID');
        $branchShort = $branchId === null ? '' : db_connect()->table('branch')->select('default_suffix')
            ->where('branch_id', (int) $branchId)->get()->getRow('default_suffix');
        $content = (new LegacyViewRenderer())->render('tracking/add_order', [
            ...$catalogues,
            'requestDate' => date('d/m/Y'),
            'times' => bin2hex(random_bytes(16)),
            'branchshort' => is_scalar($branchShort) ? (string) $branchShort : '',
            'BranchID' => $branchId,
        ]);

        return $this->layout('Tracking : Add New Order', $content, ['contentOwnsWrapper' => true], 'order');
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
        $content = (new LegacyViewRenderer())->render('tracking/edit_order', [
            ...$this->legacyOrderCatalogues($this->formMasterData()),
            'OrdersInfo' => LegacyViewRenderer::escapedRecords([$row]),
            'times' => bin2hex(random_bytes(16)),
            'BranchID' => service('session')->get('BranchID'),
        ]);

        return $this->layout('Tracking : Edit Branch', $content, ['contentOwnsWrapper' => true], 'order');
    }

    public function print(string $rawId): string
    {
        $row = $this->accessibleOrder($rawId);

        $master = $this->formMasterData();
        $print = $this->printMasterData($row);

        return (new LegacyViewRenderer())->render('tracking/print_order', [
            ...$this->legacyOrderCatalogues($master),
            'OrdersInfo' => LegacyViewRenderer::escapedRecords([$row]),
            'BranchName' => esc($print['branchName']),
        ]);
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
            'numberID' => 'number_id',
            'customerFullname' => 'customer_name',
            'customerTel' => 'customer_tel',
            'customerTelTwo' => 'customer_tel2',
            'email' => 'customer_email',
            'detailTypeId' => 'type_id',
            'detailBrandId' => 'brand_id',
            'detailAgent' => 'detail_agent',
            'detailSKUName' => 'detail_sku_name',
            'detailDatePurchase' => 'detail_date_purchase',
            'detailNumberWaranty' => 'detail_number_waranty',
            'warantyType' => 'waranty_type',
            'detailConditionOther' => 'condition_other',
            'detailEstimatePriceOther' => 'estimateprice_other',
            'detailFixedOther' => 'fixed_other',
            'detailEquipment' => 'detail_equipment',
            'detailNote' => 'note',
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
    /** @param array<string, list<array<string, mixed>>> $catalogues @return array<string, mixed> */
    private function legacyOrderCatalogues(array $catalogues): array
    {
        return [
            'Producttype' => LegacyViewRenderer::escapedRecords($catalogues['types'] ?? []),
            'Brand' => LegacyViewRenderer::escapedRecords($catalogues['brands'] ?? []),
            'Condition' => LegacyViewRenderer::escapedRecords($catalogues['conditions'] ?? []),
            'Estimateprice' => LegacyViewRenderer::escapedRecords($catalogues['estimatePrices'] ?? []),
            'Fixed' => LegacyViewRenderer::escapedRecords($catalogues['fixedItems'] ?? []),
            'branchtypes' => LegacyViewRenderer::escapedRecords($catalogues['branchTypes'] ?? []),
            'Branchs' => LegacyViewRenderer::escapedRecords($catalogues['branches'] ?? []),
        ];
    }

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

    public function legacyEdit(): \CodeIgniter\HTTP\RedirectResponse|ResponseInterface
    {
        $requestId = $this->request->getPost('request_id');
        if (! is_string($requestId)) {
            return $this->response->setStatusCode(422)->setJSON(['error' => 'invalid_order']);
        }

        return $this->edit($requestId);
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
        $startDate = is_string($startDate) && $startDate !== '' ? $startDate : date('d/m/Y');
        $endDate = is_string($endDate) && $endDate !== '' ? $endDate : date('d/m/Y');
        $rawStatusIds = $this->request->getPost('status_id');
        $error        = null;

        try {
            $rows = $report->rows($searchText, $startDate, $endDate, $rawStatusIds, $branchId);
        } catch (InvalidArgumentException $exception) {
            $rows  = [];
            $error = $exception->getMessage();
        }

        $selected = $report->parseStatusIds($rawStatusIds);
        $content = (new LegacyViewRenderer())->render('tracking/report_tracking_test', [
            'OrdersRecords' => LegacyViewRenderer::escapedRecords($rows),
            'Status' => LegacyViewRenderer::escapedRecords($report->statuses()),
            'page' => (int) $pageNumber,
            'searchText' => esc(is_string($searchText) ? $searchText : ''),
            'selected_status_id' => $selected,
            'data_status_id' => implode(',', $selected),
            'companny_id' => $branchId ?? '',
            'sdate' => esc($startDate),
            'edate' => esc($endDate),
            'BranchID' => $branchId,
            'GroupID' => $session->get('GroupID'),
        ]);
        $html = $this->layout('Tracking : Listing', $content, ['contentOwnsWrapper' => true]);

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
