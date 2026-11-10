<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/saw.php';

require_role('admin');

$pdo = db();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        try {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = (SELECT user_id FROM mahasiswa WHERE id = ?) AND role = 'mahasiswa'");
            $stmt->execute([$id]);
            flash('success', 'Data mahasiswa dihapus.');
        } catch (Throwable $exception) {
            flash('error', 'Gagal menghapus mahasiswa: ' . $exception->getMessage());
        }
        redirect('admin/mahasiswa.php');
    }

    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $nim = trim((string) ($_POST['nim'] ?? ''));
    $nama = trim((string) ($_POST['nama'] ?? ''));
    $prodi = trim((string) ($_POST['prodi'] ?? ''));
    $angkatan = trim((string) ($_POST['angkatan'] ?? ''));
    $jk = (string) ($_POST['jenis_kelamin'] ?? 'L');
    $email = trim((string) ($_POST['email'] ?? ''));
    $id = (int) ($_POST['id'] ?? 0);

    if ($username === '' || $nim === '' || $nama === '' || $prodi === '' || $email === '') {
        $errors[] = 'Username, NIM, nama, prodi, dan email wajib diisi.';
    }
    if (!preg_match('/^\d{4}$/', $angkatan) || (int) $angkatan < 2000 || (int) $angkatan > 2100) {
        $errors[] = 'Angkatan harus berupa tahun 4 digit (2000-2100).';
    }
    if (!in_array($jk, ['L', 'P'], true)) {
        $errors[] = 'Jenis kelamin tidak valid.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Format email tidak valid.';
    }
    if ($id === 0 && strlen($password) < 6) {
        $errors[] = 'Password awal minimal 6 karakter.';
    }
    if ($id > 0 && $password !== '' && strlen($password) < 6) {
        $errors[] = 'Password baru minimal 6 karakter.';
    }

    if (!$errors) {
        try {
            if ($id === 0) {
                $pdo->beginTransaction();
                $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, 'mahasiswa')")
                    ->execute([$username, password_hash($password, PASSWORD_DEFAULT)]);
                $userId = (int) $pdo->lastInsertId();
                $pdo->prepare(
                    'INSERT INTO mahasiswa (user_id, nim, nama, prodi, angkatan, jenis_kelamin, email)
                     VALUES (?, ?, ?, ?, ?, ?, ?)'
                )->execute([$userId, $nim, $nama, $prodi, $angkatan, $jk, $email]);
                $pdo->commit();
                flash('success', 'Mahasiswa baru berhasil ditambahkan.');
            } else {
                $stmt = $pdo->prepare('SELECT user_id FROM mahasiswa WHERE id = ? LIMIT 1');
                $stmt->execute([$id]);
                $userId = (int) $stmt->fetchColumn();
                if ($userId <= 0) {
                    throw new RuntimeException('Data mahasiswa tidak ditemukan.');
                }

                $pdo->beginTransaction();
                if ($password !== '') {
                    $pdo->prepare('UPDATE users SET username = ?, password = ? WHERE id = ?')
                        ->execute([$username, password_hash($password, PASSWORD_DEFAULT), $userId]);
                } else {
                    $pdo->prepare('UPDATE users SET username = ? WHERE id = ?')->execute([$username, $userId]);
                }
                $pdo->prepare(
                    'UPDATE mahasiswa SET nim = ?, nama = ?, prodi = ?, angkatan = ?, jenis_kelamin = ?, email = ?
                     WHERE id = ?'
                )->execute([$nim, $nama, $prodi, $angkatan, $jk, $email, $id]);
                $pdo->commit();
                flash('success', 'Data mahasiswa diperbarui.');
            }
            redirect('admin/mahasiswa.php');
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = 'Gagal menyimpan: ' . $exception->getMessage();
        }
    }
}

$edit = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare(
        'SELECT m.*, u.username
         FROM mahasiswa m JOIN users u ON u.id = m.user_id
         WHERE m.id = ? LIMIT 1'
    );
    $stmt->execute([(int) $_GET['edit']]);
    $edit = $stmt->fetch() ?: null;
}

$q = trim((string) ($_GET['q'] ?? ''));
$baseSql = 'SELECT m.*, u.username, h.id AS hasil_id, h.nilai_akhir, h.kategori, p.tanggal AS tanggal_pengisian
            FROM mahasiswa m
            JOIN users u ON u.id = m.user_id
            LEFT JOIN (
                SELECT mahasiswa_id, MAX(id) AS max_pengisian_id
                FROM pengisian
                GROUP BY mahasiswa_id
            ) lp ON lp.mahasiswa_id = m.id
            LEFT JOIN pengisian p ON p.id = lp.max_pengisian_id
            LEFT JOIN hasil_spk h ON h.pengisian_id = p.id ';

if ($q !== '') {
    $stmt = $pdo->prepare(
        $baseSql . 'WHERE m.nama LIKE ? OR m.nim LIKE ? OR m.prodi LIKE ? ORDER BY m.id'
    );
    $like = '%' . $q . '%';
    $stmt->execute([$like, $like, $like]);
} else {
    $stmt = $pdo->query($baseSql . 'ORDER BY m.id');
}
$daftar = $stmt->fetchAll();

$page_title = 'Data Mahasiswa';
$active = 'mahasiswa';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <div>
        <h1>Data Mahasiswa</h1>
        <p>Kelola akun dan profil mahasiswa.</p>
    </div>
    <a class="btn" href="<?= e(base_url('admin/mahasiswa.php')) ?>">+ Tambah Mahasiswa</a>
</div>

<?php foreach ($errors as $error): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
<?php endforeach; ?>

<div class="card mb-2">
    <form method="get" action="<?= e(base_url('admin/mahasiswa.php')) ?>" class="flex gap-1" style="align-items:flex-end;">
        <div class="field" style="margin:0; flex:1;">
            <label for="q">Cari mahasiswa</label>
            <input id="q" name="q" type="search" value="<?= e($q) ?>" placeholder="Nama / NIM / prodi">
        </div>
        <button class="btn" type="submit">Cari</button>
    </form>
</div>

<div class="card mb-2">
    <div class="card-head">
        <h3>Daftar Mahasiswa</h3>
        <span class="small muted"><?= e((string) count($daftar)) ?> data</span>
    </div>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>NIM</th>
                    <th>Nama</th>
                    <th>Prodi</th>
                    <th>JK</th>
                    <th>Email</th>
                    <th>Skor Terakhir (%)</th>
                    <th>Status SPK</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$daftar): ?>
                    <tr><td colspan="8" class="text-center muted">Tidak ada data.</td></tr>
                <?php endif; ?>
                <?php foreach ($daftar as $row): ?>
                    <?php
                    $hasSkor = $row['nilai_akhir'] !== null;
                    $skorVal = $hasSkor ? (float) $row['nilai_akhir'] : 0.0;
                    $skorPct = $skorVal * 100;
                    ?>
                    <tr>
                        <td><b><?= e($row['nim']) ?></b></td>
                        <td>
                            <?= e($row['nama']) ?><br>
                            <span class="small muted">@<?= e($row['username']) ?> · Angkatan <?= e($row['angkatan']) ?></span>
                        </td>
                        <td><?= e($row['prodi']) ?></td>
                        <td><?= e($row['jenis_kelamin']) ?></td>
                        <td><?= e($row['email']) ?></td>
                        <td>
                            <?php if ($hasSkor): ?>
                                <b class="mono"><?= e(pct($skorVal)) ?></b>
                                <div class="mini-bar" style="width:70px;">
                                    <span style="width: <?= min(100, max(0, $skorPct)) ?>%; background: <?= $skorPct >= 60 ? '#c07a36' : '#2f7d63' ?>;"></span>
                                </div>
                            <?php else: ?>
                                <span class="muted small">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($hasSkor): ?>
                                <span class="badge <?= e(badge_class($row['kategori'])) ?>"><?= e($row['kategori']) ?></span>
                            <?php else: ?>
                                <span class="badge">Belum mengisi</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="table-actions">
                                <?php if (!empty($row['hasil_id'])): ?>
                                    <a class="btn btn-ghost btn-sm" href="<?= e(base_url('admin/detail.php?id=' . (int) $row['hasil_id'])) ?>" title="Lihat hasil SPK">SPK</a>
                                <?php endif; ?>
                                <a href="<?= e(base_url('admin/mahasiswa.php?edit=' . (int) $row['id'])) ?>">Edit</a>
                                <form class="inline-form" method="post" action="<?= e(base_url('admin/mahasiswa.php')) ?>" data-confirm="Hapus mahasiswa ini beserta seluruh pengisiannya?">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= e((string) $row['id']) ?>">
                                    <button class="btn btn-ghost btn-sm" type="submit" style="color:var(--danger); padding:0; border:0; background:none;">Hapus</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-head">
        <h3><?= $edit ? 'Edit Mahasiswa' : 'Tambah Mahasiswa' ?></h3>
        <?php if ($edit): ?>
            <a class="btn btn-ghost btn-sm" href="<?= e(base_url('admin/mahasiswa.php')) ?>">Batal edit</a>
        <?php endif; ?>
    </div>

    <form method="post" action="<?= e(base_url('admin/mahasiswa.php')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="<?= $edit ? 'update' : 'create' ?>">
        <input type="hidden" name="id" value="<?= $edit ? e((string) $edit['id']) : '0' ?>">

        <div class="form-grid">
            <div class="field">
                <label for="username">Username</label>
                <input id="username" name="username" type="text" required value="<?= e($edit['username'] ?? '') ?>">
            </div>
            <div class="field">
                <label for="password">Password</label>
                <input id="password" name="password" type="password" <?= $edit ? '' : 'required' ?>>
                <span class="help"><?= $edit ? 'Kosongkan jika tidak ingin mengubah password.' : 'Minimal 6 karakter.' ?></span>
            </div>
            <div class="field">
                <label for="nim">NIM</label>
                <input id="nim" name="nim" type="text" required value="<?= e($edit['nim'] ?? '') ?>">
            </div>
            <div class="field">
                <label for="nama">Nama</label>
                <input id="nama" name="nama" type="text" required value="<?= e($edit['nama'] ?? '') ?>">
            </div>
            <div class="field">
                <label for="prodi">Program Studi</label>
                <input id="prodi" name="prodi" type="text" required value="<?= e($edit['prodi'] ?? '') ?>">
            </div>
            <div class="field">
                <label for="angkatan">Angkatan</label>
                <input id="angkatan" name="angkatan" type="number" min="2000" max="2100" required value="<?= e($edit['angkatan'] ?? '') ?>">
            </div>
            <div class="field">
                <label for="jenis_kelamin">Jenis Kelamin</label>
                <select id="jenis_kelamin" name="jenis_kelamin">
                    <option value="L" <?= ($edit['jenis_kelamin'] ?? '') === 'L' ? 'selected' : '' ?>>Laki-laki (L)</option>
                    <option value="P" <?= ($edit['jenis_kelamin'] ?? '') === 'P' ? 'selected' : '' ?>>Perempuan (P)</option>
                </select>
            </div>
            <div class="field">
                <label for="email">Email</label>
                <input id="email" name="email" type="email" required value="<?= e($edit['email'] ?? '') ?>">
            </div>
        </div>

        <div class="form-actions">
            <button class="btn" type="submit"><?= $edit ? 'Simpan Perubahan' : 'Tambah Mahasiswa' ?></button>
        </div>
    </form>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
