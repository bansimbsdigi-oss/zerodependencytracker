<?php

namespace App\Controllers\Admin;

use CodeIgniter\Controller;

class NotificationsController extends Controller
{
    public function index()
    {
        requireAdminLogin();
        $pdo = getDB();

        $activeTab = in_array($this->request->getGet('tab') ?? '', ['client', 'team'], true) ? $this->request->getGet('tab') : 'client';

        $clientItems = $pdo->query("
            SELECT an.*, u.name client_name, cf.suggested_area_id, pa.area_name suggested_area_name
            FROM admin_notifications an
            LEFT JOIN users u ON u.id = an.related_user_id
            LEFT JOIN client_feedback cf ON cf.id = an.related_feedback_id
            LEFT JOIN problem_areas pa ON pa.id = cf.suggested_area_id
            WHERE an.category = 'client'
            ORDER BY an.is_read ASC, an.created_at DESC
        ")->fetchAll();

        $teamItems = $pdo->query("
            SELECT an.*, au.name actor_name, u.name client_name
            FROM admin_notifications an
            LEFT JOIN admin_users au ON au.id = an.related_admin_id
            LEFT JOIN users u ON u.id = an.related_user_id
            WHERE an.category = 'team'
            ORDER BY an.is_read ASC, an.created_at DESC
        ")->fetchAll();

        $clientUnread = count(array_filter($clientItems, fn($n) => !$n->is_read));
        $teamUnread   = count(array_filter($teamItems,   fn($n) => !$n->is_read));

        return view('admin/notifications/index', [
            'activeTab' => $activeTab,
            'clientItems' => $clientItems,
            'teamItems' => $teamItems,
            'clientUnread' => $clientUnread,
            'teamUnread' => $teamUnread
        ]);
    }

    public function action()
    {
        requireAdminLogin();
        verifyCsrf();
        $pdo = getDB();

        $action   = $this->request->getPost('action') ?? '';
        $category = $this->request->getPost('category') ?? '';
        $tab      = $this->request->getPost('tab') ?? 'client';

        if ($action === 'mark_all' && in_array($category, ['client', 'team', 'all'], true)) {
            $where = $category === 'all' ? '' : "WHERE category = " . $pdo->quote($category);
            $pdo->exec("UPDATE admin_notifications SET is_read = 1 $where");
        } else {
            $id = (int)($this->request->getPost('id') ?? 0);
            $pdo->prepare("UPDATE admin_notifications SET is_read = 1 WHERE id = ?")->execute([$id]);
        }

        flash('admin', 'Notification updated.', 'success');
        return redirect()->to(APP_URL . '/admin/notifications?tab=' . urlencode($tab));
    }
}
