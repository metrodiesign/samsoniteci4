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
        $rawSearch = $this->request->getMethod() === 'POST'
            ? $this->request->getPost('searchText')
            : $this->request->getGet('search');
        $search = is_string($rawSearch) && mb_strlen($rawSearch) <= 128 ? trim($rawSearch) : '';
        $page = $rawPage === null ? 1 : $this->positiveInteger($rawPage);
        if ($page === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->renderList($type, $definition, $search, $page);
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

        return redirect()->to('/' . self::LEGACY_LISTINGS[$type]);
    }

    public function create(string $type): RedirectResponse|ResponseInterface
    {
        $this->authorizedDefinition($type);

        return $this->save($type, null, $this->legacyInput($type), '/master/' . $type);
    }

    public function update(string $type, string $rawId): RedirectResponse|ResponseInterface
    {
        $this->authorizedDefinition($type);
        $id = $this->positiveInteger($rawId);
        if ($id === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->save($type, $id, $this->legacyInput($type), '/master/' . $type);
    }

    public function legacyCreate(string $type): RedirectResponse|ResponseInterface
    {
        $this->authorizedDefinition($type);

        return $this->save($type, null, $this->legacyInput($type), '/' . self::LEGACY_LISTINGS[$type]);
    }

    public function legacyUpdate(string $type): RedirectResponse|ResponseInterface
    {
        $definition = $this->authorizedDefinition($type);
        $input = $this->legacyInput($type);
        $id = $this->positiveInteger($input[$definition['pk']] ?? null);
        if ($id === null) {
            return $this->response->setStatusCode(422)->setJSON(['error' => 'invalid_master']);
        }

        return $this->save($type, $id, $input, '/' . self::LEGACY_LISTINGS[$type]);
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

        return $this->response->setJSON(['status' => $result === 'deleted']);
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

    /** @return array{table: string, pk: string, label: string, listFields?: list<string>, fields: array<string, array{kind: string, max?: int, required?: bool, allowZero?: bool, fk?: string, formText?: string, listText?: string}>} */
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

    /** @param array<string, mixed> $input */
    private function save(string $type, ?int $id, array $input, string $successPath): RedirectResponse|ResponseInterface
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
        } elseif ($newImage !== null) {
            $images->remove($newImage);
        }

        return match ($result) {
            'created', 'updated' => redirect()->to($successPath),
            'invalid' => $this->response->setStatusCode(422)->setJSON(['error' => 'invalid_master']),
            'duplicate' => $this->response->setStatusCode(409)->setJSON(['error' => 'duplicate_master']),
            'not_found' => $this->response->setStatusCode(404)->setJSON(['error' => 'master_not_found']),
            default => $this->response->setStatusCode(503)->setJSON(['error' => 'master_unavailable']),
        };
    }

    /** @return array<string, mixed> */
    private function legacyInput(string $type): array
    {
        $input = $this->request->getPost();
        if ($type === 'provider' && isset($input['provider_details'])) {
            $input['provider_datail'] = $input['provider_details'];
        }

        return $input;
    }

    /**
     * @param array{table: string, pk: string, label: string, listFields?: list<string>, fields: array<string, array{kind: string, max?: int, required?: bool, allowZero?: bool, fk?: string, formText?: string, listText?: string}>} $definition
     */
    private function renderList(string $type, array $definition, string $search, int $page): string
    {
        $store = new MasterDataStore(db_connect());
        $rows = $store->all($type, $search, $page);
        if ($type === 'branch') {
            $branchTypes = [];
            foreach ($store->options('branchtype') as $option) {
                $branchTypes[(string) $option['value']] = (string) $option['label'];
            }
            foreach ($rows as &$row) {
                $row['branch_type_details'] = $branchTypes[(string) ($row['branch_type'] ?? '')] ?? '';
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
        $content = (new LegacyViewRenderer())->render($template, [
            $recordVariables[$type] => $records,
            'searchText' => esc($search),
            'BranchID' => service('session')->get('BranchID'),
        ]);

        return $this->layout(self::LEGACY_PAGE_TITLES[$type]['list'], $content, ['contentOwnsWrapper' => true]);
    }

    /**
     * @param array{table: string, pk: string, label: string, listFields?: list<string>, fields: array<string, array{kind: string, max?: int, required?: bool, allowZero?: bool, fk?: string, formText?: string, listText?: string}>} $definition
     * @param array<string, mixed>|null $row
     */
    private function renderForm(string $type, array $definition, ?array $row): string
    {
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
            $variables[$infoVariables[$type]] = $row === []
                ? []
                : LegacyViewRenderer::escapedRecords([$row]);
        }
        $content = (new LegacyViewRenderer())->render($template, $variables);

        return $this->layout(
            self::LEGACY_PAGE_TITLES[$type][$row === null ? 'add' : 'edit'],
            $content,
            ['contentOwnsWrapper' => true],
        );
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
