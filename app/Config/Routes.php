<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Tracking::form');
$routes->get('health', 'Health::index');
$routes->get('track', 'Tracking::form');
$routes->get('track_th', 'Tracking::formThai');
$routes->get('tracking', 'Tracking::form');
$routes->get('tracking-th', 'Tracking::formThai');
$routes->get('tracking/(:segment)', 'Tracking::english/$1');
$routes->get('tracking-th/(:segment)', 'Tracking::thai/$1');
$routes->get('rating/(:segment)', 'Rating::form/$1');
$routes->post('rating', 'Rating::submit', ['filter' => 'csrf']);
$routes->post('addRating', 'Rating::submit', ['filter' => 'csrf']);
$routes->get('contact', 'Contact::form');
$routes->post('contact', 'Contact::submit', ['filter' => 'csrf']);
$routes->get('contact-th', 'Contact::formThai');
$routes->post('contact-th', 'Contact::submitThai', ['filter' => 'csrf']);
$routes->get('contact_th', 'Contact::formThai');
$routes->post('addContact', 'Contact::submit', ['filter' => 'csrf']);
$routes->post('addContact_th', 'Contact::submitThai', ['filter' => 'csrf']);
$routes->get('contact-list', 'Contact::listing', ['filter' => 'authorized:read']);
$routes->get('contactListing', 'Contact::listing', ['filter' => 'authorized:read']);
$routes->get('contactListing/(:num)', 'Contact::listing', ['filter' => 'authorized:read']);
$routes->get('master/(:segment)', 'MasterData::listing/$1', ['filter' => 'authorized:read']);
$routes->post('master/(:segment)', 'MasterData::create/$1', ['filter' => ['authorized:write', 'csrf']]);
$routes->get('master/(:segment)/(:num)', 'MasterData::edit/$1/$2', ['filter' => 'authorized:read']);
$routes->post('master/(:segment)/(:num)', 'MasterData::update/$1/$2', ['filter' => ['authorized:write', 'csrf']]);
$routes->post('master/(:segment)/(:num)/delete', 'MasterData::delete/$1/$2', ['filter' => ['authorized:delete', 'csrf']]);
$routes->get('branch-type-image/(:segment)', 'MasterData::image/$1');
$routes->get('bookListing', 'MasterData::listing/book', ['filter' => ['web-auth', 'authorized:read']]);
$routes->get('bookListing/(:num)', 'MasterData::listing/book', ['filter' => ['web-auth', 'authorized:read']]);
$routes->get('menu', 'Menu::listing', ['filter' => 'web-auth']);
$routes->post('menu', 'Menu::create', ['filter' => ['web-auth', 'authorized:write', 'csrf']]);
$routes->get('menu/(:num)', 'Menu::edit/$1', ['filter' => 'web-auth']);
$routes->post('menu/(:num)', 'Menu::update/$1', ['filter' => ['web-auth', 'authorized:write', 'csrf']]);
$routes->get('backgrounds', 'Background::listing', ['filter' => 'web-auth']);
$routes->post('backgrounds', 'Background::create', ['filter' => ['web-auth', 'authorized:write', 'csrf']]);
$routes->get('backgrounds/(:num)', 'Background::edit/$1', ['filter' => 'web-auth']);
$routes->post('backgrounds/(:num)', 'Background::update/$1', ['filter' => ['web-auth', 'authorized:write', 'csrf']]);
$routes->post('backgrounds/(:num)/delete', 'Background::delete/$1', ['filter' => ['web-auth', 'authorized:delete', 'csrf']]);
$routes->get('background-image/(:segment)', 'Background::image/$1');
$routes->get('users', 'Users::listing', ['filter' => ['web-auth', 'authorized:read']]);
$routes->post('users', 'Users::create', ['filter' => ['web-auth', 'authorized:write', 'csrf']]);
$routes->get('users/email-exists', 'Users::emailExists', ['filter' => ['web-auth', 'authorized:read']]);
$routes->get('users/(:num)', 'Users::edit/$1', ['filter' => ['web-auth', 'authorized:read']]);
$routes->post('users/(:num)', 'Users::update/$1', ['filter' => ['web-auth', 'authorized:write', 'csrf']]);
$routes->post('users/(:num)/delete', 'Users::delete/$1', ['filter' => ['web-auth', 'authorized:delete', 'csrf']]);
$routes->get('users/(:num)/history', 'Users::history/$1', ['filter' => ['web-auth', 'authorized:read']]);
$routes->get('users/(:num)/history/(:num)', 'Users::history/$1/$2', ['filter' => ['web-auth', 'authorized:read']]);
$routes->get('login-history', 'Users::ownHistory', ['filter' => ['web-auth', 'authorized:read']]);
$routes->get('api/branches', 'Users::branches', ['filter' => ['web-auth', 'authorized:read']]);
$routes->get('api/books', 'Users::books', ['filter' => ['web-auth', 'authorized:read']]);
$routes->get('change-password', 'Users::passwordForm', ['filter' => 'web-auth']);
$routes->post('change-password', 'Users::changePassword', ['filter' => ['web-auth', 'csrf']]);
$routes->get('orders', 'Order::listing', ['filter' => ['web-auth', 'authorized:read']]);
$routes->get('orders/new', 'Order::newOrder', ['filter' => ['web-auth', 'authorized:write']]);
$routes->post('orders/new', 'Order::create', ['filter' => ['web-auth', 'authorized:write', 'csrf']]);
$routes->get('Orders', 'Order::newOrder', ['filter' => ['web-auth', 'authorized:write']]);
$routes->post('addNewOrders', 'Order::create', ['filter' => ['web-auth', 'authorized:write', 'csrf']]);
$routes->post('sendorderUpdate', 'Order::sendToProvider', ['filter' => ['web-auth', 'authorized:write', 'csrf']]);
$routes->post('sendorderUpdateStatus', 'Order::updateStatus', ['filter' => ['web-auth', 'authorized:write', 'csrf']]);
$routes->post('sendorder_deliver', 'Order::deliver', ['filter' => ['web-auth', 'authorized:write', 'csrf']]);
$routes->get('orders/(:num)', 'Order::editForm/$1', ['filter' => ['web-auth', 'authorized:read']]);
$routes->post('orders/(:num)', 'Order::edit/$1', ['filter' => ['web-auth', 'authorized:write', 'csrf']]);
$routes->get('orders/(:num)/print', 'Order::print/$1', ['filter' => ['web-auth', 'authorized:read']]);
$routes->post('orders/(:num)/delete', 'Order::delete/$1', ['filter' => ['web-auth', 'authorized:delete', 'csrf']]);
$routes->get('imports/file/(:segment)', 'Imports::download/$1', ['filter' => ['web-auth', 'authorized:write']]);
$routes->get('imports/(:segment)', 'Imports::listing/$1', ['filter' => ['web-auth', 'authorized:write']]);
$routes->post('imports/(:segment)/preview', 'Imports::preview/$1', ['filter' => ['web-auth', 'authorized:write', 'csrf']]);
$routes->post('imports/(:segment)/(:segment)/confirm', 'Imports::confirm/$1/$2', ['filter' => ['web-auth', 'authorized:write', 'csrf']]);
$routes->get('UploadexcelListing', 'Imports::listing/status', ['filter' => ['web-auth', 'authorized:write']]);
$routes->post('ExcelDataAdd', 'Imports::preview/status', ['filter' => ['web-auth', 'authorized:write', 'csrf']]);
$routes->post('ExcelConfirm', 'Imports::legacyConfirm/status', ['filter' => ['web-auth', 'authorized:write', 'csrf']]);
$routes->get('UploadexcelpriceListing', 'Imports::listing/price', ['filter' => ['web-auth', 'authorized:write']]);
$routes->post('ExcelPriceDataAdd', 'Imports::preview/price', ['filter' => ['web-auth', 'authorized:write', 'csrf']]);
$routes->post('ExcelPriceConfirm', 'Imports::legacyConfirm/price', ['filter' => ['web-auth', 'authorized:write', 'csrf']]);
$routes->get('UploadneworderexcelListing', 'Imports::listing/new-order', ['filter' => ['web-auth', 'authorized:write']]);
$routes->post('ExcelNewOrderDataAdd', 'Imports::preview/new-order', ['filter' => ['web-auth', 'authorized:write', 'csrf']]);
$routes->post('ExcelNewOrderConfirm', 'Imports::legacyConfirm/new-order', ['filter' => ['web-auth', 'authorized:write', 'csrf']]);
$routes->get('ordersListing', 'Order::listing/1', ['filter' => ['web-auth', 'authorized:read']]);
$routes->get('sendorderListing', 'Order::listing/1', ['filter' => ['web-auth', 'authorized:read']]);
$routes->get('TrackingListing', 'Order::listing/2', ['filter' => ['web-auth', 'authorized:read']]);
$routes->get('TrackingcloseListing', 'Order::listing/3', ['filter' => ['web-auth', 'authorized:read']]);
$routes->get('TrackingreturnListing', 'Order::listing/4', ['filter' => ['web-auth', 'authorized:read']]);
$routes->get('TrackingcompleteListing', 'Order::listing/5', ['filter' => ['web-auth', 'authorized:read']]);
$routes->get('TrackingCompletedListing', 'Order::listing/7', ['filter' => ['web-auth', 'authorized:read']]);
$routes->get('login', 'Login::index');
$routes->post('loginMe', 'Login::authenticate', ['filter' => 'csrf']);
$routes->post('logout', 'Login::logout', ['filter' => 'csrf']);
$routes->get('dashboard', 'Dashboard::index', ['filter' => 'web-auth:redirect']);
$reportMatrixFilters = ['filter' => ['web-auth:redirect', 'csrf']];
$routes->match(['GET', 'POST'], 'user/report', 'Reports::matrix/ratings', $reportMatrixFilters);
$routes->match(['GET', 'POST'], 'user/report_job_byday', 'Reports::matrix/jobs-by-day', $reportMatrixFilters);
$routes->match(['GET', 'POST'], 'user/report_job_pending', 'Reports::matrix/pending', $reportMatrixFilters);
$routes->match(['GET', 'POST'], 'user/report_total_job_pending', 'Reports::matrix/pending-total', $reportMatrixFilters);
$routes->match(['GET', 'POST'], 'user/report_in_progress_average', 'Reports::matrix/in-progress-average', $reportMatrixFilters);
$routes->match(['GET', 'POST'], 'user/report_in_progress_job', 'Reports::matrix/in-progress', $reportMatrixFilters);
$routes->match(['GET', 'POST'], 'reportsummary', 'Reports::summary', $reportMatrixFilters);
$routes->match(['GET', 'POST'], 'reportsummary/(:num)', 'Reports::summary/$1', $reportMatrixFilters);
$routes->match(['GET', 'POST'], 'reportsummary/(:num)/(:num)', 'Reports::summary/$1/$2', $reportMatrixFilters);
$routes->get('reports/(:segment)/export', 'Reports::export/$1', ['filter' => 'web-auth:redirect']);
$routes->get('user/excel_ratings', 'Reports::legacyExport/ratings', ['filter' => 'web-auth:redirect']);
$routes->get('user/excel_ratings/(:segment)/(:segment)/(:segment)', 'Reports::legacyExport/ratings/$1/$2/$3', ['filter' => 'web-auth:redirect']);
$routes->get('user/excel_in_progress_job', 'Reports::legacyExport/in-progress', ['filter' => 'web-auth:redirect']);
$routes->get('user/excel_in_progress_job/(:segment)/(:segment)/(:segment)', 'Reports::legacyExport/in-progress/$1/$2/$3', ['filter' => 'web-auth:redirect']);
$routes->get('Order/excel_report', 'Reports::legacyExport/tracking', ['filter' => 'web-auth:redirect']);
$routes->get('Order/excel_report/(:segment)/(:segment)/(:segment)/(:segment)/(:segment)', 'Reports::legacyExport/tracking/$1/$2/$3/$4/$5', ['filter' => 'web-auth:redirect']);
$routes->get('Order/excel_report_sum', 'Reports::legacyExport/summary', ['filter' => 'web-auth:redirect']);
$routes->get('Order/excel_report_sum/(:segment)/(:segment)/(:segment)/(:segment)/(:segment)/(:segment)/(:segment)', 'Reports::legacyExport/summary/$1/$2/$3/$4/$5/$6/$7', ['filter' => 'web-auth:redirect']);

$reportFilters = ['filter' => ['web-auth:redirect', 'csrf']];
foreach (['ReportTrackingListing', 'Order/ReportTrackingListing'] as $reportRoute) {
    $routes->match(['GET', 'POST'], $reportRoute, 'Order::reportTrackingListing', $reportFilters);
    $routes->match(
        ['GET', 'POST'],
        $reportRoute . '/(:num)',
        'Order::reportTrackingListing/$1',
        $reportFilters,
    );
    $routes->match(
        ['GET', 'POST'],
        $reportRoute . '/(:num)/(:num)',
        'Order::reportTrackingListing/$1/$2',
        $reportFilters,
    );
}

$routes->get('forgot-password', 'PasswordReset::forgotForm');
$routes->get('reset-password', 'PasswordReset::resetForm');
$routes->get('password-reset/csrf', 'PasswordReset::csrf');
$routes->post('password-reset/request', 'PasswordReset::requestReset', ['filter' => 'api-csrf']);
$routes->post('password-reset/complete', 'PasswordReset::completeReset', ['filter' => 'api-csrf']);
