<?php

namespace App\Controllers\Admin;

use CodeIgniter\Controller;

class TeamController extends Controller
{
    private function checkAdmin()
    {
        if (($_SESSION['admin_role'] ?? '') !== 'admin') {
            return redirect()->to(APP_URL . '/admin/dashboard');
        }
        return null;
    }

    public function index()
    {
        if ($redirect = $this->checkAdmin()) return $redirect;
        $pdo = getDB();

        $members = $pdo->query("
            SELECT au.*,
                   COUNT(DISTINCT ca.client_id) client_count,
                   GROUP_CONCAT(DISTINCT ap.permission ORDER BY ap.permission SEPARATOR ', ') permissions
            FROM admin_users au
            LEFT JOIN client_assignments ca ON ca.team_member_id=au.id
            LEFT JOIN admin_permissions ap ON ap.admin_user_id=au.id
            WHERE au.role='team_member'
            GROUP BY au.id
            ORDER BY au.created_at DESC
        ")->fetchAll();

        $selectedMemberId = (int)($this->request->getGet('member_id') ?? ($members[0]->id ?? 0));
        $selectedMember = null;
        foreach ($members as $member) {
            if ((int)$member->id === $selectedMemberId) {
                $selectedMember = $member;
                break;
            }
        }

        $assigned = [];
        $unassigned = [];
        if ($selectedMember) {
            $stmt = $pdo->prepare("SELECT u.*, pa.area_name FROM users u JOIN client_assignments ca ON ca.client_id=u.id LEFT JOIN problem_areas pa ON pa.id=u.area_id WHERE ca.team_member_id=? ORDER BY u.name");
            $stmt->execute([$selectedMember->id]);
            $assigned = $stmt->fetchAll();

            $unassigned = $pdo->query("SELECT u.*, pa.area_name FROM users u LEFT JOIN client_assignments ca ON ca.client_id=u.id LEFT JOIN problem_areas pa ON pa.id=u.area_id WHERE ca.client_id IS NULL ORDER BY u.name")->fetchAll();
        }

        $allPerms = ['view_clients','edit_clients','register_clients','view_scores','manage_questions','manage_mappings','manage_areas'];

        return view('admin/team/index', [
            'members' => $members,
            'selectedMemberId' => $selectedMemberId,
            'selectedMember' => $selectedMember,
            'assigned' => $assigned,
            'unassigned' => $unassigned,
            'allPerms' => $allPerms
        ]);
    }

    public function action()
    {
        if ($redirect = $this->checkAdmin()) return $redirect;
        verifyCsrf();
        $pdo = getDB();

        $action = $this->request->getPost('action') ?? '';
        $allPerms = ['view_clients','edit_clients','register_clients','view_scores','manage_questions','manage_mappings','manage_areas'];

        if ($action === 'create_member') {
            $name = trim($this->request->getPost('name') ?? '');
            $email = trim($this->request->getPost('email') ?? '');
            $password = $this->request->getPost('password') ?? '';
            $perms = $this->request->getPost('permissions') ?? [];

            if (strlen($name) < 2) {
                flash('admin', 'Name is required.', 'danger');
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                flash('admin', 'Valid email is required.', 'danger');
            } elseif (strlen($password) < 8) {
                flash('admin', 'Password must be at least 8 characters.', 'danger');
            } else {
                $dup = $pdo->prepare("SELECT id FROM admin_users WHERE email=?");
                $dup->execute([$email]);
                if ($dup->fetch()) {
                    flash('admin', 'A team member with this email already exists.', 'danger');
                } else {
                    if (in_array('edit_clients', $perms, true) && !in_array('view_clients', $perms, true)) $perms[] = 'view_clients';
                    $pdo->prepare("INSERT INTO admin_users (name,email,password,role,created_by,is_active) VALUES (?,?,?,'team_member',?,1)")
                        ->execute([$name, $email, password_hash($password, PASSWORD_BCRYPT), $_SESSION['admin_id']]);
                    $memberId = (int)$pdo->lastInsertId();
                    foreach (array_unique($perms) as $p) {
                        if (in_array($p, $allPerms, true)) {
                            $pdo->prepare("INSERT INTO admin_permissions (admin_user_id, permission) VALUES (?, ?)")->execute([$memberId, $p]);
                        }
                    }
                    flash('admin', 'Team member created.', 'success');
                    return redirect()->to(APP_URL . '/admin/team?member_id=' . $memberId);
                }
            }
            return redirect()->to(APP_URL . '/admin/team');
        }

        if (in_array($action, ['assign', 'remove'], true)) {
            $memberId = (int)($this->request->getPost('member_id') ?? 0);
            $clientId = (int)($this->request->getPost('client_id') ?? 0);

            $validMember = $pdo->prepare("SELECT id FROM admin_users WHERE id=? AND role='team_member'");
            $validMember->execute([$memberId]);

            if ($clientId && $validMember->fetchColumn() && $action === 'assign') {
                $pdo->prepare("INSERT INTO client_assignments (client_id, team_member_id, assigned_by) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE team_member_id=VALUES(team_member_id), assigned_by=VALUES(assigned_by), assigned_at=NOW()")
                    ->execute([$clientId, $memberId, $_SESSION['admin_id']]);
                flash('admin', 'Client assigned.', 'success');
            } elseif ($clientId && $action === 'remove') {
                $pdo->prepare("DELETE FROM client_assignments WHERE client_id=? AND team_member_id=?")
                    ->execute([$clientId, $memberId]);
                flash('admin', 'Client removed from assignment.', 'success');
            }

            return redirect()->to(APP_URL . '/admin/team?member_id=' . $memberId);
        }

        return redirect()->to(APP_URL . '/admin/team');
    }

    public function edit($id)
    {
        if ($redirect = $this->checkAdmin()) return $redirect;
        $id = (int)$id;
        $pdo = getDB();

        $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE id=? AND role='team_member'");
        $stmt->execute([$id]);
        $member = $stmt->fetch();
        if (!$member) {
            return redirect()->to(APP_URL . '/admin/team');
        }

        $allPerms = ['view_clients','edit_clients','register_clients','view_scores','manage_questions','manage_mappings','manage_areas'];
        
        $stmt = $pdo->prepare("SELECT permission FROM admin_permissions WHERE admin_user_id=?");
        $stmt->execute([$id]);
        $existing = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        return view('admin/team/edit', [
            'member' => $member,
            'id' => $id,
            'allPerms' => $allPerms,
            'existing' => $existing,
            'errors' => []
        ]);
    }

    public function update($id)
    {
        if ($redirect = $this->checkAdmin()) return $redirect;
        verifyCsrf();
        $id = (int)$id;
        $pdo = getDB();

        $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE id=? AND role='team_member'");
        $stmt->execute([$id]);
        $member = $stmt->fetch();
        if (!$member) {
            return redirect()->to(APP_URL . '/admin/team');
        }

        $allPerms = ['view_clients','edit_clients','register_clients','view_scores','manage_questions','manage_mappings','manage_areas'];
        
        $name = trim($this->request->getPost('name') ?? '');
        $email = trim($this->request->getPost('email') ?? '');
        $active = $this->request->getPost('is_active') !== null ? 1 : 0;
        $password = $this->request->getPost('password') ?? '';
        $perms = $this->request->getPost('permissions') ?? [];

        $errors = [];
        if (strlen($name) < 2) $errors[] = 'Name is required.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
        if ($password && strlen($password) < 8) $errors[] = 'New password must be at least 8 characters.';

        if ($errors) {
            $stmt = $pdo->prepare("SELECT permission FROM admin_permissions WHERE admin_user_id=?");
            $stmt->execute([$id]);
            $existing = $stmt->fetchAll(\PDO::FETCH_COLUMN);

            return view('admin/team/edit', [
                'member' => $member,
                'id' => $id,
                'allPerms' => $allPerms,
                'existing' => $perms ?: $existing,
                'errors' => $errors
            ]);
        }

        if (in_array('edit_clients', $perms, true) && !in_array('view_clients', $perms, true)) $perms[] = 'view_clients';

        $pdo->prepare("UPDATE admin_users SET name=?, email=?, is_active=? WHERE id=?")->execute([$name, $email, $active, $id]);
        
        if ($password) {
            $pdo->prepare("UPDATE admin_users SET password=? WHERE id=?")->execute([password_hash($password, PASSWORD_BCRYPT), $id]);
        }

        $pdo->prepare("DELETE FROM admin_permissions WHERE admin_user_id=?")->execute([$id]);
        foreach (array_unique($perms) as $p) {
            if (in_array($p, $allPerms, true)) {
                $pdo->prepare("INSERT INTO admin_permissions (admin_user_id, permission) VALUES (?, ?)")->execute([$id, $p]);
            }
        }

        $notifType = $active ? 'team_updated' : 'team_deactivated';
        $notifMsg  = $active ? "Team member updated: $name" : "Team member deactivated: $name";
        $pdo->prepare("INSERT INTO admin_notifications (type, category, message, related_admin_id) VALUES (?, 'team', ?, ?)")
            ->execute([$notifType, $notifMsg, $id]);

        flash('admin', 'Team member saved.', 'success');
        return redirect()->to(APP_URL . '/admin/team');
    }
}
