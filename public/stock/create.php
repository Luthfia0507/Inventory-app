
<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/auth.php';

requireLogin();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Mendukung nama koneksi PDO yang digunakan di config.
$pdo = $pdo ?? ($conn ?? ($db ?? null));

if (!$pdo instanceof PDO) {
    exit('Koneksi database PDO tidak ditemukan. Periksa config/database.php.');
}

if (!function_exists('e')) {
    function e($value): string {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

$pageTitle = 'Transaksi Stok | RUANG STOK';
$activeMenu = 'stock-create';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];
$old = [
    'product_id' => '',
    'type' => 'in',
    'quantity' => '',
    'notes' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old['product_id'] = $_POST['product_id'] ?? '';
    $old['type'] = $_POST['type'] ?? '';
    $old['quantity'] = $_POST['quantity'] ?? '';
    $old['notes'] = trim($_POST['notes'] ?? '');

    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $errors[] = 'Permintaan tidak valid. Silakan muat ulang halaman.';
    }

    $productId = filter_var($old['product_id'], FILTER_VALIDATE_INT);
    $quantity = filter_var($old['quantity'], FILTER_VALIDATE_INT);

    if (!$productId || $productId < 1) {
        $errors[] = 'Pilih produk terlebih dahulu.';
    }

    if (!in_array($old['type'], ['in', 'out'], true)) {
        $errors[] = 'Jenis transaksi tidak valid.';
    }

    if ($quantity === false || $quantity < 1) {
        $errors[] = 'Jumlah transaksi harus berupa angka lebih dari nol.';
    }

    if (strlen($old['notes']) > 5000) {
        $errors[] = 'Catatan maksimal 5000 karakter.';
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                "SELECT id, name, stock
                 FROM products
                 WHERE id = ?
                 FOR UPDATE"
            );
            $stmt->execute([$productId]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$product) {
                throw new RuntimeException('Produk tidak ditemukan.');
            }

            $currentStock = (int) $product['stock'];

            if ($old['type'] === 'out' && $quantity > $currentStock) {
                throw new RuntimeException(
                    'Stok tidak mencukupi. Stok tersedia saat ini: ' .
                    $currentStock . '.'
                );
            }

            if ($old['type'] === 'in') {
                $newStock = $currentStock + $quantity;
            } else {
                $newStock = $currentStock - $quantity;
            }

            $update = $pdo->prepare(
                "UPDATE products SET stock = ?, updated_at = NOW()
                 WHERE id = ?"
            );
            $update->execute([$newStock, $productId]);

            $movement = $pdo->prepare(
                "INSERT INTO stock_movements
                 (product_id, user_id, type, quantity, notes, created_at)
                 VALUES (?, ?, ?, ?, ?, NOW())"
            );

            $movement->execute([
                $productId,
                $_SESSION['user']['id'],
                $old['type'],
                $quantity,
                $old['notes'] !== '' ? $old['notes'] : null
            ]);

            $pdo->commit();

            $_SESSION['stock_success'] =
                'Transaksi stok berhasil disimpan. Stok ' .
                $product['name'] . ' sekarang ' . $newStock . '.';

            header('Location: index.php');
            exit;

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $errors[] = $e instanceof RuntimeException
                ? $e->getMessage()
                : 'Transaksi gagal disimpan. Silakan periksa kembali data dan koneksi database.';
        }
    }
}

$stmt = $pdo->query(
    "SELECT id, sku, name, stock
     FROM products
     ORDER BY name ASC"
);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../../views/header.php';
?>

<style>
.stock-page {
    max-width: 850px;
    margin: 0 auto;
}

.stock-heading {
    margin-bottom: 24px;
}

.stock-heading h2 {
    color: var(--maroon);
    font-size: 24px;
    margin: 0 0 7px;
}

.stock-heading p {
    color: var(--muted);
    margin: 0;
}

.stock-form-card {
    background: var(--cream);
    border: 1px solid var(--beige);
    border-radius: 16px;
    padding: 28px;
    box-shadow: var(--shadow);
}

.stock-form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.stock-field {
    margin-bottom: 20px;
}

.stock-field.full {
    grid-column: 1 / -1;
}

.stock-field label {
    display: block;
    color: var(--text);
    font-weight: 600;
    margin-bottom: 8px;
}

.stock-field input,
.stock-field select,
.stock-field textarea {
    width: 100%;
    padding: 12px 14px;
    border: 1px solid var(--beige);
    border-radius: 9px;
    background: #fff;
    color: var(--text);
    font: inherit;
    box-sizing: border-box;
}

.stock-field input:focus,
.stock-field select:focus,
.stock-field textarea:focus {
    outline: 2px solid var(--dusty-wine, #8D465A);
    border-color: var(--maroon);
}

.stock-field textarea {
    min-height: 105px;
    resize: vertical;
}

.stock-hint {
    display: block;
    color: var(--muted);
    font-size: 13px;
    margin-top: 6px;
}

.stock-alert {
    padding: 13px 16px;
    border-radius: 9px;
    margin-bottom: 18px;
}

.stock-alert.error {
    color: #842B35;
    background: #FCEBED;
    border: 1px solid #F1C8CE;
}

.stock-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    border-top: 1px solid var(--beige);
    padding-top: 20px;
    margin-top: 5px;
}

.stock-actions a,
.stock-actions button {
    display: inline-block;
    padding: 11px 20px;
    border-radius: 9px;
    text-decoration: none;
    font: inherit;
    font-weight: 600;
    cursor: pointer;
}

.stock-cancel {
    background: #E8DED2;
    color: var(--text);
}

.stock-submit {
    background: var(--maroon);
    color: #fff;
    border: none;
}

.stock-submit:hover {
    background: var(--maroon-dark);
}

@media (max-width: 650px) {
    .stock-form-grid {
        grid-template-columns: 1fr;
        gap: 0;
    }

    .stock-field.full {
        grid-column: auto;
    }

    .stock-form-card {
        padding: 20px;
    }
}
</style>

<div class="stock-page">

    <div class="stock-heading">
        <h2>Transaksi Stok</h2>
        <p>Catat penambahan atau pengurangan persediaan produk.</p>
    </div>

    <?php if ($errors): ?>
        <div class="stock-alert error">
            <?php foreach ($errors as $error): ?>
                <div><?= e($error) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="stock-form-card">

        <form method="POST" action="">
            <input
                type="hidden"
                name="csrf_token"
                value="<?= e($_SESSION['csrf_token']) ?>"
            >

            <div class="stock-form-grid">

                <div class="stock-field full">
                    <label for="product_id">Pilih Produk</label>
                    <select name="product_id" id="product_id" required>
                        <option value="">-- Pilih Produk --</option>

                        <?php foreach ($products as $product): ?>
                            <option
                                value="<?= (int) $product['id'] ?>"
                                <?= (string) $old['product_id'] === (string) $product['id'] ? 'selected' : '' ?>
                            >
                                <?= e($product['sku']) ?> -
                                <?= e($product['name']) ?>
                                (Stok: <?= (int) $product['stock'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="stock-field">
                    <label for="type">Jenis Transaksi</label>
                    <select name="type" id="type" required>
                        <option value="in" <?= $old['type'] === 'in' ? 'selected' : '' ?>>
                            Stok Masuk
                        </option>
                        <option value="out" <?= $old['type'] === 'out' ? 'selected' : '' ?>>
                            Stok Keluar
                        </option>
                    </select>
                </div>

                <div class="stock-field">
                    <label for="quantity">Jumlah</label>
                    <input
                        type="number"
                        name="quantity"
                        id="quantity"
                        min="1"
                        step="1"
                        value="<?= e($old['quantity']) ?>"
                        placeholder="Masukkan jumlah"
                        required
                    >
                </div>

                <div class="stock-field full">
                    <label for="notes">Catatan</label>
                    <textarea
                        name="notes"
                        id="notes"
                        maxlength="5000"
                        placeholder="Contoh: Pembelian barang dari supplier..."
                    ><?= e($old['notes']) ?></textarea>
                    <span class="stock-hint">
                        Catatan bersifat opsional.
                    </span>
                </div>

            </div>

            <div class="stock-actions">
                <a href="index.php" class="stock-cancel">Batal</a>
                <button type="submit" class="stock-submit">
                    Simpan Transaksi
                </button>
            </div>
        </form>

    </div>
</div>

</main>
</div>
</body>
</html>
