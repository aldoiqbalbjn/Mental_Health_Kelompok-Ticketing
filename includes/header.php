<?php
/**
 * Layout atas: head, topbar, sidebar, pembuka <main>.
 * Variabel opsional: $page_title, $active.
 */

require_once __DIR__ . '/functions.php';

$user = current_user();
$role = $user['role'] ?? 'guest';
$mhs  = $role === 'mahasiswa' ? current_mahasiswa() : null;
$page_title = $page_title ?? 'MindCare Student';
$active = $active ?? '';

if (!function_exists('nav_active')) {
    function nav_active(string $key, string $active): string
    {
        return $key === $active ? 'is-active' : '';
    }
}

$nav = [];
if ($role === 'mahasiswa') {
    $nav = [
        ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => 'mahasiswa/dashboard.php'],
        ['key' => 'kuesioner', 'label' => 'Isi Kuesioner', 'href' => 'mahasiswa/kuesioner.php'],
        ['key' => 'riwayat', 'label' => 'Riwayat', 'href' => 'mahasiswa/riwayat.php'],
        ['key' => 'profil', 'label' => 'Profil', 'href' => 'mahasiswa/profil.php'],
    ];
} elseif ($role === 'admin') {
    $nav = [
        ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => 'admin/dashboard.php'],
        ['key' => 'mahasiswa', 'label' => 'Data Mahasiswa', 'href' => 'admin/mahasiswa.php'],
        ['key' => 'kriteria', 'label' => 'Data Kriteria', 'href' => 'admin/kriteria.php'],
        ['key' => 'pertanyaan', 'label' => 'Data Pertanyaan', 'href' => 'admin/pertanyaan.php'],
        ['key' => 'hasil', 'label' => 'Hasil SPK', 'href' => 'admin/hasil.php'],
        ['key' => 'laporan', 'label' => 'Laporan', 'href' => 'admin/laporan.php'],
    ];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($page_title) ?> · MindCare</title>
    <link rel="stylesheet" href="<?= e(base_url('assets/css/app.css')) ?>">
</head>
<body>
<header class="topbar">
    <div class="topbar-left">
        <button class="menu-toggle" type="button" data-menu-toggle aria-label="Buka menu">☰</button>
        <a class="brand" href="<?= e(base_url($role === 'admin' ? 'admin/dashboard.php' : 'mahasiswa/dashboard.php')) ?>">
            <span class="brand-logo">MC</span>
            <span class="brand-text">MindCare <small>Student</small></span>
        </a>
    </div>
    <div class="topbar-right">
        <?php if ($user): ?>
            <div class="user-meta">
                <strong><?= e($mhs['nama'] ?? $user['username']) ?></strong>
                <span><?= e($role === 'admin' ? 'Administrator' : ($mhs['nim'] ?? 'Mahasiswa')) ?></span>
            </div>
            <a class="btn btn-ghost btn-sm" href="<?= e(base_url('logout.php')) ?>">Keluar</a>
        <?php endif; ?>
    </div>
</header>

<div class="shell">
    <?php if ($nav): ?>
    <aside class="sidebar" data-sidebar>
        <p class="sidebar-title"><?= $role === 'admin' ? 'Menu Admin' : 'Menu Mahasiswa' ?></p>
        <nav class="nav">
            <?php foreach ($nav as $item): ?>
                <a class="<?= e(nav_active($item['key'], $active)) ?>" href="<?= e(base_url($item['href'])) ?>">
                    <?= e($item['label']) ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar-foot">
            <p>Metode <b>SAW</b></p>
            <span>Simple Additive Weighting</span>
        </div>
    </aside>
    <?php endif; ?>

    <main class="main">
        <?php foreach (take_flashes() as $flash): ?>
            <div class="alert <?= e($flash['type'] === 'success' ? 'alert-ok' : ($flash['type'] === 'error' ? 'alert-error' : 'alert-info')) ?>">
                <?= e($flash['message']) ?>
            </div>
        <?php endforeach; ?>
