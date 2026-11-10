<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

logout();
flash('success', 'Anda telah keluar.');
redirect('index.php');
