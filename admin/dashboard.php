<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/saw.php';

require_role('admin');

$pdo = db();

$totalMahasiswa = (int) $pdo->query('SELECT COUNT(*) FROM mahasiswa')->fetchColumn();
$totalPengisian = (int) $pdo->query('SELECT COUNT(*) FROM pengisian')->fetchColumn();
$totalHasil = (int) $pdo->query('SELECT COUNT(*) FROM hasil_spk')->fetchColumn();
$rataRata = (float) ($pdo->query('SELECT AVG(nilai_akhir) FROM hasil_spk')->fetchColumn() ?: 0);

$distribusi = [];
foreach ($pdo->query('SELECT kategori, COUNT(*) AS jumlah FROM hasil_spk GROUP BY kategori')->fetchAll() as $row) {
    $distribusi[$row['kategori']] = (int) $row['jumlah'];
}

$rataKriteria = $pdo->query(
    'SELECT k.kode, k.nama_kriteria, k.bobot,
            AVG(d.nilai_kriteria) AS rata_nilai,
            AVG(d.kontribusi) AS rata_kontribusi
     FROM detail_hasil_spk d
     JOIN kriteria k ON k.id = d.kriteria_id
     GROUP BY k.id, k.kode, k.nama_kriteria, k.bobot
     ORDER BY k.id'
)->fetchAll();

$recent = $pdo->query(
    'SELECT h.id, h.nilai_akhir, h.kategori, p.tanggal, m.nama, m.nim
     FROM hasil_spk h
     JOIN pengisian p ON p.id = h.pengisian_id
     JOIN mahasiswa m ON m.id = p.mahasiswa_id
     ORDER BY p.tanggal DESC, h.id DESC
     LIMIT 5'
)->fetchAll();

$page_title = 'Dashboard Admin';
$active = 'dashboard';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <div>
        <h1>Dashboard Admin</h1>
        <p>Ringkasan pengisian dan hasil SPK mahasiswa.</p>
    </div>
    <span class="badge brand">Administrator</span>
</div>

<div class="grid grid-4 mb-2">
    <div class="card stat"><span class="label">Mahasiswa</span><span class="value"><?= e((string) $totalMahasiswa) ?></span></div>
    <div class="card stat"><span class="label">Pengisian</span><span class="value"><?= e((string) $totalPengisian) ?></span></div>
    <div class="card stat"><span class="label">Hasil SPK</span><span class="value"><?= e((string) $totalHasil) ?></span></div>
    <div class="card stat"><span class="label">Rata-rata Skor</span><span class="value"><?= e(pct($rataRata)) ?></span></div>
</div>

<div class="grid grid-2 mb-2">
    <div class="card">
        <div class="card-head">
            <h3>Distribusi Kategori Mahasiswa</h3>
            <span class="small muted">Ambang batas 60%</span>
        </div>
        <?php if (!$distribusi): ?>
            <div class="empty"><p>Belum ada hasil SPK.</p></div>
        <?php else: ?>
            <?php foreach ($distribusi as $kategori => $jumlah): ?>
                <?php $persen = $totalHasil > 0 ? ($jumlah / $totalHasil) * 100 : 0; ?>
                <div class="bar-row">
                    <div class="bar-label">
                        <span>
                            <b><?= e($kategori) ?></b>
                            <span class="small muted">(<?= e((string) $jumlah) ?> mahasiswa)</span>
                        </span>
                        <b style="font-size:13px;"><?= e(number_format($persen, 1, ',', '.')) ?>%</b>
                    </div>
                    <div class="bar <?= $kategori === 'Prioritas dukungan' ? 'warn' : 'ok' ?>">
                        <span style="width: <?= e(number_format($persen, 1, '.', '')) ?>%"></span>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-head">
            <h3>Rata-rata Tingkat Tekanan per Kriteria (%)</h3>
            <span class="small muted">Skala normalisasi 0-100%</span>
        </div>
        <?php if (!$rataKriteria): ?>
            <div class="empty"><p>Belum ada data kriteria.</p></div>
        <?php else: ?>
            <?php foreach ($rataKriteria as $rk): ?>
                <?php
                $rataNilai = (float) $rk['rata_nilai'];
                $tingkatPct = ($rataNilai / 5.0) * 100;
                $rataKontribusi = (float) $rk['rata_kontribusi'];
                ?>
                <div class="bar-row" style="margin-bottom:11px;">
                    <div class="bar-label">
                        <span>
                            <span class="badge brand" style="padding:1px 6px; font-size:10px;"><?= e($rk['kode']) ?></span>
                            <?= e($rk['nama_kriteria']) ?>
                        </span>
                        <span>
                            <b><?= e(number_format($tingkatPct, 1, ',', '.')) ?>%</b>
                            <span class="small muted">(Bobot <?= e(pct($rk['bobot'], 0)) ?>)</span>
                        </span>
                    </div>
                    <div class="bar <?= $tingkatPct >= 60 ? 'warn' : '' ?>">
                        <span style="width: <?= min(100, max(0, $tingkatPct)) ?>%"></span>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<div class="grid grid-2 mb-2">
    <div class="card">
        <div class="card-head">
            <h3>Bobot Kriteria SPK (%)</h3>
            <span class="small muted">Total bobot = 100%</span>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Kode</th><th>Kriteria</th><th>Jenis</th><th>Bobot (%)</th></tr></thead>
                <tbody>
                    <?php foreach (get_kriteria() as $kriteria): ?>
                        <tr>
                            <td><b><?= e($kriteria['kode']) ?></b></td>
                            <td><?= e($kriteria['nama_kriteria']) ?></td>
                            <td><span class="badge"><?= e($kriteria['jenis']) ?></span></td>
                            <td><span class="badge brand"><?= e(pct($kriteria['bobot'], 0)) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3"><b>Total bobot seluruh kriteria:</b></td>
                        <td><b><?= e(pct(total_bobot(), 0)) ?></b></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <h3>Hasil Skrining Terbaru</h3>
            <a class="btn btn-ghost btn-sm" href="<?= e(base_url('admin/hasil.php')) ?>">Lihat semua</a>
        </div>

        <?php if (!$recent): ?>
            <div class="empty"><p>Belum ada hasil SPK.</p></div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr><th>Mahasiswa</th><th>Tanggal</th><th>Skor (%)</th><th>Kategori</th><th>Aksi</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent as $item): ?>
                            <?php $skorPct = (float) $item['nilai_akhir'] * 100; ?>
                            <tr>
                                <td><?= e($item['nama']) ?><br><span class="small muted"><?= e($item['nim']) ?></span></td>
                                <td class="nowrap"><?= e(date('d M Y', strtotime($item['tanggal']))) ?></td>
                                <td>
                                    <b class="mono"><?= e(pct($item['nilai_akhir'])) ?></b>
                                    <div class="mini-bar" style="width:70px;">
                                        <span style="width: <?= min(100, max(0, $skorPct)) ?>%; background: <?= $skorPct >= 60 ? '#c07a36' : '#2f7d63' ?>;"></span>
                                    </div>
                                </td>
                                <td><span class="badge <?= e(badge_class($item['kategori'])) ?>"><?= e($item['kategori']) ?></span></td>
                                <td><a class="btn btn-ghost btn-sm" href="<?= e(base_url('admin/detail.php?id=' . (int) $item['id'])) ?>">Detail</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
