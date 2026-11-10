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
            $pdo->prepare('DELETE FROM kriteria WHERE id = ?')->execute([$id]);
            flash('success', 'Kriteria dihapus.');
        } catch (Throwable $exception) {
            flash('error', 'Kriteria tidak bisa dihapus karena masih dipakai pertanyaan atau hasil SPK.');
        }
        redirect('admin/kriteria.php');
    }

    $id = (int) ($_POST['id'] ?? 0);
    $kode = strtoupper(trim((string) ($_POST['kode'] ?? '')));
    $nama = trim((string) ($_POST['nama_kriteria'] ?? ''));
    $bobotPersen = trim((string) ($_POST['bobot'] ?? ''));
    $jenis = (string) ($_POST['jenis'] ?? 'benefit');
    $keterangan = trim((string) ($_POST['keterangan'] ?? ''));

    if ($kode === '' || $nama === '' || $bobotPersen === '') {
        $errors[] = 'Kode, nama, dan bobot wajib diisi.';
    }
    if (!is_numeric($bobotPersen) || (float) $bobotPersen < 0 || (float) $bobotPersen > 100) {
        $errors[] = 'Bobot harus berupa angka 0 sampai 100 (persen).';
    }
    if (!in_array($jenis, ['benefit', 'cost'], true)) {
        $errors[] = 'Jenis kriteria tidak valid.';
    }

    if (!$errors) {
        $bobot = round(((float) $bobotPersen) / 100, 4);
        try {
            if ($id === 0) {
                $pdo->prepare('INSERT INTO kriteria (kode, nama_kriteria, bobot, jenis, keterangan) VALUES (?, ?, ?, ?, ?)')
                    ->execute([$kode, $nama, $bobot, $jenis, $keterangan !== '' ? $keterangan : null]);
                flash('success', 'Kriteria baru ditambahkan.');
            } else {
                $pdo->prepare('UPDATE kriteria SET kode = ?, nama_kriteria = ?, bobot = ?, jenis = ?, keterangan = ? WHERE id = ?')
                    ->execute([$kode, $nama, $bobot, $jenis, $keterangan !== '' ? $keterangan : null, $id]);
                flash('success', 'Kriteria diperbarui.');
            }
            redirect('admin/kriteria.php');
        } catch (Throwable $exception) {
            $errors[] = 'Gagal menyimpan kriteria: ' . $exception->getMessage();
        }
    }
}

$edit = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM kriteria WHERE id = ? LIMIT 1');
    $stmt->execute([(int) $_GET['edit']]);
    $edit = $stmt->fetch() ?: null;
}

$daftar = $pdo->query('SELECT * FROM kriteria ORDER BY id')->fetchAll();
$total = total_bobot();

$page_title = 'Data Kriteria';
$active = 'kriteria';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <div>
        <h1>Data Kriteria</h1>
        <p>Bobot ditampilkan dalam persen dan dipakai pada perhitungan SAW.</p>
    </div>
    <a class="btn" href="<?= e(base_url('admin/kriteria.php')) ?>">+ Tambah Kriteria</a>
</div>

<?php foreach ($errors as $error): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
<?php endforeach; ?>

<div class="card mb-2">
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr><th>Kode</th><th>Nama Kriteria</th><th>Bobot (%)</th><th>Jenis</th><th>Keterangan</th><th>Aksi</th></tr>
            </thead>
            <tbody>
                <?php foreach ($daftar as $row): ?>
                    <tr>
                        <td><b><?= e($row['kode']) ?></b></td>
                        <td><?= e($row['nama_kriteria']) ?></td>
                        <td>
                            <span class="badge brand"><?= e(pct($row['bobot'], 0)) ?></span>
                            <div class="mini-bar" style="width:70px;">
                                <span style="width: <?= min(100, (float)$row['bobot'] * 100) ?>%;"></span>
                            </div>
                        </td>
                        <td><span class="badge"><?= e(ucfirst($row['jenis'])) ?></span></td>
                        <td class="small muted"><?= e($row['keterangan'] ?? '') ?></td>
                        <td>
                            <div class="table-actions">
                                <a href="<?= e(base_url('admin/kriteria.php?edit=' . (int) $row['id'])) ?>">Edit</a>
                                <form class="inline-form" method="post" action="<?= e(base_url('admin/kriteria.php')) ?>" data-confirm="Hapus kriteria ini?">
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
            <tfoot>
                <tr>
                    <td colspan="2">Total bobot</td>
                    <td><?= e(pct($total, 0)) ?></td>
                    <td colspan="3"></td>
                </tr>
            </tfoot>
        </table>
    </div>
    <?php if (abs($total - 1.0) > 0.0001): ?>
        <div class="note warn mt-2">Total bobot saat ini <b><?= e(pct($total, 2)) ?></b>, sebaiknya 100% agar perhitungan SAW ideal.</div>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-head">
        <h3><?= $edit ? 'Edit Kriteria' : 'Tambah Kriteria' ?></h3>
        <?php if ($edit): ?>
            <a class="btn btn-ghost btn-sm" href="<?= e(base_url('admin/kriteria.php')) ?>">Batal edit</a>
        <?php endif; ?>
    </div>

    <form method="post" action="<?= e(base_url('admin/kriteria.php')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="<?= $edit ? 'update' : 'create' ?>">
        <input type="hidden" name="id" value="<?= $edit ? e((string) $edit['id']) : '0' ?>">

        <div class="form-grid">
            <div class="field">
                <label for="kode">Kode</label>
                <input id="kode" name="kode" type="text" maxlength="5" required value="<?= e($edit['kode'] ?? '') ?>" placeholder="C6">
            </div>
            <div class="field">
                <label for="nama_kriteria">Nama Kriteria</label>
                <input id="nama_kriteria" name="nama_kriteria" type="text" required value="<?= e($edit['nama_kriteria'] ?? '') ?>">
            </div>
            <div class="field">
                <label for="bobot">Bobot (%)</label>
                <input id="bobot" name="bobot" type="number" min="0" max="100" step="0.01" required
                       value="<?= e($edit ? rtrim(rtrim(number_format((float) $edit['bobot'] * 100, 2, '.', ''), '0'), '.') : '') ?>"
                       placeholder="25">
                <span class="help">Masukkan angka persen, contoh 25 untuk 25%.</span>
            </div>
            <div class="field">
                <label for="jenis">Jenis</label>
                <select id="jenis" name="jenis">
                    <option value="benefit" <?= ($edit['jenis'] ?? '') === 'benefit' ? 'selected' : '' ?>>Benefit</option>
                    <option value="cost" <?= ($edit['jenis'] ?? '') === 'cost' ? 'selected' : '' ?>>Cost</option>
                </select>
            </div>
        </div>
        <div class="field">
            <label for="keterangan">Keterangan</label>
            <textarea id="keterangan" name="keterangan" rows="2"><?= e($edit['keterangan'] ?? '') ?></textarea>
        </div>

        <div class="form-actions">
            <button class="btn" type="submit"><?= $edit ? 'Simpan Perubahan' : 'Tambah Kriteria' ?></button>
        </div>
    </form>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
