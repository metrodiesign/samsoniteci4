<?php

namespace App\Presentation;

use CodeIgniter\Session\Session;

/**
 * Renders a dedicated pinned CI3 template behind one CI4 integration seam.
 *
 * The template remains the presentation authority. This adapter supplies only
 * CI3 framework conveniences, escaped view-model values and CI4 CSRF fields.
 */
final class LegacyViewRenderer
{
    public readonly LegacySessionAdapter $session;
    public readonly LegacyLoaderAdapter $load;
    public readonly LegacyPaginationAdapter $pagination;
    public readonly LegacyRequestOrderAdapter $request_order_model;
    public readonly LegacyUserModelAdapter $user_model;

    /** @param array<string, string> $validationErrors @param array<string, string> $oldValues @param array<string, string> $statusUpdates */
    public function __construct(
        ?Session $session = null,
        string $pagination = '',
        private array $validationErrors = [],
        /** @var array<string, string> */
        private array $oldValues = [],
        /** @var array<string, string> */
        array $statusUpdates = [],
    ) {
        $this->session = new LegacySessionAdapter($session ?? service('session'));
        $this->load = new LegacyLoaderAdapter();
        $this->pagination = new LegacyPaginationAdapter($pagination);
        $this->request_order_model = new LegacyRequestOrderAdapter($statusUpdates);
        $this->user_model = new LegacyUserModelAdapter(db_connect(), $this->session);
    }

    /** @param array<string, mixed> $variables @param array<string, string> $hiddenFields */
    public function render(string $template, array $variables = [], array $hiddenFields = []): string
    {
        if (preg_match('/\A[a-zA-Z0-9_\/-]+\z/D', $template) !== 1) {
            throw new \InvalidArgumentException('Invalid legacy view name');
        }
        $path = APPPATH . 'Views/ci3/' . $template . '.php';
        if (! is_file($path)) {
            throw new \InvalidArgumentException('Unknown legacy view: ' . $template);
        }

        helper(['form', 'url']);
        extract($variables, EXTR_SKIP);
        ob_start();
        try {
            $source = file_get_contents($path);
            if ($source === false) {
                throw new \RuntimeException('Cannot read legacy view: ' . $template);
            }
            $source = str_replace(
                [
                    'https://maxcdn.bootstrapcdn.com/font-awesome/4.3.0/css/font-awesome.min.css',
                    'https://oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js',
                    'https://oss.maxcdn.com/respond/1.4.2/respond.min.js',
                    'http://code.jquery.com/ui/1.11.2/jquery-ui.min.js',
                    '//cdn.datatables.net/1.10.16/css/jquery.dataTables.min.css',
                    '//cdn.datatables.net/fixedcolumns/3.2.4/css/fixedColumns.dataTables.min.css',
                    '//cdn.datatables.net/1.10.16/js/jquery.dataTables.min.js',
                    '//cdn.datatables.net/fixedcolumns/3.2.4/js/dataTables.fixedColumns.min.js',
                    'validation_errors(',
                    'set_value(',
                    '$this->request_order_model->getBranchName($branch_id)',
                    'checkdate($pBB, $pCC, $pAA)',
                ],
                [
                    base_url('assets/font-awesome/4.3.0/css/font-awesome.min.css'),
                    base_url('assets/html5shiv/3.7.2/html5shiv.min.js'),
                    base_url('assets/respond/1.4.2/respond.min.js'),
                    base_url('assets/js/jquerydatepicker/jquery-ui.min.js'),
                    base_url('assets/datatables/1.10.16/css/jquery.dataTables.min.css'),
                    base_url('assets/datatables-fixedcolumns/3.2.4/css/fixedColumns.dataTables.min.css'),
                    base_url('assets/datatables/1.10.16/js/jquery.dataTables.min.js'),
                    base_url('assets/datatables-fixedcolumns/3.2.4/js/dataTables.fixedColumns.min.js'),
                    '$this->validationErrors(',
                    '$this->setValue(',
                    '$BranchName',
                    'checkdate((int) $pBB, (int) $pCC, (int) $pAA)',
                ],
                $source,
            );
            eval('?>' . $source);
            $html = (string) ob_get_clean();
        } catch (\Throwable $error) {
            ob_end_clean();
            throw $error;
        }

        return $this->withSecurityFields($html, $hiddenFields);
    }

    public function validationErrors(string $prefix = '', string $suffix = ''): string
    {
        if ($this->validationErrors === []) {
            return '';
        }

        return $prefix . implode($suffix . $prefix, array_map(
            static fn (string $error): string => esc($error),
            array_values($this->validationErrors),
        )) . $suffix;
    }

    public function setValue(string $field, mixed $default = ''): string
    {
        $value = $this->oldValues[$field] ?? $default;

        return esc(is_scalar($value) ? (string) $value : '');
    }

    /** @param list<array<string, mixed>> $rows @return list<object> */
    public static function escapedRecords(array $rows): array
    {
        return array_map(static function (array $row): object {
            $escaped = [];
            foreach ($row as $field => $value) {
                $escaped[$field] = is_string($value) ? esc($value) : $value;
            }

            return new LegacyRecord($escaped);
        }, $rows);
    }

    /** @param array<string, string> $hiddenFields */
    private function withSecurityFields(string $html, array $hiddenFields): string
    {
        if (preg_match('/<form\b[^>]*\bmethod\s*=\s*(["\']?)post\1/i', $html) !== 1) {
            return $html;
        }
        $field = csrf_field();
        foreach ($hiddenFields as $name => $value) {
            $field .= '<input type="hidden" name="' . esc($name, 'attr') . '" value="' . esc($value, 'attr') . '">';
        }
        $result = preg_replace_callback(
            '/<form\b([^>]*)>/i',
            static function (array $match) use ($field): string {
                $attributes = $match[1];
                if (preg_match('/\bmethod\s*=\s*(["\']?)post\1/i', $attributes) !== 1) {
                    return $match[0];
                }

                return $match[0] . $field;
            },
            $html,
        );

        return is_string($result) ? $result : $html;
    }
}

final class LegacyRecord
{
    /** @param array<string, mixed> $values */
    public function __construct(private array $values)
    {
    }

    public function __get(string $name): mixed
    {
        return $this->values[$name] ?? null;
    }

    public function __isset(string $name): bool
    {
        return isset($this->values[$name]);
    }
}

final class LegacySessionAdapter
{
    public function __construct(private Session $session)
    {
    }

    public function userdata(string $key): mixed
    {
        $value = $this->session->get($key);

        return is_string($value) ? esc($value) : $value;
    }

    public function flashdata(string $key): mixed
    {
        $value = $this->session->getFlashdata($key);
        if ($value === null && $key === 'error') {
            $value = $this->session->getFlashdata('login_error');
        }

        return is_string($value) ? esc($value) : $value;
    }
}

final class LegacyLoaderAdapter
{
    public function helper(string|array $helpers): void
    {
        helper($helpers);
    }
}

final class LegacyRequestOrderAdapter
{
    /** @param array<string, string> $statusUpdates */
    public function __construct(private array $statusUpdates)
    {
    }

    public function chack_status_update(string $orderId, string $telephone): string
    {
        return esc($this->statusUpdates[$orderId . "\0" . $telephone] ?? '');
    }

    public function get_orderIDShowBytel(string $telephone): string
    {
        return '';
    }
}

final class LegacyUserModelAdapter
{
    public function __construct(
        private \CodeIgniter\Database\BaseConnection $db,
        private LegacySessionAdapter $session,
    ) {
    }

    public function getbransName(mixed $branchId): string
    {
        $id = filter_var($branchId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false || ! $this->hasBranchColumns()) {
            return '';
        }
        $name = $this->db->table('branch')->select('branch_name')->where('branch_id', $id)->get()->getRow('branch_name');

        return is_string($name) ? esc($name) : '';
    }

    /** @return list<object> */
    public function getbrans(): array
    {
        if (! $this->hasBranchColumns()) {
            return [];
        }

        $rows = $this->db->table('branch')
            ->select('branch_id, branch_name')->orderBy('branch_id', 'ASC')->get()->getResultArray();
        foreach ($rows as &$row) {
            $row['branch_name'] = strtr((string) $row['branch_name'], [
                '&' => '\\u0026', '<' => '\\u003C', '>' => '\\u003E',
                '"' => '\\u0022', "'" => '\\u0027',
            ]);
        }
        unset($row);

        return array_map(static fn (array $row): object => new LegacyRecord($row), $rows);
    }

    private function hasBranchColumns(): bool
    {
        if (! $this->db->tableExists($this->db->prefixTable('branch'), false)) {
            return false;
        }
        $fields = $this->db->getFieldNames($this->db->prefixTable('branch'));

        return is_array($fields)
            && in_array('branch_id', $fields, true)
            && in_array('branch_name', $fields, true);
    }

    /** @return list<object> */
    public function getMenoGroup(mixed $groupId): array
    {
        $id = filter_var($groupId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false || ! $this->db->tableExists($this->db->prefixTable('group_menu'), false)) {
            return [];
        }

        return LegacyViewRenderer::escapedRecords($this->db->table('group_menu')
            ->select('group_type')->where('id', $id)->get()->getResultArray());
    }

    /** @return list<object> */
    public function getMeno(mixed $groupType): array
    {
        $id = filter_var($groupType, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false || ! $this->db->tableExists($this->db->prefixTable('tbl_menu'), false)) {
            return [];
        }
        $rows = $this->db->table('tbl_menu')->select('menu_name, menu_link')
            ->where('group_type', $id)->orderBy('id', 'ASC')->get()->getResultArray();
        $visible = [];
        $seen = [];
        $isBranch = $this->session->userdata('BranchID') !== null;
        foreach ($rows as $row) {
            $link = (string) ($row['menu_link'] ?? '');
            if (preg_match('/\A[a-zA-Z0-9_\/-]+\z/D', $link) !== 1 || isset($seen[$link])) {
                continue;
            }
            if ($isBranch && in_array($link, ['TrackingListing', 'TrackingcloseListing'], true)) {
                continue;
            }
            $seen[$link] = true;
            $visible[] = $row;
        }

        return LegacyViewRenderer::escapedRecords($visible);
    }

    public function getMenoGroupType(mixed $groupType): string
    {
        return $this->groupTypeValue($groupType, 'group_type_name');
    }

    public function getMenoGroupTypeIcon(mixed $groupType): string
    {
        return $this->groupTypeValue($groupType, 'icon_menu');
    }

    private function groupTypeValue(mixed $groupType, string $column): string
    {
        $id = filter_var($groupType, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false || ! $this->db->tableExists($this->db->prefixTable('group_type'), false)) {
            return '';
        }
        $value = $this->db->table('group_type')->select($column)->where('group_type_id', $id)->get()->getRow($column);

        return is_string($value) ? esc($value) : '';
    }
}

final class LegacyPaginationAdapter
{
    public function __construct(private string $links)
    {
    }

    public function create_links(): string
    {
        return $this->links;
    }
}
