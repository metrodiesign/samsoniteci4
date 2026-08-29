<?php

namespace App\Controllers;

final class Dashboard extends BaseController
{
    /** @var list<array{label: string, href: string, icon: string}> */
    private const REPORTS_TILE = [
        ['label' => 'REPORTS', 'href' => '/ReportTrackingListing', 'icon' => 'ion-bag'],
    ];

    /** @var array<int, list<array{label: string, href: string, icon: string}>> */
    private const TILES_BY_GROUP = [
        3 => [
            ['label' => 'UPLOAD STATUS', 'href' => '/UploadexcelListing', 'icon' => 'ion-bag'],
            ['label' => 'UPLOAD CMG DATA', 'href' => '/UploadneworderexcelListing', 'icon' => 'ion-stats-bars'],
            ['label' => 'REPORTS', 'href' => '/ReportTrackingListing', 'icon' => 'ion-bag'],
        ],
        4 => [
            ['label' => '1. NEW REQUEST REPAIR', 'href' => '/ordersListing', 'icon' => 'ion-bag'],
            ['label' => '2. LOGISTICS', 'href' => '/sendorderListing', 'icon' => 'ion-stats-bars'],
            ['label' => '3. DELIVER TO CUSTOMER', 'href' => '/TrackingreturnListing', 'icon' => 'ion-pie-graph'],
            ['label' => '4. COMPLETE FEEDBACK', 'href' => '/TrackingcompleteListing', 'icon' => 'ion-pie-graph'],
            ['label' => 'REPORTS', 'href' => '/ReportTrackingListing', 'icon' => 'ion-bag'],
        ],
    ];

    public function index(): string
    {
        $groupId = service('session')->get('GroupID');
        $tiles = is_int($groupId) ? (self::TILES_BY_GROUP[$groupId] ?? self::REPORTS_TILE) : self::REPORTS_TILE;

        return $this->layout('Dashboard', view('dashboard', ['tiles' => $tiles]), ['subtitle' => 'Control panel']);
    }
}
