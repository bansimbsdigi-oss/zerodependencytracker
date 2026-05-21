<?php

namespace App\Controllers;

use CodeIgniter\Controller;

class ProfileController extends Controller
{
    public function index()
    {
        requireLogin();
        $userId = currentUserId();
        $pdo    = getDB();

        $stmt = $pdo->prepare('SELECT u.*, pa.area_name FROM users u LEFT JOIN problem_areas pa ON u.area_id = pa.id WHERE u.id = ?');
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        return view('client/profile', [
            'user'      => $user,
            'flash'     => '',
            'error'     => '',
            'activeTab' => 'details',
        ]);
    }

    public function update()
    {
        requireLogin();
        verifyCsrf();

        $userId    = currentUserId();
        $pdo       = getDB();
        $activeTab = $this->request->getPost('form_type') ?? 'details';

        $flash = '';
        $error = '';

        if ($activeTab === 'password') {
            $current = $this->request->getPost('current_password') ?? '';
            $newPass = $this->request->getPost('new_password') ?? '';
            $confirm = $this->request->getPost('confirm_password') ?? '';

            $stmt = $pdo->prepare('SELECT password FROM users WHERE id = ?');
            $stmt->execute([$userId]);
            $hash = $stmt->fetchColumn();

            if (!$hash || !password_verify($current, $hash)) {
                $error = 'Current password is incorrect.';
            } elseif (strlen($newPass) < 8 || !preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/', $newPass)) {
                $error = 'New password must be at least 8 characters and include uppercase, lowercase, and a number.';
            } elseif ($newPass !== $confirm) {
                $error = 'New password and confirmation do not match.';
            } else {
                $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')
                    ->execute([password_hash($newPass, PASSWORD_BCRYPT), $userId]);
                $flash = 'Password changed successfully.';
            }
        } else {
            $activeTab = 'details';
            $name   = trim($this->request->getPost('name') ?? '');
            $email  = trim($this->request->getPost('email') ?? '');
            $mobile = trim($this->request->getPost('mobile') ?? '');

            if (strlen($name) < 2 || strlen($name) > 100) {
                $error = 'Name must be between 2 and 100 characters.';
            } elseif (empty($email) || empty($mobile)) {
                $error = 'Email and WhatsApp mobile are required.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Invalid email format.';
            } elseif (!preg_match('/^[0-9]{10,15}$/', $mobile)) {
                $error = 'Mobile number must be 10-15 digits.';
            } else {
                $stmt = $pdo->prepare('SELECT id FROM users WHERE (email = ? OR mobile = ?) AND id != ?');
                $stmt->execute([$email, $mobile, $userId]);
                if ($stmt->fetch()) {
                    $error = 'Email or WhatsApp mobile is already in use by another account.';
                } else {
                    $pdo->prepare('UPDATE users SET name = ?, email = ?, mobile = ? WHERE id = ?')
                        ->execute([$name, $email, $mobile, $userId]);
                    $flash = 'Profile updated successfully.';
                }
            }
        }

        $stmt = $pdo->prepare('SELECT u.*, pa.area_name FROM users u LEFT JOIN problem_areas pa ON u.area_id = pa.id WHERE u.id = ?');
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        return view('client/profile', compact('user', 'flash', 'error', 'activeTab'));
    }
}
