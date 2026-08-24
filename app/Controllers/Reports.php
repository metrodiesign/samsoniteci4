<?php

namespace App\Controllers;

use App\Authorization\AuthorizationPolicy;
use App\Reporting\ReportMatrix;
use App\Reporting\TrackingReport;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;
use DateTimeImmutable;
use InvalidArgumentException;

final class Reports extends BaseController
{
    private const HEADINGS = [
        'ratings' => 'Ratings', 'jobs-by-day' => 'Jobs by day', 'pending' => 'Pending jobs',
        'pending-total' => 'Pending totals', 'in-progress-average' => 'In-progress average',
        'in-progress' => 'In-progress jobs',
    ];

    public function matrix(string $kind): string|ResponseInterface
    {
        if (! isset(self::HEADINGS[$kind])) {
            throw PageNotFoundException::forPageNotFound();
        }
        $branchId = $this->branchScope();
        $start = $this->input('start_date');
        $end = $this->input('end_date');
        [$start, $end] = $this->defaultRange($start, $end);
        $statusId = $kind === 'in-progress' ? $this->normalizeStatusIds($this->input('status_id')) : '';
        $error = null;
        try {
            $matrix = new ReportMatrix(db_connect());
            $rows = $kind === 'ratings'
                ? $matrix->ratings($start, $end, $branchId)
                : $matrix->matrix($kind, $start, $end, $branchId, $statusId);
        } catch (InvalidArgumentException $exception) {
            $rows = [];
            $error = $exception->getMessage();
        }
        $html = view('layout', [
            'title' => self::HEADINGS[$kind],
            'content' => view('reports/matrix', [
                'branchId' => $branchId, 'endDate' => $end, 'error' => $error,
                'heading' => self::HEADINGS[$kind], 'kind' => $kind, 'rows' => $rows,
                'startDate' => $start,
                'statusId' => $statusId, 'statuses' => $kind === 'in-progress' ? $this->statusOptions() : [],
            ]),
        ]);

        return $error === null ? $html : $this->response->setStatusCode(422)->setBody($html);
    }

    public function summary(string $page = '0', ?string $routeBranchId = null): string|ResponseInterface
    {
        if (preg_match('/\A[0-9]+\z/D', $page) !== 1) {
            throw PageNotFoundException::forPageNotFound();
        }
        $filters = [
            'searchText' => $this->input('searchText'),
            'sdate' => $this->input('sdate'),
            'edate' => $this->input('edate'),
            'status_id' => $this->input('status_id'),
            'detailBrandId' => $this->input('detailBrandId'),
            'detailTypeId' => $this->input('detailTypeId'),
        ];
        $branchId = $this->branchScope($routeBranchId);
        $error = null;
        try {
            $rows = (new ReportMatrix(db_connect()))->summary(
                $filters['searchText'], $filters['sdate'], $filters['edate'],
                $filters['status_id'], $filters['detailBrandId'], $filters['detailTypeId'], $branchId,
                100, (int) $page,
            );
        } catch (InvalidArgumentException $exception) {
            $rows = [];
            $error = $exception->getMessage();
        }
        $db = db_connect();
        $role = (int) service('session')->get('role');
        $html = view('layout', [
            'title' => 'Report summary',
            'content' => view('reports/summary', [
                'branches' => $role === 1 ? $db->table('branch')->select('branch_id, branch_name')->orderBy('branch_id')->get()->getResultArray() : [],
                'branchId' => $branchId,
                'brands' => $db->table('brand')->select('brand_id, brand_details')->orderBy('brand_id')->get()->getResultArray(),
                'error' => $error,
                'filters' => array_map(static fn (mixed $value): string => is_string($value) ? $value : '', $filters),
                'page' => (int) $page,
                'rows' => $rows,
                'statuses' => $db->table('statusaction')->select('status_id, status_name')->orderBy('status_id')->get()->getResultArray(),
                'types' => $db->table('type')->select('type_id, type_details')->orderBy('type_id')->get()->getResultArray(),
            ]),
        ]);

        return $error === null ? $html : $this->response->setStatusCode(422)->setBody($html);
    }

    public function export(string $type): ResponseInterface
    {
        if (! in_array($type, ['tracking', 'summary', 'ratings', 'in-progress'], true)) {
            throw PageNotFoundException::forPageNotFound();
        }
        $branchId = $this->branchScope();
        // Parity with CI3 report export, which raises memory_limit before pulling rows
        // (application/controllers/Order.php:446 / User.php:444 -> ini_set('memory_limit', '8048M')).
        if (ini_set('memory_limit', '8048M') === false) {
            log_message('warning', 'Report export could not raise memory_limit; continuing with existing ceiling.');
        }
        [$defaultStart, $defaultEnd] = $this->defaultRange($this->input('start_date'), $this->input('end_date'));
        try {
            $matrix = new ReportMatrix(db_connect());
            $rows = match ($type) {
                'tracking' => (new TrackingReport(db_connect()))->rows(
                    $this->input('searchText'), $this->input('sdate'), $this->input('edate'),
                    $this->input('status_id'), $branchId,
                ),
                'summary' => $matrix->summary(
                    $this->input('searchText'), $this->input('sdate'), $this->input('edate'),
                    $this->input('status_id'), $this->input('detailBrandId'), $this->input('detailTypeId'), $branchId,
                ),
                'ratings' => $matrix->ratings($defaultStart, $defaultEnd, $branchId),
                'in-progress' => $matrix->matrix('in-progress', $defaultStart, $defaultEnd, $branchId, $this->normalizeStatusIds($this->input('status_id'))),
            };
        } catch (InvalidArgumentException $exception) {
            return $this->response->setStatusCode(422)->setJSON(['error' => $exception->getMessage()]);
        }

        return $this->response
            ->setHeader('Content-Type', 'application/vnd.ms-excel; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $type . '-report.xls"')
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setBody(view('reports/export', ['rows' => $rows, 'title' => self::HEADINGS[$type] ?? ucfirst($type)]));
    }

    public function legacyExport(string $type, string ...$ignored): ResponseInterface
    {
        return $this->export($type);
    }

    /**
     * CI3 parity: when a date field is omitted, default the range to the last month
     * (start = today minus one month, end = today) before filtering.
     *
     * @return array{string, string}
     */
    private function defaultRange(mixed $start, mixed $end): array
    {
        $today = new DateTimeImmutable('today');

        return [
            is_string($start) && $start !== '' ? $start : $today->modify('-1 month')->format('d/m/Y'),
            is_string($end) && $end !== '' ? $end : $today->format('d/m/Y'),
        ];
    }

    /**
     * CI3 parity: the in-progress filter accepts status_id as either a CSV string or a
     * status_id[] array; collapse both to a CSV string before parsing (decision 6).
     */
    private function normalizeStatusIds(mixed $value): string
    {
        if (is_array($value)) {
            return implode(',', array_map(static fn (mixed $id): string => is_scalar($id) ? (string) $id : '', $value));
        }

        return is_string($value) ? $value : '';
    }

    /** @return list<array<string, int|string|null>> */
    private function statusOptions(): array
    {
        return db_connect()->table('statusaction')
            ->select('status_id, status_name_th')
            ->where('status_id >=', 1)->where('status_id <=', 5)
            ->orderBy('status_id', 'ASC')->get()->getResultArray();
    }

    private function branchScope(?string $routeBranchId = null): ?int
    {
        $session = service('session');
        $role = (int) $session->get('role');
        $sessionBranch = $session->get('BranchID');
        $sessionBranch = $sessionBranch === null ? null : (int) $sessionBranch;
        $requested = $routeBranchId ?? $this->input('branch_id');
        if ($requested !== null && $requested !== '' && $requested !== '0'
            && (! is_string($requested) || preg_match('/\A[1-9][0-9]*\z/D', $requested) !== 1)) {
            throw PageNotFoundException::forPageNotFound();
        }
        $requested = is_string($requested) && $requested !== '' && $requested !== '0' ? (int) $requested : null;
        if ($role !== 1) {
            if ($requested !== null) {
                (new AuthorizationPolicy())->assertBranchAccess($role, $sessionBranch, $requested);
            }

            return $sessionBranch;
        }

        return $requested;
    }

    private function input(string $name): mixed
    {
        return strtoupper($this->request->getMethod()) === 'POST'
            ? $this->request->getPost($name)
            : $this->request->getGet($name);
    }
}
