<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/saw.php';

require_role('mahasiswa');

$user = current_user();
$mhs = current_mahasiswa();
if (!$mhs || !$user) {
    flash('error', 'Data mahasiswa tidak ditemukan.');
    redirect('index.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $current = (string) ($_POST['current_password'] ?? '');
    $new = (string) ($_POST['new_password'] ?? '');
    $confirm = (string) ($_POST['confirm_password'] ?? '');

    $stmt = db()->prepare('SELECT password FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$user['id']]);
    $hash = (string) $stmt->fetchColumn();

    if (!password_verify($current, $hash)) {
        $errors[] = 'Password saat ini tidak sesuai.';
    } elseif (strlen($new) < 6) {
        $errors[] = 'Password baru minimal 6 karakter.';
    } elseif ($new !== $confirm) {
        $errors[] = 'Konfirmasi password baru tidak sama.';
    } else {
        $stmt = db()->prepare('UPDATE users SET password = ? WHERE id = ?');
        $stmt->execute([password_hash($new, PASSWORD_DEFAULT), $user['id']]);
        flash('success', 'Password berhasil diperbarui.');
        redirect('mahasiswa/profil.php');
    }
}

$page_title = 'Profil';
$active = 'profil';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <div>
        <h1>Profil Mahasiswa</h1>
        <p>Data akun dan keamanan.</p>
    </div>
</div>

<?php foreach ($errors as $error): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
<?php endforeach; ?>

<div class="grid grid-2">
    <div class="card">
        <div class="card-head"><h3>Data Diri</h3></div>
        <div class="table-wrap">
            <table class="table">
                <tbody>
                    <tr><th>NIM</th><td><?= e($mhs['nim']) ?></td></tr>
                    <tr><th>Nama</th><td><?= e($mhs['nama']) ?></td></tr>
                    <tr><th>Program Studi</th><td><?= e($mhs['prodi']) ?></td></tr>
                    <tr><th>Angkatan</th><td><?= e($mhs['angkatan']) ?></td></tr>
                    <tr><th>Jenis Kelamin</th><td><?= e($mhs['jenis_kelamin'] === 'L' ? 'Laki-laki' : 'Perempuan') ?></td></tr>
                    <tr><th>Email</th><td><?= e($mhs['email']) ?></td></tr>
                    <tr><th>Username</th><td><?= e($user['username']) ?></td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h3>Ubah Password</h3></div>
        <form method="post" action="<?= e(base_url('mahasiswa/profil.php')) ?>">
            <?= csrf_field() ?>

            <div class="field">
                <label for="current_password">Password saat ini</label>
                <input id="current_password" name="current_password" type="password" required>
            </div>
            <div class="field">
                <label for="new_password">Password baru</label>
                <input id="new_password" name="new_password" type="password" minlength="6" required>
                <span class="help">Minimal 6 karakter.</span>
            </div>
            <div class="field">
                <label for="confirm_password">Konfirmasi password baru</label>
                <input id="confirm_password" name="confirm_password" type="password" minlength="6" required>
            </div>

            <button class="btn" type="submit">Simpan Password</button>
        </form>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
