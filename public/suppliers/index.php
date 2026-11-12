
<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/auth.php';

requireRole('admin');

// Token CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];
$error = '';
$success = $_SESSION['flash'] ?? '';

unset($_SESSION['flash']);

function e($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

// Proses tambah, edit, dan hapus supplier
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals($csrfToken, $_POST['csrf_token'])
    ) {
        exit('Permintaan tidak valid. Silakan muat ulang halaman.');
    }

    $action = $_POST['action'] ?? '';

    try {

        // TAMBAH SUPPLIER
        if ($action === 'create') {

            $name = trim($_POST['name'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $address = trim($_POST['address'] ?? '');

            if ($name === '') {
                throw new Exception('Nama supplier wajib diisi.');
            }

            if (strlen($name) > 150) {
                throw new Exception('Nama supplier maksimal 150 karakter.');
            }

            if (strlen($phone) > 20) {
                throw new Exception('Nomor telepon maksimal 20 karakter.');
            }

            if (strlen($email) > 150) {
                throw new Exception('Email maksimal 150 karakter.');
            }

            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Format email tidak valid.');
            }

            $stmt = $pdo->prepare(
                "INSERT INTO suppliers (name, phone, email, address)
                 VALUES (:name, :phone, :email, :address)"
            );

            $stmt->execute([
                'name' => $name,
                'phone' => $phone !== '' ? $phone : null,
                'email' => $email !== '' ? $email : null,
                'address' => $address !== '' ? $address : null
            ]);

            $_SESSION['flash'] = 'Supplier berhasil ditambahkan.';
        }

        // EDIT SUPPLIER
        elseif ($action === 'update') {

            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            $name = trim($_POST['name'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $address = trim($_POST['address'] ?? '');

            if (!$id || $id < 1) {
                throw new Exception('ID supplier tidak valid.');
            }

            if ($name === '') {
                throw new Exception('Nama supplier wajib diisi.');
            }

            if (strlen($name) > 150) {
                throw new Exception('Nama supplier maksimal 150 karakter.');
            }

            if (strlen($phone) > 20) {
                throw new Exception('Nomor telepon maksimal 20 karakter.');
            }

            if (strlen($email) > 150) {
                throw new Exception('Email maksimal 150 karakter.');
            }

            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Format email tidak valid.');
            }

            $stmt = $pdo->prepare(
                "UPDATE suppliers
                 SET name = :name,
                     phone = :phone,
                     email = :email,
                     address = :address
                 WHERE id = :id"
            );

            $stmt->execute([
                'name' => $name,
                'phone' => $phone !== '' ? $phone : null,
                'email' => $email !== '' ? $email : null,
                'address' => $address !== '' ? $address : null,
                'id' => $id
            ]);

            $_SESSION['flash'] = 'Supplier berhasil diperbarui.';
        }

        // HAPUS SUPPLIER
        elseif ($action === 'delete') {

            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

            if (!$id || $id < 1) {
                throw new Exception('ID supplier tidak valid.');
            }

            $check = $pdo->prepare(
                "SELECT COUNT(*)
                 FROM product_supplier
                 WHERE supplier_id = :id"
            );

            $check->execute(['id' => $id]);
            $totalProducts = (int) $check->fetchColumn();

            if ($totalProducts > 0) {
                throw new Exception(
                    'Supplier tidak dapat dihapus karena masih terhubung dengan produk.'
                );
            }

            $stmt = $pdo->prepare(
                "DELETE FROM suppliers WHERE id = :id"
            );

            $stmt->execute(['id' => $id]);

            $_SESSION['flash'] = 'Supplier berhasil dihapus.';
        }

        else {
            throw new Exception('Aksi tidak dikenali.');
        }

        header('Location: index.php');
        exit;

    } catch (PDOException $e) {

        $error = 'Terjadi kesalahan pada database.';

    } catch (Exception $e) {

        $error = $e->getMessage();
    }
}

// Ambil daftar supplier
$stmt = $pdo->query(
    "SELECT
        s.id,
        s.name,
        s.phone,
        s.email,
        s.address,
        COUNT(ps.product_id) AS total_products
     FROM suppliers s
     LEFT JOIN product_supplier ps ON ps.supplier_id = s.id
     GROUP BY s.id, s.name, s.phone, s.email, s.address
     ORDER BY s.name ASC"
);

$suppliers = $stmt->fetchAll();

// Ambil supplier untuk diedit
$editSupplier = null;

if (isset($_GET['edit'])) {

    $editId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);

    if ($editId && $editId > 0) {

        $stmt = $pdo->prepare(
            "SELECT * FROM suppliers WHERE id = :id"
        );

        $stmt->execute(['id' => $editId]);
        $editSupplier = $stmt->fetch();
    }

    if (!$editSupplier) {
        $error = 'Supplier tidak ditemukan.';
    }
}

$pageTitle = 'Manajemen Supplier | Ruang Stok';

require_once __DIR__ . '/../../views/header.php';

?>

<style>
    .supplier-heading {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 15px;
        flex-wrap: wrap;
        margin-bottom: 24px;
    }

    .supplier-heading h1 {
        margin: 0 0 5px;
    }

    .supplier-heading p {
        margin: 0;
        color: var(--muted);
        font-size: 14px;
    }

    .btn-add {
        background: var(--maroon);
        color: white;
        padding: 11px 17px;
        border-radius: 7px;
        border: none;
        text-decoration: none;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
    }

    .btn-add:hover {
        background: var(--maroon-dark);
    }

    .supplier-card {
        background: white;
        border: 1px solid var(--beige);
        border-radius: 11px;
        padding: 20px;
        margin-bottom: 20px;
        box-shadow: var(--shadow);
    }

    .supplier-card h2 {
        color: var(--maroon);
        font-size: 17px;
        margin: 0 0 17px;
    }

    .supplier-form {
        display: grid;
        gap: 14px;
    }

    .supplier-form label {
        display: block;
        font-size: 13px;
        font-weight: 600;
        color: var(--text);
        margin-bottom: 7px;
    }

    .supplier-form input,
    .supplier-form textarea {
        width: 100%;
        box-sizing: border-box;
    }

    .form-actions {
        display: flex;
        gap: 10px;
        align-items: center;
        flex-wrap: wrap;
        margin-top: 5px;
    }

    .btn-cancel {
        color: var(--maroon);
        text-decoration: none;
        padding: 10px 15px;
        border: 1px solid var(--beige);
        border-radius: 7px;
        font-size: 14px;
        background: white;
        cursor: pointer;
    }

    .alert {
        padding: 12px 15px;
        border-radius: 7px;
        margin-bottom: 18px;
        font-size: 14px;
    }

    .alert-success {
        background: #EDF5ED;
        color: #386641;
        border: 1px solid #D5E7D5;
    }

    .alert-error {
        background: #FBEDEE;
        color: #A33D4D;
        border: 1px solid #F0D0D5;
    }

    .supplier-table-wrapper {
        overflow-x: auto;
    }

    .supplier-table {
        width: 100%;
        min-width: 850px;
        border-collapse: collapse;
    }

    .supplier-table th {
        background: #F0E6E3;
        color: var(--maroon);
        font-size: 13px;
    }

    .supplier-table th,
    .supplier-table td {
        padding: 14px 13px;
        border-bottom: 1px solid #EEE7E2;
        text-align: left;
        font-size: 13px;
    }

    .supplier-table tbody tr:hover {
        background: #FCF9F6;
    }

    .supplier-count {
        display: inline-block;
        background: var(--ivory);
        color: var(--maroon);
        padding: 5px 10px;
        border-radius: 20px;
        font-weight: 700;
    }

    .action-link {
        color: var(--maroon);
        font-weight: 600;
        text-decoration: none;
        margin-right: 10px;
    }

    .action-link:hover {
        text-decoration: underline;
    }

    .action-delete {
        background: none;
        border: none;
        color: var(--danger);
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        padding: 0;
    }

    .empty-state {
        padding: 30px 15px;
        text-align: center;
        color: var(--muted);
    }
</style>

<div class="supplier-heading">

    <div>
        <h1>Manajemen Supplier</h1>
        <p>Kelola informasi pemasok produk.</p>
    </div>

    <?php if (!$editSupplier): ?>

        <button
            type="button"
            class="btn-add"
            onclick="toggleSupplierForm()"
        >
            + Tambah Supplier
        </button>

    <?php endif; ?>

</div>

<?php if ($success !== ''): ?>
    <div class="alert alert-success">
        <?= e($success) ?>
    </div>
<?php endif; ?>

<?php if ($error !== ''): ?>
    <div class="alert alert-error">
        <?= e($error) ?>
    </div>
<?php endif; ?>

<?php if ($editSupplier): ?>

    <div class="supplier-card">

        <h2>Edit Supplier</h2>

        <form method="POST" class="supplier-form">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= e($csrfToken) ?>"
            >

            <input type="hidden" name="action" value="update">

            <input
                type="hidden"
                name="id"
                value="<?= (int) $editSupplier['id'] ?>"
            >

            <div>
                <label>Nama Supplier</label>

                <input
                    type="text"
                    name="name"
                    maxlength="150"
                    required
                    value="<?= e($editSupplier['name']) ?>"
                >
            </div>

            <div>
                <label>Nomor Telepon</label>

                <input
                    type="text"
                    name="phone"
                    maxlength="20"
                    value="<?= e($editSupplier['phone'] ?? '') ?>"
                >
            </div>

            <div>
                <label>Email</label>

                <input
                    type="email"
                    name="email"
                    maxlength="150"
                    value="<?= e($editSupplier['email'] ?? '') ?>"
                >
            </div>

            <div>
                <label>Alamat</label>

                <textarea
                    name="address"
                    rows="4"
                ><?= e($editSupplier['address'] ?? '') ?></textarea>
            </div>

            <div class="form-actions">

                <button type="submit" class="btn-add">
                    Simpan Perubahan
                </button>

                <a href="index.php" class="btn-cancel">
                    Batal
                </a>

            </div>

        </form>

    </div>

<?php else: ?>

    <div
        class="supplier-card"
        id="supplierForm"
        style="display:none;"
    >

        <h2>Tambah Supplier Baru</h2>

        <form method="POST" class="supplier-form">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= e($csrfToken) ?>"
            >

            <input type="hidden" name="action" value="create">

            <div>
                <label>Nama Supplier</label>

                <input
                    type="text"
                    name="name"
                    maxlength="150"
                    placeholder="Masukkan nama supplier"
                    required
                >
            </div>

            <div>
                <label>Nomor Telepon</label>

                <input
                    type="text"
                    name="phone"
                    maxlength="20"
                    placeholder="Masukkan nomor telepon"
                >
            </div>

            <div>
                <label>Email</label>

                <input
                    type="email"
                    name="email"
                    maxlength="150"
                    placeholder="supplier@email.com"
                >
            </div>

            <div>
                <label>Alamat</label>

                <textarea
                    name="address"
                    rows="4"
                    placeholder="Masukkan alamat supplier"
                ></textarea>
            </div>

            <div class="form-actions">

                <button type="submit" class="btn-add">
                    Tambah Supplier
                </button>

                <button
                    type="button"
                    class="btn-cancel"
                    onclick="toggleSupplierForm()"
                >
                    Batal
                </button>

            </div>

        </form>

    </div>

<?php endif; ?>

<div class="supplier-card">

    <h2>Daftar Supplier</h2>

    <div class="supplier-table-wrapper">

        <table class="supplier-table">

            <thead>
                <tr>
                    <th>No.</th>
                    <th>Nama Supplier</th>
                    <th>Telepon</th>
                    <th>Email</th>
                    <th>Alamat</th>
                    <th>Jumlah Produk</th>
                    <th>Aksi</th>
                </tr>
            </thead>

            <tbody>

                <?php if (count($suppliers) > 0): ?>

                    <?php foreach ($suppliers as $index => $supplier): ?>

                        <tr>

                            <td><?= $index + 1 ?></td>

                            <td>
                                <strong><?= e($supplier['name']) ?></strong>
                            </td>

                            <td>
                                <?= e($supplier['phone'] ?? '-') ?>
                            </td>

                            <td>
                                <?= e($supplier['email'] ?? '-') ?>
                            </td>

                            <td>
                                <?= e($supplier['address'] ?? '-') ?>
                            </td>

                            <td>
                                <span class="supplier-count">
                                    <?= (int) $supplier['total_products'] ?>
                                </span>
                            </td>

                            <td>

                                <a
                                    href="index.php?edit=<?= (int) $supplier['id'] ?>"
                                    class="action-link"
                                >
                                    Edit
                                </a>

                                <form
                                    method="POST"
                                    style="display:inline;"
                                    onsubmit="return confirm('Yakin ingin menghapus supplier ini?')"
                                >

                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= e($csrfToken) ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="delete"
                                    >

                                    <input
                                        type="hidden"
                                        name="id"
                                        value="<?= (int) $supplier['id'] ?>"
                                    >

                                    <button
                                        type="submit"
                                        class="action-delete"
                                    >
                                        Hapus
                                    </button>

                                </form>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="7">
                            <div class="empty-state">
                                Belum ada supplier yang ditambahkan.
                            </div>
                        </td>
                    </tr>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>

<script>
    function toggleSupplierForm() {
        const form = document.getElementById('supplierForm');

        if (form.style.display === 'none') {
            form.style.display = 'block';
        } else {
            form.style.display = 'none';
        }
    }
</script>

</main>
</div>

</body>
</html>
