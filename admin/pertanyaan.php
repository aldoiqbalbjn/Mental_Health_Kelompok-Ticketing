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
        try {
            $pdo->prepare('DELETE FROM pertanyaan WHERE id = ?')->execute([(int) ($_POST['id'] ?? 0)]);
            flash('success', 'Pertanyaan dihapus.');
        } catch (Throwable $exception) {
            flash('error', 'Pertanyaan tidak bisa dihapus karena sudah dipakai pada jawaban.');
        }
        redirect('admin/pertanyaan.php');
    }

    if ($action === 'toggle') {
        $pdo->prepare('UPDATE pertanyaan SET aktif = IF(aktif = 1, 0, 1) WHERE id = ?')
            ->execute([(int) ($_POST['id'] ?? 0)]);
        flash('success', 'Status pertanyaan diubah.');
        redirect('admin/pertanyaan.php');
    }

    $id = (int) ($_POST['id'] ?? 0);
    $kriteriaId = (int) ($_POST['kriteria_id'] ?? 0);
    $pertanyaan = trim((string) ($_POST['pertanyaan'] ?? ''));
    $urutan = (int) ($_POST['urutan'] ?? 0);
    $aktif = isset($_POST['aktif']) ? 1 : 0;

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM kriteria WHERE id = ?');
    $stmt->execute([$kriteriaId]);
    $kriteriaValid = (int) $stmt->fetchColumn() > 0;

    if (!$kriteriaValid) {
        $errors[] = 'Kriteria tidak valid.';
    }
    if ($pertanyaan === '') {
        $errors[] = 'Teks pertanyaan wajib diisi.';
    }
    if ($urutan < 1) {
        $errors[] = 'Urutan minimal 1.';
    }

    if (!$errors) {
        try {
            if ($id === 0) {
                $pdo->prepare('INSERT INTO pertanyaan (kriteria_id, pertanyaan, urutan, aktif) VALUES (?, ?, ?, ?)')
                    ->execute([$kriteriaId, $pertanyaan, $urutan, $aktif]);
                flash('success', 'Pertanyaan baru ditambahkan.');
            } else {
                $pdo->prepare('UPDATE pertanyaan SET kriteria_id = ?, pertanyaan = ?, urutan = ?, aktif = ? WHERE id = ?')
                    ->execute([$kriteriaId, $pertanyaan, $urutan, $aktif, $id]);
                flash('success', 'Pertanyaan diperbarui.');
            }
            redirect('admin/pertanyaan.php');
        } catch (Throwable $exception) {
            $errors[] = 'Gagal menyimpan. Pastikan urutan dalam satu kriteria belum dipakai.';
        }
    }
}

$edit = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM pertanyaan WHERE id = ? LIMIT 1');
    $stmt->execute([(int) $_GET['edit']]);
    $edit = $stmt->fetch() ?: null;
}

$kriteriaList = $pdo->query('SELECT * FROM kriteria ORDER BY id')->fetchAll();
$questions = $pdo->query(
    'SELECT q.*, k.kode, k.nama_kriteria, k.bobot
     FROM pertanyaan q
     JOIN kriteria k ON k.id = q.kriteria_id
     ORDER BY k.id, q.urutan'
)->fetchAll();

$groups = [];
foreach ($questions as $question) {
    $groups[$question['kode']][] = $question;
}

$page_title = 'Data Pertanyaan';
$active = 'pertanyaan';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <div>
        <h1>Data Pertanyaan</h1>
        <p>Kelola pertanyaan kuesioner untuk setiap kriteria.</p>
    </div>
    <a class="btn" href="<?= e(base_url('admin/pertanyaan.php')) ?>">+ Tambah Pertanyaan</a>
</div>

<?php foreach ($errors as $error): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
<?php endforeach; ?>

<?php foreach ($groups as $kode => $items): ?>
    <div class="card mb-2">
        <div class="criteria-head">
            <h3><?= e($kode) ?> · <?= e($items[0]['nama_kriteria']) ?></h3>
            <span class="badge brand">Bobot <?= e(pct($items[0]['bobot'], 0)) ?></span>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Urutan</th><th>Pertanyaan</th><th>Status</th><th>Aksi</th></tr></thead>
                <tbody>
                    <?php foreach ($items as $row): ?>
                        <tr>
                            <td><?= e((string) $row['urutan']) ?></td>
                            <td><?= e($row['pertanyaan']) ?></td>
                            <td>
                                <span class="badge <?= $row['aktif'] ? 'ok' : '' ?>">
                                    <?= $row['aktif'] ? 'Aktif' : 'Nonaktif' ?>
                                </span>
                            </td>
                            <td>
                                <div class="table-actions">
                                    <a href="<?= e(base_url('admin/pertanyaan.php?edit=' . (int) $row['id'])) ?>">Edit</a>
                                    <form class="inline-form" method="post" action="<?= e(base_url('admin/pertanyaan.php')) ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="id" value="<?= e((string) $row['id']) ?>">
                                        <button class="btn btn-ghost btn-sm" type="submit" style="padding:0; border:0; background:none;"><?= $row['aktif'] ? 'Nonaktifkan' : 'Aktifkan' ?></button>
                                    </form>
                                    <form class="inline-form" method="post" action="<?= e(base_url('admin/pertanyaan.php')) ?>" data-confirm="Hapus pertanyaan ini?">
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
<?php endforeach; ?>

<div class="card">
    <div class="card-head">
        <h3><?= $edit ? 'Edit Pertanyaan' : 'Tambah Pertanyaan' ?></h3>
        <?php if ($edit): ?>
            <a class="btn btn-ghost btn-sm" href="<?= e(base_url('admin/pertanyaan.php')) ?>">Batal edit</a>
        <?php endif; ?>
    </div>

    <form method="post" action="<?= e(base_url('admin/pertanyaan.php')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="<?= $edit ? 'update' : 'create' ?>">
        <input type="hidden" name="id" value="<?= $edit ? e((string) $edit['id']) : '0' ?>">

        <div class="form-grid">
            <div class="field">
                <label for="kriteria_id">Kriteria</label>
                <select id="kriteria_id" name="kriteria_id" required>
                    <?php foreach ($kriteriaList as $kriteria): ?>
                        <option value="<?= e((string) $kriteria['id']) ?>"
                            <?= (int) ($edit['kriteria_id'] ?? 0) === (int) $kriteria['id'] ? 'selected' : '' ?>>
                            <?= e($kriteria['kode']) ?> · <?= e($kriteria['nama_kriteria']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="urutan">Urutan</label>
                <input id="urutan" name="urutan" type="number" min="1" required value="<?= e($edit['urutan'] ?? '1') ?>">
            </div>
        </div>

        <div class="field">
            <label for="pertanyaan">Teks Pertanyaan</label>
            <textarea id="pertanyaan" name="pertanyaan" rows="3" required><?= e($edit['pertanyaan'] ?? '') ?></textarea>
        </div>

        <div class="field">
            <label>
                <input type="checkbox" name="aktif" value="1" <?= !$edit || $edit['aktif'] ? 'checked' : '' ?>>
                Aktif
            </label>
        </div>

        <div class="form-actions">
            <button class="btn" type="submit"><?= $edit ? 'Simpan Perubahan' : 'Tambah Pertanyaan' ?></button>
        </div>
    </form>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
