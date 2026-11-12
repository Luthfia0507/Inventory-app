
<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/auth.php';

requireRole('admin');

if (!function_exists('e')) {
    function e($value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];
$error = '';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    exit('ID produk tidak valid.');
}

// Ambil produk
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = :id");
$stmt->execute(['id' => $id]);
$product = $stmt->fetch();

if (!$product) {
    exit('Produk tidak ditemukan.');
}

// Ambil kategori dan supplier
$categories = $pdo->query(
    "SELECT id, name FROM categories ORDER BY name ASC"
)->fetchAll();

$suppliers = $pdo->query(
    "SELECT id, name FROM suppliers ORDER BY name ASC"
)->fetchAll();

// Ambil supplier produk saat ini
$stmt = $pdo->prepare(
    "SELECT supplier_id FROM product_supplier WHERE product_id = :id"
);
$stmt->execute(['id' => $id]);

$currentSuppliers = array_map(
    'strval',
    $stmt->fetchAll(PDO::FETCH_COLUMN)
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $sku = strtoupper(trim($_POST['sku'] ?? ''));
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $categoryId = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT);
    $priceInput = trim($_POST['price'] ?? '');
    $minimumStockInput = trim($_POST['minimum_stock'] ?? '');
    $supplierIds = $_POST['suppliers'] ?? [];

    try {

        if (
            !isset($_POST['csrf_token']) ||
            !hash_equals($csrfToken, $_POST['csrf_token'])
        ) {
            throw new Exception('Permintaan tidak valid. Muat ulang halaman.');
        }

        if ($sku === '' || strlen($sku) > 50) {
            throw new Exception('SKU wajib diisi dan maksimal 50 karakter.');
        }

        if ($name === '' || strlen($name) > 150) {
            throw new Exception('Nama produk wajib diisi dan maksimal 150 karakter.');
        }

        if (!$categoryId || $categoryId < 1) {
            throw new Exception('Silakan pilih kategori.');
        }

        if (strlen($description) > 65535) {
            throw new Exception('Deskripsi terlalu panjang.');
        }

        $categoryCheck = $pdo->prepare(
            "SELECT COUNT(*) FROM categories WHERE id = :id"
        );
        $categoryCheck->execute(['id' => $categoryId]);

        if ((int) $categoryCheck->fetchColumn() === 0) {
            throw new Exception('Kategori tidak ditemukan.');
        }

        if (
            $priceInput === '' ||
            !is_numeric($priceInput) ||
            (float) $priceInput < 0 ||
            (float) $priceInput > 9999999999.99
        ) {
            throw new Exception('Harga harus berupa angka valid dan tidak boleh negatif.');
        }

        if (!preg_match('/^\d+$/', $minimumStockInput)) {
            throw new Exception('Minimum stok harus bilangan bulat dan tidak boleh negatif.');
        }

        $minimumStock = (int) $minimumStockInput;
        $price = (float) $priceInput;

        if ($minimumStock > 2147483647) {
            throw new Exception('Minimum stok terlalu besar.');
        }

        // SKU harus unik untuk produk lain
        $skuCheck = $pdo->prepare(
            "SELECT COUNT(*) FROM products WHERE sku = :sku AND id != :id"
        );
        $skuCheck->execute([
            'sku' => $sku,
            'id' => $id
        ]);

        if ((int) $skuCheck->fetchColumn() > 0) {
            throw new Exception('SKU sudah digunakan oleh produk lain.');
        }

        // Validasi supplier
        if (!is_array($supplierIds)) {
            throw new Exception('Pilihan supplier tidak valid.');
        }

        $supplierIds = array_unique($supplierIds);
        $validSupplierIds = [];

        foreach ($supplierIds as $supplierId) {
            $supplierId = filter_var($supplierId, FILTER_VALIDATE_INT);

            if (!$supplierId || $supplierId < 1) {
                throw new Exception('Supplier tidak valid.');
            }

            $validSupplierIds[] = $supplierId;
        }

        if (!empty($validSupplierIds)) {
            $placeholders = implode(
                ',',
                array_fill(0, count($validSupplierIds), '?')
            );

            $supplierCheck = $pdo->prepare(
                "SELECT COUNT(*) FROM suppliers WHERE id IN ($placeholders)"
            );
            $supplierCheck->execute($validSupplierIds);

            if ((int) $supplierCheck->fetchColumn() !== count($validSupplierIds)) {
                throw new Exception('Supplier yang dipilih tidak ditemukan.');
            }
        }

        $pdo->beginTransaction();

        // Perbarui data produk tanpa mengubah stok
        $update = $pdo->prepare(
            "UPDATE products SET
                category_id = :category_id,
                sku = :sku,
                name = :name,
                description = :description,
                price = :price,
                minimum_stock = :minimum_stock
             WHERE id = :id"
        );

        $update->execute([
            'category_id' => $categoryId,
            'sku' => $sku,
            'name' => $name,
            'description' => $description !== '' ? $description : null,
            'price' => $price,
            'minimum_stock' => $minimumStock,
            'id' => $id
        ]);

        // Perbarui relasi supplier
        $deleteRelations = $pdo->prepare(
            "DELETE FROM product_supplier WHERE product_id = :id"
        );
        $deleteRelations->execute(['id' => $id]);

        if (!empty($validSupplierIds)) {
            $insertRelation = $pdo->prepare(
                "INSERT INTO product_supplier (product_id, supplier_id)
                 VALUES (:product_id, :supplier_id)"
            );

            foreach ($validSupplierIds as $supplierId) {
                $insertRelation->execute([
                    'product_id' => $id,
                    'supplier_id' => $supplierId
                ]);
            }
        }

        $pdo->commit();

        $_SESSION['flash'] = 'Data produk berhasil diperbarui. Stok tidak diubah.';

        header('Location: index.php');
        exit;

    } catch (Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        if ($e instanceof PDOException && $e->getCode() === '23000') {
            $error = 'SKU sudah digunakan atau data terkait tidak valid.';
        } else {
            $error = $e->getMessage();
        }
    }
}

$selectedSuppliers = isset($_POST['suppliers'])
    ? array_map('strval', (array) $_POST['suppliers'])
    : $currentSuppliers;

$selectedCategory = $_POST['category_id'] ?? $product['category_id'];

require_once __DIR__ . '/../../views/header.php';

?>

<style>
/* ===== EDIT PRODUK: CSS KHUSUS HALAMAN INI ===== */

.product-edit-page {
    max-width: 950px;
    margin: 0 auto;
    padding-bottom: 30px;
}

.product-edit-page .product-edit-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
    margin-bottom: 20px;
}

.product-edit-page .product-edit-heading h2 {
    color: #641F35;
    font-size: 23px;
    margin: 0 0 5px;
}

.product-edit-page .product-edit-heading p {
    color: #887B7D;
    font-size: 13px;
    margin: 0;
}

.product-edit-page .product-edit-back {
    color: #641F35;
    background: #F5F0E6;
    border: 1px solid #DED2C9;
    padding: 9px 15px;
    border-radius: 8px;
    text-decoration: none;
    font-size: 13px;
    font-weight: 600;
}

.product-edit-page .product-edit-card {
    background: #F5F0E6;
    border: 1px solid #E8DED2;
    border-radius: 13px;
    padding: 25px;
    box-shadow: 0 4px 15px rgba(72, 21, 38, .06);
}

.product-edit-page .product-edit-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 17px 20px;
}

.product-edit-page .product-edit-field {
    min-width: 0;
}

.product-edit-page .product-edit-field.full {
    grid-column: 1 / -1;
}

.product-edit-page .product-edit-field label {
    display: block;
    color: #641F35;
    font-size: 13px;
    font-weight: 650;
    margin-bottom: 7px;
}

.product-edit-page .product-edit-required {
    color: #A33E55;
}

.product-edit-page .product-edit-field input,
.product-edit-page .product-edit-field select,
.product-edit-page .product-edit-field textarea {
    width: 100%;
    box-sizing: border-box;
    background: #FFFEFC;
    color: #352B2D;
    border: 1px solid #DED2C9;
    border-radius: 8px;
    padding: 11px 13px;
    font-family: inherit;
    font-size: 13px;
    transition: .2s;
}

.product-edit-page .product-edit-field input,
.product-edit-page .product-edit-field select {
    height: 43px;
}

.product-edit-page .product-edit-field textarea {
    min-height: 85px;
    resize: vertical;
}

.product-edit-page .product-edit-field input:focus,
.product-edit-page .product-edit-field select:focus,
.product-edit-page .product-edit-field textarea:focus {
    outline: none;
    border-color: #8D465A;
    box-shadow: 0 0 0 3px rgba(141, 70, 90, .11);
}

.product-edit-page .product-edit-field select[multiple] {
    height: 105px;
    padding: 7px;
}

.product-edit-page .product-edit-field select[multiple] option {
    padding: 6px;
}

.product-edit-page .product-edit-field small {
    display: block;
    color: #887B7D;
    font-size: 11px;
    margin-top: 6px;
}

.product-edit-page .product-edit-stock {
    display: flex;
    align-items: center;
    gap: 10px;
}

.product-edit-page .product-edit-stock input {
    flex: 1;
    background: #EEE7DC;
    color: #514448;
    cursor: not-allowed;
}

.product-edit-page .product-edit-stock small {
    flex: 1;
    margin: 0;
}

.product-edit-page .product-edit-error {
    background: #FCEBED;
    color: #842B35;
    border: 1px solid #F0C8CE;
    padding: 12px 15px;
    border-radius: 8px;
    font-size: 13px;
    margin-bottom: 17px;
}

.product-edit-page .product-edit-footer {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    border-top: 1px solid #E8DED2;
    padding-top: 19px;
    margin-top: 23px;
}

.product-edit-page .product-edit-btn {
    padding: 10px 19px;
    border-radius: 8px;
    font-family: inherit;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
    transition: .2s;
}

.product-edit-page .product-edit-cancel {
    color: #514448;
    background: #E8DED2;
    border: 1px solid #DED2C9;
}

.product-edit-page .product-edit-save {
    color: #fff;
    background: #641F35;
    border: 1px solid #641F35;
}

.product-edit-page .product-edit-save:hover {
    background: #481526;
}

@media (max-width: 650px) {
    .product-edit-page .product-edit-card {
        padding: 17px;
    }

    .product-edit-page .product-edit-grid {
        grid-template-columns: 1fr;
        gap: 15px;
    }

    .product-edit-page .product-edit-field.full {
        grid-column: auto;
    }

    .product-edit-page .product-edit-stock {
        align-items: flex-start;
        flex-direction: column;
    }

    .product-edit-page .product-edit-footer {
        flex-direction: column-reverse;
    }

    .product-edit-page .product-edit-btn {
        text-align: center;
    }
}
</style>

<div class="product-edit-page">

    <div class="product-edit-heading">
        <div>
            <h2>Edit Produk</h2>
            <p>Perbarui informasi produk tanpa mengubah stok saat ini.</p>
        </div>

        <a href="index.php" class="product-edit-back">← Kembali</a>
    </div>

    <?php if ($error !== ''): ?>
        <div class="product-edit-error">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <div class="product-edit-card">

        <form method="POST">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= e($csrfToken) ?>"
            >

            <div class="product-edit-grid">

                <div class="product-edit-field">
                    <label for="sku">SKU Produk <span class="product-edit-required">*</span></label>
                    <input
                        type="text"
                        name="sku"
                        id="sku"
                        maxlength="50"
                        value="<?= e($_POST['sku'] ?? $product['sku']) ?>"
                        required
                    >
                </div>

                <div class="product-edit-field">
                    <label for="name">Nama Produk <span class="product-edit-required">*</span></label>
                    <input
                        type="text"
                        name="name"
                        id="name"
                        maxlength="150"
                        value="<?= e($_POST['name'] ?? $product['name']) ?>"
                        required
                    >
                </div>

                <div class="product-edit-field">
                    <label for="category_id">Kategori <span class="product-edit-required">*</span></label>
                    <select name="category_id" id="category_id" required>
                        <option value="">-- Pilih Kategori --</option>

                        <?php foreach ($categories as $category): ?>
                            <option
                                value="<?= (int) $category['id'] ?>"
                                <?= ((string) $selectedCategory === (string) $category['id']) ? 'selected' : '' ?>
                            >
                                <?= e($category['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="product-edit-field">
                    <label for="suppliers">Supplier</label>
                    <select name="suppliers[]" id="suppliers" multiple size="2">
                        <?php foreach ($suppliers as $supplier): ?>
                            <option
                                value="<?= (int) $supplier['id'] ?>"
                                <?= in_array(
                                    (string) $supplier['id'],
                                    $selectedSuppliers,
                                    true
                                ) ? 'selected' : '' ?>
                            >
                                <?= e($supplier['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small>Pilih lebih dari satu dengan menahan Ctrl.</small>
                </div>

                <div class="product-edit-field">
                    <label for="price">Harga Produk <span class="product-edit-required">*</span></label>
                    <input
                        type="number"
                        name="price"
                        id="price"
                        min="0"
                        step="0.01"
                        value="<?= e($_POST['price'] ?? $product['price']) ?>"
                        required
                    >
                </div>

                <div class="product-edit-field">
                    <label for="stock">Stok Saat Ini</label>
                    <div class="product-edit-stock">
                        <input
                            type="number"
                            id="stock"
                            value="<?= (int) $product['stock'] ?>"
                            disabled
                        >
                        <small>Ubah stok melalui Transaksi Stok.</small>
                    </div>
                </div>

                <div class="product-edit-field">
                    <label for="minimum_stock">Minimum Stok <span class="product-edit-required">*</span></label>
                    <input
                        type="number"
                        name="minimum_stock"
                        id="minimum_stock"
                        min="0"
                        step="1"
                        value="<?= e($_POST['minimum_stock'] ?? $product['minimum_stock']) ?>"
                        required
                    >
                </div>

                <div class="product-edit-field full">
                    <label for="description">Deskripsi Produk</label>
                    <textarea
                        name="description"
                        id="description"
                        placeholder="Masukkan deskripsi produk (opsional)"
                    ><?= e($_POST['description'] ?? $product['description']) ?></textarea>
                </div>

            </div>

            <div class="product-edit-footer">
                <a href="index.php" class="product-edit-btn product-edit-cancel">
                    Batal
                </a>

                <button type="submit" class="product-edit-btn product-edit-save">
                    Simpan Perubahan
                </button>
            </div>

        </form>

    </div>
</div>

</main>
</div>
</body>
</html>
