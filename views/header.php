
<?php

require_once __DIR__ . '/../helpers/auth.php';
requireLogin();

$user = $_SESSION['user'];

$currentPage = basename($_SERVER['PHP_SELF']);
$currentFolder = basename(dirname($_SERVER['PHP_SELF']));

if (!function_exists('e')) {
    function e($value): string
    {
        return htmlspecialchars(
            (string) ($value ?? ''),
            ENT_QUOTES,
            'UTF-8'
        );
    }
}

if (!function_exists('menuActive')) {
    function menuActive(
        string $page,
        string $currentPage,
        string $currentFolder
    ): string {
        return ($page === $currentPage || $page === $currentFolder)
            ? 'active'
            : '';
    }
}

?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= e($pageTitle ?? 'Ruang Stok') ?></title>

    <link rel="stylesheet"
          href="/inventory-app/public/assets/css/style.css">

    <style>
        /* =====================================
           RUANG STOK - GLOBAL HEADER & SIDEBAR
        ===================================== */

        body.rs-app {
            margin: 0;
            background: #F5F0E6;
            color: #352B2D;
            font-family: Arial, Helvetica, sans-serif;
        }

        body.rs-app .topbar {
            width: 100%;
            min-height: 66px;
            padding: 0 27px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            background: #641F35;
            color: #fff;
            border-bottom: 1px solid #481526;
            box-shadow: 0 3px 12px rgba(72, 21, 38, .12);
            box-sizing: border-box;
            position: relative;
            z-index: 10;
        }

        body.rs-app .brand {
            display: flex;
            align-items: center;
            gap: 11px;
            color: #fff;
            font-size: 19px;
            font-weight: 800;
            letter-spacing: 1.5px;
            white-space: nowrap;
        }

        body.rs-app .brand-mark {
            width: 35px;
            height: 35px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid rgba(255, 255, 255, .35);
            border-radius: 9px;
            background: rgba(255, 255, 255, .10);
            font-size: 17px;
        }

        body.rs-app .user-info {
            display: flex;
            align-items: center;
            gap: 13px;
            color: #F5F0E6;
            font-size: 12px;
        }

        body.rs-app .user-profile {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 3px;
        }

        body.rs-app .user-name {
            color: #fff;
            font-weight: 700;
            font-size: 13px;
        }

        body.rs-app .user-role {
            color: #E8C8D0;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: .8px;
        }

        body.rs-app .user-avatar {
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: #F5F0E6;
            color: #641F35;
            font-weight: 800;
            font-size: 14px;
        }

        body.rs-app .user-info a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 8px 13px;
            border: 1px solid rgba(255, 255, 255, .35);
            border-radius: 7px;
            color: #fff;
            background: rgba(255, 255, 255, .08);
            text-decoration: none;
            font-size: 11px;
            font-weight: 600;
            transition: .2s ease;
        }

        body.rs-app .user-info a:hover {
            background: #F5F0E6;
            color: #641F35;
        }

        body.rs-app .layout {
            display: flex;
            align-items: stretch;
            min-height: calc(100vh - 66px);
            width: 100%;
        }

        body.rs-app .sidebar {
            width: 245px;
            min-width: 245px;
            padding: 23px 15px;
            background: #FAF8F3;
            border-right: 1px solid #E8DED2;
            box-sizing: border-box;
        }

        body.rs-app .menu-label {
            padding: 0 12px;
            margin: 18px 0 9px;
            color: #A18F91;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 1.1px;
            text-transform: uppercase;
        }

        body.rs-app .menu-label:first-child {
            margin-top: 0;
        }

        body.rs-app .sidebar a {
            display: flex;
            align-items: center;
            gap: 11px;
            min-height: 41px;
            padding: 10px 12px;
            margin-bottom: 4px;
            border-radius: 8px;
            border-left: 3px solid transparent;
            color: #65585B;
            font-size: 13px;
            font-weight: 500;
            text-decoration: none;
            box-sizing: border-box;
            transition: .2s ease;
        }

        body.rs-app .sidebar a:hover {
            background: #F3E9E8;
            color: #641F35;
        }

        body.rs-app .sidebar a.active {
            background: #F1E5E7;
            border-left-color: #641F35;
            color: #641F35;
            font-weight: 700;
        }

        body.rs-app .menu-symbol {
            width: 20px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #8D465A;
            font-size: 15px;
        }

        body.rs-app .sidebar a.active .menu-symbol {
            color: #641F35;
        }

        body.rs-app .main-content {
            flex: 1;
            min-width: 0;
            padding: 27px 29px;
            background: #F5F0E6;
            box-sizing: border-box;
        }

        /* RESPONSIVE */

        @media (max-width: 900px) {
            body.rs-app .sidebar {
                width: 210px;
                min-width: 210px;
            }

            body.rs-app .main-content {
                padding: 22px 19px;
            }
        }

        @media (max-width: 650px) {
            body.rs-app .topbar {
                min-height: 60px;
                padding: 10px 14px;
                gap: 10px;
            }

            body.rs-app .brand {
                font-size: 15px;
                letter-spacing: 1px;
                gap: 7px;
            }

            body.rs-app .brand-mark {
                width: 30px;
                height: 30px;
            }

            body.rs-app .user-info {
                gap: 7px;
            }

            body.rs-app .user-profile {
                display: none;
            }

            body.rs-app .user-avatar {
                width: 31px;
                height: 31px;
            }

            body.rs-app .user-info a {
                padding: 7px 9px;
                font-size: 10px;
            }

            body.rs-app .layout {
                flex-direction: column;
                min-height: calc(100vh - 60px);
            }

            body.rs-app .sidebar {
                width: 100%;
                min-width: 0;
                padding: 10px;
                display: flex;
                align-items: center;
                gap: 5px;
                overflow-x: auto;
                border-right: none;
                border-bottom: 1px solid #E8DED2;
            }

            body.rs-app .menu-label {
                display: none;
            }

            body.rs-app .sidebar a {
                flex-shrink: 0;
                min-height: 36px;
                padding: 8px 11px;
                margin: 0;
                border-left: none;
                border-bottom: 2px solid transparent;
                font-size: 11px;
                white-space: nowrap;
            }

            body.rs-app .sidebar a.active {
                border-left: none;
                border-bottom-color: #641F35;
            }

            body.rs-app .menu-symbol {
                display: none;
            }

            body.rs-app .main-content {
                padding: 18px 13px;
            }
        }
    </style>
</head>

<body class="rs-app">

<header class="topbar">

    <div class="brand">
        <span class="brand-mark">R</span>
        <span>RUANG STOK</span>
    </div>

    <div class="user-info">

        <div class="user-profile">
            <span class="user-name">
                <?= e($user['name']) ?>
            </span>

            <span class="user-role">
                <?= e(ucfirst($user['role'])) ?>
            </span>
        </div>

        <div class="user-avatar">
            <?= e(strtoupper(substr($user['name'], 0, 1))) ?>
        </div>

        <a href="/inventory-app/public/logout.php"
           onclick="return confirm('Yakin ingin keluar?')">
            Logout
        </a>

    </div>

</header>

<div class="layout">

    <aside class="sidebar">

        <div class="menu-label">Menu Utama</div>

        <a class="<?= menuActive('dashboard.php', $currentPage, $currentFolder) ?>"
           href="/inventory-app/public/dashboard.php">
            <span class="menu-symbol">⌂</span>
            Dashboard
        </a>

        <a class="<?= menuActive('products', $currentPage, $currentFolder) ?>"
           href="/inventory-app/public/products/index.php">
            <span class="menu-symbol">▣</span>
            Produk
        </a>

        <a class="<?= menuActive('categories', $currentPage, $currentFolder) ?>"
           href="/inventory-app/public/categories/index.php">
            <span class="menu-symbol">▤</span>
            Kategori
        </a>

        <a class="<?= menuActive('suppliers', $currentPage, $currentFolder) ?>"
           href="/inventory-app/public/suppliers/index.php">
            <span class="menu-symbol">♧</span>
            Supplier
        </a>

        <div class="menu-label">Persediaan</div>

        <a class="<?= menuActive('stock', $currentPage, $currentFolder) ?>"
           href="/inventory-app/public/stock/index.php">
            <span class="menu-symbol">↕</span>
            Riwayat Stok
        </a>

        <a class="<?= menuActive('create.php', $currentPage, $currentFolder) ?>"
           href="/inventory-app/public/stock/create.php">
            <span class="menu-symbol">＋</span>
            Transaksi Stok
        </a>

        <div class="menu-label">Lainnya</div>

        <a class="<?= menuActive('reports.php', $currentPage, $currentFolder) ?>"
           href="/inventory-app/public/reports.php">
            <span class="menu-symbol">▥</span>
            Laporan
        </a>

        <?php if ($user['role'] === 'admin'): ?>

            <a class="<?= menuActive('users.php', $currentPage, $currentFolder) ?>"
               href="/inventory-app/public/users.php">
                <span class="menu-symbol">♙</span>
                Manajemen Pengguna
            </a>

        <?php endif; ?>

    </aside>

    <main class="main-content">
