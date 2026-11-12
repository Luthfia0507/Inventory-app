
<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth.php';

requireLogin();

$pageTitle = 'Laporan Persediaan | Inventory App';

// Ringkasan produk
$totalProducts = (int) $pdo->query(
    "SELECT COUNT(*) FROM products"
)->fetchColumn();

$totalStock = (int) $pdo->query(
    "SELECT COALESCE(SUM(stock), 0) FROM products"
)->fetchColumn();

$lowStockCount = (int) $pdo->query(
    "SELECT COUNT(*) FROM products WHERE stock <= minimum_stock"
)->fetchColumn();

// Total stok masuk
$totalStockIn = (int) $pdo->query(
    "SELECT COALESCE(SUM(quantity), 0)
     FROM stock_movements
     WHERE type = 'in'"
)->fetchColumn();

// Total stok keluar
$totalStockOut = (int) $pdo->query(
    "SELECT COALESCE(SUM(quantity), 0)
     FROM stock_movements
     WHERE type = 'out'"
)->fetchColumn();

// Jumlah transaksi
$countIn = (int) $pdo->query(
    "SELECT COUNT(*) FROM stock_movements WHERE type = 'in'"
)->fetchColumn();

$countOut = (int) $pdo->query(
    "SELECT COUNT(*) FROM stock_movements WHERE type = 'out'"
)->fetchColumn();

// Produk dengan stok menipis
$lowStockStmt = $pdo->query(
    "SELECT
        p.id,
        p.sku,
        p.name,
        p.stock,
        p.minimum_stock,
        c.name AS category_name
     FROM products p
     LEFT JOIN categories c ON c.id = p.category_id
     WHERE p.stock <= p.minimum_stock
     ORDER BY p.stock ASC, p.name ASC"
);

$lowStockProducts = $lowStockStmt->fetchAll();

// Pergerakan stok per bulan
$monthlyStmt = $pdo->query(
    "SELECT
        YEAR(created_at) AS year_number,
        MONTH(created_at) AS month_number,
        SUM(CASE WHEN type = 'in' THEN quantity ELSE 0 END) AS stock_in,
        SUM(CASE WHEN type = 'out' THEN quantity ELSE 0 END) AS stock_out
     FROM stock_movements
     WHERE created_at >= DATE_FORMAT(
         CURRENT_DATE - INTERVAL 5 MONTH,
         '%Y-%m-01'
     )
     GROUP BY YEAR(created_at), MONTH(created_at)
     ORDER BY year_number ASC, month_number ASC"
);

$monthlyReports = $monthlyStmt->fetchAll();

$monthNames = [
    1 => 'Januari',
    2 => 'Februari',
    3 => 'Maret',
    4 => 'April',
    5 => 'Mei',
    6 => 'Juni',
    7 => 'Juli',
    8 => 'Agustus',
    9 => 'September',
    10 => 'Oktober',
    11 => 'November',
    12 => 'Desember'
];

require_once __DIR__ . '/../views/header.php';

?>

<style>
    .page-heading {
        margin-bottom: 25px;
    }

    .page-heading h1 {
        margin-top: 0;
    }

    .report-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 16px;
        margin-bottom: 25px;
    }

    .report-card {
        background: white;
        border: 1px solid #e5e9ef;
        border-radius: 10px;
        padding: 20px;
    }

    .report-card h3 {
        color: #687887;
        font-size: 14px;
        font-weight: normal;
        margin: 0 0 12px;
    }

    .report-card strong {
        font-size: 25px;
    }

    .report-section {
        background: white;
        border: 1px solid #e5e9ef;
        border-radius: 10px;
        padding: 20px;
        margin-bottom: 22px;
        overflow-x: auto;
    }

    .report-section h2 {
        font-size: 18px;
        margin-top: 0;
    }

    .report-table {
        width: 100%;
        border-collapse: collapse;
        min-width: 550px;
    }

    .report-table th,
    .report-table td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid #edf0f3;
        font-size: 14px;
    }

    .report-table th {
        background: #f5f7fa;
    }

    .status-low {
        color: #d04b4b;
        font-weight: bold;
    }

    .print-button {
        background: #315b7b;
        color: white;
        border: none;
        padding: 11px 20px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 14px;
        margin-bottom: 20px;
    }

    .print-button:hover {
        background: #24445d;
    }

    @media (max-width: 800px) {
        .report-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 500px) {
        .report-grid {
            grid-template-columns: 1fr;
        }
    }

    @media print {
        .sidebar,
        .topbar,
        .print-button {
            display: none !important;
        }

        .layout {
            display: block;
        }

        .main-content {
            padding: 0;
        }

        .report-section,
        .report-card {
            break-inside: avoid;
        }

        body {
            background: white;
        }
    }
</style>

<div class="page-heading">
    <h1>Laporan Persediaan</h1>
    <p>Ringkasan kondisi persediaan dan aktivitas stok.</p>
</div>

<button class="print-button" onclick="window.print()">
    Cetak Laporan
</button>

<div class="report-grid">

    <div class="report-card">
        <h3>Total Produk</h3>
        <strong><?= number_format($totalProducts) ?></strong>
    </div>

    <div class="report-card">
        <h3>Total Stok Tersedia</h3>
        <strong><?= number_format($totalStock) ?> unit</strong>
    </div>

    <div class="report-card">
        <h3>Produk Stok Menipis</h3>
        <strong><?= number_format($lowStockCount) ?></strong>
    </div>

    <div class="report-card">
        <h3>Jumlah Transaksi Masuk</h3>
        <strong><?= number_format($countIn) ?></strong>
    </div>

    <div class="report-card">
        <h3>Total Unit Stok Masuk</h3>
        <strong><?= number_format($totalStockIn) ?></strong>
    </div>

    <div class="report-card">
        <h3>Total Unit Stok Keluar</h3>
        <strong><?= number_format($totalStockOut) ?></strong>
    </div>

</div>

<div class="report-section">

    <h2>Ringkasan Pergerakan Stok</h2>

    <table class="report-table">
        <thead>
            <tr>
                <th>Jenis Transaksi</th>
                <th>Jumlah Transaksi</th>
                <th>Total Unit</th>
            </tr>
        </thead>

        <tbody>
            <tr>
                <td>Stok Masuk</td>
                <td><?= number_format($countIn) ?></td>
                <td><?= number_format($totalStockIn) ?></td>
            </tr>

            <tr>
                <td>Stok Keluar</td>
                <td><?= number_format($countOut) ?></td>
                <td><?= number_format($totalStockOut) ?></td>
            </tr>
        </tbody>
    </table>

</div>

<div class="report-section">

    <h2>Riwayat Pergerakan Stok per Bulan</h2>

    <table class="report-table">

        <thead>
            <tr>
                <th>No.</th>
                <th>Bulan</th>
                <th>Stok Masuk</th>
                <th>Stok Keluar</th>
            </tr>
        </thead>

        <tbody>

            <?php if (!empty($monthlyReports)): ?>

                <?php foreach ($monthlyReports as $index => $report): ?>

                    <tr>
                        <td><?= $index + 1 ?></td>

                        <td>
                            <?= e($monthNames[(int) $report['month_number']]) ?>
                            <?= (int) $report['year_number'] ?>
                        </td>

                        <td>
                            <?= number_format((int) $report['stock_in']) ?>
                        </td>

                        <td>
                            <?= number_format((int) $report['stock_out']) ?>
                        </td>
                    </tr>

                <?php endforeach; ?>

            <?php else: ?>

                <tr>
                    <td colspan="4">
                        Belum ada data pergerakan stok.
                    </td>
                </tr>

            <?php endif; ?>

        </tbody>

    </table>

</div>

<div class="report-section">

    <h2>Daftar Produk dengan Stok Menipis</h2>

    <table class="report-table">

        <thead>
            <tr>
                <th>No.</th>
                <th>SKU</th>
                <th>Nama Produk</th>
                <th>Kategori</th>
                <th>Stok Tersedia</th>
                <th>Minimum Stok</th>
                <th>Keterangan</th>
            </tr>
        </thead>

        <tbody>

            <?php if (!empty($lowStockProducts)): ?>

                <?php foreach ($lowStockProducts as $index => $product): ?>

                    <tr>
                        <td><?= $index + 1 ?></td>
                        <td><?= e($product['sku']) ?></td>
                        <td><?= e($product['name']) ?></td>
                        <td><?= e($product['category_name'] ?? '-') ?></td>
                        <td><?= number_format((int) $product['stock']) ?></td>
                        <td><?= number_format((int) $product['minimum_stock']) ?></td>

                        <td class="status-low">
                            Stok Menipis
                        </td>
                    </tr>

                <?php endforeach; ?>

            <?php else: ?>

                <tr>
                    <td colspan="7">
                        Semua produk memiliki stok di atas batas minimum.
                    </td>
                </tr>

            <?php endif; ?>

        </tbody>

    </table>

</div>

</main>
</div>

</body>
</html>
