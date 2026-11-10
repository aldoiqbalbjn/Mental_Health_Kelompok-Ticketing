<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/saw.php';

require_role('admin');

$id = (int) ($_GET['id'] ?? 0);

$stmt = db()->prepare(
    'SELECT h.*, p.tanggal, p.id AS pengisian_id, m.nama, m.nim, m.prodi, m.angkatan
     FROM hasil_spk h
     JOIN pengisian p ON p.id = h.pengisian_id
     JOIN mahasiswa m ON m.id = p.mahasiswa_id
     WHERE h.id = ?
     LIMIT 1'
);
$stmt->execute([$id]);
$hasil = $stmt->fetch();

if (!$hasil) {
    flash('error', 'Hasil SPK tidak ditemukan.');
    redirect('admin/hasil.php');
}

$detail = saw_detail_rows($id);
$jawaban = saw_jawaban_rows((int) $hasil['pengisian_id']);
$rekomendasi = saw_rekomendasi($id);

$page_title = 'Detail SAW';
$active = 'hasil';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <div>
        <h1>Detail Perhitungan SAW</h1>
        <p>
            <?= e($hasil['nama']) ?> · <?= e($hasil['nim']) ?> · <?= e($hasil['prodi']) ?>
            · <?= e(date('d M Y H:i', strtotime($hasil['tanggal']))) ?>
        </p>
    </div>
    <div class="page-head-actions">
        <a class="btn btn-ghost" href="<?= e(base_url('admin/hasil.php')) ?>">Kembali</a>
    </div>
</div>

<?php
$nilaiAkhir = (float) $hasil['nilai_akhir'];
$nilaiAkhirPct = $nilaiAkhir * 100;
$isPrioritas = $hasil['kategori'] === 'Prioritas dukungan';
?>

<!-- Gauge Meter Persentase Skor SPK -->
<div class="spk-meter-card">
    <div class="spk-meter-head">
        <div>
            <span class="label" style="font-size:11px; text-transform:uppercase; letter-spacing:.8px; color:var(--muted);">Skor Akhir SPK SAW Mahasiswa</span>
            <div class="spk-score-big <?= $isPrioritas ? 'warn' : 'ok' ?>">
                <?= e(pct($nilaiAkhir)) ?>
                <span class="badge <?= e(badge_class($hasil['kategori'])) ?>" style="font-size:13px; font-weight:600;"><?= e($hasil['kategori']) ?></span>
            </div>
        </div>
        <div style="text-align:right;">
            <span class="small muted">Standar Ambang Batas: <b>60,00%</b></span><br>
            <span class="small <?= $isPrioritas ? 'color-warn' : 'color-ok' ?>">
                <?= $isPrioritas ? 'Mahasiswa membutuhkan prioritas pendampingan/konseling' : 'Skor dalam kategori perlu perhatian (rutin terkelola)' ?>
            </span>
        </div>
    </div>
    <div class="spk-meter-track">
        <div class="spk-meter-fill" style="width: <?= min(100, max(0, $nilaiAkhirPct)) ?>%;"></div>
        <div class="spk-meter-marker" style="left: 60%;"></div>
    </div>
    <div class="spk-meter-legend">
        <span>0% (Sangat Rendah)</span>
        <span style="font-weight:600;">Garis Batas Ambang (60%)</span>
        <span>100% (Sangat Tinggi)</span>
    </div>
</div>

<div class="grid grid-3 mb-2">
    <div class="card stat">
        <span class="label">Nilai Akhir Total</span>
        <span class="value"><?= e(pct($hasil['nilai_akhir'])) ?></span>
        <span class="hint">Σ kontribusi seluruh kriteria</span>
    </div>
    <div class="card stat">
        <span class="label">Kategori Rekomendasi</span>
        <span class="value" style="font-size:20px;"><?= e($hasil['kategori']) ?></span>
        <span class="badge <?= e(badge_class($hasil['kategori'])) ?>">Batas 60%</span>
    </div>
    <div class="card stat">
        <span class="label">Tanggal Hitung SAW</span>
        <span class="value" style="font-size:18px;"><?= e(date('d M Y H:i', strtotime($hasil['tanggal_hitung']))) ?></span>
        <span class="hint">Tersimpan otomatis</span>
    </div>
</div>

<!-- Visualisasi Persentase Data Tiap Kriteria -->
<div class="card mb-2">
    <div class="card-head">
        <div>
            <h3>Visualisasi Capaian &amp; Kontribusi per Kriteria</h3>
            <p class="card-sub" style="margin:0;">Persentase tingkat tekanan mahasiswa per kriteria dan kontribusinya terhadap nilai akhir.</p>
        </div>
    </div>
    <div class="criteria-cards-grid mt-2">
        <?php foreach ($detail as $row): ?>
            <?php
            $nilaiKriteria = (float) $row['nilai_kriteria'];
            $capaianPct = ($nilaiKriteria / 5.0) * 100;
            $kontribusi = (float) $row['kontribusi'];
            $kontribusiPct = $kontribusi * 100;
            $porsiPct = $nilaiAkhir > 0 ? ($kontribusi / $nilaiAkhir) * 100 : 0;
            ?>
            <div class="criteria-box">
                <div class="criteria-box-head">
                    <div>
                        <span class="badge brand" style="font-size:10.5px;"><?= e($row['kode']) ?></span>
                        <div class="criteria-box-title mt-1"><?= e($row['nama_kriteria']) ?></div>
                    </div>
                    <span class="badge" title="Bobot"><?= e(pct($row['bobot'], 0)) ?></span>
                </div>

                <div class="bar-row" style="margin-bottom:6px;">
                    <div class="bar-label">
                        <span class="small muted">Tingkat Tekanan</span>
                        <b><?= e(number_format($capaianPct, 1, ',', '.')) ?>%</b>
                    </div>
                    <div class="bar <?= $capaianPct >= 60 ? 'warn' : 'ok' ?>">
                        <span style="width: <?= min(100, max(0, $capaianPct)) ?>%"></span>
                    </div>
                </div>

                <div class="criteria-box-stats">
                    <div class="stat-item">
                        <small>Rata-rata</small>
                        <strong><?= e(num($nilaiKriteria, 2)) ?> / 5</strong>
                    </div>
                    <div class="stat-item">
                        <small>Kontribusi</small>
                        <strong><?= e(number_format($kontribusiPct, 2, ',', '.')) ?>%</strong>
                    </div>
                    <div class="stat-item">
                        <small>Porsi Skor</small>
                        <strong><?= e(number_format($porsiPct, 1, ',', '.')) ?>%</strong>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="card mb-2">
    <div class="card-head">
        <div>
            <h3>Tabel Matriks Normalisasi &amp; Kontribusi SAW</h3>
            <p class="card-sub" style="margin:0;">Rincian perhitungan matematis metode Simple Additive Weighting.</p>
        </div>
    </div>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Kode</th>
                    <th>Kriteria</th>
                    <th>Bobot (%)</th>
                    <th>Nilai Kriteria (1-5)</th>
                    <th>Tingkat (%)</th>
                    <th>Normalisasi (R)</th>
                    <th>Kontribusi (W × R)</th>
                    <th>Kontribusi (%)</th>
                    <th>Porsi ke Skor (%)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($detail as $row): ?>
                    <?php
                    $nilaiKriteria = (float) $row['nilai_kriteria'];
                    $capaianPct = ($nilaiKriteria / 5.0) * 100;
                    $kontribusi = (float) $row['kontribusi'];
                    $porsiPct = $nilaiAkhir > 0 ? ($kontribusi / $nilaiAkhir) * 100 : 0;
                    ?>
                    <tr>
                        <td><b><?= e($row['kode']) ?></b></td>
                        <td><?= e($row['nama_kriteria']) ?></td>
                        <td><span class="badge brand"><?= e(pct($row['bobot'], 0)) ?></span></td>
                        <td class="mono"><?= e(num($row['nilai_kriteria'], 4)) ?></td>
                        <td class="mono"><b><?= e(number_format($capaianPct, 1, ',', '.')) ?>%</b></td>
                        <td class="mono"><?= e(num($row['nilai_normalisasi'], 4)) ?></td>
                        <td class="mono"><?= e(num($row['kontribusi'], 4)) ?></td>
                        <td class="mono"><b><?= e(pct($row['kontribusi'])) ?></b></td>
                        <td class="mono muted"><?= e(number_format($porsiPct, 1, ',', '.')) ?>%</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="7" class="text-right"><b>Nilai Akhir Total (Σ Kontribusi):</b></td>
                    <td class="mono" style="font-size:15px; font-weight:700; color:var(--brand-dark);"><?= e(pct($hasil['nilai_akhir'])) ?></td>
                    <td class="mono"><b>100%</b></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<div class="grid grid-2">
    <div class="card">
        <div class="card-head"><h3>Jawaban Kuesioner</h3></div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Kriteria</th><th>Pertanyaan</th><th class="text-center">Nilai</th></tr></thead>
                <tbody>
                    <?php foreach ($jawaban as $item): ?>
                        <tr>
                            <td class="nowrap"><?= e($item['kode']) ?></td>
                            <td><?= e($item['pertanyaan']) ?></td>
                            <td class="text-center mono"><?= e((string) $item['nilai']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h3>Rekomendasi</h3></div>
        <div class="note"><?= e($rekomendasi ?: rekomendasi_teks($hasil['kategori'])) ?></div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
