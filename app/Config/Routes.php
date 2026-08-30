<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Tracking::form');
$routes->get('health', 'Health::index');
$routes->get('condition/pageNotFound', 'ParityErrors::legacyPageNotFound', ['filter' => 'web-auth']);
if (defined('ENVIRONMENT') && ENVIRONMENT === 'parity' && getenv('PARITY_SESSION_BOOTSTRAP') === 'enabled') {
    $routes->get('__parity/session/admin', 'ParitySession::admin');
    $routes->get('__parity/session/branch', 'ParitySession::branch');
}
if (defined('ENVIRONMENT') && ENVIRONMENT === 'parity' && getenv('PARITY_ERROR_TRIGGER') === 'enabled') {
    $routes->get('__parity/error/(:segment)', 'ParityErrors::trigger/$1');
}
$routes->get('track', 'Tracking::form');
$routes->get('track_th', 'Tracking::formThai');
$routes->post('track/trackstatus', 'Tracking::legacyEnglish');
$routes->post('track_th/trackstatus', 'Tracking::legacyThai');
$routes->get('tracking', 'Tracking::form');
$routes->get('tracking-th', 'Tracking::formThai');
$routes->get('tracking/(:any)', 'Tracking::english/$1');
$routes->get('tracking-th/(:any)', 'Tracking::thai/$1');
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
$routes->post('contact/addContact', 'Contact::submit', ['filter' => 'csrf']);
$routes->post('contact_th/addContact', 'Contact::submitThai', ['filter' => 'csrf']);
$routes->get('contact-list', 'Contact::listing', ['filter' => 'authorized:read']);
$routes->match(['GET', 'POST'], 'contactListing', 'Contact::listing', ['filter' => 'authorized:read']);
$routes->match(['GET', 'POST'], 'contactListing/(:num)', 'Contact::listing', ['filter' => 'authorized:read']);
$routes->get('master/(:segment)', 'MasterData::listing/$1', ['filter' => 'authorized:read']);
$routes->get('master/(:segment)/new', 'MasterData::add/$1', ['filter' => 'authorized:read']);
$routes->post('master/(:segment)', 'MasterData::create/$1', ['filter' => ['authorized:write', 'csrf']]);
$routes->get('master/(:segment)/(:num)', 'MasterData::edit/$1/$2', ['filter' => 'authorized:read']);
$routes->post('master/(:segment)/(:num)', 'MasterData::update/$1/$2', ['filter' => ['authorized:write', 'csrf']]);
$routes->post('master/(:segment)/(:num)/delete', 'MasterData::delete/$1/$2', ['filter' => ['authorized:delete', 'csrf']]);
$routes->get('branch-type-image/(:segment)', 'MasterData::image/$1');
// CI3 master listing, add and edit URLs remain public browser contracts. POST aliases
// need the legacy record-id payload adapter and are added with each source form migration.
foreach ([
    'branch' => ['branchListing', 'BranchNew', 'addNewBranch', 'editBranchOld', 'editBranch'],
    'branchtype' => ['branchtypeListing', 'add_new_branchtype', 'addNewBranchtype', 'editBranchtypeOld', 'editBranchtype'],
    'statustype' => ['statustypeListing', 'add_new_statustype', 'addNewStatustype', 'editStatustypeOld', 'editStatustype'],
    'producttype' => ['producttypeListing', 'add_new_producttype', 'addNewProducttype', 'editProducttypeOld', 'editProducttype'],
    'book' => ['bookListing', 'BookNew', 'addNewBook', 'editBookOld', 'editBook'],
    'brand' => ['brandListing', 'add_new_brand', 'addNewBrand', 'editBrandOld', 'editBrand'],
    'condition' => ['conditionListing', 'add_new_condition', 'addNewCondition', 'editConditionOld', 'editCondition'],
    'estimateprice' => ['estimatepriceListing', 'add_new_estimateprice', 'addNewEstimateprice', 'editEstimatepriceOld', 'editEstimateprice'],
    'fixed' => ['fixedListing', 'add_new_fixed', 'addNewFixed', 'editFixedOld', 'editFixed'],
    'provider' => ['providerListing', 'add_new_provider', 'addNewProvider', 'editProviderOld', 'editProvider'],
] as $type => [$listing, $new, $create, $edit, $update]) {
    $routes->match(['GET', 'POST'], $listing, 'MasterData::legacyListing/' . $type, ['filter' => ['web-auth', 'authorized:read']]);
    $routes->match(['GET', 'POST'], $listing . '/(:num)', 'MasterData::legacyListing/' . $type . '/$1', ['filter' => ['web-auth', 'authorized:read']]);
    $routes->get($new, 'MasterData::add/' . $type, ['filter' => ['web-auth', 'authorized:read']]);
    $routes->post($create, 'MasterData::legacyCreate/' . $type, ['filter' => ['web-auth', 'authorized:write', 'csrf']]);
    $routes->get($edit, 'MasterData::legacyEditMissing/' . $type, ['filter' => ['web-auth', 'authorized:read']]);
    $routes->get($edit . '/(:num)', 'MasterData::edit/' . $type . '/$1', ['filter' => ['web-auth', 'authorized:read']]);
    $routes->post($update, 'MasterData::legacyUpdate/' . $type, ['filter' => ['web-auth', 'authorized:write', 'csrf']]);
}
foreach ([
    'branch' => 'deleteBranch', 'branchtype' => 'deleteBranchtype', 'statustype' => 'deleteStatustype',
    'producttype' => 'deleteProducttype', 'book' => 'deleteBook', 'brand' => 'deleteBrand',
    'condition' => 'deleteCondition', 'estimateprice' => 'deleteEstimateprice', 'fixed' => 'deleteFixed',
    'provider' => 'deleteProvider',
] as $type => $route) {
    $routes->post($route, 'MasterData::legacyDelete/' . $type, ['filter' => ['web-auth', 'authorized:delete', 'csrf']]);
}
$routes->get('menu', 'Menu::listing', ['filter' => 'web-auth']);
$routes->get('menu/new', 'Menu::add', ['filter' => 'web-auth']);
$routes->post('menu', 'Menu::create', ['filter' => ['web-auth', 'authorized:write', 'csrf']]);
$routes->get('menu/(:num)', 'Menu::edit/$1', ['filter' => 'web-auth']);
$routes->post('menu/(:num)', 'Menu::update/$1', ['filter' => ['web-auth', 'authorized:write', 'csrf']]);
$routes->match(['GET', 'POST'], 'menuListing', 'Menu::legacyListing', ['filter' => 'web-auth']);
$routes->match(['GET', 'POST'], 'menuListing/(:num)', 'Menu::legacyListing', ['filter' => 'web-auth']);
$routes->get('addNewMenu', 'Menu::legacyAdd', ['filter' => 'web-auth']);
$routes->post('addMenu', 'Menu::legacyCreate', ['filter' => ['web-auth', 'authorized:write', 'csrf']]);
$routes->get('editMunuOld', 'Menu::legacyEditMissing', ['filter' => 'web-auth']);
$routes->get('editMunuOld/(:num)', 'Menu::legacyEdit/$1', ['filter' => 'web-auth']);
$routes->post('editMenu', 'Menu::legacyUpdate', ['filter' => ['web-auth', 'authorized:write', 'csrf']]);
$routes->get('backgrounds', 'Background::listing', ['filter' => 'web-auth']);
$routes->get('backgrounds/new', 'Background::add', ['filter' => 'web-auth']);
$routes->post('backgrounds', 'Background::create', ['filter' => ['web-auth', 'authorized:write', 'csrf']]);
$routes->get('backgrounds/(:num)', 'Background::edit/$1', ['filter' => 'web-auth']);
$routes->post('backgrounds/(:num)', 'Background::update/$1', ['filter' => ['web-auth', 'authorized:write', 'csrf']]);
$routes->post('backgrounds/(:num)/delete', 'Background::delete/$1', ['filter' => ['web-auth', 'authorized:delete', 'csrf']]);
$routes->get('background-image/(:segment)', 'Background::image/$1');
$routes->get('BackgroundListing', 'Background::legacyListing', ['filter' => 'web-auth']);
$routes->get('BackgroundListing/(:num)', 'Background::legacyListing', ['filter' => 'web-auth']);
$routes->get('BackgroundNew', 'Background::legacyAdd', ['filter' => 'web-auth']);
$routes->post('addBackground', 'Background::legacyCreate', ['filter' => ['web-auth', 'authorized:write', 'csrf']]);
$routes->get('editBackgroundOld', 'Background::legacyEditMissing', ['filter' => 'web-auth']);
$routes->get('editBackgroundOld/(:num)', 'Background::legacyEdit/$1', ['filter' => 'web-auth']);
$routes->post('editBackground', 'Background::legacyUpdate', ['filter' => ['web-auth', 'authorized:write', 'csrf']]);
$routes->get('users', 'Users::listing', ['filter' => ['web-auth', 'authorized:read']]);
$routes->match(['GET', 'POST'], 'userListing', 'Users::listing', ['filter' => ['web-auth', 'authorized:read']]);
$routes->post('users', 'Users::create', ['filter' => ['web-auth', 'authorized:write', 'csrf']]);
$routes->get('users/email-exists', 'Users::emailExists', ['filter' => ['web-auth', 'authorized:read']]);
$routes->get('users/new', 'Users::add', ['filter' => ['web-auth', 'authorized:write']]);
$routes->get('users/(:num)', 'Users::edit/$1', ['filter' => ['web-auth', 'authorized:read']]);
$routes->post('users/(:num)', 'Users::update/$1', ['filter' => ['web-auth', 'authorized:write', 'csrf']]);
$routes->post('users/(:num)/delete', 'Users::delete/$1', ['filter' => ['web-auth', 'authorized:delete', 'csrf']]);
$routes->get('users/(:num)/history', 'Users::history/$1', ['filter' => ['web-auth', 'authorized:read']]);
$routes->get('users/(:num)/history/(:num)', 'Users::history/$1/$2', ['filter' => ['web-auth', 'authorized:read']]);
$routes->match(['GET', 'POST'], 'login-history', 'Users::ownHistory', ['filter' => ['web-auth', 'authorized:read']]);
$routes->match(['GET', 'POST'], 'login-history/(:num)/(:num)', 'Users::legacyHistory/$1/$2', ['filter' => ['web-auth', 'authorized:read']]);
$routes->get('api/branches', 'Users::branches', ['filter' => ['web-auth', 'authorized:read']]);
$routes->get('api/books', 'Users::books', ['filter' => ['web-auth', 'authorized:read']]);
$routes->get('user/get_list_branch/(:num)', 'Users::legacyBranches/$1', ['filter' => ['web-auth', 'authorized:read']]);
$routes->get('user/get_list_book/(:num)', 'Users::legacyBooks/$1', ['filter' => ['web-auth', 'authorized:read']]);
$routes->get('user/get_list_branchshort/(:num)', 'Users::legacyBranchShort/$1', ['filter' => ['web-auth', 'authorized:read']]);
$routes->get('user/get_list_bookshort/(:num)', 'Users::legacyBookShort/$1', ['filter' => ['web-auth', 'authorized:read']]);
$routes->get('change-password', 'Users::passwordForm', ['filter' => 'web-auth']);
$routes->post('change-password', 'Users::changePassword', ['filter' => ['web-auth', 'csrf']]);
$routes->get('loadChangePass', 'Users::legacyPasswordForm', ['filter' => 'web-auth']);
$routes->post('changePassword', 'Users::changePasswordLegacy', ['filter' => ['web-auth', 'csrf']]);
$routes->get('orders', 'Order::listing', ['filter' => ['web-auth', 'authorized:read']]);
$routes->get('orders/new', 'Order::newOrder', ['filter' => ['web-auth', 'authorized:write']]);
$routes->post('orders/new', 'Order::create', ['filter' => ['web-auth', 'authorized:write', 'csrf']]);
$routes->get('Orders', 'Order::newOrder', ['filter' => ['web-auth', 'authorized:write']]);
$routes->post('addNewOrders', 'Order::create', ['filter' => ['web-auth', 'authorized:write', 'csrf']]);
$routes->get('editOrdersOld/(:num)', 'Order::editForm/$1', ['filter' => ['web-auth', 'authorized:read']]);
$routes->post('editOrders', 'Order::legacyEdit', ['filter' => ['web-auth', 'authorized:write', 'csrf']]);
$routes->get('OrderPrint/(:num)', 'Order::print/$1', ['filter' => ['web-auth', 'authorized:read']]);
$routes->post('order/do_upload_multi/(:segment)', 'Order::previewUpload/$1', ['filter' => ['web-auth', 'authorized:write', 'csrf']]);
$routes->post('sendorderUpdate', 'Order::sendToProvider', ['filter' => ['web-auth', 'authorized:write', 'csrf']]);
$routes->post('sendorderUpdateStatus', 'Order::updateStatus', ['filter' => ['web-auth', 'authorized:write', 'csrf']]);
$routes->post('sendorder_deliver', 'Order::deliver', ['filter' => ['web-auth', 'authorized:write', 'csrf']]);
$routes->get('orders/(:num)', 'Order::editForm/$1', ['filter' => ['web-auth', 'authorized:read']]);
$routes->post('orders/(:num)', 'Order::edit/$1', ['filter' => ['web-auth', 'authorized:write', 'csrf']]);
$routes->get('orders/(:num)/print', 'Order::print/$1', ['filter' => ['web-auth', 'authorized:read']]);
$routes->get('order-image/(:segment)', 'Order::image/$1', ['filter' => ['web-auth', 'authorized:read']]);
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
$routes->match(['GET', 'POST'], 'ordersListing', 'Order::listing/1', ['filter' => ['web-auth', 'authorized:read']]);
$routes->match(['GET', 'POST'], 'sendorderListing', 'Order::listing/1', ['filter' => ['web-auth', 'authorized:read']]);
$routes->match(['GET', 'POST'], 'TrackingListing', 'Order::listing/2', ['filter' => ['web-auth', 'authorized:read', 'branchless']]);
$routes->match(['GET', 'POST'], 'TrackingcloseListing', 'Order::listing/3', ['filter' => ['web-auth', 'authorized:read', 'branchless']]);
$routes->match(['GET', 'POST'], 'TrackingreturnListing', 'Order::listing/4', ['filter' => ['web-auth', 'authorized:read']]);
$routes->match(['GET', 'POST'], 'TrackingcompleteListing', 'Order::listing/5', ['filter' => ['web-auth', 'authorized:read']]);
$routes->match(['GET', 'POST'], 'TrackingCompletedListing', 'Order::listing/7', ['filter' => ['web-auth:legacy-redirect', 'authorized:read']]);
$routes->match(['GET', 'POST'], 'ordersListing/(:num)', 'Order::listing/1/$1', ['filter' => ['web-auth', 'authorized:read']]);
$routes->match(['GET', 'POST'], 'sendorderListing/(:num)', 'Order::listing/1/$1', ['filter' => ['web-auth', 'authorized:read']]);
$routes->match(['GET', 'POST'], 'TrackingListing/(:num)', 'Order::listing/2/$1', ['filter' => ['web-auth', 'authorized:read', 'branchless']]);
$routes->match(['GET', 'POST'], 'TrackingcloseListing/(:num)', 'Order::listing/3/$1', ['filter' => ['web-auth', 'authorized:read', 'branchless']]);
$routes->match(['GET', 'POST'], 'TrackingreturnListing/(:num)', 'Order::listing/4/$1', ['filter' => ['web-auth', 'authorized:read']]);
$routes->match(['GET', 'POST'], 'TrackingcompleteListing/(:num)', 'Order::listing/5/$1', ['filter' => ['web-auth', 'authorized:read']]);
$routes->match(['GET', 'POST'], 'TrackingCompletedListing/(:num)', 'Order::listing/7/$1', ['filter' => ['web-auth:legacy-redirect', 'authorized:read']]);
$routes->get('login', 'Login::index');
$routes->post('loginMe', 'Login::authenticate', ['filter' => 'csrf']);
$routes->get('logout', 'Login::logoutBridge', ['filter' => 'web-auth']);
$routes->post('logout', 'Login::logout', ['filter' => 'csrf']);
$routes->get('dashboard', 'Dashboard::index', ['filter' => 'web-auth:redirect']);
$reportMatrixFilters = ['filter' => ['web-auth:redirect', 'csrf']];
$routes->match(['GET', 'POST'], 'user/report', 'Reports::matrix/ratings', $reportMatrixFilters);
$routes->match(
    ['GET', 'POST'],
    'user/report_job_byday',
    'Reports::matrix/jobs-by-day',
    ['filter' => ['web-auth:legacy-redirect', 'csrf']],
);
$routes->match(
    ['GET', 'POST'],
    'user/report_job_pending',
    'Reports::matrix/pending',
    ['filter' => ['web-auth:legacy-redirect', 'csrf']],
);
$routes->match(['GET', 'POST'], 'user/report_total_job_pending', 'Reports::matrix/pending-total', $reportMatrixFilters);
$routes->match(
    ['GET', 'POST'],
    'user/report_in_progress_average',
    'Reports::matrix/in-progress-average',
    ['filter' => ['web-auth:legacy-redirect', 'csrf']],
);
$routes->match(
    ['GET', 'POST'],
    'user/report_in_progress_job',
    'Reports::matrix/in-progress',
    ['filter' => ['web-auth:legacy-redirect', 'csrf']],
);
$routes->match(['GET', 'POST'], 'reportsummary', 'Reports::summary', $reportMatrixFilters);
$routes->match(['GET', 'POST'], 'reportsummary/(:num)', 'Reports::summary/$1', $reportMatrixFilters);
$routes->match(['GET', 'POST'], 'reportsummary/(:num)/(:num)', 'Reports::summary/$1/$2', $reportMatrixFilters);
$routes->get('reports/(:segment)/export', 'Reports::export/$1', ['filter' => 'web-auth:redirect']);
$routes->get('user/excel_ratings', 'Reports::legacyExport/ratings', ['filter' => 'web-auth:redirect']);
$routes->get('user/excel_ratings/(:segment)/(:segment)/(:segment)', 'Reports::legacyExport/ratings/$1/$2/$3', ['filter' => 'web-auth:redirect']);
$routes->get('user/excel_ratings/(:segment)/(:segment)', 'Reports::legacyExport/ratings/$1/$2', ['filter' => 'web-auth:redirect']);
$routes->get('user/excel_in_progress_job', 'Reports::legacyExport/in-progress', ['filter' => 'web-auth:legacy-redirect']);
$routes->get('user/excel_in_progress_job/(:segment)/(:segment)/(:segment)', 'Reports::legacyExport/in-progress/$1/$2/$3', ['filter' => 'web-auth:redirect']);
$routes->get('Order/excel_report', 'Reports::legacyExport/tracking', ['filter' => 'web-auth:redirect']);
$routes->get('Order/excel_report/(:segment)/(:segment)/(:segment)/(:segment)/(:segment)', 'Reports::legacyExport/tracking/$1/$2/$3/$4/$5', ['filter' => 'web-auth:redirect']);
$routes->get('order/excel_report', 'Reports::legacyExport/tracking', ['filter' => 'web-auth:redirect']);
$routes->get('order/excel_report/(:segment)/(:segment)/(:segment)/(:segment)/(:segment)', 'Reports::legacyExport/tracking/$1/$2/$3/$4/$5', ['filter' => 'web-auth:redirect']);
$routes->get('order/excel_report/(:segment)/(:segment)/(:segment)/(:segment)', 'Reports::legacyExport/tracking/$1/$2/$3/$4', ['filter' => 'web-auth:redirect']);
$routes->get('Order/excel_report_sum', 'Reports::legacyExport/summary', ['filter' => 'web-auth:redirect']);
$routes->get('Order/excel_report_sum/(:segment)/(:segment)/(:segment)/(:segment)/(:segment)/(:segment)/(:segment)', 'Reports::legacyExport/summary/$1/$2/$3/$4/$5/$6/$7', ['filter' => 'web-auth:redirect']);

$reportFilters = ['filter' => ['web-auth:redirect', 'csrf']];
foreach (['ReportTrackingListing', 'Order/ReportTrackingListing', 'ReportTrackingListingTest'] as $reportRoute) {
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
$routes->get('forgotPassword', 'PasswordReset::forgotForm');
$routes->post('resetPasswordUser', 'PasswordReset::requestResetForm', ['filter' => 'csrf']);
$routes->get('reset-password', 'PasswordReset::resetForm');
$routes->get('resetPasswordConfirmUser/(:any)/(:any)', 'PasswordReset::legacyResetForm');
$routes->get('resetPasswordConfirmUser/(:any)', 'PasswordReset::legacyResetForm');
$routes->post('createPasswordUser', 'PasswordReset::completeResetForm', ['filter' => 'csrf']);
$routes->get('password-reset/csrf', 'PasswordReset::csrf');
$routes->post('password-reset/request', 'PasswordReset::requestReset', ['filter' => 'api-csrf']);
$routes->post('password-reset/complete', 'PasswordReset::completeReset', ['filter' => 'api-csrf']);
