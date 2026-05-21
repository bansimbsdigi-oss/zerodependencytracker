<?php

namespace App\Controllers\Admin;

use CodeIgniter\Controller;

class SectionsController extends Controller
{
    public function index()
    {
        requirePermission('manage_questions');
        $pdo = getDB();
        
        $sections = $pdo->query("
            SELECT qs.*, pa.area_name, COUNT(q.sno) AS question_count
            FROM question_sections qs
            JOIN problem_areas pa ON pa.id = qs.area_id
            LEFT JOIN questions q ON q.section_id = qs.id
            GROUP BY qs.id
            ORDER BY pa.display_order, pa.area_name, qs.display_order, qs.id
        ")->fetchAll();
        
        return view('admin/sections/index', compact('sections'));
    }

    public function create()
    {
        requirePermission('manage_questions');
        $pdo = getDB();
        $areas = $pdo->query("SELECT id, area_name FROM problem_areas WHERE is_active = 1 ORDER BY display_order, area_name")->fetchAll();
        return view('admin/sections/create', [
            'areas' => $areas,
            'errors' => []
        ]);
    }

    public function store()
    {
        requirePermission('manage_questions');
        verifyCsrf();
        $pdo = getDB();
        
        $name    = trim($this->request->getPost('section_name') ?? '');
        $areaId  = (int)($this->request->getPost('area_id') ?? 0);
        $order   = (int)($this->request->getPost('display_order') ?? 0);
        
        $errors = [];
        if (strlen($name) < 2)  $errors[] = 'Section name must be at least 2 characters.';
        if ($areaId < 1)        $errors[] = 'Please select a problem area.';
        
        if ($errors) {
            $areas = $pdo->query("SELECT id, area_name FROM problem_areas WHERE is_active = 1 ORDER BY display_order, area_name")->fetchAll();
            return view('admin/sections/create', [
                'areas' => $areas,
                'errors' => $errors
            ]);
        }
        
        $pdo->prepare("INSERT INTO question_sections (area_id, section_name, display_order) VALUES (?, ?, ?)")->execute([$areaId, $name, $order]);
        flash('admin', 'Section created.', 'success');
        return redirect()->to(APP_URL . '/admin/sections');
    }

    public function edit($id)
    {
        requirePermission('manage_questions');
        $id = (int)$id;
        $pdo = getDB();
        
        $stmt = $pdo->prepare("SELECT * FROM question_sections WHERE id = ?");
        $stmt->execute([$id]);
        $section = $stmt->fetch();
        if (!$section) {
            return redirect()->to(APP_URL . '/admin/sections');
        }
        
        $areas  = $pdo->query("SELECT id, area_name FROM problem_areas WHERE is_active = 1 ORDER BY display_order, area_name")->fetchAll();
        
        return view('admin/sections/edit', [
            'section' => $section,
            'areas' => $areas,
            'id' => $id,
            'errors' => []
        ]);
    }

    public function update($id)
    {
        requirePermission('manage_questions');
        verifyCsrf();
        $id = (int)$id;
        $pdo = getDB();
        
        $stmt = $pdo->prepare("SELECT * FROM question_sections WHERE id = ?");
        $stmt->execute([$id]);
        $section = $stmt->fetch();
        if (!$section) {
            return redirect()->to(APP_URL . '/admin/sections');
        }
        
        $name   = trim($this->request->getPost('section_name') ?? '');
        $areaId = (int)($this->request->getPost('area_id') ?? 0);
        $order  = (int)($this->request->getPost('display_order') ?? 0);
        
        $errors = [];
        if (strlen($name) < 2) $errors[] = 'Section name must be at least 2 characters.';
        if ($areaId < 1)       $errors[] = 'Please select a problem area.';
        
        if ($errors) {
            $areas  = $pdo->query("SELECT id, area_name FROM problem_areas WHERE is_active = 1 ORDER BY display_order, area_name")->fetchAll();
            
            $section->section_name = $name;
            $section->area_id = $areaId;
            $section->display_order = $order;
            
            return view('admin/sections/edit', [
                'section' => $section,
                'areas' => $areas,
                'id' => $id,
                'errors' => $errors
            ]);
        }
        
        $pdo->prepare("UPDATE question_sections SET area_id=?, section_name=?, display_order=? WHERE id=?")->execute([$areaId, $name, $order, $id]);
        // Sync question_area_map for questions in this section
        $pdo->prepare("
            INSERT IGNORE INTO question_area_map (question_id, area_id)
            SELECT q.sno, ? FROM questions q WHERE q.section_id = ?
        ")->execute([$areaId, $id]);
        
        flash('admin', 'Section saved.', 'success');
        return redirect()->to(APP_URL . '/admin/sections');
    }

    public function delete($id)
    {
        requirePermission('manage_questions');
        verifyCsrf();
        $id = (int)$id;
        $pdo = getDB();
        
        // Unlink questions from this section before deleting
        $pdo->prepare("UPDATE questions SET section_id = NULL WHERE section_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM question_sections WHERE id = ?")->execute([$id]);
        
        flash('admin', 'Section deleted.', 'success');
        return redirect()->to(APP_URL . '/admin/sections');
    }
}
