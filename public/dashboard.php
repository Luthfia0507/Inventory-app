
<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth.php';

requireLogin();

$user = $_SESSION['user'];
$isAdmin = $user['role'] === 'admin';

$pageTitle = 'Dashboard | Inventory App';

// Statistik utama
$totalProducts = (int) $pdo->query(
    "SELECT COUNT(*) FROM products"
)->fetchColumn();

$totalCategories = (int) $pdo->query(
    "SELECT COUNT(*) FROM categories"
)->fetchColumn();

$totalSuppliers = (int) $pdo->query(
    "SELECT COUNT(*) FROM suppliers"
)->fetchColumn();

$totalStock = (int) $pdo->query(
    "SELECT COALESCE(SUM(stock), 0) FROM products"
)->fetchColumn();

$lowStock = (int) $pdo->query(
    "SELECT COUNT(*) FROM products WHERE stock <= minimum_stock"
)->fetchColumn();

$totalMovements = (int) $pdo->query(
    "SELECT COUNT(*) FROM stock_movements"
)->fetchColumn();

// Aktivitas stok terbaru
$recentStmt = $pdo->query(
    "SELECT
        sm.type,
        sm.quantity,
        sm.created_at,
        p.name AS product_name,
        p.sku,
        u.name AS user_name
     FROM stock_movements sm
     JOIN products p ON p.id = sm.product_id
     JOIN users u ON u.id = sm.user_id
     ORDER BY sm.created_at DESC, sm.id DESC
     LIMIT 5"
);

$recentMovements = $recentStmt->fetchAll();

function movementLabel(string $type): string
{
    return match ($type) {
        'in' => 'Stok Masuk',
        'out' => 'Stok Keluar',
        'adjustment' => 'Penyesuaian',
        default => 'Tidak diketahui'
    };
}

require_once __DIR__ . '/../views/header.php';

?>

<style>
    .welcome {
        margin-bottom: 25px;
    }

    .welcome h1 {
        margin-top: 0;
        margin-bottom: 8px;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 18px;
        margin: 20px 0 30px;
    }

    .stat-card {
        display: block;
        background: white;
        padding: 22px;
        border-radius: 10px;
        border: 1px solid #e5e9ef;
        color: #263238;
        text-decoration: none;
        transition: 0.2s;
    }

    .stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 4px 14px rgba(0,0,0,0.07);
    }

    .stat-card h3 {
        font-size: 14px;
        color: #687887;
        margin: 0 0 12px;
        font-weight: normal;
    }

    .stat-card strong {
        font-size: 27px;
    }

    .section-card {
        background: white;
        border: 1px solid #e5e9ef;
        border-radius: 10px;
        padding: 20px;
        margin-bottom: 20px;
        overflow-x: auto;
    }

    .section-card h2 {
        margin-top: 0;
        font-size: 18px;
    }

    .data-table {
        width: 100%;
        border-collapse: collapse;
        min-width: 600px;
    }

    .data-table th,
    .data-table td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid #edf0f3;
        font-size: 14px;
    }

    .data-table th {
        background: #f5f7fa;
    }

    .movement-in {
        color: #16834a;
        font-weight: bold;
    }

    .movement-out {
        color: #d04b4b;
        font-weight: bold;
    }

    .movement-adjustment {
        color: #b7791f;
        font-weight: bold;
    }

    .table-link {
        display: inline-block;
        margin-top: 15px;
        color: #315b7b;
        text-decoration: none;
    }

    @media (max-width: 900px) {
        .stats-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 550px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="welcome">
    <h1>Dashboard</h1>

    <p>
        Selamat datang,
        <strong><?= e($user['name']) ?></strong>!
    </p>

    <p>
        Ringkasan persediaan dan aktivitas inventory.
    </p>
</div>

<div class="stats-grid">

    <a href="/inventory-app/public/products/index.php"
       class="stat-card">
        <h3>Total Produk</h3>
        <strong><?= number_format($totalProducts) ?></strong>
    </a>

    <a href="/inventory-app/public/categories/index.php"
       class="stat-card">
        <h3>Total Kategori</h3>
        <strong><?= number_format($totalCategories) ?></strong>
    </a>

    <a href="/inventory-app/public/suppliers/index.php"
       class="stat-card">
        <h3>Total Supplier</h3>
        <strong><?= number_format($totalSuppliers) ?></strong>
    </a>

    <a href="/inventory-app/public/stock/index.php"
       class="stat-card">
        <h3>Total Stok</h3>
        <strong><?= number_format($totalStock) ?> unit</strong>
    </a>

    <a href="/inventory-app/public/reports.php"
       class="stat-card">
        <h3>Produk Stok Menipis</h3>
        <strong><?= number_format($lowStock) ?></strong>
    </a>

    <a href="/inventory-app/public/stock/index.php"
       class="stat-card">
        <h3>Total Riwayat Stok</h3>
        <strong><?= number_format($totalMovements) ?></strong>
    </a>

</div>

<div class="section-card">

    <h2>Aktivitas Stok Terbaru</h2>

    <table class="data-table">

        <thead>
            <tr>
                <th>No.</th>
                <th>Tanggal</th>
                <th>SKU</th>
                <th>Produk</th>
                <th>Jenis</th>
                <th>Jumlah</th>
                <th>Pengguna</th>
            </tr>
        </thead>

        <tbody>

            <?php if (!empty($recentMovements)): ?>

                <?php foreach ($recentMovements as $index => $movement): ?>

                    <tr>
                        <td><?= $index + 1 ?></td>

                        <td>
                            <?= e(date(
                                'd-m-Y H:i',
                                strtotime($movement['created_at'])
                            )) ?>
                        </td>

                        <td><?= e($movement['sku']) ?></td>

                        <td><?= e($movement['product_name']) ?></td>

                        <td>
                            <?php
                            $movementClass = match ($movement['type']) {
                                'in' => 'movement-in',
                                'out' => 'movement-out',
                                default => 'movement-adjustment'
                            };
                            ?>

                            <span class="<?= $movementClass ?>">
                                <?= e(movementLabel($movement['type'])) ?>
                            </span>
                        </td>

                        <td>
                            <?= number_format((int) $movement['quantity']) ?>
                        </td>

                        <td><?= e($movement['user_name']) ?></td>
                    </tr>

                <?php endforeach; ?>

            <?php else: ?>

                <tr>
                    <td colspan="7">
                        Belum ada aktivitas stok.
                    </td>
                </tr>

            <?php endif; ?>

        </tbody>

    </table>

    <a class="table-link"
       href="/inventory-app/public/stock/index.php">
        Lihat seluruh riwayat stok →
    </a>

</div>

</main>
</div>

</body>
</html>
