<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/saw.php';

require_role('mahasiswa');

$mhs = current_mahasiswa();
if (!$mhs) {
    flash('error', 'Data mahasiswa tidak ditemukan.');
    redirect('index.php');
}

$pengisianId = isset($_GET['pengisian_id']) ? (int) $_GET['pengisian_id'] : 0;

$sql = 'SELECT p.*, h.id AS hasil_id, h.nilai_akhir, h.kategori, h.tanggal_hitung
        FROM pengisian p
        LEFT JOIN hasil_spk h ON h.pengisian_id = p.id
        WHERE p.mahasiswa_id = ?';
$params = [$mhs['id']];

if ($pengisianId > 0) {
    $sql .= ' AND p.id = ?';
    $params[] = $pengisianId;
}

$sql .= ' ORDER BY p.tanggal DESC LIMIT 1';

$stmt = db()->prepare($sql);
$stmt->execute($params);
$pengisian = $stmt->fetch();

if ($pengisian && $pengisian['hasil_id'] === null) {
    try {
        saw_hitung((int) $pengisian['id']);
        $stmt->execute($params);
        $pengisian = $stmt->fetch();
    } catch (Throwable $exception) {
        flash('error', 'Gagal menghitung SAW: ' . $exception->getMessage());
    }
}

$detail = $pengisian && $pengisian['hasil_id'] ? saw_detail_rows((int) $pengisian['hasil_id']) : [];
$jawaban = $pengisian ? saw_jawaban_rows((int) $pengisian['id']) : [];
$rekomendasi = $pengisian && $pengisian['hasil_id'] ? saw_rekomendasi((int) $pengisian['hasil_id']) : null;

$page_title = 'Hasil Skrining';
$active = 'riwayat';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <div>
        <h1>Hasil Skrining &amp; Rincian SAW</h1>
        <p>
            <?php if ($pengisian): ?>
                Pengisian <?= e(date('d M Y H:i', strtotime($pengisian['tanggal']))) ?> · Metode Simple Additive Weighting
            <?php else: ?>
                Belum ada pengisian kuesioner.
            <?php endif; ?>
        </p>
    </div>
    <div class="page-head-actions">
        <a class="btn btn-ghost" href="<?= e(base_url('mahasiswa/riwayat.php')) ?>">Kembali</a>
        <a class="btn" href="<?= e(base_url('mahasiswa/kuesioner.php')) ?>">Isi Ulang</a>
    </div>
</div>

<?php if (!$pengisian || !$pengisian['hasil_id']): ?>
    <div class="card">
        <div class="empty">
            <h3>Hasil belum tersedia</h3>
            <p>Isi kuesioner terlebih dahulu agar sistem dapat menghitung skor SAW.</p>
            <a class="btn" href="<?= e(base_url('mahasiswa/kuesioner.php')) ?>">Mulai Kuesioner</a>
        </div>
    </div>
<?php else: ?>
    <?php
    $nilaiAkhir = (float) $pengisian['nilai_akhir'];
    $nilaiAkhirPct = $nilaiAkhir * 100;
    $isPrioritas = $pengisian['kategori'] === 'Prioritas dukungan';
    ?>

    <!-- Gauge Meter Persentase Skor SPK -->
    <div class="spk-meter-card">
        <div class="spk-meter-head">
            <div>
                <span class="label" style="font-size:11px; text-transform:uppercase; letter-spacing:.8px; color:var(--muted);">Skor Akhir SPK (SAW)</span>
                <div class="spk-score-big <?= $isPrioritas ? 'warn' : 'ok' ?>">
                    <?= e(pct($nilaiAkhir)) ?>
                    <span class="badge <?= e(badge_class($pengisian['kategori'])) ?>" style="font-size:13px; font-weight:600;"><?= e($pengisian['kategori']) ?></span>
                </div>
            </div>
            <div style="text-align:right;">
                <span class="small muted">Standar Ambang Batas: <b>60,00%</b></span><br>
                <span class="small <?= $isPrioritas ? 'color-warn' : 'color-ok' ?>">
                    <?= $isPrioritas ? 'Melebihi batas aman (butuh pendampingan/konseling)' : 'Di bawah batas ambang (kondisi umum terkelola)' ?>
                </span>
            </div>
        </div>
        <div class="spk-meter-track">
            <div class="spk-meter-fill" style="width: <?= min(100, max(0, $nilaiAkhirPct)) ?>%;"></div>
            <div class="spk-meter-marker" style="left: 60%;"></div>
        </div>
        <div class="spk-meter-legend">
            <span>0% (Sangat Rendah)</span>
            <span style="font-weight:600;">Ambang Evaluasi (60%)</span>
            <span>100% (Sangat Tinggi)</span>
        </div>
    </div>

    <!-- Ringkasan Statistik -->
    <div class="grid grid-3 mb-2">
        <div class="card stat">
            <span class="label">Total Nilai Akhir</span>
            <span class="value"><?= e(pct($pengisian['nilai_akhir'])) ?></span>
            <span class="hint">Σ kontribusi dari <?= e((string) count($detail)) ?> kriteria</span>
        </div>
        <div class="card stat">
            <span class="label">Status Evaluasi</span>
            <span class="value" style="font-size:20px;"><?= e($pengisian['kategori']) ?></span>
            <span class="badge <?= e(badge_class($pengisian['kategori'])) ?>">Batas 60%</span>
        </div>
        <div class="card stat">
            <span class="label">Total Bobot Sistem</span>
            <span class="value"><?= e(pct(total_bobot(), 0)) ?></span>
            <span class="hint">Tervalidasi 100%</span>
        </div>
    </div>

    <!-- Visualisasi Persentase Tiap Data Kriteria -->
    <div class="card mb-2">
        <div class="card-head">
            <div>
                <h3>Persentase Capaian &amp; Kontribusi Tiap Kriteria</h3>
                <p class="card-sub" style="margin:0;">Melihat tingkat tekanan dan kontribusi masing-masing kriteria terhadap skor akhir.</p>
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

    <!-- Tabel Rincian Matriks Normalisasi SAW -->
    <div class="card mb-2">
        <div class="card-head">
            <div>
                <h3>Matriks Perhitungan SAW Lengkap</h3>
                <p class="card-sub" style="margin:0;">Tahapan normalisasi nilai dan perhitungan kontribusi berbobot.</p>
            </div>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Kriteria</th>
                        <th>Bobot (%)</th>
                        <th>Nilai Rata-rata (1-5)</th>
                        <th>Tingkat (%)</th>
                        <th>Nilai Normalisasi (R)</th>
                        <th>Kontribusi (W × R)</th>
                        <th>Kontribusi (%)</th>
                        <th>Porsi ke Skor Akhir (%)</th>
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
                            <td class="mono"><?= e(num($row['nilai_kriteria'], 2)) ?></td>
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
                        <td class="mono" style="font-size:15px; font-weight:700; color:var(--brand-dark);"><?= e(pct($pengisian['nilai_akhir'])) ?></td>
                        <td class="mono"><b>100%</b></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <p class="small muted mt-1 mb-0">
            * Rumus: Normalisasi benefit = $R_{ij} = X_{ij} / 5$. Kontribusi = $W_j \times R_{ij}$. Nilai akhir = $\sum (W_j \times R_{ij})$.
        </p>
    </div>

    <div class="card mb-2">
        <div class="card-head"><h3>Rekomendasi Tindak Lanjut</h3></div>
        <div class="note" style="font-size:14px; line-height:1.6;"><?= e($rekomendasi ?: rekomendasi_teks($pengisian['kategori'])) ?></div>
    </div>

    <div class="card">
        <div class="card-head"><h3>Rincian Jawaban Kuesioner</h3></div>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr><th>Kriteria</th><th>Pertanyaan</th><th class="text-center">Nilai (1-5)</th><th class="text-center">Persentase</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($jawaban as $item): ?>
                        <?php $itemPct = ((int) $item['nilai'] / 5) * 100; ?>
                        <tr>
                            <td class="nowrap"><span class="badge brand"><?= e($item['kode']) ?></span></td>
                            <td><?= e($item['pertanyaan']) ?></td>
                            <td class="text-center mono"><b><?= e((string) $item['nilai']) ?></b></td>
                            <td class="text-center mono"><?= e((string) $itemPct) ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
