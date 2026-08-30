<?php

namespace App\Controllers;

use App\Authorization\AuthorizationPolicy;
use App\Presentation\LegacyViewRenderer;
use App\Reporting\ReportMatrix;
use App\Reporting\TrackingReport;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;
use DateTimeImmutable;
use InvalidArgumentException;

final class Reports extends BaseController
{
    /**
     * Page heading, card caption and in-card section title copied from the CI3 report views:
     * `report.php`, `report_job_byday.php`, `report_job_pending.php`,
     * `report_total_job_pending.php`, `report_in_progress_average.php` and
     * `report_in_progress_job.php`. CI3 repeats "In Progress Report" as both the page title
     * and the card caption; that is the original, not a copy-paste slip.
     *
     * @var array<string, array{0: string, 1: string, 2: string}>
     */
    private const HEADINGS = [
        'ratings' => ['Rating Report', '', ''],
        'jobs-by-day' => ['KPI', 'KPI Report', 'Completed Job'],
        'pending' => ['KPI', 'KPI Report', 'Pending Job'],
        'pending-total' => ['KPI', 'KPI Report', ''],
        'in-progress-average' => ['In Progress Report', 'In Progress Report', ''],
        'in-progress' => ['In Progress Report', 'In Progress Report', ''],
    ];

    public function matrix(string $kind): string|ResponseInterface
    {
        if (! isset(self::HEADINGS[$kind])) {
            throw PageNotFoundException::forPageNotFound();
        }
        $requestedBranch = $this->reportInput($kind, 'branch_id');
        $branchId = match ($kind) {
            'pending', 'in-progress-average', 'in-progress' => $this->legacyPostedBranchScope($requestedBranch),
            default => $this->branchScope(),
        };
        $start = $this->reportInput($kind, 'start_date');
        $end = $this->reportInput($kind, 'end_date');
        [$start, $end] = $this->defaultRange($start, $end);
        $statusId = $kind === 'in-progress'
            ? $this->legacyInProgressStatus($this->reportInput($kind, 'status_id'))
            : '';
        if ($kind === 'in-progress' && $this->isInvalidLegacyInProgressStatus($statusId)) {
            return $this->legacyInProgressDatabaseError($statusId, $start, $end, $branchId);
        }
        $error = null;
        $db = db_connect();
        try {
            $matrix = new ReportMatrix($db);
            $rows = $kind === 'ratings'
                ? $matrix->ratings($start, $end, $branchId)
                : $matrix->matrix($kind, $start, $end, $branchId, $statusId);
        } catch (InvalidArgumentException $exception) {
            $rows = [];
            // CI3 renders these reports empty with HTTP 200 when their date input is malformed.
            $error = in_array($kind, ['pending', 'in-progress-average', 'in-progress'], true)
                ? null
                : $exception->getMessage();
        }
        $role = (int) service('session')->get('role');
        [$title, $caption, $sectionTitle] = self::HEADINGS[$kind];
        if ($kind === 'ratings') {
            $html = $this->ratingReport(
                $rows,
                (new ReportMatrix($db))->ratingComments($start, $end, $branchId),
                $role === 1 ? $db->table('branch')->select('branch_id, branch_name, branch_user_name')->orderBy('branch_id')->get()->getResultArray() : [],
                $branchId,
                $requestedBranch === '0' ? 0 : $branchId,
                $start,
                $end,
            );
        } else {
            $templates = [
                'jobs-by-day' => 'report_job_byday',
                'pending' => 'report_job_pending',
                'pending-total' => 'report_total_job_pending',
                'in-progress-average' => 'report_in_progress_average',
                'in-progress' => 'report_in_progress_job',
            ];
            $branches = $role === 1 || $kind === 'in-progress'
                ? $db->table('branch')->select('branch_id, branch_name, branch_user_name')->orderBy('branch_id')->get()->getResultArray()
                : [];
            $legacyRows = $rows;
            if ($kind === 'jobs-by-day') {
                $legacyRows = array_map(static fn (array $row): array => [
                    'brand_details' => $row['Brand'] ?? '', 'type_details' => $row['Product Type'] ?? '',
                    'result_a' => self::integerReportValue($row['0'] ?? 0),
                    'result_b' => self::integerReportValue($row['1-7'] ?? 0),
                    'result_c' => self::integerReportValue($row['8-30'] ?? 0),
                    'result_d' => self::integerReportValue($row['31-45'] ?? 0),
                    'result_e' => self::integerReportValue($row['> 45'] ?? 0),
                ], array_filter($rows, static fn (array $row): bool =>
                    ($row['Brand'] ?? '') !== 'TOTAL'
                    && ! str_starts_with((string) ($row['Brand'] ?? ''), 'Over all repair time')
                ));
            } elseif ($kind === 'pending') {
                $legacyRows = array_map(static fn (array $row): array => [
                    'trackID' => $row['trackID'] ?? '', 'status_name_th' => $row['Status'] ?? '',
                    'orderIDShow' => $row['เล่มที่/เลขที่'] ?? '', 'customerTel' => $row['เบอร์มือถือลูกค้า'] ?? '',
                    'date_repair' => self::legacyDate((string) ($row['วันที่ส่งซ่อม'] ?? '')),
                    'Total' => $row['Day'] ?? 0,
                ], array_filter($rows, static fn (array $row): bool => ($row['No'] ?? '') !== 'TOTAL'));
            } elseif ($kind === 'in-progress') {
                $legacyRows = array_map(static fn (array $row): array => [
                    'status_name_th' => $row['Status'] ?? '', 'trackID' => $row['Track Id'] ?? '',
                    'orderIDShow' => $row['Order Id'] ?? '', 'branchID' => $row['Branch Name'] ?? '',
                    'customerFullname' => $row['Full Name'] ?? '', 'customerTel' => $row['Tel'] ?? '',
                    'requestDate' => self::legacyDate((string) ($row['Request Date'] ?? '')),
                    'Total' => $row['Day'] ?? 0,
                ], $rows);
            }
            $statusOptions = array_map(static fn (array $status): array => [
                'status_id' => $status['status_id'], 'status_name' => $status['status_name'],
            ], $this->statusOptions());
            $records = LegacyViewRenderer::escapedRecords($legacyRows);
            $variables = [
                'GroupID' => service('session')->get('GroupID'),
                'BranchID' => $kind === 'in-progress' && $requestedBranch !== null && $requestedBranch !== ''
                    ? $requestedBranch
                    : service('session')->get('BranchID'),
                'BID' => $branchId ?? 0, 'start_date' => esc($start), 'end_date' => esc($end),
                'branch_type_image' => '',
                'brans_list' => LegacyViewRenderer::escapedRecords($branches),
                'branchs' => array_column($branches, 'branch_name', 'branch_name'),
                'statuses' => LegacyViewRenderer::escapedRecords($statusOptions),
                'status_id' => $statusId, 'selected_status_id' => $statusId === '' ? [] : explode(',', $statusId),
                'resultInfo' => $kind === 'jobs-by-day' ? $legacyRows : $rows,
                'pending_list' => $records, 'jobs' => $records,
            ];
            if ($kind === 'pending-total') {
                $variables['pending_Neworder'] = $rows[0]['Job'] ?? 0;
                $variables['pending_Complete'] = $rows[1]['Job'] ?? 0;
                $variables['pending_Aftercomplete'] = $rows[2]['Job'] ?? 0;
            } elseif ($kind === 'in-progress-average') {
                $variables['newStatusTotals'] = $rows[0]['Job'] ?? 0;
                $variables['requestStatusTotals'] = $rows[1]['Job'] ?? 0;
                $variables['repairStatusTotals'] = $rows[2]['Job'] ?? 0;
                $variables['closeStatusTotals'] = $rows[3]['Job'] ?? 0;
                $variables['returnStatusTotals'] = $rows[4]['Job'] ?? 0;
            }
            foreach (['Total_result_a', 'Total_result_b', 'Total_result_c', 'Total_result_d', 'Total_result_e',
                'pTotal_result_a', 'pTotal_result_b', 'pTotal_result_c', 'pTotal_result_d', 'p_total_result',
                'Total_p_result', 'Total_p_result_a', 'Total_p_result_b', 'Total_p_result_c', 'Total_p_result_d',
                'Total_p_result_e', 'pending_Aftercomplete', 'pending_Complete', 'pending_Neworder',
                'closeStatusTotals', 'newStatusTotals', 'repairStatusTotals', 'requestStatusTotals',
                'returnStatusTotals', 'temp_Total_result'] as $total) {
                if (! array_key_exists($total, $variables)) {
                    $variables[$total] = 0;
                }
            }
            $content = (new LegacyViewRenderer(oldValues: [
                'branch_id' => (string) ($branchId ?? 0),
            ]))->render($templates[$kind], $variables);
            $html = $this->layout('Tracking : Dashboard', $content, ['contentOwnsWrapper' => true]);
        }

        return $error === null ? $html : $this->response->setStatusCode(422)->setBody($html);
    }

    /**
     * @param list<array{question:int,total:int,scores:array<int, array{count:int,percentage:string}>}> $rows
     * @param list<array<string, mixed>> $comments
     * @param list<array<string, mixed>> $branches
     */
    private function ratingReport(
        array $rows,
        array $comments,
        array $branches,
        ?int $branchId,
        ?int $exportBranchId,
        string $start,
        string $end,
    ): string
    {
        $groups = [];
        foreach ($rows as $row) {
            $question = $row['question'];
            $groups[$question] = ['total' => $row['total'], 'score' => [], 'average' => []];
            foreach ($row['scores'] as $score => $result) {
                $groups[$question]['score'][$score] = $result['count'];
                $groups[$question]['average'][$score] = $result['count'] > 0 ? $result['percentage'] : 0;
            }
        }
        $safeComments = array_map(static fn (array $comment): array => [
            'comment' => esc(is_scalar($comment['comment'] ?? null) ? (string) $comment['comment'] : ''),
        ], $comments);
        $session = service('session');
        $group = $session->get('GroupID');
        $groupId = is_int($group) ? $group : (is_string($group) && ctype_digit($group) ? (int) $group : 0);
        $selectedBranch = $branchId ?? 0;
        $renderer = new LegacyViewRenderer(oldValues: ['branch_id' => (string) $selectedBranch]);
        $content = $renderer->render('report', [
            'GroupID' => $groupId,
            'BranchID' => $exportBranchId ?? '',
            'start_date' => esc($start),
            'end_date' => esc($end),
            'ratings' => ['group' => $groups],
            'ratingComments' => $safeComments,
            'brans_list' => LegacyViewRenderer::escapedRecords($branches),
        ]);

        return $this->layout('Tracking : Dashboard', $content, ['contentOwnsWrapper' => true]);
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
        $content = (new LegacyViewRenderer())->render('tracking/reportsummary', [
            'OrdersRecords' => LegacyViewRenderer::escapedRecords($rows),
            'Brand' => LegacyViewRenderer::escapedRecords($db->table('brand')->select('brand_id, brand_details')->orderBy('brand_id')->get()->getResultArray()),
            'Producttype' => LegacyViewRenderer::escapedRecords($db->table('type')->select('type_id, type_details')->orderBy('type_id')->get()->getResultArray()),
            'Condition' => LegacyViewRenderer::escapedRecords($db->table('condition')->select('condition_id, condition_details')->orderBy('condition_id')->get()->getResultArray()),
            'Estimateprice' => LegacyViewRenderer::escapedRecords($db->table('estimateprice')->select('estimateprice_id, estimateprice_details')->orderBy('estimateprice_id')->get()->getResultArray()),
            'Fixed' => LegacyViewRenderer::escapedRecords($db->table('fixed')->select('fixed_id, fixed_details')->orderBy('fixed_id')->get()->getResultArray()),
            'Status' => LegacyViewRenderer::escapedRecords($db->table('statusaction')->select('status_id, status_name, status_name_th')->orderBy('status_id')->get()->getResultArray()),
            'searchText' => esc($filters['searchText']), 'sdate' => esc($filters['sdate']), 'edate' => esc($filters['edate']),
            'status_id' => esc($filters['status_id']), 'companny_id' => $branchId ?? '', 'page' => (int) $page,
        ]);
        $html = $this->layout('Tracking : Listing', $content, ['contentOwnsWrapper' => true]);

        return $error === null ? $html : $this->response->setStatusCode(422)->setBody($html);
    }

    public function export(string $type): ResponseInterface
    {
        return $this->buildExport($type);
    }

    private function buildExport(
        string $type,
        bool $legacyExport = false,
        ?string $routeBranchId = null,
        ?string $routeStartDate = null,
        ?string $routeEndDate = null,
        bool $detailedRatings = false,
        ?string $routeStatusId = null,
        bool $trustRouteBranch = false,
    ): ResponseInterface {
        if (! in_array($type, ['tracking', 'summary', 'ratings', 'in-progress'], true)) {
            throw PageNotFoundException::forPageNotFound();
        }
        $branchId = $trustRouteBranch
            ? $this->legacyExportBranchScope($routeBranchId)
            : $this->branchScope($routeBranchId);
        // Parity with CI3 report export, which raises memory_limit before pulling rows
        // (application/controllers/Order.php:446 / User.php:444 -> ini_set('memory_limit', '8048M')).
        if (ini_set('memory_limit', '8048M') === false) {
            log_message('warning', 'Report export could not raise memory_limit; continuing with existing ceiling.');
        }
        [$defaultStart, $defaultEnd] = $routeStartDate !== null || $routeEndDate !== null
            ? $this->defaultRange(self::legacyRouteDate($routeStartDate), self::legacyRouteDate($routeEndDate))
            : $this->defaultRange($this->input('start_date'), $this->input('end_date'));
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
                'ratings' => $detailedRatings
                    ? $matrix->ratingExport($defaultStart, $defaultEnd, $branchId)
                    : $matrix->ratings($defaultStart, $defaultEnd, $branchId),
                'in-progress' => $matrix->matrix(
                    'in-progress',
                    $defaultStart,
                    $defaultEnd,
                    $branchId,
                    $routeStatusId ?? $this->normalizeStatusIds($this->input('status_id')),
                ),
            };
        } catch (InvalidArgumentException $exception) {
            return $this->response->setStatusCode(422)->setJSON(['error' => $exception->getMessage()]);
        }

        if (! $legacyExport) {
            return $this->response
                ->setHeader('Content-Type', 'application/vnd.ms-excel; charset=UTF-8')
                ->setHeader('Content-Disposition', 'attachment; filename="' . $type . '-report.xls"')
                ->setHeader('X-Content-Type-Options', 'nosniff')
                ->setBody(view('reports/export', [
                    'rows' => $rows,
                    'title' => self::HEADINGS[$type][0] ?? ucfirst($type),
                ]));
        }

        $template = [
            'ratings' => 'excel_report_rating',
            'in-progress' => 'excel_in_progress_job',
            'tracking' => 'tracking/excel_report_tracking',
            'summary' => 'tracking/excel_reportsummary',
        ][$type];
        if ($type === 'in-progress') {
            $rows = array_map(static fn (array $row): array => [
                'status_name_th' => $row['Status'] ?? '', 'trackID' => $row['Track Id'] ?? '',
                'orderIDShow' => $row['Order Id'] ?? '', 'branchID' => $row['Branch Name'] ?? '',
                'customerFullname' => $row['Full Name'] ?? '', 'customerTel' => $row['Tel'] ?? '',
                'requestDate' => self::legacyDate((string) ($row['Request Date'] ?? '')),
                'Total' => $row['Day'] ?? 0,
            ], $rows);
        }
        $records = $type === 'ratings' && $detailedRatings ? [] : LegacyViewRenderer::escapedRecords($rows);
        $body = (new LegacyViewRenderer())->render($template, [
            'ratings' => $type === 'ratings' && $detailedRatings ? self::escapedRatingExport($rows) : [],
            'jobs' => $records, 'OrdersRecords' => $records,
            'branchs' => $type === 'in-progress' ? array_column($rows, 'branchID', 'branchID') : [],
            'Condition' => [], 'Estimateprice' => [], 'Fixed' => [],
            'BranchID' => service('session')->get('BranchID'),
        ]);

        if ($type === 'ratings') {
            $filename = 'Rating_Report_' . time() . '.xls';

            return $this->response
                ->setHeader('Content-Type', 'application/x-msexcel; name="' . $filename . '"')
                ->setHeader('Content-Disposition', 'inline; filename="' . $filename . '"')
                ->setHeader('Pragma', 'no-cache')
                ->setHeader('X-Content-Type-Options', 'nosniff')
                ->setBody($body);
        }
        if ($type === 'in-progress') {
            $filename = 'In_Progress_Report_' . time() . '.xls';

            return $this->response
                ->setHeader('Content-Type', 'application/x-msexcel; name="' . $filename . '"')
                ->setHeader('Content-Disposition', 'inline; filename="' . $filename . '"')
                ->setHeader('Pragma', 'no-cache')
                ->setBody($body);
        }

        return $this->response
            ->setHeader('Content-Type', 'application/vnd.ms-excel; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $type . '-report.xls"')
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setBody($body);
    }

    public function legacyExport(string $type, string ...$segments): ResponseInterface
    {
        if ($type === 'ratings' && count($segments) === 3) {
            return $this->buildExport(
                $type,
                legacyExport: true,
                routeBranchId: $segments[0],
                routeStartDate: $segments[1],
                routeEndDate: $segments[2],
                detailedRatings: true,
            );
        }
        if ($type === 'ratings' && ! in_array(count($segments), [0, 2], true)) {
            throw PageNotFoundException::forPageNotFound();
        }
        if ($type === 'in-progress' && $segments === []) {
            $branchId = $this->request->getGet('branchId');
            $startDate = $this->request->getGet('startDate');
            $endDate = $this->request->getGet('endDate');
            $statusId = $this->request->getGet('status');

            return $this->buildExport(
                $type,
                legacyExport: true,
                routeBranchId: is_string($branchId) ? $branchId : null,
                routeStartDate: is_string($startDate) ? $startDate : null,
                routeEndDate: is_string($endDate) ? $endDate : null,
                routeStatusId: is_string($statusId) ? $statusId : null,
                trustRouteBranch: true,
            );
        }

        return $this->buildExport($type, legacyExport: true);
    }

    /** @param array<string, array<string, mixed>> $rows @return array<string, array<string, mixed>> */
    private static function escapedRatingExport(array $rows): array
    {
        foreach ($rows as &$row) {
            foreach ($row as $field => $value) {
                if (is_string($value)) {
                    $row[$field] = esc($value);
                }
            }
        }
        unset($row);

        return $rows;
    }

    private static function legacyRouteDate(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('!d-m-Y', $value);
        $errors = DateTimeImmutable::getLastErrors();
        if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $date->format('d-m-Y') !== $value) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $date->format('d/m/Y');
    }

    private static function integerReportValue(mixed $value): int
    {
        return (int) str_replace(',', '', is_scalar($value) ? (string) $value : '0');
    }

    private static function legacyDate(string $value): string
    {
        $date = DateTimeImmutable::createFromFormat('!d/m/Y', $value);

        return $date === false ? $value : $date->format('Y-m-d');
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

    private function legacyInProgressStatus(mixed $value): string
    {
        if (is_array($value)) {
            return 'Array';
        }

        return is_string($value) ? $value : '';
    }

    private function isInvalidLegacyInProgressStatus(string $statusId): bool
    {
        if ($statusId === '' || $statusId === '0' || $statusId === 'Array') {
            return false;
        }

        return preg_match('/\A\s*[0-9]+\s*(?:,\s*[0-9]+\s*)*\z/D', $statusId) !== 1;
    }

    private function legacyInProgressDatabaseError(
        string $statusId,
        string $startDate,
        string $endDate,
        ?int $branchId,
    ): ResponseInterface {
        // Deliberately reproduce CI3's leaked SQL error page for this approved route-specific seam.
        $startDate = substr($startDate, 6, 4) . '-' . substr($startDate, 3, 2) . '-' . substr($startDate, 0, 2);
        $endDate = substr($endDate, 6, 4) . '-' . substr($endDate, 3, 2) . '-' . substr($endDate, 0, 2);
        $branch = $branchId === null ? '' : " and branchID = '{$branchId}'";
        $query = "SELECT *, DATEDIFF('" . date('Y-m-d') . "', requestDate) AS Total, statusaction.status_name_th FROM request_order\n"
            . "        inner join statusaction on request_order.action_status = statusaction.status_id\n"
            . "        WHERE requestDate BETWEEN '{$startDate}' and '{$endDate}'{$branch} and action_status IN ({$statusId})"
            . ' and date_complete IS NULL group by request_id order by requestDate asc';
        $message = '<p>Error Number: 1054</p>'
            . "<p>Unknown column '{$statusId}' in 'WHERE'</p>"
            . '<p>' . $query . '</p>'
            . '<p>Filename: models/Request_order_model.php</p><p>Line Number: 2002</p>';
        $source = (string) file_get_contents(APPPATH . 'Views/ci3/errors/html/error_db.php');
        $documentStart = strpos($source, '<!DOCTYPE html>');
        $body = $documentStart === false ? '' : substr($source, $documentStart);
        $body = str_replace(
            ['<?php echo $heading; ?>', '<?php echo $message; ?>' . "\n"],
            ['A Database Error Occurred', $message],
            $body,
        );

        return $this->response->setStatusCode(500)->setBody($body);
    }

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
            ->select('status_id, status_name, status_name_th')
            ->where('status_id >=', 1)->where('status_id <=', 5)
            ->orderBy('status_id', 'ASC')->get()->getResultArray();
    }

    private function reportInput(string $kind, string $name): mixed
    {
        // These CI3 actions read input->post() even when the route is requested with GET.
        if (in_array($kind, ['pending', 'in-progress-average', 'in-progress'], true)
            && strtoupper($this->request->getMethod()) !== 'POST') {
            return null;
        }

        return $this->input($name);
    }

    private function legacyExportBranchScope(?string $requested): ?int
    {
        if ($requested === null || $requested === '' || $requested === '0') {
            return null;
        }
        if (preg_match('/\A[1-9][0-9]*\z/D', $requested) !== 1) {
            throw PageNotFoundException::forPageNotFound();
        }

        return (int) $requested;
    }

    private function legacyPostedBranchScope(mixed $requested): ?int
    {
        // CI3 trusts these routes' posted branch for every role and falls back only when it is empty.
        $sessionBranch = service('session')->get('BranchID');
        $sessionBranch = $sessionBranch === null ? null : (int) $sessionBranch;
        if ($requested === null || $requested === '') {
            return $sessionBranch;
        }
        if ($requested === '0') {
            return null;
        }
        if (! is_string($requested) || preg_match('/\A[1-9][0-9]*\z/D', $requested) !== 1) {
            throw PageNotFoundException::forPageNotFound();
        }

        return (int) $requested;
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
