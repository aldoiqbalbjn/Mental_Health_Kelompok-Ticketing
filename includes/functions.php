<?php
/**
 * Helper umum: session, autentikasi, keamanan, format angka/persentase.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function base_url(string $path = ''): string
{
    static $root = null;

    if ($root === null) {
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
        $script = preg_replace('#/(admin|mahasiswa)/[^/]+$#', '', $script) ?? $script;
        $script = preg_replace('#/index\.php$#', '', $script) ?? $script;
        $root = rtrim($script, '/');
        if ($root === '/' || $root === '.') {
            $root = '';
        }
    }

    return $root . '/' . ltrim($path, '/');
}

function redirect(string $path): void
{
    header('Location: ' . base_url($path));
    exit;
}

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function pct($value, int $decimals = 2): string
{
    return number_format(((float) $value) * 100, $decimals, ',', '.') . '%';
}

function num($value, int $decimals = 2): string
{
    return number_format((float) $value, $decimals, ',', '.');
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $items = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $items;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_verify(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(419);
        exit('Sesi kedaluwarsa. Muat ulang halaman lalu coba lagi.');
    }
}

function current_user(): ?array
{
    if (empty($_SESSION['user'])) {
        return null;
    }
    return $_SESSION['user'];
}

function current_mahasiswa(): ?array
{
    $user = current_user();
    if (!$user || $user['role'] !== 'mahasiswa') {
        return null;
    }

    static $mhs = null;
    if ($mhs === null) {
        $stmt = db()->prepare('SELECT * FROM mahasiswa WHERE user_id = ? LIMIT 1');
        $stmt->execute([$user['id']]);
        $mhs = $stmt->fetch() ?: null;
    }

    return $mhs;
}

function require_login(): void
{
    if (!current_user()) {
        flash('error', 'Silakan masuk terlebih dahulu.');
        redirect('index.php');
    }
}

function require_role(string $role): void
{
    require_login();
    if ((current_user()['role'] ?? '') !== $role) {
        http_response_code(403);
        exit('Akses ditolak.');
    }
}

function kategori_dari_skor(float $skor): string
{
    return $skor >= 0.60 ? 'Prioritas dukungan' : 'Perlu perhatian';
}

function rekomendasi_teks(string $kategori): string
{
    if ($kategori === 'Prioritas dukungan') {
        return 'Disarankan menghubungi layanan konseling kampus atau tenaga profesional untuk berdiskusi. '
            . 'Ini bukan diagnosis.';
    }

    return 'Pertimbangkan mengakses dukungan kampus, berbicara dengan orang tepercaya, dan meninjau kembali rutinitas '
        . 'istirahat serta belajar. Ini bukan diagnosis.';
}

function badge_class(string $kategori): string
{
    return $kategori === 'Prioritas dukungan' ? 'badge warn' : 'badge ok';
}

function get_kriteria(): array
{
    static $rows = null;
    if ($rows === null) {
        $rows = db()->query('SELECT * FROM kriteria ORDER BY id')->fetchAll();
    }
    return $rows;
}

function total_bobot(): float
{
    $total = 0.0;
    foreach (get_kriteria() as $k) {
        $total += (float) $k['bobot'];
    }
    return $total;
}
