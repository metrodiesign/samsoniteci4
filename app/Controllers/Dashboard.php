<?php

namespace App\Controllers;

final class Dashboard extends BaseController
{
    /** @var list<array{label: string, href: string}> */
    private const REPORTS_TILE = [
        ['label' => 'REPORTS', 'href' => '/ReportTrackingListing'],
    ];

    /** @var array<int, list<array{label: string, href: string}>> */
    private const TILES_BY_GROUP = [
        3 => [
            ['label' => 'UPLOAD STATUS', 'href' => '/UploadexcelListing'],
            ['label' => 'UPLOAD CMG DATA', 'href' => '/UploadneworderexcelListing'],
            ['label' => 'REPORTS', 'href' => '/ReportTrackingListing'],
        ],
        4 => [
            ['label' => '1. NEW REQUEST REPAIR', 'href' => '/ordersListing'],
            ['label' => '3. DELIVER TO CUSTOMER', 'href' => '/TrackingreturnListing'],
            ['label' => '4. COMPLETE FEEDBACK', 'href' => '/TrackingcompleteListing'],
            ['label' => 'REPORTS', 'href' => '/ReportTrackingListing'],
        ],
    ];

    public function index(): string
    {
        $groupId = service('session')->get('GroupID');
        $tiles = is_int($groupId) ? (self::TILES_BY_GROUP[$groupId] ?? self::REPORTS_TILE) : self::REPORTS_TILE;

        return $this->layout('Dashboard', view('dashboard', ['tiles' => $tiles]));
    }
}
