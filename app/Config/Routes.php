<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

// ── Client / Public routes ────────────────────────────────────────────────────
$routes->get('/',                   'Auth::index');
$routes->get('login',               'Auth::index');
$routes->post('login',              'Auth::login');
$routes->get('register',            'Auth::registerForm');
$routes->post('register',           'Auth::register');
$routes->get('otp-verify',          'Auth::otpForm');
$routes->post('otp-verify',         'Auth::otpVerify');
$routes->get('logout',              'Auth::logout');
$routes->post('logout',             'Auth::logout');

$routes->get('dashboard',           'ClientDashboard::index');
$routes->get('audit',               'AuditController::index');
$routes->post('audit',              'AuditController::submit');
$routes->get('audit-report/(:num)', 'AuditController::report/$1');
$routes->post('audit-report/(:num)','AuditController::submitFeedback/$1');
$routes->get('profile',             'ProfileController::index');
$routes->post('profile',            'ProfileController::update');

// ── Ajax endpoints ────────────────────────────────────────────────────────────
$routes->post('ajax/resend-otp',       'Ajax::resendOtp');
$routes->post('ajax/set-tutorial-done','Ajax::setTutorialDone');

// ── Admin routes ──────────────────────────────────────────────────────────────
$routes->get('admin',              'Admin\AdminAuth::index');
$routes->get('admin/login',        'Admin\AdminAuth::index');
$routes->post('admin/login',       'Admin\AdminAuth::login');
$routes->get('admin/logout',       'Admin\AdminAuth::logout');
$routes->post('admin/logout',      'Admin\AdminAuth::logout');

$routes->get('admin/dashboard',    'Admin\AdminDashboard::index');

// Clients
$routes->get('admin/clients',                   'Admin\ClientsController::index');
$routes->get('admin/clients/create',            'Admin\ClientsController::create');
$routes->post('admin/clients/create',           'Admin\ClientsController::store');
$routes->get('admin/clients/edit/(:num)',        'Admin\ClientsController::edit/$1');
$routes->post('admin/clients/edit/(:num)',       'Admin\ClientsController::update/$1');
$routes->get('admin/clients/view/(:num)',        'Admin\ClientsController::view/$1');
$routes->post('admin/clients/view/(:num)',       'Admin\ClientsController::viewAction/$1');

// Questions
$routes->get('admin/questions',                  'Admin\QuestionsController::index');
$routes->get('admin/questions/create',           'Admin\QuestionsController::create');
$routes->post('admin/questions/create',          'Admin\QuestionsController::store');
$routes->get('admin/questions/edit/(:num)',      'Admin\QuestionsController::edit/$1');
$routes->post('admin/questions/edit/(:num)',     'Admin\QuestionsController::update/$1');
$routes->post('admin/questions/toggle/(:num)',   'Admin\QuestionsController::toggle/$1');

// Sections
$routes->get('admin/sections',                   'Admin\SectionsController::index');
$routes->get('admin/sections/create',            'Admin\SectionsController::create');
$routes->post('admin/sections/create',           'Admin\SectionsController::store');
$routes->get('admin/sections/edit/(:num)',       'Admin\SectionsController::edit/$1');
$routes->post('admin/sections/edit/(:num)',      'Admin\SectionsController::update/$1');
$routes->post('admin/sections/delete/(:num)',    'Admin\SectionsController::delete/$1');

// Areas
$routes->get('admin/areas',                      'Admin\AreasController::index');
$routes->get('admin/areas/create',               'Admin\AreasController::create');
$routes->post('admin/areas/create',              'Admin\AreasController::store');
$routes->get('admin/areas/edit/(:num)',          'Admin\AreasController::edit/$1');
$routes->post('admin/areas/edit/(:num)',         'Admin\AreasController::update/$1');
$routes->post('admin/areas/toggle/(:num)',       'Admin\AreasController::toggle/$1');

// Options
$routes->get('admin/options',                    'Admin\OptionsController::index');
$routes->post('admin/options/delete/(:num)',     'Admin\OptionsController::delete/$1');

// Audits
$routes->get('admin/audits',                     'Admin\AuditsController::index');
$routes->get('admin/audits/create',              'Admin\AuditsController::create');
$routes->post('admin/audits/create',             'Admin\AuditsController::store');
$routes->post('admin/audits/action',             'Admin\AuditsController::action');
$routes->get('admin/audits/report/(:num)',       'Admin\AuditsController::report/$1');
$routes->post('admin/audits/report/(:num)',      'Admin\AuditsController::saveReport/$1');

// Team
$routes->get('admin/team',                       'Admin\TeamController::index');
$routes->post('admin/team',                      'Admin\TeamController::action');
$routes->get('admin/team/edit/(:num)',           'Admin\TeamController::edit/$1');
$routes->post('admin/team/edit/(:num)',          'Admin\TeamController::update/$1');

// Mappings
$routes->get('admin/mappings',                   'Admin\MappingsController::index');
$routes->post('admin/mappings',                  'Admin\MappingsController::save');

// Notifications
$routes->get('admin/notifications',              'Admin\NotificationsController::index');
$routes->post('admin/notifications',             'Admin\NotificationsController::action');

// CLI / Cron
$routes->cli('cron/send-audit-reminders',        'Cron::sendAuditReminders');
