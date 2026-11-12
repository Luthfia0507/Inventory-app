
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

$error = '';

$categories = $pdo->query(
    "SELECT id, name FROM categories ORDER BY name ASC"
)->fetchAll();

$suppliers = $pdo->query(
    "SELECT id, name FROM suppliers ORDER BY name ASC"
)->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $sku = strtoupper(trim($_POST['sku'] ?? ''));
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $categoryId = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT);
    $priceInput = trim($_POST['price'] ?? '');
    $stockInput = trim($_POST['stock'] ?? '');
    $minimumStockInput = trim($_POST['minimum_stock'] ?? '');
    $supplierIds = $_POST['suppliers'] ?? [];

    try {

        if (
            !isset($_POST['csrf_token']) ||
            !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
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
            throw new Exception('Silakan pilih kategori produk.');
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

        if (!preg_match('/^\d+$/', $stockInput)) {
            throw new Exception('Stok harus berupa bilangan bulat dan tidak boleh negatif.');
        }

        if (!preg_match('/^\d+$/', $minimumStockInput)) {
            throw new Exception('Minimum stok harus berupa bilangan bulat dan tidak boleh negatif.');
        }

        if (strlen($description) > 65535) {
            throw new Exception('Deskripsi terlalu panjang.');
        }

        $stock = (int) $stockInput;
        $minimumStock = (int) $minimumStockInput;
        $price = (float) $priceInput;

        if ($stock > 2147483647 || $minimumStock > 2147483647) {
            throw new Exception('Jumlah stok terlalu besar.');
        }

        // Periksa SKU
        $skuCheck = $pdo->prepare(
            "SELECT COUNT(*) FROM products WHERE sku = :sku"
        );
        $skuCheck->execute(['sku' => $sku]);

        if ((int) $skuCheck->fetchColumn() > 0) {
            throw new Exception('SKU sudah digunakan. Gunakan SKU lain.');
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
                throw new Exception('Supplier yang dipilih tidak valid.');
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
                throw new Exception('Ada supplier yang tidak ditemukan.');
            }
        }

        $pdo->beginTransaction();

        // Simpan produk
        $insertProduct = $pdo->prepare(
            "INSERT INTO products
                (category_id, sku, name, description, price, stock, minimum_stock)
             VALUES
                (:category_id, :sku, :name, :description, :price, :stock, :minimum_stock)"
        );

        $insertProduct->execute([
            'category_id' => $categoryId,
            'sku' => $sku,
            'name' => $name,
            'description' => $description !== '' ? $description : null,
            'price' => $price,
            'stock' => $stock,
            'minimum_stock' => $minimumStock
        ]);

        $productId = (int) $pdo->lastInsertId();

        // Simpan relasi supplier
        if (!empty($validSupplierIds)) {
            $insertSupplier = $pdo->prepare(
                "INSERT INTO product_supplier (product_id, supplier_id)
                 VALUES (:product_id, :supplier_id)"
            );

            foreach ($validSupplierIds as $supplierId) {
                $insertSupplier->execute([
                    'product_id' => $productId,
                    'supplier_id' => $supplierId
                ]);
            }
        }

        // Catat stok awal
        if ($stock > 0) {
            $insertMovement = $pdo->prepare(
                "INSERT INTO stock_movements
                    (product_id, user_id, type, quantity, notes)
                 VALUES
                    (:product_id, :user_id, 'in', :quantity, :notes)"
            );

            $insertMovement->execute([
                'product_id' => $productId,
                'user_id' => (int) $_SESSION['user']['id'],
                'quantity' => $stock,
                'notes' => 'Stok awal saat produk dibuat.'
            ]);
        }

        $pdo->commit();

        $_SESSION['flash'] = 'Produk berhasil ditambahkan dan stok awal tercatat.';

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

require_once __DIR__ . '/../../views/header.php';

$selectedSuppliers = $_POST['suppliers'] ?? [];
if (!is_array($selectedSuppliers)) {
    $selectedSuppliers = [];
}
$selectedSuppliers = array_map('strval', $selectedSuppliers);

?>

<style>
/* ===== FORM TAMBAH PRODUK ===== */

.product-create {
    max-width: 950px;
    margin: 0 auto;
    padding-bottom: 30px;
}

.product-create-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.product-create-header h2 {
    color: #641F35;
    font-size: 23px;
    margin: 0 0 5px;
}

.product-create-header p {
    color: #887B7D;
    font-size: 13px;
    margin: 0;
}

.product-back {
    color: #641F35;
    background: #F5F0E6;
    border: 1px solid #DED2C9;
    padding: 9px 15px;
    border-radius: 8px;
    text-decoration: none;
    font-size: 13px;
    font-weight: 600;
}

.product-back:hover {
    background: #641F35;
    color: #fff;
}

.product-card {
    background: #F5F0E6;
    border: 1px solid #E8DED2;
    border-radius: 13px;
    padding: 25px;
    box-shadow: 0 4px 15px rgba(72, 21, 38, .06);
}

.product-form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 17px 20px;
}

.product-field {
    min-width: 0;
}

.product-field.full {
    grid-column: 1 / -1;
}

.product-field label {
    display: block;
    color: #641F35;
    font-size: 13px;
    font-weight: 650;
    margin-bottom: 7px;
}

.product-field .required {
    color: #A33E55;
}

.product-field input,
.product-field select,
.product-field textarea {
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

.product-field input,
.product-field select {
    height: 43px;
}

.product-field textarea {
    min-height: 85px;
    resize: vertical;
}

.product-field input:focus,
.product-field select:focus,
.product-field textarea:focus {
    outline: none;
    border-color: #8D465A;
    box-shadow: 0 0 0 3px rgba(141, 70, 90, .11);
}

.product-field input::placeholder,
.product-field textarea::placeholder {
    color: #B3A7A5;
}

.product-field select[multiple] {
    height: 105px;
    padding: 7px;
}

.product-field select[multiple] option {
    padding: 6px;
}

.product-field small {
    display: block;
    color: #887B7D;
    font-size: 11px;
    margin-top: 6px;
}

.product-error {
    background: #FCEBED;
    color: #842B35;
    border: 1px solid #F0C8CE;
    padding: 12px 15px;
    border-radius: 8px;
    font-size: 13px;
    margin-bottom: 17px;
}

.product-form-footer {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    border-top: 1px solid #E8DED2;
    padding-top: 19px;
    margin-top: 23px;
}

.product-btn {
    padding: 10px 19px;
    border-radius: 8px;
    font-family: inherit;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
    transition: .2s;
}

.product-btn-cancel {
    color: #514448;
    background: #E8DED2;
    border: 1px solid #DED2C9;
}

.product-btn-save {
    color: #fff;
    background: #641F35;
    border: 1px solid #641F35;
}

.product-btn-save:hover {
    background: #481526;
}

@media (max-width: 650px) {
    .product-card {
        padding: 17px;
    }

    .product-form-grid {
        grid-template-columns: 1fr;
        gap: 15px;
    }

    .product-field.full {
        grid-column: auto;
    }

    .product-form-footer {
        flex-direction: column-reverse;
    }

    .product-btn {
        text-align: center;
    }
}
</style>

<div class="product-create">

    <div class="product-create-header">
        <div>
            <h2>Tambah Produk</h2>
            <p>Masukkan informasi produk baru ke dalam persediaan.</p>
        </div>

        <a href="index.php" class="product-back">← Kembali</a>
    </div>

    <?php if ($error !== ''): ?>
        <div class="product-error">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <div class="product-card">

        <form method="POST">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= e($_SESSION['csrf_token']) ?>"
            >

            <div class="product-form-grid">

                <div class="product-field">
                    <label for="sku">SKU Produk <span class="required">*</span></label>
                    <input
                        type="text"
                        name="sku"
                        id="sku"
                        maxlength="50"
                        placeholder="Contoh: PRD-001"
                        value="<?= e($_POST['sku'] ?? '') ?>"
                        required
                    >
                </div>

                <div class="product-field">
                    <label for="name">Nama Produk <span class="required">*</span></label>
                    <input
                        type="text"
                        name="name"
                        id="name"
                        maxlength="150"
                        placeholder="Masukkan nama produk"
                        value="<?= e($_POST['name'] ?? '') ?>"
                        required
                    >
                </div>

                <div class="product-field">
                    <label for="category_id">Kategori <span class="required">*</span></label>
                    <select name="category_id" id="category_id" required>
                        <option value="">-- Pilih Kategori --</option>

                        <?php foreach ($categories as $category): ?>
                            <option
                                value="<?= (int) $category['id'] ?>"
                                <?= (($_POST['category_id'] ?? '') == $category['id']) ? 'selected' : '' ?>
                            >
                                <?= e($category['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="product-field">
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

                <div class="product-field">
                    <label for="price">Harga Produk <span class="required">*</span></label>
                    <input
                        type="number"
                        name="price"
                        id="price"
                        min="0"
                        step="0.01"
                        placeholder="Masukkan harga"
                        value="<?= e($_POST['price'] ?? '') ?>"
                        required
                    >
                </div>

                <div class="product-field">
                    <label for="stock">Stok Awal <span class="required">*</span></label>
                    <input
                        type="number"
                        name="stock"
                        id="stock"
                        min="0"
                        step="1"
                        placeholder="Masukkan jumlah stok"
                        value="<?= e($_POST['stock'] ?? '0') ?>"
                        required
                    >
                </div>

                <div class="product-field">
                    <label for="minimum_stock">Minimum Stok <span class="required">*</span></label>
                    <input
                        type="number"
                        name="minimum_stock"
                        id="minimum_stock"
                        min="0"
                        step="1"
                        value="<?= e($_POST['minimum_stock'] ?? '5') ?>"
                        required
                    >
                </div>

                <div class="product-field full">
                    <label for="description">Deskripsi Produk</label>
                    <textarea
                        name="description"
                        id="description"
                        placeholder="Masukkan deskripsi produk (opsional)"
                    ><?= e($_POST['description'] ?? '') ?></textarea>
                </div>

            </div>

            <div class="product-form-footer">
                <a href="index.php" class="product-btn product-btn-cancel">
                    Batal
                </a>

                <button type="submit" class="product-btn product-btn-save">
                    Simpan Produk
                </button>
            </div>

        </form>

    </div>
</div>

</main>
</div>
</body>
</html>
