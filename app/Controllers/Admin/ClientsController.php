<?php

namespace App\Controllers\Admin;

use CodeIgniter\Controller;
use App\Libraries\WhatsAppService;

class ClientsController extends Controller
{
    public function index()
    {
        requirePermission('view_clients');
        $pdo = getDB();
        
        if (($_SESSION['admin_role'] ?? '') === 'admin') {
            $clients = $pdo->query("SELECT u.*, pa.area_name, GROUP_CONCAT(DISTINCT au.name ORDER BY au.name SEPARATOR ', ') team_member, MAX(aus.completed_at) last_audit FROM users u LEFT JOIN problem_areas pa ON pa.id=u.area_id LEFT JOIN client_assignments ca ON ca.client_id=u.id LEFT JOIN admin_users au ON au.id=ca.team_member_id LEFT JOIN audit_sessions aus ON aus.user_id=u.id AND aus.status='completed' GROUP BY u.id ORDER BY u.created_at DESC")->fetchAll();
        } else {
            $stmt = $pdo->prepare("SELECT u.*, pa.area_name, ? team_member, MAX(aus.completed_at) last_audit FROM users u JOIN client_assignments ca ON ca.client_id=u.id AND ca.team_member_id=? LEFT JOIN problem_areas pa ON pa.id=u.area_id LEFT JOIN audit_sessions aus ON aus.user_id=u.id AND aus.status='completed' GROUP BY u.id ORDER BY u.created_at DESC");
            $stmt->execute([$_SESSION['admin_name'] ?? 'Assigned', $_SESSION['admin_id']]);
            $clients = $stmt->fetchAll();
        }
        
        $featured = $clients[0] ?? null;
        
        return view('admin/clients/index', compact('clients', 'featured'));
    }

    public function create()
    {
        requirePermission('register_clients');
        $pdo = getDB();
        $areas = $pdo->query("SELECT id, area_name FROM problem_areas WHERE is_active=1 ORDER BY display_order, area_name")->fetchAll();
        return view('admin/clients/create', [
            'areas' => $areas,
            'errors' => []
        ]);
    }

    public function store()
    {
        requirePermission('register_clients');
        verifyCsrf();
        $pdo = getDB();
        
        $name = trim($this->request->getPost('name') ?? '');
        $email = trim($this->request->getPost('email') ?? '');
        $mobile = preg_replace('/\D+/', '', $this->request->getPost('mobile') ?? '');
        $areaId = (int)($this->request->getPost('area_id') ?? 0);
        
        $errors = [];
        if (strlen($name) < 2) $errors[] = 'Name is required.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
        if (!preg_match('/^\d{10,15}$/', $mobile)) $errors[] = 'Mobile must be 10 to 15 digits.';
        if ($areaId < 1) $errors[] = 'Select a problem area.';
        
        if (!$errors) {
            $dup = $pdo->prepare("SELECT id FROM users WHERE email=? OR mobile=?");
            $dup->execute([$email, $mobile]);
            if ($dup->fetch()) $errors[] = 'Email or mobile already exists.';
        }
        
        if ($errors) {
            $areas = $pdo->query("SELECT id, area_name FROM problem_areas WHERE is_active=1 ORDER BY display_order, area_name")->fetchAll();
            return view('admin/clients/create', [
                'areas' => $areas,
                'errors' => $errors
            ]);
        }
        
        $password = generateTemporaryPassword();
        $pdo->prepare("INSERT INTO users (name,email,mobile,password,area_id) VALUES (?,?,?,?,?)")
            ->execute([$name, $email, $mobile, password_hash($password, PASSWORD_BCRYPT), $areaId]);
            
        WhatsAppService::sendMessage($mobile, APP_NAME . ": Your account password is $password");
        
        flash('admin', 'Client registered and password sent.', 'success');
        return redirect()->to(APP_URL . '/admin/clients');
    }

    public function edit($id)
    {
        requirePermission('edit_clients');
        $id = (int)$id;
        if (!adminCanAccessClient($id)) {
            return redirect()->to(APP_URL . '/admin/clients');
        }
        
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id=?");
        $stmt->execute([$id]);
        $client = $stmt->fetch();
        if (!$client) {
            return redirect()->to(APP_URL . '/admin/clients');
        }
        
        $areas = $pdo->query("SELECT id, area_name FROM problem_areas WHERE is_active=1 ORDER BY display_order, area_name")->fetchAll();
        
        return view('admin/clients/edit', [
            'client' => $client,
            'areas' => $areas,
            'id' => $id,
            'errors' => []
        ]);
    }

    public function update($id)
    {
        requirePermission('edit_clients');
        $id = (int)$id;
        if (!adminCanAccessClient($id)) {
            return redirect()->to(APP_URL . '/admin/clients');
        }
        
        verifyCsrf();
        $pdo = getDB();
        
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id=?");
        $stmt->execute([$id]);
        $client = $stmt->fetch();
        if (!$client) {
            return redirect()->to(APP_URL . '/admin/clients');
        }
        
        $name            = trim($this->request->getPost('name')   ?? '');
        $email           = trim($this->request->getPost('email')  ?? '');
        $mobile          = preg_replace('/\D+/', '', $this->request->getPost('mobile') ?? '');
        $areaId          = (int)($this->request->getPost('area_id') ?? 0);
        $graduated       = $this->request->getPost('is_graduated') !== null ? 1 : 0;
        $graduationNotes = trim($this->request->getPost('graduation_notes') ?? '');
        
        $errors = [];
        if (strlen($name) < 2)                              $errors[] = 'Name is required.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL))     $errors[] = 'Valid email is required.';
        if (!preg_match('/^\d{10,15}$/', $mobile))          $errors[] = 'Mobile must be 10 to 15 digits.';
        if ($areaId < 1)                                    $errors[] = 'Select a problem area.';
        if ($graduated && strlen($graduationNotes) < 5)     $errors[] = 'Please enter graduation feedback (at least 5 characters).';
        
        if (!$errors) {
            $dup = $pdo->prepare("SELECT id FROM users WHERE (email=? OR mobile=?) AND id<>?");
            $dup->execute([$email, $mobile, $id]);
            if ($dup->fetch()) $errors[] = 'Email or mobile already exists.';
        }
        
        if ($errors) {
            $areas = $pdo->query("SELECT id, area_name FROM problem_areas WHERE is_active=1 ORDER BY display_order, area_name")->fetchAll();
            // Retain POST changes in local client object for view consistency
            $client->name = $name;
            $client->email = $email;
            $client->mobile = $mobile;
            $client->area_id = $areaId;
            $client->is_graduated = $graduated;
            $client->graduation_notes = $graduationNotes;
            
            return view('admin/clients/edit', [
                'client' => $client,
                'areas' => $areas,
                'id' => $id,
                'errors' => $errors
            ]);
        }
        
        $pdo->prepare("UPDATE users SET name=?, email=?, mobile=?, area_id=?, is_graduated=?, graduation_notes=? WHERE id=?")
            ->execute([$name, $email, $mobile, $areaId, $graduated, $graduated ? $graduationNotes : null, $id]);
            
        flash('admin', 'Client saved.', 'success');
        return redirect()->to(APP_URL . '/admin/clients/view/' . $id);
    }

    public function view($id)
    {
        requirePermission('view_clients');
        $id = (int)$id;
        if (!adminCanAccessClient($id)) {
            return redirect()->to(APP_URL . '/admin/clients');
        }
        
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT u.*, pa.area_name FROM users u LEFT JOIN problem_areas pa ON pa.id=u.area_id WHERE u.id=?");
        $stmt->execute([$id]);
        $client = $stmt->fetch();
        if (!$client) {
            return redirect()->to(APP_URL . '/admin/clients');
        }
        
        $sessions     = [];
        if (hasPermission('view_scores')) {
            $stmt = $pdo->prepare("
                SELECT aus.*, aw.audit_type, aw.audit_month, aw.audit_year, pa.area_name
                FROM audit_sessions aus
                JOIN audit_windows aw ON aw.id = aus.audit_window_id
                LEFT JOIN problem_areas pa ON pa.id = aus.area_id
                WHERE aus.user_id = ? AND aus.status = 'completed'
                ORDER BY aus.completed_at DESC
            ");
            $stmt->execute([$id]);
            $sessions = $stmt->fetchAll();
        }
        
        // Stats
        $totalAudits = count($sessions);
        $latestScore = 0;
        $improvement = null;
        if ($totalAudits > 0) {
            $s0 = $sessions[0];
            $latestScore = $s0->max_score > 0 ? round(($s0->total_score / $s0->max_score) * 100) : 0;
            if ($totalAudits > 1) {
                $sl = $sessions[$totalAudits - 1];
                $oldestPct   = $sl->max_score > 0 ? round(($sl->total_score / $sl->max_score) * 100) : 0;
                $improvement = $latestScore - $oldestPct;
            }
        }
        
        // Latest score per area
        $areaProgress = [];
        foreach ($sessions as $s) {
            $area = $s->area_name ?? 'General';
            if (!isset($areaProgress[$area])) {
                $pct = $s->max_score > 0 ? round(($s->total_score / $s->max_score) * 100) : 0;
                $areaProgress[$area] = $pct;
            }
        }
        
        $feedbacks = $pdo->prepare("SELECT cf.*, pa.area_name suggested_area FROM client_feedback cf LEFT JOIN problem_areas pa ON pa.id=cf.suggested_area_id WHERE cf.user_id=? ORDER BY cf.created_at DESC");
        $feedbacks->execute([$id]);
        $feedbacks = $feedbacks->fetchAll();
        
        return view('admin/clients/view', compact(
            'client', 'sessions', 'totalAudits', 'latestScore', 'improvement', 'areaProgress', 'feedbacks'
        ));
    }

    public function viewAction($id)
    {
        requirePermission('edit_clients');
        $id = (int)$id;
        if (!adminCanAccessClient($id)) {
            return redirect()->to(APP_URL . '/admin/clients');
        }
        
        verifyCsrf();
        $pdo = getDB();
        
        $action = $this->request->getPost('action') ?? '';
        if ($action === 'review_feedback') {
            $feedbackId = (int)$this->request->getPost('feedback_id');
            $pdo->prepare("UPDATE client_feedback SET is_reviewed=1 WHERE id=? AND user_id=?")->execute([$feedbackId, $id]);
            
            $clientNameStmt = $pdo->prepare("SELECT name FROM users WHERE id=?");
            $clientNameStmt->execute([$id]);
            $cname = $clientNameStmt->fetchColumn();
            
            teamNotify('team_feedback_reviewed', ($_SESSION['admin_name'] ?? 'Team member') . " marked feedback reviewed for client: $cname", $id);
            flash('admin', 'Feedback marked reviewed.', 'success');
        }
        
        if ($action === 'change_area') {
            $feedbackId = (int)$this->request->getPost('feedback_id');
            $fbRow  = $pdo->prepare("SELECT cf.suggested_area_id, pa.area_name, u.name client_name FROM client_feedback cf LEFT JOIN problem_areas pa ON pa.id=cf.suggested_area_id JOIN users u ON u.id=cf.user_id WHERE cf.id=? AND cf.user_id=? AND cf.suggested_area_id IS NOT NULL");
            $fbRow->execute([$feedbackId, $id]);
            $fbData = $fbRow->fetch();
            if ($fbData) {
                $pdo->prepare("UPDATE users SET area_id=? WHERE id=?")->execute([(int)$fbData->suggested_area_id, $id]);
                $pdo->prepare("UPDATE client_feedback SET is_reviewed=1 WHERE id=? AND user_id=?")->execute([$feedbackId, $id]);
                teamNotify('team_area_changed', ($_SESSION['admin_name'] ?? 'Team member') . " changed area of {$fbData->client_name} to {$fbData->area_name}", $id);
                flash('admin', 'Client area updated and feedback marked reviewed.', 'success');
            }
        }
        
        return redirect()->to(APP_URL . '/admin/clients/view/' . $id . '#feedback');
    }
}
