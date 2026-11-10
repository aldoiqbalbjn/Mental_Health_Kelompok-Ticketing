<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/saw.php';

require_role('admin');

$pdo = db();

$q = trim((string) ($_GET['q'] ?? ''));
$kategoriFilter = trim((string) ($_GET['kategori'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 25;

$where = [];
$params = [];

if ($q !== '') {
    $where[] = '(m.nama LIKE ? OR m.nim LIKE ?)';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}
if (in_array($kategoriFilter, ['Prioritas dukungan', 'Perlu perhatian'], true)) {
    $where[] = 'h.kategori = ?';
    $params[] = $kategoriFilter;
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$baseFrom = 'FROM hasil_spk h
             JOIN pengisian p ON p.id = h.pengisian_id
             JOIN mahasiswa m ON m.id = p.mahasiswa_id';

$countStmt = $pdo->prepare("SELECT COUNT(*) $baseFrom $whereSql");
$countStmt->execute($params);
$totalRows = (int) $countStmt->fetchColumn();

$totalPages = max(1, (int) ceil($totalRows / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$sql = "SELECT h.id, h.nilai_akhir, h.kategori, p.tanggal, m.nama, m.nim, m.prodi
        $baseFrom
        $whereSql
        ORDER BY p.tanggal DESC, h.id DESC
        LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$daftar = $stmt->fetchAll();

$kriteria = get_kriteria();

// Ambil detail kriteria untuk data yang sedang tampil di halaman ini
$hasilIds = array_map(static fn($r) => (int) $r['id'], $daftar);
$detailMap = [];
if ($hasilIds) {
    $placeholders = implode(',', array_fill(0, count($hasilIds), '?'));
    $dStmt = $pdo->prepare(
        "SELECT d.hasil_id, d.kriteria_id, d.nilai_kriteria, d.kontribusi, k.kode, k.bobot
         FROM detail_hasil_spk d
         JOIN kriteria k ON k.id = d.kriteria_id
         WHERE d.hasil_id IN ($placeholders)
         ORDER BY k.id"
    );
    $dStmt->execute($hasilIds);
    foreach ($dStmt->fetchAll() as $d) {
        $detailMap[(int) $d['hasil_id']][] = $d;
    }
}

$page_title = 'Hasil SPK';
$active = 'hasil';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <div>
        <h1>Data Hasil SPK</h1>
        <p>Semua hasil perhitungan SAW. Bobot tiap kriteria ditampilkan dalam persen.</p>
    </div>
    <div class="page-head-actions">
        <a class="btn btn-ghost" href="<?= e(base_url('admin/laporan.php')) ?>">Laporan</a>
    </div>
</div>

<div class="card mb-2">
    <form method="get" action="<?= e(base_url('admin/hasil.php')) ?>" class="form-grid" style="align-items:flex-end;">
        <div class="field" style="margin:0;">
            <label for="q">Cari Mahasiswa</label>
            <input id="q" name="q" type="search" value="<?= e($q) ?>" placeholder="Nama / NIM">
        </div>
        <div class="field" style="margin:0;">
            <label for="kategori">Kategori</label>
            <select id="kategori" name="kategori">
                <option value="">Semua</option>
                <option value="Prioritas dukungan" <?= $kategoriFilter === 'Prioritas dukungan' ? 'selected' : '' ?>>Prioritas dukungan</option>
                <option value="Perlu perhatian" <?= $kategoriFilter === 'Perlu perhatian' ? 'selected' : '' ?>>Perlu perhatian</option>
            </select>
        </div>
        <div class="field" style="margin:0;">
            <label>&nbsp;</label>
            <button class="btn" type="submit">Terapkan Filter</button>
        </div>
    </form>
</div>

<div class="card">
    <div class="card-head">
        <h3>Daftar Hasil</h3>
        <span class="small muted"><?= e((string) $totalRows) ?> data</span>
    </div>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Mahasiswa</th>
                    <th>Tanggal</th>
                    <th>Kontribusi Tiap Kriteria (%)</th>
                    <th style="min-width:140px;">Skor Akhir SPK (%)</th>
                    <th>Kategori</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$daftar): ?>
                    <tr><td colspan="6" class="text-center muted">Tidak ada data.</td></tr>
                <?php endif; ?>
                <?php foreach ($daftar as $row): ?>
                    <?php
                    $skorAkhir = (float) $row['nilai_akhir'];
                    $skorPct = $skorAkhir * 100;
                    $rincian = $detailMap[(int) $row['id']] ?? [];
                    ?>
                    <tr>
                        <td>
                            <b><?= e($row['nama']) ?></b><br>
                            <span class="small muted"><?= e($row['nim']) ?> · <?= e($row['prodi']) ?></span>
                        </td>
                        <td class="nowrap"><?= e(date('d M Y', strtotime($row['tanggal']))) ?></td>
                        <td>
                            <div class="chip-row">
                                <?php if ($rincian): ?>
                                    <?php foreach ($rincian as $item): ?>
                                        <?php
                                        $kNilai = (float) $item['nilai_kriteria'];
                                        $kPct = ($kNilai / 5.0) * 100;
                                        $kKontribusi = (float) $item['kontribusi'];
                                        ?>
                                        <span class="badge <?= $kPct >= 60 ? 'warn' : '' ?>" title="<?= e($item['kode']) ?>: Tekanan <?= number_format($kPct, 1, ',', '.') ?>% (Kontribusi <?= e(pct($kKontribusi)) ?>)">
                                            <?= e($item['kode']) ?>: <?= e(pct($kKontribusi, 1)) ?>
                                        </span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="muted small">—</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:3px;">
                                <b class="mono"><?= e(pct($row['nilai_akhir'])) ?></b>
                                <span class="small muted"><?= $skorPct >= 60 ? '≥ 60%' : '< 60%' ?></span>
                            </div>
                            <div class="mini-bar">
                                <span style="width: <?= min(100, max(0, $skorPct)) ?>%; background: <?= $skorPct >= 60 ? '#c07a36' : '#2f7d63' ?>;"></span>
                            </div>
                        </td>
                        <td><span class="badge <?= e(badge_class($row['kategori'])) ?>"><?= e($row['kategori']) ?></span></td>
                        <td><a class="btn btn-ghost btn-sm" href="<?= e(base_url('admin/detail.php?id=' . (int) $row['id'])) ?>">Detail SAW</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
        <?php
        $queryBase = ['q' => $q, 'kategori' => $kategoriFilter];
        $buildUrl = static function (int $target) use ($queryBase): string {
            $queryBase['page'] = $target;
            return base_url('admin/hasil.php?' . http_build_query($queryBase));
        };
        ?>
        <div class="flex-between mt-2">
            <span class="small muted">Halaman <?= e((string) $page) ?> dari <?= e((string) $totalPages) ?></span>
            <div class="page-head-actions">
                <?php if ($page > 1): ?>
                    <a class="btn btn-ghost btn-sm" href="<?= e($buildUrl($page - 1)) ?>">Sebelumnya</a>
                <?php endif; ?>
                <?php if ($page < $totalPages): ?>
                    <a class="btn btn-ghost btn-sm" href="<?= e($buildUrl($page + 1)) ?>">Berikutnya</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
