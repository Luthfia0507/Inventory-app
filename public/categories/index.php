
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

// Proses tambah, edit, dan hapus
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals($csrfToken, $_POST['csrf_token'])
    ) {
        exit('Permintaan tidak valid. Silakan muat ulang halaman.');
    }

    $action = $_POST['action'] ?? '';

    try {

        // TAMBAH KATEGORI
        if ($action === 'create') {

            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');

            if ($name === '') {
                throw new Exception('Nama kategori wajib diisi.');
            }

            if (strlen($name) > 100) {
                throw new Exception('Nama kategori maksimal 100 karakter.');
            }

            $stmt = $pdo->prepare(
                "INSERT INTO categories (name, description)
                 VALUES (:name, :description)"
            );

            $stmt->execute([
                'name' => $name,
                'description' => $description !== '' ? $description : null
            ]);

            $_SESSION['flash'] = 'Kategori berhasil ditambahkan.';
        }

        // EDIT KATEGORI
        elseif ($action === 'update') {

            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');

            if (!$id || $id < 1) {
                throw new Exception('ID kategori tidak valid.');
            }

            if ($name === '') {
                throw new Exception('Nama kategori wajib diisi.');
            }

            if (strlen($name) > 100) {
                throw new Exception('Nama kategori maksimal 100 karakter.');
            }

            $stmt = $pdo->prepare(
                "UPDATE categories
                 SET name = :name, description = :description
                 WHERE id = :id"
            );

            $stmt->execute([
                'name' => $name,
                'description' => $description !== '' ? $description : null,
                'id' => $id
            ]);

            $_SESSION['flash'] = 'Kategori berhasil diperbarui.';
        }

        // HAPUS KATEGORI
        elseif ($action === 'delete') {

            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

            if (!$id || $id < 1) {
                throw new Exception('ID kategori tidak valid.');
            }

            $check = $pdo->prepare(
                "SELECT COUNT(*) FROM products WHERE category_id = :id"
            );

            $check->execute(['id' => $id]);
            $productCount = (int) $check->fetchColumn();

            if ($productCount > 0) {
                throw new Exception(
                    'Kategori tidak dapat dihapus karena masih digunakan oleh produk.'
                );
            }

            $stmt = $pdo->prepare(
                "DELETE FROM categories WHERE id = :id"
            );

            $stmt->execute(['id' => $id]);

            $_SESSION['flash'] = 'Kategori berhasil dihapus.';
        }

        else {
            throw new Exception('Aksi tidak dikenali.');
        }

        header('Location: index.php');
        exit;

    } catch (PDOException $e) {

        if ($e->getCode() === '23000') {
            $error = 'Nama kategori sudah digunakan.';
        } else {
            $error = 'Terjadi kesalahan pada database.';
        }

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Ambil daftar kategori
$stmt = $pdo->query(
    "SELECT
        c.id,
        c.name,
        c.description,
        COUNT(p.id) AS total_products
     FROM categories c
     LEFT JOIN products p ON p.category_id = c.id
     GROUP BY c.id, c.name, c.description
     ORDER BY c.name ASC"
);

$categories = $stmt->fetchAll();

// Ambil kategori untuk diedit
$editCategory = null;

if (isset($_GET['edit'])) {

    $editId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);

    if ($editId && $editId > 0) {

        $stmt = $pdo->prepare(
            "SELECT * FROM categories WHERE id = :id"
        );

        $stmt->execute(['id' => $editId]);
        $editCategory = $stmt->fetch();
    }

    if (!$editCategory) {
        $error = 'Kategori yang ingin diedit tidak ditemukan.';
    }
}

$pageTitle = 'Manajemen Kategori | Ruang Stok';

require_once __DIR__ . '/../../views/header.php';

?>

<style>
    .category-heading {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 15px;
        flex-wrap: wrap;
        margin-bottom: 24px;
    }

    .category-heading h1 {
        margin: 0 0 5px;
    }

    .category-heading p {
        margin: 0;
        color: var(--muted);
        font-size: 14px;
    }

    .btn-add {
        background: var(--maroon);
        color: white;
        padding: 11px 17px;
        border-radius: 7px;
        text-decoration: none;
        font-size: 14px;
        font-weight: 600;
        border: none;
        cursor: pointer;
    }

    .btn-add:hover {
        background: var(--maroon-dark);
    }

    .category-card {
        background: white;
        border: 1px solid var(--beige);
        border-radius: 11px;
        padding: 20px;
        margin-bottom: 20px;
        box-shadow: var(--shadow);
    }

    .category-card h2 {
        color: var(--maroon);
        font-size: 17px;
        margin: 0 0 17px;
    }

    .category-form {
        display: grid;
        gap: 14px;
    }

    .category-form label {
        display: block;
        font-size: 13px;
        font-weight: 600;
        color: var(--text);
        margin-bottom: 7px;
    }

    .category-form input,
    .category-form textarea {
        width: 100%;
        box-sizing: border-box;
    }

    .form-actions {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-top: 5px;
    }

    .btn-cancel {
        color: var(--maroon);
        text-decoration: none;
        padding: 10px 15px;
        border: 1px solid var(--beige);
        border-radius: 7px;
        font-size: 14px;
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

    .category-table-wrapper {
        overflow-x: auto;
    }

    .category-table {
        width: 100%;
        min-width: 650px;
        border-collapse: collapse;
    }

    .category-table th {
        background: #F0E6E3;
        color: var(--maroon);
        font-size: 13px;
    }

    .category-table th,
    .category-table td {
        padding: 14px 13px;
        border-bottom: 1px solid #EEE7E2;
        text-align: left;
        font-size: 13px;
    }

    .category-table tbody tr:hover {
        background: #FCF9F6;
    }

    .category-count {
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

    @media (max-width: 600px) {
        .category-heading {
            align-items: flex-start;
        }

        .form-actions {
            flex-wrap: wrap;
        }
    }
</style>

<div class="category-heading">

    <div>
        <h1>Manajemen Kategori</h1>
        <p>Kelompokkan produk agar persediaan lebih terorganisir.</p>
    </div>

    <?php if (!$editCategory): ?>
        <button
            type="button"
            class="btn-add"
            onclick="toggleCategoryForm()"
        >
            + Tambah Kategori
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

<?php if ($editCategory): ?>

    <div class="category-card">

        <h2>Edit Kategori</h2>

        <form method="POST" class="category-form">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= e($csrfToken) ?>"
            >

            <input type="hidden" name="action" value="update">

            <input
                type="hidden"
                name="id"
                value="<?= (int) $editCategory['id'] ?>"
            >

            <div>
                <label>Nama Kategori</label>

                <input
                    type="text"
                    name="name"
                    maxlength="100"
                    required
                    value="<?= e($editCategory['name']) ?>"
                >
            </div>

            <div>
                <label>Deskripsi</label>

                <textarea
                    name="description"
                    rows="4"
                ><?= e($editCategory['description'] ?? '') ?></textarea>
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
        class="category-card"
        id="categoryForm"
        style="display:none;"
    >

        <h2>Tambah Kategori Baru</h2>

        <form method="POST" class="category-form">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= e($csrfToken) ?>"
            >

            <input type="hidden" name="action" value="create">

            <div>
                <label>Nama Kategori</label>

                <input
                    type="text"
                    name="name"
                    maxlength="100"
                    placeholder="Contoh: Makeup"
                    required
                >
            </div>

            <div>
                <label>Deskripsi</label>

                <textarea
                    name="description"
                    rows="4"
                    placeholder="Keterangan kategori (opsional)"
                ></textarea>
            </div>

            <div class="form-actions">

                <button type="submit" class="btn-add">
                    Tambah Kategori
                </button>

                <button
                    type="button"
                    class="btn-cancel"
                    onclick="toggleCategoryForm()"
                >
                    Batal
                </button>

            </div>

        </form>

    </div>

<?php endif; ?>

<div class="category-card">

    <h2>Daftar Kategori</h2>

    <div class="category-table-wrapper">

        <table class="category-table">

            <thead>
                <tr>
                    <th>No.</th>
                    <th>Nama Kategori</th>
                    <th>Deskripsi</th>
                    <th>Jumlah Produk</th>
                    <th>Aksi</th>
                </tr>
            </thead>

            <tbody>

                <?php if (count($categories) > 0): ?>

                    <?php foreach ($categories as $index => $category): ?>

                        <tr>

                            <td><?= $index + 1 ?></td>

                            <td>
                                <strong><?= e($category['name']) ?></strong>
                            </td>

                            <td>
                                <?= e($category['description'] ?? '-') ?>
                            </td>

                            <td>
                                <span class="category-count">
                                    <?= (int) $category['total_products'] ?>
                                </span>
                            </td>

                            <td>

                                <a
                                    href="index.php?edit=<?= (int) $category['id'] ?>"
                                    class="action-link"
                                >
                                    Edit
                                </a>

                                <form
                                    method="POST"
                                    style="display:inline;"
                                    onsubmit="return confirm('Yakin ingin menghapus kategori ini?')"
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
                                        value="<?= (int) $category['id'] ?>"
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
                        <td colspan="5">
                            <div class="empty-state">
                                Belum ada kategori yang ditambahkan.
                            </div>
                        </td>
                    </tr>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>

<script>
    function toggleCategoryForm() {
        const form = document.getElementById('categoryForm');

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
