<?php

namespace App\Controllers\Admin;

use CodeIgniter\Controller;

class MappingsController extends Controller
{
    public function index()
    {
        requirePermission('manage_mappings');
        $pdo = getDB();

        $questions = $pdo->query("SELECT sno, question_text, question_type FROM questions ORDER BY sno")->fetchAll();
        $areas = $pdo->query("SELECT id, area_name FROM problem_areas WHERE is_active = 1 ORDER BY display_order, area_name")->fetchAll();
        
        $mapped = [];
        foreach ($pdo->query("SELECT question_id, area_id FROM question_area_map")->fetchAll() as $m) {
            $mapped[$m->question_id . '_' . $m->area_id] = true;
        }

        return view('admin/mappings/index', [
            'questions' => $questions,
            'areas' => $areas,
            'mapped' => $mapped
        ]);
    }

    public function save()
    {
        requirePermission('manage_mappings');
        verifyCsrf();
        $pdo = getDB();

        $submitted = array_keys($this->request->getPost('mapping') ?? []);
        $submittedSet = array_fill_keys($submitted, true);

        $existing = $pdo->query("SELECT question_id, area_id FROM question_area_map")->fetchAll();
        $existingSet = [];
        foreach ($existing as $row) {
            $existingSet[$row->question_id . '_' . $row->area_id] = true;
        }

        $pdo->beginTransaction();
        try {
            foreach ($submittedSet as $key => $_) {
                if (!isset($existingSet[$key]) && preg_match('/^(\d+)_(\d+)$/', $key, $m)) {
                    $pdo->prepare("INSERT IGNORE INTO question_area_map (question_id, area_id) VALUES (?, ?)")
                        ->execute([(int)$m[1], (int)$m[2]]);
                }
            }
            foreach ($existingSet as $key => $_) {
                if (!isset($submittedSet[$key])) {
                    [$q, $a] = array_map('intval', explode('_', $key));
                    $pdo->prepare("DELETE FROM question_area_map WHERE question_id = ? AND area_id = ?")
                        ->execute([$q, $a]);
                }
            }
            $pdo->commit();
            flash('admin', 'Mappings saved.', 'success');
        } catch (\Exception $e) {
            $pdo->rollBack();
            flash('admin', 'Failed to save mappings: ' . $e->getMessage(), 'danger');
        }

        return redirect()->to(APP_URL . '/admin/mappings');
    }
}
