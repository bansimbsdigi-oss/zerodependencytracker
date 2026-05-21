<?php

namespace App\Controllers\Admin;

use CodeIgniter\Controller;

class OptionsController extends Controller
{
    public function index()
    {
        requirePermission('manage_questions');
        $pdo = getDB();
        
        $questionId = (int)($this->request->getGet('question_id') ?? 0);
        $stmt = $pdo->prepare("SELECT * FROM questions WHERE sno = ? AND question_type IN ('mcq','multi_select')");
        $stmt->execute([$questionId]);
        $question = $stmt->fetch();
        if (!$question) {
            return redirect()->to(APP_URL . '/admin/questions');
        }
        
        $opts = $pdo->prepare("SELECT * FROM options WHERE question_id = ? ORDER BY display_order, id");
        $opts->execute([$questionId]);
        $options = $opts->fetchAll();
        
        return view('admin/options/index', compact('question', 'options', 'questionId'));
    }

    public function delete($id)
    {
        requirePermission('manage_questions');
        verifyCsrf();
        $id = (int)$id;
        $pdo = getDB();
        
        $questionId = (int)($this->request->getPost('question_id') ?? 0);
        
        $pdo->prepare("DELETE FROM options WHERE id = ? AND question_id = ?")->execute([$id, $questionId]);
        
        flash('admin', 'Option deleted.', 'success');
        return redirect()->to(APP_URL . '/admin/options?question_id=' . $questionId);
    }
}
