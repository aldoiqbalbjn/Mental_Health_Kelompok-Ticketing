<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/saw.php';

require_role('admin');

$pdo = db();
$kriteria = get_kriteria();

if (($_GET['export'] ?? '') === 'csv') {
    $rows = $pdo->query(
        'SELECT h.id, h.nilai_akhir, h.kategori, p.tanggal, m.nim, m.nama, m.prodi
         FROM hasil_spk h
         JOIN pengisian p ON p.id = h.pengisian_id
         JOIN mahasiswa m ON m.id = p.mahasiswa_id
         ORDER BY p.tanggal DESC, h.id DESC'
    )->fetchAll();

    $filename = 'laporan_spk_' . date('Ymd_His') . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');
    fwrite($output, "\xEF\xBB\xBF");

    $header = ['NIM', 'Nama', 'Prodi', 'Tanggal', 'Nilai Akhir (%)', 'Kategori'];
    foreach ($kriteria as $k) {
        $header[] = $k['kode'] . ' Nilai';
        $header[] = $k['kode'] . ' Bobot (%)';
        $header[] = $k['kode'] . ' Kontribusi (%)';
    }
    fputcsv($output, $header);

    foreach ($rows as $row) {
        $line = [
            $row['nim'],
            $row['nama'],
            $row['prodi'],
            date('d-m-Y H:i', strtotime($row['tanggal'])),
            number_format((float) $row['nilai_akhir'] * 100, 2, ',', '.'),
            $row['kategori'],
        ];

        $detailByKriteria = [];
        foreach (saw_detail_rows((int) $row['id']) as $detail) {
            $detailByKriteria[(int) $detail['kriteria_id']] = $detail;
        }

        foreach ($kriteria as $k) {
            $detail = $detailByKriteria[(int) $k['id']] ?? null;
            $line[] = $detail ? number_format((float) $detail['nilai_kriteria'], 2, ',', '.') : '';
            $line[] = number_format((float) $k['bobot'] * 100, 2, ',', '.');
            $line[] = $detail ? number_format((float) $detail['kontribusi'] * 100, 2, ',', '.') : '';
        }

        fputcsv($output, $line);
    }

    fclose($output);
    exit;
}

$totalHasil = (int) $pdo->query('SELECT COUNT(*) FROM hasil_spk')->fetchColumn();
$rataRata = (float) ($pdo->query('SELECT AVG(nilai_akhir) FROM hasil_spk')->fetchColumn() ?: 0);

$distribusi = [];
foreach ($pdo->query('SELECT kategori, COUNT(*) AS jumlah FROM hasil_spk GROUP BY kategori')->fetchAll() as $row) {
    $distribusi[$row['kategori']] = (int) $row['jumlah'];
}

$perKriteria = $pdo->query(
    'SELECT k.kode, k.nama_kriteria, k.bobot, AVG(d.nilai_kriteria) AS rata_nilai, AVG(d.kontribusi) AS rata_kontribusi
     FROM detail_hasil_spk d
     JOIN kriteria k ON k.id = d.kriteria_id
     GROUP BY k.id, k.kode, k.nama_kriteria, k.bobot
     ORDER BY k.id'
)->fetchAll();

$perProdi = $pdo->query(
    'SELECT m.prodi,
            COUNT(DISTINCT m.id) AS jumlah_mahasiswa,
            AVG(h.nilai_akhir) AS rata_skor,
            SUM(CASE WHEN h.kategori = \'Prioritas dukungan\' THEN 1 ELSE 0 END) AS prioritas
     FROM mahasiswa m
     LEFT JOIN pengisian p ON p.mahasiswa_id = m.id
     LEFT JOIN hasil_spk h ON h.pengisian_id = p.id
     GROUP BY m.prodi
     ORDER BY m.prodi'
)->fetchAll();

$page_title = 'Laporan';
$active = 'laporan';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <div>
        <h1>Laporan Hasil SPK</h1>
        <p>Rekap distribusi kategori dan rata-rata skor.</p>
    </div>
    <div class="page-head-actions">
        <a class="btn btn-ghost" href="<?= e(base_url('admin/laporan.php?export=csv')) ?>">Export CSV</a>
        <a class="btn" href="<?= e(base_url('admin/laporan.php')) ?>" onclick="window.print(); return false;">Cetak</a>
    </div>
</div>

<div class="grid grid-3 mb-2">
    <div class="card stat"><span class="label">Total Hasil</span><span class="value"><?= e((string) $totalHasil) ?></span></div>
    <div class="card stat"><span class="label">Rata-rata Skor</span><span class="value"><?= e(pct($rataRata)) ?></span></div>
    <div class="card stat"><span class="label">Total Bobot Kriteria</span><span class="value"><?= e(pct(total_bobot(), 0)) ?></span></div>
</div>

<div class="grid grid-2">
    <div class="card">
        <div class="card-head"><h3>Distribusi Kategori</h3></div>
        <?php if (!$distribusi): ?>
            <div class="empty"><p>Belum ada hasil SPK.</p></div>
        <?php else: ?>
            <?php foreach ($distribusi as $kategori => $jumlah): ?>
                <?php $persen = $totalHasil > 0 ? ($jumlah / $totalHasil) * 100 : 0; ?>
                <div class="bar-row">
                    <div class="bar-label">
                        <span><?= e($kategori) ?> (<?= e((string) $jumlah) ?> orang)</span>
                        <b><?= e(number_format($persen, 1, ',', '.')) ?>%</b>
                    </div>
                    <div class="bar <?= $kategori === 'Prioritas dukungan' ? 'warn' : '' ?>">
                        <span style="width: <?= e(number_format($persen, 1, '.', '')) ?>%"></span>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-head"><h3>Rata-rata Capaian &amp; Kontribusi per Kriteria</h3></div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Kriteria</th><th>Bobot (%)</th><th>Rata-rata Nilai</th><th>Tingkat Tekanan (%)</th><th>Rata-rata Kontribusi</th></tr></thead>
                <tbody>
                    <?php foreach ($perKriteria as $row): ?>
                        <?php
                        $rNilai = (float) $row['rata_nilai'];
                        $rPct = ($rNilai / 5.0) * 100;
                        ?>
                        <tr>
                            <td><b><?= e($row['kode']) ?></b> · <?= e($row['nama_kriteria']) ?></td>
                            <td><span class="badge brand"><?= e(pct($row['bobot'], 0)) ?></span></td>
                            <td class="mono"><?= e(num($rNilai, 2)) ?> / 5</td>
                            <td>
                                <div style="display:flex; justify-content:space-between; margin-bottom:2px;">
                                    <b><?= e(number_format($rPct, 1, ',', '.')) ?>%</b>
                                </div>
                                <div class="mini-bar">
                                    <span style="width: <?= min(100, max(0, $rPct)) ?>%; background: <?= $rPct >= 60 ? '#c07a36' : '#2f7d63' ?>;"></span>
                                </div>
                            </td>
                            <td class="mono"><b><?= e(pct($row['rata_kontribusi'])) ?></b></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-head"><h3>Rekap per Program Studi</h3></div>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr><th>Prodi</th><th>Jumlah Mahasiswa</th><th>Rata-rata Skor</th><th>Prioritas Dukungan</th></tr>
            </thead>
            <tbody>
                <?php foreach ($perProdi as $row): ?>
                    <?php
                    $jumlah = (int) $row['jumlah_mahasiswa'];
                    $prioritas = (int) $row['prioritas'];
                    $persenPrioritas = $jumlah > 0 ? ($prioritas / $jumlah) * 100 : 0;
                    ?>
                    <tr>
                        <td><b><?= e($row['prodi']) ?></b></td>
                        <td><?= e((string) $jumlah) ?> orang</td>
                        <td class="mono"><?= $row['rata_skor'] !== null ? e(pct($row['rata_skor'])) : '—' ?></td>
                        <td>
                            <div style="display:flex; justify-content:space-between; margin-bottom:2px;">
                                <span><?= e((string) $prioritas) ?> orang</span>
                                <b><?= e(number_format($persenPrioritas, 1, ',', '.')) ?>%</b>
                            </div>
                            <div class="mini-bar">
                                <span style="width: <?= min(100, max(0, $persenPrioritas)) ?>%; background: <?= $persenPrioritas >= 50 ? '#c07a36' : '#2f7d63' ?>;"></span>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
