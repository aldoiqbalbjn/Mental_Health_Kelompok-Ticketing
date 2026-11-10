<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/saw.php';

require_role('mahasiswa');

$mhs = current_mahasiswa();
if (!$mhs) {
    flash('error', 'Data mahasiswa tidak ditemukan.');
    redirect('index.php');
}

$latest = saw_hasil_terakhir((int) $mhs['id']);

$stmt = db()->prepare('SELECT COUNT(*) FROM pengisian WHERE mahasiswa_id = ?');
$stmt->execute([$mhs['id']]);
$totalPengisian = (int) $stmt->fetchColumn();

$stmt = db()->prepare(
    'SELECT p.id, p.tanggal, h.nilai_akhir, h.kategori
     FROM pengisian p
     LEFT JOIN hasil_spk h ON h.pengisian_id = p.id
     WHERE p.mahasiswa_id = ?
     ORDER BY p.tanggal DESC
     LIMIT 5'
);
$stmt->execute([$mhs['id']]);
$riwayat = $stmt->fetchAll();

$detail = $latest ? saw_detail_rows((int) $latest['id']) : [];

$page_title = 'Dashboard';
$active = 'dashboard';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <div>
        <h1>Halo, <?= e($mhs['nama']) ?></h1>
        <p>NIM <?= e($mhs['nim']) ?> · <?= e($mhs['prodi']) ?> · Angkatan <?= e($mhs['angkatan']) ?></p>
    </div>
    <div class="page-head-actions">
        <a class="btn" href="<?= e(base_url('mahasiswa/kuesioner.php')) ?>">Isi Kuesioner</a>
    </div>
</div>

<?php if ($latest): ?>
    <?php
    $latestSkor = (float) $latest['nilai_akhir'];
    $latestPct = $latestSkor * 100;
    $isPrioritas = $latest['kategori'] === 'Prioritas dukungan';
    ?>
    <!-- Meter Skor SPK Mahasiswa -->
    <div class="spk-meter-card">
        <div class="spk-meter-head">
            <div>
                <span class="label" style="font-size:11px; text-transform:uppercase; letter-spacing:.8px; color:var(--muted);">Skor Skrining Kesehatan Mental Terakhir (SAW)</span>
                <div class="spk-score-big <?= $isPrioritas ? 'warn' : 'ok' ?>">
                    <?= e(pct($latestSkor)) ?>
                    <span class="badge <?= e(badge_class($latest['kategori'])) ?>" style="font-size:13px; font-weight:600;"><?= e($latest['kategori']) ?></span>
                </div>
            </div>
            <div style="text-align:right;">
                <span class="small muted">Batas Evaluasi: <b>60,00%</b></span><br>
                <a class="btn btn-soft btn-sm mt-1" href="<?= e(base_url('mahasiswa/hasil.php?pengisian_id=' . (int) $latest['pengisian_id'])) ?>">Lihat Rincian Lengkap &amp; Matriks SAW &rarr;</a>
            </div>
        </div>
        <div class="spk-meter-track">
            <div class="spk-meter-fill" style="width: <?= min(100, max(0, $latestPct)) ?>%;"></div>
            <div class="spk-meter-marker" style="left: 60%;"></div>
        </div>
        <div class="spk-meter-legend">
            <span>0% (Stabil / Terkelola)</span>
            <span style="font-weight:600;">Ambang 60%</span>
            <span>100% (Prioritas Pendampingan)</span>
        </div>
    </div>
<?php endif; ?>

<div class="grid grid-3 mb-2">
    <div class="card stat">
        <span class="label">Skor SPK Terakhir</span>
        <?php if ($latest): ?>
            <span class="value"><?= e(pct($latest['nilai_akhir'])) ?></span>
            <span class="badge <?= e(badge_class($latest['kategori'])) ?>"><?= e($latest['kategori']) ?></span>
        <?php else: ?>
            <span class="value">—</span>
            <span class="hint">Belum ada pengisian</span>
        <?php endif; ?>
    </div>
    <div class="card stat">
        <span class="label">Total Pengisian</span>
        <span class="value"><?= e((string) $totalPengisian) ?> <small>kali</small></span>
        <span class="hint"><?= $latest ? 'Terakhir ' . e(date('d M Y', strtotime($latest['tanggal']))) : 'Mulai isi kuesioner pertamamu' ?></span>
    </div>
    <div class="card stat">
        <span class="label">Metode Penilaian</span>
        <span class="value" style="font-size:21px;">SAW (SPK)</span>
        <span class="hint">5 Kriteria Terbobot 100%</span>
    </div>
</div>

<div class="grid grid-2 mb-2">
    <div class="card">
        <div class="card-head">
            <h3>Bobot Kriteria SPK (%)</h3>
            <span class="small muted">Penetapan bobot tetap</span>
        </div>
        <p class="card-sub">Bobot kriteria menentukan proporsi pengaruh faktor pada skor akhir.</p>
        <div class="chip-row">
            <?php foreach (get_kriteria() as $kriteria): ?>
                <div class="chip">
                    <b><?= e(pct($kriteria['bobot'], 0)) ?></b>
                    <span><?= e($kriteria['kode']) ?> · <?= e($kriteria['nama_kriteria']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="note mt-2">Total bobot tervalidasi: <b><?= e(pct(total_bobot(), 0)) ?></b> (100%).</div>
    </div>

    <div class="card">
        <div class="card-head">
            <h3>Kontribusi Kriteria Terakhir</h3>
            <?php if ($latest): ?>
                <a class="btn btn-soft btn-sm" href="<?= e(base_url('mahasiswa/hasil.php?pengisian_id=' . (int) $latest['pengisian_id'])) ?>">Rincian SAW</a>
            <?php endif; ?>
        </div>

        <?php if (!$latest): ?>
            <div class="empty">
                <h3>Belum ada hasil</h3>
                <p>Isi kuesioner untuk melihat hasil skrining dan perhitungan SAW.</p>
                <a class="btn" href="<?= e(base_url('mahasiswa/kuesioner.php')) ?>">Mulai Kuesioner</a>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr><th>Kriteria</th><th>Bobot</th><th>Kontribusi (%)</th><th>Tingkat</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($detail as $row): ?>
                            <?php
                            $nKriteria = (float) $row['nilai_kriteria'];
                            $capaian = ($nKriteria / 5.0) * 100;
                            ?>
                            <tr>
                                <td><b><?= e($row['kode']) ?></b> · <?= e($row['nama_kriteria']) ?></td>
                                <td><span class="badge brand"><?= e(pct($row['bobot'], 0)) ?></span></td>
                                <td class="mono"><b><?= e(pct($row['kontribusi'])) ?></b></td>
                                <td>
                                    <div class="bar <?= $capaian >= 60 ? 'warn' : 'ok' ?>" style="height:6px; min-width:60px;">
                                        <span style="width: <?= min(100, max(0, $capaian)) ?>%;"></span>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="2"><b>Nilai Akhir Total (Σ)</b></td>
                            <td colspan="2" class="mono" style="font-weight:700; color:var(--brand-dark);"><?= e(pct($latest['nilai_akhir'])) ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-head">
        <h3>Riwayat Pengisian</h3>
        <a class="btn btn-ghost btn-sm" href="<?= e(base_url('mahasiswa/riwayat.php')) ?>">Lihat semua</a>
    </div>

    <?php if (!$riwayat): ?>
        <div class="empty">
            <h3>Belum ada riwayat</h3>
            <p>Pengisian kuesioner pertamamu akan tampil di sini.</p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr><th>Tanggal</th><th>Skor</th><th>Kategori</th><th>Aksi</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($riwayat as $item): ?>
                        <tr>
                            <td><?= e(date('d M Y H:i', strtotime($item['tanggal']))) ?></td>
                            <td class="mono"><?= $item['nilai_akhir'] !== null ? e(pct($item['nilai_akhir'])) : '—' ?></td>
                            <td>
                                <?php if ($item['kategori'] !== null): ?>
                                    <span class="badge <?= e(badge_class($item['kategori'])) ?>"><?= e($item['kategori']) ?></span>
                                <?php else: ?>
                                    <span class="badge">Belum dihitung</span>
                                <?php endif; ?>
                            </td>
                            <td><a href="<?= e(base_url('mahasiswa/hasil.php?pengisian_id=' . (int) $item['id'])) ?>">Detail</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
