
<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/auth.php';

requireLogin();

if (!function_exists('e')) {
    function e($value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

$success = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

$search = trim($_GET['search'] ?? '');
$type = $_GET['type'] ?? '';

if (!in_array($type, ['', 'in', 'out', 'adjustment'], true)) {
    $type = '';
}

// Pagination
$perPage = 10;
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT);

if (!$page || $page < 1) {
    $page = 1;
}

// Kondisi pencarian
$where = [];
$params = [];

if ($search !== '') {
    $where[] = "(p.name LIKE :name OR p.sku LIKE :sku OR u.name LIKE :user_name)";
    $params['name'] = "%$search%";
    $params['sku'] = "%$search%";
    $params['user_name'] = "%$search%";
}

if ($type !== '') {
    $where[] = "sm.type = :type";
    $params['type'] = $type;
}

$whereSql = '';

if (!empty($where)) {
    $whereSql = 'WHERE ' . implode(' AND ', $where);
}

// Hitung total riwayat
$countStmt = $pdo->prepare(
    "SELECT COUNT(*)
     FROM stock_movements sm
     JOIN products p ON p.id = sm.product_id
     JOIN users u ON u.id = sm.user_id
     $whereSql"
);

$countStmt->execute($params);

$totalRecords = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalRecords / $perPage));

if ($page > $totalPages) {
    $page = $totalPages;
}

$offset = ($page - 1) * $perPage;

// Ambil riwayat stok
$sql = "
    SELECT
        sm.id,
        sm.type,
        sm.quantity,
        sm.notes,
        sm.created_at,
        p.sku,
        p.name AS product_name,
        u.name AS user_name
    FROM stock_movements sm
    JOIN products p ON p.id = sm.product_id
    JOIN users u ON u.id = sm.user_id
    $whereSql
    ORDER BY sm.created_at DESC, sm.id DESC
    LIMIT $perPage OFFSET $offset
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$movements = $stmt->fetchAll();

function rsStockPageUrl(int $page, string $search, string $type): string
{
    return '?' . http_build_query([
        'search' => $search,
        'type' => $type,
        'page' => $page
    ]);
}

function rsStockMovementLabel(string $type): string
{
    return match ($type) {
        'in' => 'Stok Masuk',
        'out' => 'Stok Keluar',
        'adjustment' => 'Penyesuaian',
        default => 'Tidak diketahui'
    };
}

$pageTitle = 'Riwayat Stok | Ruang Stok';

require_once __DIR__ . '/../../views/header.php';

?>

<style>
    .rs-stock-history {
        color: #352B2D;
        width: 100%;
    }

    .rs-stock-history * {
        box-sizing: border-box;
    }

    .rs-stock-history .history-heading {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 15px;
        flex-wrap: wrap;
        margin-bottom: 22px;
    }

    .rs-stock-history .history-heading h2 {
        color: #641F35;
        font-size: 21px;
        margin: 0 0 5px;
    }

    .rs-stock-history .history-heading p {
        color: #887B7D;
        font-size: 13px;
        margin: 0;
    }

    .rs-stock-history .btn-transaction {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
        padding: 10px 16px;
        border-radius: 8px;
        background: #641F35;
        color: #fff;
        font-size: 13px;
        font-weight: 600;
        text-decoration: none;
        transition: .2s ease;
    }

    .rs-stock-history .btn-transaction:hover {
        background: #481526;
    }

    .rs-stock-history .history-summary {
        display: flex;
        align-items: center;
        gap: 16px;
        background: #FAF8F3;
        border: 1px solid #E8DED2;
        border-radius: 12px;
        padding: 17px 20px;
        margin-bottom: 18px;
    }

    .rs-stock-history .summary-icon {
        width: 43px;
        height: 43px;
        flex-shrink: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 11px;
        background: #F1E5E7;
        color: #641F35;
        font-size: 21px;
    }

    .rs-stock-history .summary-label {
        color: #887B7D;
        font-size: 12px;
        margin-bottom: 4px;
    }

    .rs-stock-history .summary-number {
        color: #641F35;
        font-size: 22px;
        font-weight: 700;
        line-height: 1.2;
    }

    .rs-stock-history .filter-panel {
        background: #FAF8F3;
        border: 1px solid #E8DED2;
        border-radius: 12px;
        padding: 17px 19px;
        margin-bottom: 18px;
    }

    .rs-stock-history .filter-title {
        color: #641F35;
        font-size: 14px;
        font-weight: 700;
        margin: 0 0 13px;
    }

    .rs-stock-history .filter-form {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }

    .rs-stock-history .filter-form input,
    .rs-stock-history .filter-form select {
        min-height: 39px;
        border: 1px solid #DED1C3;
        border-radius: 7px;
        background: #fff;
        color: #352B2D;
        padding: 8px 11px;
        font-size: 13px;
        outline: none;
    }

    .rs-stock-history .filter-form input {
        flex: 1;
        min-width: 210px;
    }

    .rs-stock-history .filter-form select {
        min-width: 165px;
    }

    .rs-stock-history .filter-form input:focus,
    .rs-stock-history .filter-form select:focus {
        border-color: #8D465A;
        box-shadow: 0 0 0 3px rgba(141, 70, 90, .10);
    }

    .rs-stock-history .btn-filter {
        min-height: 39px;
        padding: 8px 17px;
        border: 0;
        border-radius: 7px;
        background: #641F35;
        color: #fff;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
    }

    .rs-stock-history .btn-filter:hover {
        background: #481526;
    }

    .rs-stock-history .btn-reset {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 39px;
        padding: 8px 14px;
        border-radius: 7px;
        border: 1px solid #DED1C3;
        color: #641F35;
        background: #F5F0E6;
        font-size: 13px;
        text-decoration: none;
    }

    .rs-stock-history .btn-reset:hover {
        background: #E8DED2;
    }

    .rs-stock-history .alert-success {
        background: #EDF5EC;
        border: 1px solid #C9DEC7;
        color: #38623B;
        border-radius: 8px;
        padding: 12px 15px;
        margin-bottom: 17px;
        font-size: 13px;
    }

    .rs-stock-history .table-panel {
        background: #FAF8F3;
        border: 1px solid #E8DED2;
        border-radius: 12px;
        overflow: hidden;
    }

    .rs-stock-history .table-heading {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 10px;
        padding: 17px 19px;
        border-bottom: 1px solid #E8DED2;
    }

    .rs-stock-history .table-heading h3 {
        color: #641F35;
        font-size: 15px;
        margin: 0;
    }

    .rs-stock-history .table-count {
        color: #887B7D;
        font-size: 12px;
    }

    .rs-stock-history .table-scroll {
        width: 100%;
        overflow-x: auto;
    }

    .rs-stock-history table {
        width: 100%;
        min-width: 900px;
        border-collapse: collapse;
        font-size: 12px;
    }

    .rs-stock-history thead th {
        background: #F1E8E4;
        color: #641F35;
        font-size: 11px;
        font-weight: 700;
        text-align: left;
        padding: 13px 12px;
        white-space: nowrap;
        border-bottom: 1px solid #E8DED2;
    }

    .rs-stock-history tbody td {
        padding: 13px 12px;
        color: #514649;
        border-bottom: 1px solid #EEE7DE;
        vertical-align: middle;
    }

    .rs-stock-history tbody tr:last-child td {
        border-bottom: none;
    }

    .rs-stock-history tbody tr:hover {
        background: #F7F1ED;
    }

    .rs-stock-history .number-cell {
        color: #887B7D;
        width: 45px;
    }

    .rs-stock-history .date-cell {
        white-space: nowrap;
        line-height: 1.5;
    }

    .rs-stock-history .date-main {
        color: #514649;
        font-weight: 600;
    }

    .rs-stock-history .date-time {
        color: #887B7D;
        font-size: 11px;
    }

    .rs-stock-history .sku-text {
        color: #641F35;
        font-weight: 700;
        white-space: nowrap;
    }

    .rs-stock-history .product-text {
        color: #352B2D;
        font-weight: 600;
    }

    .rs-stock-history .movement-badge {
        display: inline-flex;
        align-items: center;
        padding: 5px 9px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 700;
        white-space: nowrap;
    }

    .rs-stock-history .movement-in {
        color: #38623B;
        background: #E7F1E5;
    }

    .rs-stock-history .movement-out {
        color: #963F4A;
        background: #F8E8E9;
    }

    .rs-stock-history .movement-adjustment {
        color: #76551D;
        background: #F5EEDC;
    }

    .rs-stock-history .quantity-cell {
        font-weight: 700;
        white-space: nowrap;
    }

    .rs-stock-history .quantity-in {
        color: #38623B;
    }

    .rs-stock-history .quantity-out {
        color: #963F4A;
    }

    .rs-stock-history .quantity-adjustment {
        color: #76551D;
    }

    .rs-stock-history .user-cell {
        font-weight: 600;
        white-space: nowrap;
    }

    .rs-stock-history .notes-cell {
        max-width: 190px;
        color: #887B7D;
        line-height: 1.5;
    }

    .rs-stock-history .empty-state {
        text-align: center;
        padding: 42px 15px;
        color: #887B7D;
    }

    .rs-stock-history .empty-icon {
        font-size: 27px;
        margin-bottom: 9px;
        color: #8D465A;
    }

    .rs-stock-history .empty-state strong {
        display: block;
        color: #641F35;
        font-size: 14px;
        margin-bottom: 5px;
    }

    .rs-stock-history .empty-state span {
        font-size: 12px;
    }

    .rs-stock-history .pagination-area {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        padding: 15px 19px;
        border-top: 1px solid #E8DED2;
    }

    .rs-stock-history .pagination-info {
        color: #887B7D;
        font-size: 12px;
    }

    .rs-stock-history .pagination {
        display: flex;
        align-items: center;
        gap: 5px;
        flex-wrap: wrap;
    }

    .rs-stock-history .pagination a,
    .rs-stock-history .pagination span {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 31px;
        height: 31px;
        padding: 0 9px;
        border: 1px solid #E8DED2;
        border-radius: 6px;
        color: #641F35;
        background: #fff;
        font-size: 12px;
        text-decoration: none;
    }

    .rs-stock-history .pagination a:hover {
        background: #F1E8E4;
    }

    .rs-stock-history .pagination .active {
        background: #641F35;
        border-color: #641F35;
        color: #fff;
        font-weight: 700;
    }

    @media (max-width: 700px) {
        .rs-stock-history .history-heading {
            align-items: flex-start;
        }

        .rs-stock-history .btn-transaction {
            width: 100%;
        }

        .rs-stock-history .filter-form {
            align-items: stretch;
        }

        .rs-stock-history .filter-form input,
        .rs-stock-history .filter-form select,
        .rs-stock-history .btn-filter,
        .rs-stock-history .btn-reset {
            width: 100%;
        }

        .rs-stock-history .pagination-area {
            align-items: flex-start;
        }
    }
</style>

<div class="rs-stock-history">

    <?php if ($success !== ''): ?>
        <div class="alert-success">
            <?= e($success) ?>
        </div>
    <?php endif; ?>

    <div class="history-heading">
        <div>
            <h2>Riwayat Pergerakan Stok</h2>
            <p>Catatan seluruh aktivitas perubahan stok produk.</p>
        </div>

        <a href="create.php" class="btn-transaction">
            + Transaksi Stok
        </a>
    </div>

    <div class="history-summary">
        <div class="summary-icon">↕</div>
        <div>
            <div class="summary-label">Total Riwayat Transaksi</div>
            <div class="summary-number">
                <?= number_format($totalRecords, 0, ',', '.') ?>
            </div>
        </div>
    </div>

    <div class="filter-panel">
        <div class="filter-title">Cari dan Filter Riwayat</div>

        <form method="GET" class="filter-form">

            <input
                type="text"
                name="search"
                placeholder="Cari SKU, produk, atau pengguna..."
                value="<?= e($search) ?>"
            >

            <select name="type">
                <option value="">Semua Transaksi</option>

                <option value="in" <?= $type === 'in' ? 'selected' : '' ?>>
                    Stok Masuk
                </option>

                <option value="out" <?= $type === 'out' ? 'selected' : '' ?>>
                    Stok Keluar
                </option>

                <option value="adjustment" <?= $type === 'adjustment' ? 'selected' : '' ?>>
                    Penyesuaian
                </option>
            </select>

            <button type="submit" class="btn-filter">Terapkan</button>

            <a href="index.php" class="btn-reset">Reset</a>

        </form>
    </div>

    <div class="table-panel">

        <div class="table-heading">
            <h3>Daftar Riwayat Stok</h3>
            <span class="table-count">
                <?= number_format($totalRecords, 0, ',', '.') ?> data ditemukan
            </span>
        </div>

        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Tanggal</th>
                        <th>SKU</th>
                        <th>Produk</th>
                        <th>Jenis Transaksi</th>
                        <th>Jumlah</th>
                        <th>Pengguna</th>
                        <th>Catatan</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if (!empty($movements)): ?>

                        <?php foreach ($movements as $index => $movement): ?>

                            <?php
                            $movementType = $movement['type'];

                            $badgeClass = match ($movementType) {
                                'in' => 'movement-in',
                                'out' => 'movement-out',
                                'adjustment' => 'movement-adjustment',
                                default => ''
                            };

                            $quantityClass = match ($movementType) {
                                'in' => 'quantity-in',
                                'out' => 'quantity-out',
                                'adjustment' => 'quantity-adjustment',
                                default => ''
                            };

                            $quantitySign = match ($movementType) {
                                'in' => '+',
                                'out' => '-',
                                default => ''
                            };

                            $date = strtotime($movement['created_at']);
                            ?>

                            <tr>
                                <td class="number-cell">
                                    <?= $offset + $index + 1 ?>
                                </td>

                                <td class="date-cell">
                                    <div class="date-main">
                                        <?= e(date('d-m-Y', $date)) ?>
                                    </div>
                                    <div class="date-time">
                                        <?= e(date('H:i:s', $date)) ?>
                                    </div>
                                </td>

                                <td>
                                    <span class="sku-text">
                                        <?= e($movement['sku']) ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="product-text">
                                        <?= e($movement['product_name']) ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="movement-badge <?= $badgeClass ?>">
                                        <?= e(rsStockMovementLabel($movementType)) ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="quantity-cell <?= $quantityClass ?>">
                                        <?= $quantitySign . number_format((int) $movement['quantity'], 0, ',', '.') ?>
                                    </span>
                                </td>

                                <td class="user-cell">
                                    <?= e($movement['user_name']) ?>
                                </td>

                                <td class="notes-cell">
                                    <?= e($movement['notes'] ?: '-') ?>
                                </td>
                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="8">
                                <div class="empty-state">
                                    <div class="empty-icon">↕</div>
                                    <strong>Belum ada riwayat stok</strong>
                                    <span>
                                        Data transaksi stok akan muncul di halaman ini.
                                    </span>
                                </div>
                            </td>
                        </tr>

                    <?php endif; ?>

                </tbody>
            </table>
        </div>

        <?php if ($totalRecords > 0): ?>

            <div class="pagination-area">

                <div class="pagination-info">
                    Halaman <?= $page ?> dari <?= $totalPages ?>
                </div>

                <div class="pagination">

                    <?php if ($page > 1): ?>
                        <a href="<?= e(rsStockPageUrl($page - 1, $search, $type)) ?>">
                            &laquo;
                        </a>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>

                        <?php if ($i === $page): ?>
                            <span class="active"><?= $i ?></span>
                        <?php else: ?>
                            <a href="<?= e(rsStockPageUrl($i, $search, $type)) ?>">
                                <?= $i ?>
                            </a>
                        <?php endif; ?>

                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                        <a href="<?= e(rsStockPageUrl($page + 1, $search, $type)) ?>">
                            &raquo;
                        </a>
                    <?php endif; ?>

                </div>
            </div>

        <?php endif; ?>

    </div>

</div>

</main>
</div>
</body>
</html>
