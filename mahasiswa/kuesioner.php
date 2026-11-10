<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/saw.php';

require_role('mahasiswa');

$mhs = current_mahasiswa();
if (!$mhs) {
    flash('error', 'Data mahasiswa tidak ditemukan.');
    redirect('index.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $answers = $_POST['answers'] ?? [];
    if (!is_array($answers)) {
        $answers = [];
    }

    try {
        $pengisianId = saw_simpan_pengisian((int) $mhs['id'], $answers);
        flash('success', 'Kuesioner tersimpan dan hasil SAW berhasil dihitung.');
        redirect('mahasiswa/hasil.php?pengisian_id=' . $pengisianId);
    } catch (Throwable $exception) {
        $errors[] = $exception->getMessage();
    }
}

$questions = db()->query(
    'SELECT q.id, q.pertanyaan, q.urutan, k.kode, k.nama_kriteria, k.bobot
     FROM pertanyaan q
     JOIN kriteria k ON k.id = q.kriteria_id
     WHERE q.aktif = 1
     ORDER BY k.id, q.urutan'
)->fetchAll();

$groups = [];
foreach ($questions as $question) {
    $groups[$question['kode']][] = $question;
}

$totalPertanyaan = count($questions);

$page_title = 'Kuesioner';
$active = 'kuesioner';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <div>
        <h1>Kuesioner Kesehatan Mental</h1>
        <p><?= e((string) $totalPertanyaan) ?> pertanyaan · skala 1 (sangat tidak setuju) sampai 5 (sangat setuju).</p>
    </div>
    <span class="badge brand" data-progress-badge>0 dari <?= e((string) $totalPertanyaan) ?> dijawab</span>
</div>

<?php foreach ($errors as $error): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
<?php endforeach; ?>

<div class="card mb-2">
    <div class="progress">
        <div class="bar"><span data-progress-bar style="width:0%"></span></div>
        <span class="small muted" data-progress-label>0%</span>
    </div>
    <p class="small muted mt-1 mb-0">Semua pertanyaan wajib dijawab sebelum menyimpan.</p>
</div>

<form method="post" action="<?= e(base_url('mahasiswa/kuesioner.php')) ?>" data-questionnaire>
    <?= csrf_field() ?>

    <?php foreach ($groups as $kode => $items): ?>
        <div class="card mb-2">
            <div class="criteria-head">
                <h3><?= e($kode) ?> · <?= e($items[0]['nama_kriteria']) ?></h3>
                <span class="badge brand">Bobot <?= e(pct($items[0]['bobot'], 0)) ?></span>
            </div>
            <p class="card-sub">Skala: 1 = sangat tidak setuju, 2 = tidak setuju, 3 = netral, 4 = setuju, 5 = sangat setuju.</p>

            <?php foreach ($items as $question): ?>
                <div class="question" data-question-group>
                    <div class="q-text"><?= e($question['urutan']) ?>. <?= e($question['pertanyaan']) ?></div>
                    <div class="scale">
                        <?php for ($nilai = 1; $nilai <= 5; $nilai++): ?>
                            <label>
                                <input type="radio" name="answers[<?= e((string) $question['id']) ?>]" value="<?= $nilai ?>">
                                <span><?= $nilai ?></span>
                            </label>
                        <?php endfor; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>

    <div class="card flex-between">
        <span class="small muted">Jawabanmu bersifat rahasia dan hanya dipakai untuk skrining awal.</span>
        <div class="page-head-actions">
            <a class="btn btn-ghost" href="<?= e(base_url('mahasiswa/dashboard.php')) ?>">Batal</a>
            <button class="btn" type="submit">Simpan &amp; Hitung SAW</button>
        </div>
    </div>
</form>

<?php require __DIR__ . '/../includes/footer.php'; ?>
