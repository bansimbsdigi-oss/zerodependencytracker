<?php

namespace App\Controllers\Admin;

use CodeIgniter\Controller;

class QuestionsController extends Controller
{
    public function index()
    {
        requirePermission('manage_questions');
        $pdo = getDB();
        
        $areas = $pdo->query("SELECT id, area_name FROM problem_areas WHERE is_active=1 ORDER BY display_order, area_name")->fetchAll();
        $filterAreaId = (int)($this->request->getGet('area_id') ?? 0);
        
        $questions = [];
        $grouped   = [];
        
        if ($filterAreaId) {
            $stmt = $pdo->prepare("
                SELECT q.*, qs.section_name, qs.display_order AS sec_order,
                       COUNT(DISTINCT o.id) AS option_count
                FROM questions q
                LEFT JOIN question_sections qs ON qs.id = q.section_id
                LEFT JOIN problem_areas pa ON pa.id = qs.area_id
                LEFT JOIN options o ON o.question_id = q.sno
                WHERE pa.id = ?
                GROUP BY q.sno
                ORDER BY qs.display_order, qs.id, q.sno
            ");
            $stmt->execute([$filterAreaId]);
            $questions = $stmt->fetchAll();
            
            foreach ($questions as $q) {
                $grouped[$q->section_name ?? '— No Section —'][] = $q;
            }
        }
        
        return view('admin/questions/index', compact('areas', 'filterAreaId', 'questions', 'grouped'));
    }

    public function create()
    {
        requirePermission('manage_questions');
        $pdo = getDB();
        
        $areas    = $pdo->query("SELECT id, area_name FROM problem_areas WHERE is_active = 1 ORDER BY display_order, area_name")->fetchAll();
        $sections = $pdo->query("SELECT id, area_id, section_name, display_order FROM question_sections ORDER BY display_order, section_name")->fetchAll();
        
        $filterAreaId = (int)($this->request->getGet('area_id') ?? 0);
        
        return view('admin/questions/create', [
            'areas' => $areas,
            'sections' => $sections,
            'selectedArea' => $filterAreaId,
            'errors' => []
        ]);
    }

    public function store()
    {
        requirePermission('manage_questions');
        verifyCsrf();
        $pdo = getDB();
        
        $text      = trim($this->request->getPost('question_text') ?? '');
        $type      = $this->request->getPost('question_type') ?? 'mcq';
        $ratingMin = (int)($this->request->getPost('rating_min') ?? 1);
        $ratingMax = (int)($this->request->getPost('rating_max') ?? 10);
        $sectionId = (int)($this->request->getPost('section_id') ?? 0) ?: null;
        
        $optTexts  = array_map('trim', $this->request->getPost('option_text') ?? []);
        $optPoints = $this->request->getPost('option_points') ?? [];
        $optTexts  = array_values(array_filter($optTexts, fn($t) => $t !== ''));
        
        $errors = [];
        if (strlen($text) < 5) $errors[] = 'Question text must be at least 5 characters.';
        if (!in_array($type, ['mcq','text','multi_select','rating'], true)) $errors[] = 'Invalid question type.';
        if ($type === 'rating' && $ratingMax <= $ratingMin) $errors[] = 'Rating max must be greater than min.';
        if (in_array($type, ['mcq','multi_select'], true) && count($optTexts) < 2) $errors[] = 'Add at least 2 options for this question type.';
        
        if ($errors) {
            $areas    = $pdo->query("SELECT id, area_name FROM problem_areas WHERE is_active = 1 ORDER BY display_order, area_name")->fetchAll();
            $sections = $pdo->query("SELECT id, area_id, section_name, display_order FROM question_sections ORDER BY display_order, section_name")->fetchAll();
            
            return view('admin/questions/create', [
                'areas' => $areas,
                'sections' => $sections,
                'selectedArea' => (int)($this->request->getPost('area_id') ?? 0),
                'errors' => $errors
            ]);
        }
        
        $pdo->prepare("INSERT INTO questions (question_text, question_type, rating_min, rating_max, flag, section_id) VALUES (?, ?, ?, ?, 1, ?)")
            ->execute([$text, $type, $ratingMin, $ratingMax, $sectionId]);
        $qId = (int)$pdo->lastInsertId();
        
        // Auto-map to area via section
        if ($sectionId) {
            $sec = $pdo->prepare("SELECT area_id FROM question_sections WHERE id = ?");
            $sec->execute([$sectionId]);
            $secRow = $sec->fetch();
            if ($secRow) {
                $pdo->prepare("INSERT IGNORE INTO question_area_map (question_id, area_id) VALUES (?, ?)")->execute([$qId, $secRow->area_id]);
            }
        }
        
        // Save inline options
        foreach ($optTexts as $i => $optText) {
            $pts = max(0, (int)($optPoints[$i] ?? 0));
            $pdo->prepare("INSERT INTO options (question_id, option_text, points, display_order) VALUES (?, ?, ?, ?)")
                ->execute([$qId, $optText, $pts, $i + 1]);
        }
        
        flash('admin', 'Question created.', 'success');
        return redirect()->to(APP_URL . '/admin/questions' . ($sectionId && isset($secRow) ? '?area_id=' . $secRow->area_id : ''));
    }

    public function edit($id)
    {
        requirePermission('manage_questions');
        $id = (int)$id;
        $pdo = getDB();
        
        $stmt = $pdo->prepare("SELECT * FROM questions WHERE sno = ?");
        $stmt->execute([$id]);
        $q = $stmt->fetch();
        if (!$q) {
            return redirect()->to(APP_URL . '/admin/questions');
        }
        
        $areas    = $pdo->query("SELECT id, area_name FROM problem_areas WHERE is_active = 1 ORDER BY display_order, area_name")->fetchAll();
        $sections = $pdo->query("SELECT id, area_id, section_name FROM question_sections ORDER BY display_order, section_name")->fetchAll();
        
        $currentSection = null;
        if ($q->section_id) {
            $ss = $pdo->prepare("SELECT * FROM question_sections WHERE id = ?");
            $ss->execute([$q->section_id]);
            $currentSection = $ss->fetch();
        }
        
        $isChoiceType = in_array($q->question_type, ['mcq', 'multi_select'], true);
        $existingOptions = [];
        if ($isChoiceType) {
            $os = $pdo->prepare("SELECT * FROM options WHERE question_id = ? ORDER BY display_order, id");
            $os->execute([$id]);
            $existingOptions = $os->fetchAll();
        }
        
        $selectedSection = (int)$q->section_id;
        $selectedArea    = (int)($currentSection->area_id ?? 0);
        
        return view('admin/questions/edit', compact(
            'q', 'areas', 'sections', 'existingOptions', 'selectedSection', 'selectedArea', 'isChoiceType', 'id'
        ) + ['errors' => []]);
    }

    public function update($id)
    {
        requirePermission('manage_questions');
        verifyCsrf();
        $id = (int)$id;
        $pdo = getDB();
        
        $stmt = $pdo->prepare("SELECT * FROM questions WHERE sno = ?");
        $stmt->execute([$id]);
        $q = $stmt->fetch();
        if (!$q) {
            return redirect()->to(APP_URL . '/admin/questions');
        }
        
        $text      = trim($this->request->getPost('question_text') ?? '');
        $ratingMin = (int)($this->request->getPost('rating_min') ?? $q->rating_min);
        $ratingMax = (int)($this->request->getPost('rating_max') ?? $q->rating_max);
        $flag      = $this->request->getPost('flag') !== null ? 1 : 0;
        $sectionId = (int)($this->request->getPost('section_id') ?? 0) ?: null;
        
        $isChoiceType = in_array($q->question_type, ['mcq', 'multi_select'], true);
        
        $errors = [];
        if (strlen($text) < 5) $errors[] = 'Question text must be at least 5 characters.';
        if ($q->question_type === 'rating' && $ratingMax <= $ratingMin) $errors[] = 'Rating max must be greater than min.';
        
        $newOptTexts  = [];
        $newOptPoints = [];
        $editOptIds   = [];
        $editOptTexts = [];
        $editOptPoints= [];
        $deleteOptIds = array_filter(array_map('intval', $this->request->getPost('delete_option_id') ?? []));
        
        if ($isChoiceType) {
            $newOptTexts  = array_map('trim', $this->request->getPost('new_option_text') ?? []);
            $newOptPoints = $this->request->getPost('new_option_points') ?? [];
            $newOptTexts  = array_values(array_filter($newOptTexts, fn($t) => $t !== ''));
            
            $editOptIds    = array_map('intval', $this->request->getPost('edit_option_id') ?? []);
            $editOptTexts  = array_map('trim',   $this->request->getPost('edit_option_text') ?? []);
            $editOptPoints = $this->request->getPost('edit_option_points') ?? [];
        }
        
        if ($errors) {
            $areas    = $pdo->query("SELECT id, area_name FROM problem_areas WHERE is_active = 1 ORDER BY display_order, area_name")->fetchAll();
            $sections = $pdo->query("SELECT id, area_id, section_name FROM question_sections ORDER BY display_order, section_name")->fetchAll();
            
            $existingOptions = [];
            if ($isChoiceType) {
                $os = $pdo->prepare("SELECT * FROM options WHERE question_id = ? ORDER BY display_order, id");
                $os->execute([$id]);
                $existingOptions = $os->fetchAll();
            }
            
            $selectedSection = (int)($this->request->getPost('section_id') ?? 0);
            $selectedArea    = (int)($this->request->getPost('area_id') ?? 0);
            
            $q->question_text = $text;
            $q->rating_min = $ratingMin;
            $q->rating_max = $ratingMax;
            $q->flag = $flag;
            
            return view('admin/questions/edit', compact(
                'q', 'areas', 'sections', 'existingOptions', 'selectedSection', 'selectedArea', 'isChoiceType', 'id', 'errors'
            ));
        }
        
        $pdo->prepare("UPDATE questions SET question_text=?, rating_min=?, rating_max=?, flag=?, section_id=? WHERE sno=?")
            ->execute([$text, $ratingMin, $ratingMax, $flag, $sectionId, $id]);
            
        // Sync question_area_map from section
        $pdo->prepare("DELETE FROM question_area_map WHERE question_id = ?")->execute([$id]);
        if ($sectionId) {
            $sec = $pdo->prepare("SELECT area_id FROM question_sections WHERE id = ?");
            $sec->execute([$sectionId]);
            $secRow = $sec->fetch();
            if ($secRow) {
                $pdo->prepare("INSERT IGNORE INTO question_area_map (question_id, area_id) VALUES (?, ?)")->execute([$id, $secRow->area_id]);
            }
        }
        
        if ($isChoiceType) {
            // Delete flagged options
            foreach ($deleteOptIds as $delId) {
                $pdo->prepare("DELETE FROM options WHERE id = ? AND question_id = ?")->execute([$delId, $id]);
            }
            // Update existing options
            foreach ($editOptIds as $i => $optId) {
                $oText = $editOptTexts[$i] ?? '';
                $oPts  = max(0, (int)($editOptPoints[$i] ?? 0));
                if ($oText !== '') {
                    $pdo->prepare("UPDATE options SET option_text=?, points=? WHERE id=? AND question_id=?")
                        ->execute([$oText, $oPts, $optId, $id]);
                }
            }
            // Insert new options
            $maxOrdStmt = $pdo->prepare("SELECT COALESCE(MAX(display_order),0) FROM options WHERE question_id=?");
            $maxOrdStmt->execute([$id]);
            $maxOrd = (int)$maxOrdStmt->fetchColumn();
            foreach ($newOptTexts as $i => $oText) {
                $oPts = max(0, (int)($newOptPoints[$i] ?? 0));
                $pdo->prepare("INSERT INTO options (question_id, option_text, points, display_order) VALUES (?, ?, ?, ?)")
                    ->execute([$id, $oText, $oPts, $maxOrd + $i + 1]);
            }
        }
        
        flash('admin', 'Question saved.', 'success');
        return redirect()->to(APP_URL . '/admin/questions' . ($sectionId && isset($secRow) ? '?area_id=' . $secRow->area_id : ''));
    }

    public function toggle($id)
    {
        requirePermission('manage_questions');
        verifyCsrf();
        $id = (int)$id;
        $pdo = getDB();
        
        $flag = (int)$this->request->getPost('flag');
        $pdo->prepare("UPDATE questions SET flag = ? WHERE sno = ?")->execute([$flag, $id]);
        
        flash('admin', 'Question visibility updated.', 'success');
        $back = (int)($this->request->getPost('area_id') ?? 0);
        return redirect()->to(APP_URL . '/admin/questions' . ($back ? '?area_id=' . $back : ''));
    }
}
