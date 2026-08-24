<?php

namespace App\Controllers;

use App\Reporting\TrackingReport;

final class Dashboard extends BaseController
{
    public function index(): string
    {
        $session  = service('session');
        $branchId = $session->get('BranchID');
        $branchId = $branchId === null ? null : (int) $branchId;
        $counts   = (new TrackingReport(db_connect()))->statusCounts($branchId);
        $branch = null;
        if ($branchId !== null && db_connect()->tableExists('branch') && db_connect()->tableExists('branch_type')) {
            $branch = db_connect()->table('branch branches')
                ->select('branches.branch_name, types.branch_type_image')
                ->join('branch_type types', 'types.branch_type_id = branches.branch_type', 'left')
                ->where('branches.branch_id', $branchId)->get()->getRowArray();
        }

        return $this->layout('Dashboard', view('dashboard', [
            'counts' => $counts,
            'name'   => (string) $session->get('name'),
            'branchName' => (string) ($branch['branch_name'] ?? ''),
            'background' => $this->safeBackground($branch['branch_type_image'] ?? null),
        ]));
    }

    private function safeBackground(mixed $value): string
    {
        return is_string($value) && ! str_contains($value, '..')
            && preg_match('/\A[A-Za-z0-9][A-Za-z0-9._\/-]{0,249}\z/D', $value) === 1
            ? $value
            : 'assets/images/bg-dashbord.png';
    }
}
