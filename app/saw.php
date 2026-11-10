<?php
/**
 * Mesin perhitungan Simple Additive Weighting (SAW).
 *
 * Rumus:
 *   Normalisasi benefit = nilai_kriteria / 5
 *   Normalisasi cost    = 1 / nilai_kriteria
 *   Kontribusi          = bobot * normalisasi
 *   Nilai akhir         = jumlah seluruh kontribusi
 *   Kategori            = >= 0,60 "Prioritas dukungan", selain itu "Perlu perhatian"
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

const SAW_SKALA_MAKS = 5.0;
const SAW_AMBANG_KATEGORI = 0.60;

function saw_hitung(int $pengisianId, bool $manageTransaction = true): array
{
    $pdo = db();

    $stmt = $pdo->prepare(
        'SELECT p.kriteria_id, SUM(j.nilai) AS total, COUNT(*) AS jumlah
         FROM jawaban j
         JOIN pertanyaan p ON p.id = j.pertanyaan_id
         WHERE j.pengisian_id = ?
         GROUP BY p.kriteria_id'
    );
    $stmt->execute([$pengisianId]);

    $nilaiPerKriteria = [];
    foreach ($stmt->fetchAll() as $row) {
        $jumlah = max(1, (int) $row['jumlah']);
        $nilaiPerKriteria[(int) $row['kriteria_id']] = (float) $row['total'] / $jumlah;
    }

    if (!$nilaiPerKriteria) {
        throw new RuntimeException('Jawaban kuesioner belum tersedia.');
    }

    $detail = [];
    $total = 0.0;

    foreach (get_kriteria() as $kriteria) {
        $kriteriaId = (int) $kriteria['id'];
        $nilai = $nilaiPerKriteria[$kriteriaId] ?? 0.0;

        if ($kriteria['jenis'] === 'cost') {
            $normalisasi = $nilai > 0 ? (1.0 / $nilai) : 0.0;
        } else {
            $normalisasi = $nilai / SAW_SKALA_MAKS;
        }

        $bobot = (float) $kriteria['bobot'];
        $kontribusi = $bobot * $normalisasi;
        $total += $kontribusi;

        $detail[] = [
            'kriteria_id'        => $kriteriaId,
            'nilai_kriteria'     => $nilai,
            'nilai_normalisasi'  => $normalisasi,
            'bobot'              => $bobot,
            'kontribusi'         => $kontribusi,
        ];
    }

    $nilaiAkhir = round($total, 6);
    $kategori = $nilaiAkhir >= SAW_AMBANG_KATEGORI ? 'Prioritas dukungan' : 'Perlu perhatian';
    $rekomendasi = rekomendasi_teks($kategori);

    if ($manageTransaction) {
        $pdo->beginTransaction();
    }

    try {
        $stmt = $pdo->prepare('SELECT id FROM hasil_spk WHERE pengisian_id = ? LIMIT 1');
        $stmt->execute([$pengisianId]);
        $existingId = $stmt->fetchColumn();

        if ($existingId) {
            $hasilId = (int) $existingId;
            $pdo->prepare('UPDATE hasil_spk SET nilai_akhir = ?, kategori = ?, tanggal_hitung = NOW() WHERE id = ?')
                ->execute([$nilaiAkhir, $kategori, $hasilId]);
            $pdo->prepare('DELETE FROM detail_hasil_spk WHERE hasil_id = ?')->execute([$hasilId]);
            $pdo->prepare('DELETE FROM rekomendasi WHERE hasil_id = ?')->execute([$hasilId]);
        } else {
            $pdo->prepare('INSERT INTO hasil_spk (pengisian_id, nilai_akhir, kategori) VALUES (?, ?, ?)')
                ->execute([$pengisianId, $nilaiAkhir, $kategori]);
            $hasilId = (int) $pdo->lastInsertId();
        }

        $insertDetail = $pdo->prepare(
            'INSERT INTO detail_hasil_spk
                (hasil_id, kriteria_id, nilai_kriteria, nilai_normalisasi, bobot, kontribusi)
             VALUES (?, ?, ?, ?, ?, ?)'
        );

        foreach ($detail as $row) {
            $insertDetail->execute([
                $hasilId,
                $row['kriteria_id'],
                round($row['nilai_kriteria'], 4),
                round($row['nilai_normalisasi'], 6),
                $row['bobot'],
                round($row['kontribusi'], 6),
            ]);
        }

        $pdo->prepare('INSERT INTO rekomendasi (hasil_id, deskripsi) VALUES (?, ?)')
            ->execute([$hasilId, $rekomendasi]);

        if ($manageTransaction) {
            $pdo->commit();
        }
    } catch (Throwable $exception) {
        if ($manageTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }

    return [
        'hasil_id'    => $hasilId,
        'nilai_akhir' => $nilaiAkhir,
        'kategori'    => $kategori,
        'detail'      => $detail,
    ];
}

function saw_simpan_pengisian(int $mahasiswaId, array $answers): int
{
    $pdo = db();

    $pertanyaan = $pdo->query('SELECT id FROM pertanyaan WHERE aktif = 1 ORDER BY id')->fetchAll();
    if (!$pertanyaan) {
        throw new RuntimeException('Belum ada pertanyaan aktif.');
    }

    $bersih = [];
    foreach ($pertanyaan as $row) {
        $pertanyaanId = (int) $row['id'];
        $nilai = $answers[$pertanyaanId] ?? $answers[(string) $pertanyaanId] ?? null;

        if ($nilai === null || !is_numeric($nilai) || (int) $nilai < 1 || (int) $nilai > 5) {
            throw new RuntimeException('Semua pertanyaan wajib dijawab dengan nilai 1 sampai 5.');
        }

        $bersih[$pertanyaanId] = (int) $nilai;
    }

    $pdo->beginTransaction();

    try {
        $pdo->prepare("INSERT INTO pengisian (mahasiswa_id, tanggal, status) VALUES (?, NOW(), 'selesai')")
            ->execute([$mahasiswaId]);
        $pengisianId = (int) $pdo->lastInsertId();

        $insertJawaban = $pdo->prepare('INSERT INTO jawaban (pengisian_id, pertanyaan_id, nilai) VALUES (?, ?, ?)');
        foreach ($bersih as $pertanyaanId => $nilai) {
            $insertJawaban->execute([$pengisianId, $pertanyaanId, $nilai]);
        }

        saw_hitung($pengisianId, false);
        $pdo->commit();

        return $pengisianId;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function saw_detail_rows(int $hasilId): array
{
    $stmt = db()->prepare(
        'SELECT d.*, k.kode, k.nama_kriteria, k.jenis
         FROM detail_hasil_spk d
         JOIN kriteria k ON k.id = d.kriteria_id
         WHERE d.hasil_id = ?
         ORDER BY k.id'
    );
    $stmt->execute([$hasilId]);
    return $stmt->fetchAll();
}

function saw_jawaban_rows(int $pengisianId): array
{
    $stmt = db()->prepare(
        'SELECT j.nilai, q.pertanyaan, q.urutan, k.kode, k.nama_kriteria
         FROM jawaban j
         JOIN pertanyaan q ON q.id = j.pertanyaan_id
         JOIN kriteria k ON k.id = q.kriteria_id
         WHERE j.pengisian_id = ?
         ORDER BY k.id, q.urutan'
    );
    $stmt->execute([$pengisianId]);
    return $stmt->fetchAll();
}

function saw_hasil_terakhir(int $mahasiswaId): ?array
{
    $stmt = db()->prepare(
        'SELECT h.*, p.tanggal, p.mahasiswa_id
         FROM hasil_spk h
         JOIN pengisian p ON p.id = h.pengisian_id
         WHERE p.mahasiswa_id = ?
         ORDER BY p.tanggal DESC, h.id DESC
         LIMIT 1'
    );
    $stmt->execute([$mahasiswaId]);
    return $stmt->fetch() ?: null;
}

function saw_rekomendasi(int $hasilId): ?string
{
    $stmt = db()->prepare('SELECT deskripsi FROM rekomendasi WHERE hasil_id = ? LIMIT 1');
    $stmt->execute([$hasilId]);
    $value = $stmt->fetchColumn();
    return $value === false ? null : (string) $value;
}
