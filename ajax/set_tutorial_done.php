<?php
// ajax/set_tutorial_done.php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'done') {
    verifyCsrf();
    $pdo = getDB();
    $stmt = $pdo->prepare("UPDATE users SET tutorial_done = 1 WHERE id = ?");
    $stmt->execute([currentUserId()]);
    
    echo json_encode(['status' => 'success']);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
