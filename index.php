<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

$user = current_user();
if ($user) {
    redirect($user['role'] === 'admin' ? 'admin/dashboard.php' : 'mahasiswa/dashboard.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $errors[] = 'Username dan password wajib diisi.';
    } elseif (attempt_login($username, $password)) {
        $role = current_user()['role'] ?? 'mahasiswa';
        redirect($role === 'admin' ? 'admin/dashboard.php' : 'mahasiswa/dashboard.php');
    } else {
        $errors[] = 'Username atau password salah.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk · MindCare Student</title>
    <link rel="stylesheet" href="<?= e(base_url('assets/css/app.css')) ?>">
</head>
<body class="auth-page">
    <div class="auth-card">
        <div class="auth-brand">
            <span class="brand">
                <span class="brand-logo">MC</span>
                <span class="brand-text">MindCare <small>Student</small></span>
            </span>
        </div>

        <h1>Masuk ke akun</h1>
        <p class="auth-sub">Ruang aman untuk memantau kesehatan mentalmu.</p>

        <?php foreach ($errors as $error): ?>
            <div class="alert alert-error"><?= e($error) ?></div>
        <?php endforeach; ?>

        <form method="post" action="<?= e(base_url('index.php')) ?>" autocomplete="off">
            <?= csrf_field() ?>

            <div class="field">
                <label for="username">Username</label>
                <input id="username" name="username" type="text" placeholder="contoh: mhs001 / admin" required autofocus>
            </div>

            <div class="field">
                <label for="password">Password</label>
                <input id="password" name="password" type="password" placeholder="••••••••" required>
            </div>

            <button class="btn btn-block" type="submit">Masuk</button>
        </form>

        <p class="auth-hint">
            Akun demo: <code>admin</code> atau <code>mhs001</code> · password <code>password</code>
        </p>
    </div>
</body>
</html>
