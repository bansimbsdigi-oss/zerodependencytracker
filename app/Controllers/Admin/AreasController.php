<?php

namespace App\Controllers\Admin;

use CodeIgniter\Controller;

class AreasController extends Controller
{
    public function index()
    {
        requirePermission('manage_areas');
        $pdo = getDB();
        $areas = $pdo->query("SELECT * FROM problem_areas ORDER BY display_order, area_name")->fetchAll();
        return view('admin/areas/index', compact('areas'));
    }

    public function create()
    {
        requirePermission('manage_areas');
        return view('admin/areas/create', [
            'errors' => []
        ]);
    }

    public function store()
    {
        requirePermission('manage_areas');
        verifyCsrf();
        $pdo = getDB();
        
        $name = trim($this->request->getPost('area_name') ?? '');
        $order = (int)($this->request->getPost('display_order') ?? 0);
        
        $errors = [];
        if (strlen($name) < 2) $errors[] = 'Area name is required.';
        
        if ($errors) {
            return view('admin/areas/create', [
                'errors' => $errors
            ]);
        }
        
        $pdo->prepare("INSERT INTO problem_areas (area_name, display_order, is_active) VALUES (?, ?, 1)")->execute([$name, $order]);
        flash('admin', 'Problem area created.', 'success');
        return redirect()->to(APP_URL . '/admin/areas');
    }

    public function edit($id)
    {
        requirePermission('manage_areas');
        $id = (int)$id;
        $pdo = getDB();
        
        $stmt = $pdo->prepare("SELECT * FROM problem_areas WHERE id = ?");
        $stmt->execute([$id]);
        $area = $stmt->fetch();
        if (!$area) {
            return redirect()->to(APP_URL . '/admin/areas');
        }
        
        return view('admin/areas/edit', [
            'area' => $area,
            'id' => $id,
            'errors' => []
        ]);
    }

    public function update($id)
    {
        requirePermission('manage_areas');
        verifyCsrf();
        $id = (int)$id;
        $pdo = getDB();
        
        $stmt = $pdo->prepare("SELECT * FROM problem_areas WHERE id = ?");
        $stmt->execute([$id]);
        $area = $stmt->fetch();
        if (!$area) {
            return redirect()->to(APP_URL . '/admin/areas');
        }
        
        $name = trim($this->request->getPost('area_name') ?? '');
        $order = (int)($this->request->getPost('display_order') ?? 0);
        $active = $this->request->getPost('is_active') !== null ? 1 : 0;
        
        $errors = [];
        if (strlen($name) < 2) $errors[] = 'Area name is required.';
        
        if ($errors) {
            $area->area_name = $name;
            $area->display_order = $order;
            $area->is_active = $active;
            
            return view('admin/areas/edit', [
                'area' => $area,
                'id' => $id,
                'errors' => $errors
            ]);
        }
        
        $pdo->prepare("UPDATE problem_areas SET area_name = ?, display_order = ?, is_active = ? WHERE id = ?")->execute([$name, $order, $active, $id]);
        flash('admin', 'Problem area saved.', 'success');
        return redirect()->to(APP_URL . '/admin/areas');
    }

    public function toggle($id)
    {
        requirePermission('manage_areas');
        verifyCsrf();
        $id = (int)$id;
        $pdo = getDB();
        
        $active = (int)$this->request->getPost('is_active');
        $pdo->prepare("UPDATE problem_areas SET is_active = ? WHERE id = ?")->execute([$active, $id]);
        
        flash('admin', 'Problem area updated.', 'success');
        return redirect()->to(APP_URL . '/admin/areas');
    }
}
