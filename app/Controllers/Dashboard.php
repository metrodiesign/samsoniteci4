<?php

namespace App\Controllers;

use App\Orders\OrderStore;
use App\Presentation\LegacyViewRenderer;

final class Dashboard extends BaseController
{
    public function index(): string
    {
        $session = service('session');
        $group = $session->get('GroupID');
        $groupId = is_int($group) ? $group : (is_string($group) && ctype_digit($group) ? (int) $group : 0);
        $branch = $session->get('BranchID');
        $branchId = is_int($branch) ? $branch : (is_string($branch) && ctype_digit($branch) ? (int) $branch : null);

        $staleNewOrderCount = 0;
        if ($groupId === 4 && $branchId !== null && $branchId > 0) {
            $staleNewOrderCount = (new OrderStore(db_connect()))->staleNewOrderCount(
                $branchId,
                new \DateTimeImmutable('today', new \DateTimeZone('Asia/Bangkok')),
            );
        }

        return $this->layout('Tracking : Dashboard', (new LegacyViewRenderer())->render('dashboard', [
            'GroupID' => $groupId,
            'day_job_newover' => $staleNewOrderCount,
            'branch_type_image' => '',
        ]), ['contentOwnsWrapper' => true]);
    }
}
