<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/saw.php';

require_role('mahasiswa');

$mhs = current_mahasiswa();
if (!$mhs) {
    flash('error', 'Data mahasiswa tidak ditemukan.');
    redirect('index.php');
}

$stmt = db()->prepare(
    'SELECT p.id, p.tanggal, p.status, h.nilai_akhir, h.kategori
     FROM pengisian p
     LEFT JOIN hasil_spk h ON h.pengisian_id = p.id
     WHERE p.mahasiswa_id = ?
     ORDER BY p.tanggal DESC'
);
$stmt->execute([$mhs['id']]);
$riwayat = $stmt->fetchAll();

$page_title = 'Riwayat';
$active = 'riwayat';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <div>
        <h1>Riwayat Pengisian</h1>
        <p>Semua hasil skrining yang pernah kamu isi.</p>
    </div>
    <a class="btn" href="<?= e(base_url('mahasiswa/kuesioner.php')) ?>">Isi Kuesioner Baru</a>
</div>

<div class="card">
    <?php if (!$riwayat): ?>
        <div class="empty">
            <h3>Belum ada riwayat</h3>
            <p>Pengisian kuesioner pertamamu akan tampil di sini.</p>
            <a class="btn" href="<?= e(base_url('mahasiswa/kuesioner.php')) ?>">Mulai Kuesioner</a>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Tanggal</th>
                        <th>Status</th>
                        <th style="min-width:140px;">Nilai Akhir SPK (%)</th>
                        <th>Kategori</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($riwayat as $index => $item): ?>
                        <?php
                        $hasSkor = $item['nilai_akhir'] !== null;
                        $skorVal = $hasSkor ? (float) $item['nilai_akhir'] : 0.0;
                        $skorPct = $skorVal * 100;
                        ?>
                        <tr>
                            <td><?= e((string) ($index + 1)) ?></td>
                            <td class="nowrap"><?= e(date('d M Y H:i', strtotime($item['tanggal']))) ?></td>
                            <td>
                                <span class="badge <?= e($item['status'] === 'selesai' ? 'ok' : '') ?>">
                                    <?= e(ucfirst($item['status'])) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($hasSkor): ?>
                                    <b class="mono"><?= e(pct($skorVal)) ?></b>
                                    <div class="mini-bar">
                                        <span style="width: <?= min(100, max(0, $skorPct)) ?>%; background: <?= $skorPct >= 60 ? '#c07a36' : '#2f7d63' ?>;"></span>
                                    </div>
                                <?php else: ?>
                                    <span class="muted small">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($item['kategori'] !== null): ?>
                                    <span class="badge <?= e(badge_class($item['kategori'])) ?>"><?= e($item['kategori']) ?></span>
                                <?php else: ?>
                                    <span class="badge">Belum dihitung</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a class="btn btn-ghost btn-sm" href="<?= e(base_url('mahasiswa/hasil.php?pengisian_id=' . (int) $item['id'])) ?>">Lihat Detail &rarr;</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
