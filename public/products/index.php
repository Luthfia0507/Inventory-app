
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

$user = $_SESSION['user'];
$isAdmin = $user['role'] === 'admin';

$pageTitle = 'Data Produk | Ruang Stok';

// Pencarian dan filter
$search = trim($_GET['search'] ?? '');
$categoryId = filter_input(INPUT_GET, 'category', FILTER_VALIDATE_INT);

$allowedSort = [
    'name' => 'p.name',
    'sku' => 'p.sku',
    'price' => 'p.price',
    'stock' => 'p.stock',
    'category' => 'c.name'
];

$sort = $_GET['sort'] ?? 'name';
$order = strtolower($_GET['order'] ?? 'asc');

if (!isset($allowedSort[$sort])) {
    $sort = 'name';
}

if (!in_array($order, ['asc', 'desc'], true)) {
    $order = 'asc';
}

$sortColumn = $allowedSort[$sort];
$sortOrder = strtoupper($order);

// Pagination
$perPage = 5;
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT);

if (!$page || $page < 1) {
    $page = 1;
}

// Kategori
$categoryStmt = $pdo->query(
    "SELECT id, name FROM categories ORDER BY name ASC"
);
$categories = $categoryStmt->fetchAll();

// Kondisi pencarian
$where = [];
$params = [];

if ($search !== '') {
    $where[] = "(p.name LIKE :search_name OR p.sku LIKE :search_sku)";
    $params['search_name'] = "%$search%";
    $params['search_sku'] = "%$search%";
}

if ($categoryId && $categoryId > 0) {
    $where[] = "p.category_id = :category_id";
    $params['category_id'] = $categoryId;
}

$whereSql = '';

if (!empty($where)) {
    $whereSql = 'WHERE ' . implode(' AND ', $where);
}

// Hitung produk
$countSql = "
    SELECT COUNT(*)
    FROM products p
    $whereSql
";

$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);

$totalProducts = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalProducts / $perPage));

if ($page > $totalPages) {
    $page = $totalPages;
}

$offset = ($page - 1) * $perPage;

// Ambil data produk
$sql = "
    SELECT
        p.id,
        p.sku,
        p.name,
        p.description,
        p.price,
        p.stock,
        p.minimum_stock,
        c.name AS category_name,
        GROUP_CONCAT(
            DISTINCT s.name
            ORDER BY s.name
            SEPARATOR ', '
        ) AS supplier_names
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    LEFT JOIN product_supplier ps ON ps.product_id = p.id
    LEFT JOIN suppliers s ON s.id = ps.supplier_id
    $whereSql
    GROUP BY
        p.id,
        p.sku,
        p.name,
        p.description,
        p.price,
        p.stock,
        p.minimum_stock,
        c.name
    ORDER BY $sortColumn $sortOrder
    LIMIT $perPage OFFSET $offset
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$products = $stmt->fetchAll();

// URL pengurutan
function sortUrl(
    string $column,
    string $search,
    $categoryId,
    string $currentSort,
    string $currentOrder
): string {
    $nextOrder = ($currentSort === $column && $currentOrder === 'asc')
        ? 'desc'
        : 'asc';

    return '?' . http_build_query([
        'search' => $search,
        'category' => $categoryId,
        'sort' => $column,
        'order' => $nextOrder,
        'page' => 1
    ]);
}

// URL pagination
function pageUrl(
    int $page,
    string $search,
    $categoryId,
    string $sort,
    string $order
): string {
    return '?' . http_build_query([
        'search' => $search,
        'category' => $categoryId,
        'sort' => $sort,
        'order' => $order,
        'page' => $page
    ]);
}

require_once __DIR__ . '/../../views/header.php';

?>

<style>
/* ===== RUANG STOK - DAFTAR PRODUK ===== */

.rs-products {
    width: 100%;
    padding-bottom: 30px;
    color: #352B2D;
}

.rs-products .rs-products-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
    margin-bottom: 20px;
}

.rs-products .rs-products-heading h1 {
    color: #641F35;
    font-size: 23px;
    margin: 0 0 5px;
}

.rs-products .rs-products-heading p {
    color: #887B7D;
    font-size: 13px;
    margin: 0;
}

.rs-products .rs-add-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    padding: 10px 16px;
    border-radius: 8px;
    background: #641F35;
    color: #fff;
    text-decoration: none;
    font-size: 13px;
    font-weight: 600;
    transition: .2s;
}

.rs-products .rs-add-button:hover {
    background: #481526;
}

/* Filter */

.rs-products .rs-filter-panel {
    background: #F5F0E6;
    border: 1px solid #E8DED2;
    border-radius: 11px;
    padding: 17px;
    margin-bottom: 17px;
}

.rs-products .rs-filter-form {
    display: grid;
    grid-template-columns: minmax(180px, 2fr) minmax(150px, 1fr) auto auto;
    gap: 10px;
    align-items: center;
}

.rs-products .rs-filter-form input,
.rs-products .rs-filter-form select {
    width: 100%;
    min-width: 0;
    height: 41px;
    box-sizing: border-box;
    padding: 9px 12px;
    border: 1px solid #DED2C9;
    border-radius: 7px;
    background: #FFFEFC;
    color: #352B2D;
    font-family: inherit;
    font-size: 13px;
}

.rs-products .rs-filter-form input:focus,
.rs-products .rs-filter-form select:focus {
    outline: none;
    border-color: #8D465A;
    box-shadow: 0 0 0 3px rgba(141, 70, 90, .10);
}

.rs-products .rs-filter-submit,
.rs-products .rs-filter-reset {
    height: 41px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    box-sizing: border-box;
    padding: 0 15px;
    border-radius: 7px;
    font-family: inherit;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
}

.rs-products .rs-filter-submit {
    background: #641F35;
    border: 1px solid #641F35;
    color: #fff;
}

.rs-products .rs-filter-submit:hover {
    background: #481526;
}

.rs-products .rs-filter-reset {
    background: #E8DED2;
    border: 1px solid #DED2C9;
    color: #641F35;
}

.rs-products .rs-filter-reset:hover {
    background: #DED2C9;
}

/* Ringkasan */

.rs-products .rs-products-summary {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    color: #887B7D;
    font-size: 12px;
    margin: 0 2px 12px;
}

/* Tabel */

.rs-products .rs-table-panel {
    background: #F5F0E6;
    border: 1px solid #E8DED2;
    border-radius: 11px;
    padding: 15px;
    box-shadow: 0 3px 12px rgba(72, 21, 38, .04);
    overflow-x: auto;
}

.rs-products .rs-product-table {
    width: 100%;
    min-width: 900px;
    border-collapse: collapse;
}

.rs-products .rs-product-table th {
    background: #E8DED2;
    color: #641F35;
    white-space: nowrap;
    font-size: 12px;
    font-weight: 700;
}

.rs-products .rs-product-table th,
.rs-products .rs-product-table td {
    padding: 13px 11px;
    border-bottom: 1px solid #E8DED2;
    text-align: left;
    font-size: 12px;
    vertical-align: middle;
}

.rs-products .rs-product-table tbody tr {
    background: #FFFEFC;
    transition: background .15s;
}

.rs-products .rs-product-table tbody tr:hover {
    background: #FAF5F0;
}

.rs-products .rs-product-table tbody tr:last-child td {
    border-bottom: none;
}

.rs-products .rs-product-table th a {
    color: #641F35;
    text-decoration: none;
}

.rs-products .rs-product-table th a:hover {
    text-decoration: underline;
}

.rs-products .rs-product-name {
    color: #352B2D;
    font-weight: 650;
}

.rs-products .rs-sku {
    display: inline-block;
    padding: 5px 8px;
    border-radius: 5px;
    background: #F0E6E3;
    color: #641F35;
    font-size: 11px;
    font-weight: 700;
    white-space: nowrap;
}

.rs-products .rs-stock-normal {
    color: #477653;
    font-weight: 700;
}

.rs-products .rs-stock-low {
    color: #A33E55;
    font-weight: 700;
}

.rs-products .rs-stock-status {
    display: inline-block;
    margin-left: 4px;
    padding: 3px 6px;
    border-radius: 4px;
    background: #FCEBED;
    color: #842B35;
    font-size: 10px;
    font-weight: 700;
}

.rs-products .rs-action-group {
    display: flex;
    align-items: center;
    gap: 10px;
    white-space: nowrap;
}

.rs-products .rs-action-link {
    color: #641F35;
    font-size: 12px;
    font-weight: 650;
    text-decoration: none;
}

.rs-products .rs-action-link:hover {
    text-decoration: underline;
}

.rs-products .rs-action-delete {
    color: #A33E55;
}

.rs-products .rs-staff-label {
    color: #887B7D;
    font-size: 11px;
}

/* Kosong */

.rs-products .rs-empty-state {
    padding: 35px 15px;
    text-align: center;
    color: #887B7D;
}

.rs-products .rs-empty-state strong {
    display: block;
    color: #641F35;
    font-size: 14px;
    margin-bottom: 5px;
}

.rs-products .rs-empty-state p {
    font-size: 12px;
    margin: 0;
}

/* Pagination */

.rs-products .rs-pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 7px;
    flex-wrap: wrap;
    margin-top: 20px;
}

.rs-products .rs-pagination a,
.rs-products .rs-pagination span {
    min-width: 34px;
    box-sizing: border-box;
    padding: 8px 11px;
    border-radius: 6px;
    border: 1px solid #DED2C9;
    color: #641F35;
    text-align: center;
    text-decoration: none;
    font-size: 12px;
}

.rs-products .rs-pagination a:hover {
    background: #E8DED2;
}

.rs-products .rs-pagination .rs-current {
    background: #641F35;
    border-color: #641F35;
    color: #fff;
    font-weight: 700;
}

@media (max-width: 750px) {
    .rs-products .rs-filter-form {
        grid-template-columns: 1fr 1fr;
    }

    .rs-products .rs-filter-form input {
        grid-column: 1 / -1;
    }
}

@media (max-width: 450px) {
    .rs-products .rs-filter-form {
        grid-template-columns: 1fr;
    }

    .rs-products .rs-filter-form input {
        grid-column: auto;
    }

    .rs-products .rs-filter-submit,
    .rs-products .rs-filter-reset {
        width: 100%;
    }

    .rs-products .rs-table-panel {
        padding: 9px;
    }
}
</style>

<div class="rs-products">

    <div class="rs-products-heading">
        <div>
            <h1>Data Produk</h1>
            <p>Kelola informasi dan persediaan produk.</p>
        </div>

        <?php if ($isAdmin): ?>
            <a href="create.php" class="rs-add-button">
                + Tambah Produk
            </a>
        <?php endif; ?>
    </div>

    <div class="rs-filter-panel">

        <form method="GET" class="rs-filter-form">

            <input
                type="text"
                name="search"
                placeholder="Cari nama atau SKU produk..."
                value="<?= e($search) ?>"
            >

            <select name="category">
                <option value="">Semua Kategori</option>

                <?php foreach ($categories as $category): ?>
                    <option
                        value="<?= (int) $category['id'] ?>"
                        <?= ($categoryId == $category['id']) ? 'selected' : '' ?>
                    >
                        <?= e($category['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <input type="hidden" name="sort" value="<?= e($sort) ?>">
            <input type="hidden" name="order" value="<?= e($order) ?>">

            <button type="submit" class="rs-filter-submit">
                Terapkan
            </button>

            <a href="index.php" class="rs-filter-reset">
                Reset
            </a>

        </form>

    </div>

    <div class="rs-products-summary">
        <span>
            Menampilkan <?= count($products) ?> dari
            <?= number_format($totalProducts) ?> produk
        </span>

        <span>
            Halaman <?= $page ?> dari <?= $totalPages ?>
        </span>
    </div>

    <div class="rs-table-panel">

        <table class="rs-product-table">

            <thead>
                <tr>
                    <th>No.</th>

                    <th>
                        <a href="<?= e(sortUrl('sku', $search, $categoryId, $sort, $order)) ?>">
                            SKU ↕
                        </a>
                    </th>

                    <th>
                        <a href="<?= e(sortUrl('name', $search, $categoryId, $sort, $order)) ?>">
                            Nama Produk ↕
                        </a>
                    </th>

                    <th>
                        <a href="<?= e(sortUrl('category', $search, $categoryId, $sort, $order)) ?>">
                            Kategori ↕
                        </a>
                    </th>

                    <th>
                        <a href="<?= e(sortUrl('price', $search, $categoryId, $sort, $order)) ?>">
                            Harga ↕
                        </a>
                    </th>

                    <th>
                        <a href="<?= e(sortUrl('stock', $search, $categoryId, $sort, $order)) ?>">
                            Stok ↕
                        </a>
                    </th>

                    <th>Minimum Stok</th>
                    <th>Supplier</th>
                    <th>Aksi</th>
                </tr>
            </thead>

            <tbody>

                <?php if (!empty($products)): ?>

                    <?php foreach ($products as $index => $product): ?>

                        <tr>
                            <td><?= $offset + $index + 1 ?></td>

                            <td>
                                <span class="rs-sku">
                                    <?= e($product['sku']) ?>
                                </span>
                            </td>

                            <td>
                                <span class="rs-product-name">
                                    <?= e($product['name']) ?>
                                </span>
                            </td>

                            <td>
                                <?= e($product['category_name'] ?? '-') ?>
                            </td>

                            <td>
                                Rp <?= number_format(
                                    (float) $product['price'],
                                    0,
                                    ',',
                                    '.'
                                ) ?>
                            </td>

                            <td>
                                <?php if ((int) $product['stock'] <= (int) $product['minimum_stock']): ?>

                                    <span class="rs-stock-low">
                                        <?= number_format((int) $product['stock']) ?>
                                    </span>

                                    <span class="rs-stock-status">Menipis</span>

                                <?php else: ?>

                                    <span class="rs-stock-normal">
                                        <?= number_format((int) $product['stock']) ?>
                                    </span>

                                <?php endif; ?>
                            </td>

                            <td>
                                <?= number_format((int) $product['minimum_stock']) ?>
                            </td>

                            <td>
                                <?= e($product['supplier_names'] ?? '-') ?>
                            </td>

                            <td>
                                <?php if ($isAdmin): ?>

                                    <div class="rs-action-group">
                                        <a
                                            href="edit.php?id=<?= (int) $product['id'] ?>"
                                            class="rs-action-link"
                                        >
                                            Edit
                                        </a>

                                        <a
                                            href="delete.php?id=<?= (int) $product['id'] ?>"
                                            class="rs-action-link rs-action-delete"
                                        >
                                            Hapus
                                        </a>
                                    </div>

                                <?php else: ?>

                                    <span class="rs-staff-label">Lihat saja</span>

                                <?php endif; ?>
                            </td>
                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="9">
                            <div class="rs-empty-state">
                                <strong>Produk tidak ditemukan</strong>
                                <p>Coba ubah kata pencarian atau filter kategori.</p>
                            </div>
                        </td>
                    </tr>

                <?php endif; ?>

            </tbody>

        </table>

        <?php if ($totalProducts > 0): ?>

            <div class="rs-pagination">

                <?php if ($page > 1): ?>
                    <a href="<?= e(pageUrl($page - 1, $search, $categoryId, $sort, $order)) ?>">
                        &laquo;
                    </a>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $totalPages; $i++): ?>

                    <?php if ($i === $page): ?>
                        <span class="rs-current"><?= $i ?></span>
                    <?php else: ?>
                        <a href="<?= e(pageUrl($i, $search, $categoryId, $sort, $order)) ?>">
                            <?= $i ?>
                        </a>
                    <?php endif; ?>

                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                    <a href="<?= e(pageUrl($page + 1, $search, $categoryId, $sort, $order)) ?>">
                        &raquo;
                    </a>
                <?php endif; ?>

            </div>

        <?php endif; ?>

    </div>

</div>

</main>
</div>
</body>
</html>
