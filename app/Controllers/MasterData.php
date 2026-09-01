<?php

namespace App\Controllers;

use App\Master\MasterCatalog;
use App\Master\MasterDataStore;
use App\Master\BranchTypeImageStore;
use App\Presentation\LegacyViewRenderer;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

final class MasterData extends BaseController
{
    /** @var array<string, string> */
    private const LEGACY_LISTINGS = [
        'branch' => 'branchListing', 'branchtype' => 'branchtypeListing', 'statustype' => 'statustypeListing',
        'producttype' => 'producttypeListing', 'book' => 'bookListing', 'brand' => 'brandListing',
        'condition' => 'conditionListing', 'estimateprice' => 'estimatepriceListing', 'fixed' => 'fixedListing',
        'provider' => 'providerListing',
    ];

    /** @var array<string, string> */
    private const LEGACY_ADD_ROUTES = [
        'branch' => 'BranchNew', 'branchtype' => 'add_new_branchtype', 'statustype' => 'add_new_statustype',
        'producttype' => 'add_new_producttype', 'book' => 'BookNew', 'brand' => 'add_new_brand',
        'condition' => 'add_new_condition', 'estimateprice' => 'add_new_estimateprice', 'fixed' => 'add_new_fixed',
        'provider' => 'add_new_provider',
    ];

    /** @var array<string, array{list: string, add: string, edit: string}> */
    private const LEGACY_PAGE_TITLES = [
        'branch' => ['list' => 'Tracking : branch Listing', 'add' => 'Tracking : Add New User', 'edit' => 'Tracking : Edit Branch'],
        'branchtype' => ['list' => 'Tracking : branch Listing', 'add' => 'Tracking : Add New User', 'edit' => 'Tracking : Edit Branch'],
        'statustype' => ['list' => 'Tracking : branch Listing', 'add' => 'Tracking : Add New status', 'edit' => 'Tracking : Edit Status'],
        'producttype' => ['list' => 'Tracking : branch Listing', 'add' => 'Tracking : Add New User', 'edit' => 'Tracking : Edit Branch'],
        'book' => ['list' => 'Tracking : Book Listing', 'add' => 'Tracking : Add New User', 'edit' => 'CodeInsect : Edit Book'],
        'brand' => ['list' => 'Tracking : Brand Listing', 'add' => 'Tracking : Add New Brand', 'edit' => 'Tracking : Edit Brand'],
        'condition' => ['list' => 'Tracking : condition Listing', 'add' => 'Tracking : Add New Condition', 'edit' => 'Tracking : Edit condition'],
        'estimateprice' => ['list' => 'Tracking : estimateprice Listing', 'add' => 'Tracking : Add New estimateprice', 'edit' => 'Tracking : Edit estimateprice'],
        'fixed' => ['list' => 'Tracking : fixed Listing', 'add' => 'Tracking : Add New fixed', 'edit' => 'Tracking : Edit fixed'],
        'provider' => ['list' => 'Tracking : provider Listing', 'add' => 'Tracking : Add New provider', 'edit' => 'Tracking : Edit provider'],
    ];

    /** @var array<string, string> */
    private const LEGACY_DELETE_FIELDS = [
        'branch' => 'branchid', 'branchtype' => 'branchid', 'statustype' => 'statusid',
        'producttype' => 'productstypeid', 'book' => 'bookid', 'brand' => 'brandid',
        'condition' => 'condition_id', 'estimateprice' => 'estimateprice_id', 'fixed' => 'fixed_id',
        'provider' => 'provider_id',
    ];

    public function listing(string $type): string
    {
        $definition = $this->authorizedDefinition($type);
        $rawSearch  = $this->request->getGet('search');
        $search     = is_string($rawSearch) && mb_strlen($rawSearch) <= 128 ? trim($rawSearch) : '';
        $rawPage    = $this->request->getGet('page');
        $page       = $rawPage === null ? 1 : (is_string($rawPage) && preg_match('/\A[1-9][0-9]*\z/D', $rawPage) === 1 ? (int) $rawPage : 0);
        if ($page < 1) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->renderList($type, $definition, $search, $page);
    }

    public function legacyListing(string $type, ?string $rawPage = null): string
    {
        $definition = $this->authorizedDefinition($type);
        // The pinned CI3 controllers read only POST searchText. A GET query named
        // "search" must not silently change the legacy alias result set.
        $rawSearch = $this->request->getPost('searchText');
        // CI3 preserves the submitted legacy search value verbatim. The query builder
        // still binds it safely and the renderer escapes it before output.
        $search = is_string($rawSearch) ? $rawSearch : '';
        $offset = $rawPage === null
            ? 0
            : (preg_match('/\A(?:0|[1-9][0-9]*)\z/D', $rawPage) === 1 ? (int) $rawPage : null);
        if ($offset === null) {
            throw PageNotFoundException::forPageNotFound();
        }
        // CI3 pagination links carry a row offset in segment 2 (0, 50, 100, ...).
        $page = intdiv($offset, 50) + 1;

        return $this->renderList($type, $definition, $search, $page, $offset);
    }

    public function add(string $type): string
    {
        $definition = $this->authorizedDefinition($type);

        return $this->renderForm($type, $definition, null);
    }

    public function edit(string $type, string $rawId): string
    {
        $definition = $this->authorizedDefinition($type);
        $id         = $this->positiveInteger($rawId);
        $row        = $id === null ? null : (new MasterDataStore(db_connect()))->find($type, $id);
        return $this->renderForm($type, $definition, $row ?? []);
    }

    public function legacyEditMissing(string $type): RedirectResponse
    {
        $this->authorizedDefinition($type);
        $response = redirect()->to('/' . self::LEGACY_LISTINGS[$type]);
        $response->setStatusCode(307);

        return $response;
    }

    public function create(string $type): string|RedirectResponse|ResponseInterface
    {
        $this->authorizedDefinition($type);

        return $this->save($type, null, $this->legacyInput($type), '/master/' . $type);
    }

    public function update(string $type, string $rawId): string|RedirectResponse|ResponseInterface
    {
        $this->authorizedDefinition($type);
        $id = $this->positiveInteger($rawId);
        if ($id === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->save($type, $id, $this->legacyInput($type), '/master/' . $type);
    }

    public function legacyCreate(string $type): string|RedirectResponse|ResponseInterface
    {
        $definition = $this->authorizedDefinition($type);
        $input = $this->legacyInput($type);

        return $this->save(
            $type,
            null,
            $input,
            '/' . self::LEGACY_ADD_ROUTES[$type],
            fn (): string => $this->renderForm(
                $type,
                $definition,
                null,
                $this->legacyOldValues($type, $input),
                $this->legacyValidationErrors($type, $input, 'create'),
            ),
            $this->legacySuccessMessage($type, 'create'),
            303,
        );
    }

    public function legacyUpdate(string $type): string|RedirectResponse|ResponseInterface
    {
        $definition = $this->authorizedDefinition($type);
        $input = $this->legacyInput($type);
        $id = $this->positiveInteger($input[$definition['pk']] ?? null);
        if ($id === null) {
            if (in_array($type, ['statustype', 'brand', 'condition', 'estimateprice', 'fixed', 'provider'], true)
                && ! array_key_exists($definition['pk'], $input)) {
                $response = redirect()->to('/' . self::LEGACY_LISTINGS[$type]);
                $response->setStatusCode(303);

                return $response;
            }

            return $this->response->setStatusCode(422)->setJSON(['error' => 'invalid_master']);
        }

        $row = (new MasterDataStore(db_connect()))->find($type, $id);

        return $this->save(
            $type,
            $id,
            $input,
            '/' . self::LEGACY_LISTINGS[$type],
            fn (): string => $this->renderForm(
                $type,
                $definition,
                $row ?? [],
                [],
                $this->legacyValidationErrors($type, $input, 'update'),
            ),
            $this->legacySuccessMessage($type, 'update'),
            303,
        );
    }

    public function delete(string $type, string $rawId): ResponseInterface
    {
        $this->authorizedDefinition($type);
        $id = $this->positiveInteger($rawId);
        $result = $id === null ? 'not_found' : (new MasterDataStore(db_connect()))->delete($type, $id);

        return match ($result) {
            'deleted' => $this->response->setStatusCode(204),
            'referenced' => $this->response->setStatusCode(409)->setJSON(['error' => 'master_referenced']),
            'not_found' => $this->response->setStatusCode(404)->setJSON(['error' => 'master_not_found']),
            default => $this->response->setStatusCode(503)->setJSON(['error' => 'master_unavailable']),
        };
    }

    public function legacyDelete(string $type): ResponseInterface
    {
        $this->authorizedDefinition($type);
        $rawId = $this->request->getPost(self::LEGACY_DELETE_FIELDS[$type]);
        $id = $this->positiveInteger($rawId);
        $result = $id === null ? 'not_found' : (new MasterDataStore(db_connect()))->delete($type, $id);

        $response = $result === 'referenced'
            ? $this->response->setStatusCode(409)->setJSON(['status' => false, 'error' => 'master_referenced'])
            : $this->response->setJSON(['status' => $result === 'deleted']);
        $security = service('security');

        return $response->setHeader($security->getHeaderName(), $security->getHash());
    }

    public function image(string $name): ResponseInterface
    {
        $path = WRITEPATH . 'uploads/branch-types/' . $name;
        if (preg_match('/\A[a-f0-9]{32}\.png\z/D', $name) !== 1 || ! is_file($path)) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'branch_type_image_not_found']);
        }

        return $this->response
            ->setHeader('Content-Type', 'image/png')
            ->setHeader('Cache-Control', 'public, max-age=86400, immutable')
            ->setBody((string) file_get_contents($path));
    }

    /** @return array{table: string, pk: string, label: string, listFields?: list<string>, fields: array<string, array{kind: string, max?: int, required?: bool, allowZero?: bool, preserveEmpty?: bool, trim?: bool, preserveNull?: bool, emptyIntValue?: int, fk?: string, formText?: string, listText?: string}>} */
    private function authorizedDefinition(string $type): array
    {
        // CI3 parity (business decision 2026-08-23): master data is open to every
        // authenticated role; the route-level authorized filters remain in force.
        $definition = MasterCatalog::definition($type);
        if ($definition === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $definition;
    }

    /**
     * @param array<string, mixed> $input
     * @param (\Closure(): string)|null $invalidResponse
     */
    private function save(
        string $type,
        ?int $id,
        array $input,
        string $successPath,
        ?\Closure $invalidResponse = null,
        ?string $successMessage = null,
        int $successStatus = 302,
    ): string|RedirectResponse|ResponseInterface
    {
        $db = db_connect();
        $store = new MasterDataStore($db);
        $images = new BranchTypeImageStore();
        $newImage = null;
        $oldImage = $type === 'branchtype' && $id !== null
            ? ($store->find($type, $id)['branch_type_image'] ?? null)
            : null;
        if ($type === 'branchtype') {
            try {
                $newImage = $images->store($this->request->getFile('branch_type_image'));
            } catch (\InvalidArgumentException) {
                return $this->response->setStatusCode(422)->setJSON(['error' => 'invalid_branch_type_image']);
            }
        }
        $trusted = $newImage === null ? [] : ['branch_type_image' => $newImage];
        $result = $store->save($type, $id, $input, $trusted);
        if (in_array($result, ['created', 'updated'], true)) {
            if ($newImage !== null && is_string($oldImage)) {
                $images->remove($oldImage);
            }
            if ($successMessage !== null) {
                service('session')->setFlashdata('success', $successMessage);
            }
        } elseif ($newImage !== null) {
            $images->remove($newImage);
        }

        return match ($result) {
            'created', 'updated' => redirect()->to($successPath)->setStatusCode($successStatus),
            'invalid' => $invalidResponse === null
                ? $this->response->setStatusCode(422)->setJSON(['error' => 'invalid_master'])
                : $invalidResponse(),
            'duplicate' => $this->response->setStatusCode(409)->setJSON(['error' => 'duplicate_master']),
            'not_found' => $this->response->setStatusCode(404)->setJSON(['error' => 'master_not_found']),
            default => $this->response->setStatusCode(503)->setJSON(['error' => 'master_unavailable']),
        };
    }

    /** @return array<string, mixed> */
    private function legacyInput(string $type): array
    {
        $input = $this->request->getPost();
        if ($type === 'branchtype' && array_key_exists('branch_type_name', $input)) {
            $input['branch_type_details'] = $input['branch_type_name'];
        }
        if ($type === 'provider' && isset($input['provider_details'])) {
            $input['provider_datail'] = $input['provider_details'];
        }

        return $input;
    }

    /** @param array<string, mixed> $input @return array<string, string> */
    private function legacyOldValues(string $type, array $input): array
    {
        $values = [];
        foreach ($input as $name => $value) {
            if ($type === 'statustype' && $name === 'success') {
                continue;
            }
            if (is_scalar($value)) {
                $trimLegacyValue = ($type === 'producttype' && $name === 'type_details')
                    || ($type === 'brand' && $name === 'brand_details')
                    || ($type === 'condition' && $name === 'condition_details')
                    || ($type === 'estimateprice' && $name === 'estimateprice_details')
                    || ($type === 'fixed' && $name === 'fixed_details')
                    || ($type === 'provider' && in_array($name, ['provider_name', 'provider_tel'], true))
                    || ($type === 'statustype' && in_array($name, ['description_th', 'description_en'], true));
                $values[$name] = $trimLegacyValue ? trim((string) $value) : (string) $value;
            }
        }

        return $values;
    }

    /** @param array<string, mixed> $input @return array<string, string> */
    private function legacyValidationErrors(string $type, array $input, string $operation): array
    {
        if ($type === 'statustype') {
            $errors = [];
            $thai = $input['description_th'] ?? null;
            if (! is_string($thai) || trim($thai) === '') {
                $errors['description_th'] = 'The description th field is required.';
            }
            $english = $input['description_en'] ?? null;
            if (! is_string($english) || trim($english) === '') {
                $errors['description_en'] = 'The description en field is required.';
            }

            return $errors === [] ? ['master' => 'Invalid master data.'] : $errors;
        }
        if ($type === 'producttype') {
            $details = $input['type_details'] ?? null;
            if (! is_string($details) || trim($details) === '') {
                return ['type_details' => $operation === 'update'
                    ? 'The Product type Name field is required.'
                    : 'The Product type  field is required.'];
            }

            return ['master' => 'Invalid master data.'];
        }
        if ($type === 'brand') {
            $details = $input['brand_details'] ?? null;
            if (! is_string($details) || trim($details) === '') {
                return ['brand_details' => $operation === 'update'
                    ? 'The Product type Name field is required.'
                    : 'The brand  field is required.'];
            }

            return ['master' => 'Invalid master data.'];
        }
        if ($type === 'condition') {
            $details = $input['condition_details'] ?? null;
            if (! is_string($details) || trim($details) === '') {
                return ['condition_details' => $operation === 'update'
                    ? 'The condition type Name field is required.'
                    : 'The brand  field is required.'];
            }

            return ['master' => 'Invalid master data.'];
        }
        if ($type === 'estimateprice') {
            $details = $input['estimateprice_details'] ?? null;
            if (! is_string($details) || trim($details) === '') {
                return ['estimateprice_details' => $operation === 'update'
                    ? 'The estimateprice type Name field is required.'
                    : 'The brand  field is required.'];
            }

            return ['master' => 'Invalid master data.'];
        }
        if ($type === 'fixed') {
            $details = $input['fixed_details'] ?? null;
            if (! is_string($details) || trim($details) === '') {
                return ['fixed_details' => $operation === 'update'
                    ? 'The fixed Name field is required.'
                    : 'The brand  field is required.'];
            }

            return ['master' => 'Invalid master data.'];
        }
        if ($type === 'provider') {
            $errors = [];
            $name = $input['provider_name'] ?? null;
            if (! is_string($name) || trim($name) === '') {
                $errors['provider_name'] = 'The provider_name  field is required.';
            }
            $telephone = $input['provider_tel'] ?? null;
            if (! is_string($telephone) || trim($telephone) === '') {
                $errors['provider_tel'] = 'The provider tel  field is required.';
            }

            return $errors === [] ? ['master' => 'Invalid master data.'] : $errors;
        }
        if ($type === 'branchtype') {
            $name = $input['branch_type_name'] ?? null;

            return ! is_string($name) || trim($name) === ''
                ? ['branch_type_name' => 'The Branch Name field is required.']
                : ['master' => 'Invalid master data.'];
        }
        if ($type !== 'branch') {
            return ['master' => 'Invalid master data.'];
        }

        $errors = [];
        $branchType = $input['branch_type'] ?? null;
        if (! is_string($branchType) || preg_match('/\A[1-9][0-9]*\z/D', $branchType) !== 1) {
            $errors['branch_type'] = 'The Branch type field is required.';
        }
        $branchName = $input['branch_name'] ?? null;
        if (! is_string($branchName) || trim($branchName) === '') {
            $errors['branch_name'] = 'The Branch Name field is required.';
        }

        return $errors === [] ? ['master' => 'Invalid master data.'] : $errors;
    }

    private function legacySuccessMessage(string $type, string $operation): ?string
    {
        return match ($type) {
            'statustype' => $operation === 'update'
                ? 'status type updated successfully'
                : 'New status type created successfully',
            'producttype' => $operation === 'update'
                ? 'branch type updated successfully'
                : 'New Product type created successfully',
            'brand' => $operation === 'update'
                ? 'brand updated successfully'
                : 'New brand created successfully',
            'condition' => $operation === 'update'
                ? 'condition updated successfully'
                : 'New condition created successfully',
            'estimateprice' => $operation === 'update'
                ? 'estimateprice updated successfully'
                : 'New estimateprice created successfully',
            'fixed' => $operation === 'update'
                ? 'fixed updated successfully'
                : 'New fixed created successfully',
            'provider' => $operation === 'update'
                ? 'provider updated successfully'
                : 'New provider created successfully',
            default => null,
        };
    }

    /**
     * @param array{table: string, pk: string, label: string, listFields?: list<string>, fields: array<string, array{kind: string, max?: int, required?: bool, allowZero?: bool, preserveEmpty?: bool, trim?: bool, preserveNull?: bool, emptyIntValue?: int, fk?: string, formText?: string, listText?: string}>} $definition
     */
    private function renderList(
        string $type,
        array $definition,
        string $search,
        int $page,
        ?int $legacyOffset = null,
    ): string
    {
        $store = new MasterDataStore(db_connect());
        $sessionBranch = service('session')->get('BranchID');
        $branchScope = is_int($sessionBranch) && $sessionBranch > 0 ? $sessionBranch : null;
        $rows = $store->all($type, $search, $page, $branchScope, $legacyOffset);
        if ($type === 'branch') {
            $branchTypes = [];
            foreach ($store->options('branchtype') as $option) {
                $branchTypes[(string) $option['value']] = (string) $option['label'];
            }
            foreach ($rows as &$row) {
                $row['branch_type_details'] = $branchTypes[(string) ($row['branch_type'] ?? '')] ?? '';
            }
            unset($row);
        } elseif ($type === 'branchtype') {
            foreach ($rows as &$row) {
                $row['branch_type_image'] = $this->branchTypeImageViewValue($row['branch_type_image'] ?? null);
            }
            unset($row);
        } elseif ($type === 'book') {
            $branches = [];
            foreach ($store->options('branch') as $option) {
                $branches[(string) $option['value']] = (string) $option['label'];
            }
            foreach ($rows as &$row) {
                $row['branch_name'] = $branches[(string) ($row['branch_id'] ?? '')] ?? '';
            }
            unset($row);
        }
        $records = LegacyViewRenderer::escapedRecords($rows);
        $recordVariables = [
            'branch' => 'branchRecords', 'branchtype' => 'branchRecords', 'statustype' => 'statusRecords',
            'producttype' => 'productstypeRecords', 'book' => 'bookRecords', 'brand' => 'brandRecords',
            'condition' => 'conditionRecords', 'estimateprice' => 'estimatepriceRecords',
            'fixed' => 'fixedRecords', 'provider' => 'providerRecords',
        ];
        $template = $type === 'book' ? 'master/books' : 'master/' . $type;
        $paginationOffset = $legacyOffset ?? (($page - 1) * 50);
        $pagination = $this->legacyPagination(
            $type,
            $paginationOffset,
            $store->count($type, $search, $branchScope),
        );
        $content = (new LegacyViewRenderer(null, $pagination))->render($template, [
            $recordVariables[$type] => $records,
            'searchText' => esc($search),
            'BranchID' => service('session')->get('BranchID'),
        ]);

        return $this->layout(self::LEGACY_PAGE_TITLES[$type]['list'], $content, ['contentOwnsWrapper' => true]);
    }

    /**
     * @param array{table: string, pk: string, label: string, listFields?: list<string>, fields: array<string, array{kind: string, max?: int, required?: bool, allowZero?: bool, preserveEmpty?: bool, trim?: bool, preserveNull?: bool, emptyIntValue?: int, fk?: string, formText?: string, listText?: string}>} $definition
     * @param array<string, mixed>|null $row
     * @param array<string, string> $oldValues
     * @param array<string, string> $validationErrors
     */
    private function renderForm(
        string $type,
        array $definition,
        ?array $row,
        array $oldValues = [],
        array $validationErrors = [],
    ): string
    {
        if ($type === 'branchtype' && is_array($row) && $row !== []) {
            $row['branch_type_image'] = $this->branchTypeImageViewValue($row['branch_type_image'] ?? null);
        }
        $store     = new MasterDataStore(db_connect());
        $fkOptions = [];
        foreach ($definition['fields'] as $field => $rule) {
            if (isset($rule['fk'])) {
                $fkOptions[$field] = $store->options($rule['fk']);
            }
        }

        $templateType = $type === 'book' ? 'book' : $type;
        $template = 'master/' . ($row === null ? 'add_' : 'edit_') . $templateType;
        $variables = [];
        if (isset($fkOptions['branch_type'])) {
            $variables['branchtypes'] = LegacyViewRenderer::escapedRecords(array_map(
                static fn (array $option): array => [
                    'branch_type_id' => $option['value'], 'branch_type_details' => $option['label'],
                ],
                $fkOptions['branch_type'],
            ));
        }
        if (isset($fkOptions['branch_id'])) {
            $variables['branch_list'] = LegacyViewRenderer::escapedRecords(array_map(
                static fn (array $option): array => [
                    'branch_id' => $option['value'], 'branch_name' => $option['label'],
                ],
                $fkOptions['branch_id'],
            ));
        }
        if ($row !== null) {
            $infoVariables = [
                'branch' => 'BranchInfo', 'branchtype' => 'BranchInfo', 'statustype' => 'SatusInfo',
                'producttype' => 'TypeInfo', 'book' => 'bookInfo', 'brand' => 'BrandInfo',
                'condition' => 'ConditionInfo', 'estimateprice' => 'EstimatepriceInfo',
                'fixed' => 'FixedInfo', 'provider' => 'ProviderInfo',
            ];
            if ($row === [] && $type === 'statustype') {
                $variables[$infoVariables[$type]] = LegacyViewRenderer::escapedRecords([[
                    'status_id' => '', 'description_th' => '', 'description_en' => '', 'success' => '',
                ]]);
            } else {
                $variables[$infoVariables[$type]] = $row === []
                    ? []
                    : LegacyViewRenderer::escapedRecords([$row]);
            }
        }
        $content = (new LegacyViewRenderer(
            validationErrors: $validationErrors,
            oldValues: $oldValues,
        ))->render($template, $variables);

        return $this->layout(
            self::LEGACY_PAGE_TITLES[$type][$row === null ? 'add' : 'edit'],
            $content,
            ['contentOwnsWrapper' => true],
        );
    }

    private function branchTypeImageViewValue(mixed $value): mixed
    {
        if (! is_string($value) || preg_match('/\A[a-f0-9]{32}\.png\z/D', $value) !== 1) {
            return $value;
        }

        return 'branch-type-image/' . $value;
    }

    private function legacyPagination(string $type, int $offset, int $total): string
    {
        $pages = (int) ceil($total / 50);
        if ($pages <= 1) {
            return '';
        }
        // Reproduce the paginator bases configured by the pinned CI3 controllers,
        // including Book::bookListing's historical userListing alias.
        $base = match ($type) {
            'branch', 'branchtype', 'statustype', 'producttype' => 'branchListing',
            'book' => 'userListing',
            default => self::LEGACY_LISTINGS[$type],
        };
        $firstUrl = base_url($base . '/');
        $currentOffset = $offset > $total ? ($pages - 1) * 50 : $offset;
        $currentPage = intdiv($currentOffset, 50) + 1;
        $numberLinks = 5;
        $start = ($currentPage - $numberLinks) > 0 ? $currentPage - ($numberLinks - 1) : 1;
        $end = ($currentPage + $numberLinks) < $pages ? $currentPage + $numberLinks : $pages;
        $links = '<nav><ul class="pagination">';

        if ($currentPage > $numberLinks + 1) {
            $links .= '<li class="arrow"><a href="' . $firstUrl
                . '" data-ci-pagination-page="1" rel="start">First</a></li>';
        }
        if ($currentPage !== 1) {
            $previousOffset = $currentOffset - 50;
            $previousUrl = $previousOffset === 0 ? $firstUrl : $firstUrl . $previousOffset;
            $links .= '<li class="arrow"><a href="' . $previousUrl
                . '" data-ci-pagination-page="' . ($currentPage - 1)
                . '" rel="prev">Previous</a></li>';
        }
        for ($number = max(1, $start - 1); $number <= $end; $number++) {
            if ($number === $currentPage) {
                $links .= '<li class="active"><a href="#">' . $number . '</a></li>';
                continue;
            }
            $pageOffset = ($number - 1) * 50;
            $url = $pageOffset === 0 ? $firstUrl : $firstUrl . $pageOffset;
            $relation = $pageOffset === 0 ? ' rel="start"' : '';
            $links .= '<li><a href="' . $url . '" data-ci-pagination-page="' . $number . '"'
                . $relation . '>' . $number . '</a></li>';
        }
        if ($currentPage < $pages) {
            $nextOffset = $currentPage * 50;
            $links .= '<li class="arrow"><a href="' . $firstUrl . $nextOffset
                . '" data-ci-pagination-page="' . ($currentPage + 1)
                . '" rel="next">Next</a></li>';
        }
        if ($currentPage + $numberLinks < $pages) {
            $lastOffset = ($pages - 1) * 50;
            $links .= '<li class="arrow"><a href="' . $firstUrl . $lastOffset
                . '" data-ci-pagination-page="' . $pages . '">Last</a></li>';
        }

        return $links . '</ul></nav>';
    }

    private function positiveInteger(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }
        if (! is_string($value) || preg_match('/^[1-9][0-9]*$/D', $value) !== 1) {
            return null;
        }
        $integer = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $integer === false ? null : (int) $integer;
    }
}
