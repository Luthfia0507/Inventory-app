
<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth.php';

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
$success = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

$currentUserId = (int) $_SESSION['user']['id'];

$editId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);
$editUser = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    $editId = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);
}

if ($editId && $editId > 0) {
    $stmt = $pdo->prepare(
        "SELECT id, name, email, role FROM users WHERE id = :id"
    );
    $stmt->execute(['id' => $editId]);
    $editUser = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$editUser) {
        exit('Pengguna tidak ditemukan.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (
            !isset($_POST['csrf_token']) ||
            !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
        ) {
            throw new Exception('Permintaan tidak valid. Muat ulang halaman.');
        }

        $action = $_POST['action'] ?? '';

        // TAMBAH PENGGUNA
        if ($action === 'create') {
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $role = $_POST['role'] ?? '';

            if ($name === '' || strlen($name) > 150) {
                throw new Exception('Nama wajib diisi dan maksimal 150 karakter.');
            }

            if (
                !filter_var($email, FILTER_VALIDATE_EMAIL) ||
                strlen($email) > 150
            ) {
                throw new Exception('Alamat email tidak valid.');
            }

            if (strlen($password) < 8) {
                throw new Exception('Password minimal 8 karakter.');
            }

            if (!in_array($role, ['admin', 'staff'], true)) {
                throw new Exception('Role tidak valid.');
            }

            $check = $pdo->prepare(
                "SELECT COUNT(*) FROM users WHERE email = :email"
            );
            $check->execute(['email' => $email]);

            if ((int) $check->fetchColumn() > 0) {
                throw new Exception('Email sudah digunakan.');
            }

            $stmt = $pdo->prepare(
                "INSERT INTO users (name, email, password, role)
                 VALUES (:name, :email, :password, :role)"
            );

            $stmt->execute([
                'name' => $name,
                'email' => $email,
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'role' => $role
            ]);

            $_SESSION['flash'] = 'Pengguna berhasil ditambahkan.';
            header('Location: users.php');
            exit;
        }

        // EDIT PENGGUNA
        if ($action === 'update') {
            $id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $role = $_POST['role'] ?? '';

            if (!$id || $id < 1) {
                throw new Exception('ID pengguna tidak valid.');
            }

            if ($name === '' || strlen($name) > 150) {
                throw new Exception('Nama wajib diisi dan maksimal 150 karakter.');
            }

            if (
                !filter_var($email, FILTER_VALIDATE_EMAIL) ||
                strlen($email) > 150
            ) {
                throw new Exception('Alamat email tidak valid.');
            }

            if (!in_array($role, ['admin', 'staff'], true)) {
                throw new Exception('Role tidak valid.');
            }

            if ($password !== '' && strlen($password) < 8) {
                throw new Exception('Password baru minimal 8 karakter.');
            }

            $pdo->beginTransaction();

            $lock = $pdo->query(
                "SELECT id, role FROM users FOR UPDATE"
            )->fetchAll(PDO::FETCH_ASSOC);

            $target = null;
            $adminCount = 0;

            foreach ($lock as $account) {
                if ($account['role'] === 'admin') {
                    $adminCount++;
                }

                if ((int) $account['id'] === $id) {
                    $target = $account;
                }
            }

            if (!$target) {
                throw new Exception('Pengguna tidak ditemukan.');
            }

            if (
                $target['role'] === 'admin' &&
                $role !== 'admin' &&
                $adminCount <= 1
            ) {
                throw new Exception('Admin terakhir tidak boleh diubah menjadi Staff.');
            }

            if ($id === $currentUserId && $role !== 'admin') {
                throw new Exception('Kamu tidak dapat mengubah role akun sendiri menjadi Staff.');
            }

            $check = $pdo->prepare(
                "SELECT COUNT(*) FROM users
                 WHERE email = :email AND id != :id"
            );

            $check->execute([
                'email' => $email,
                'id' => $id
            ]);

            if ((int) $check->fetchColumn() > 0) {
                throw new Exception('Email sudah digunakan pengguna lain.');
            }

            if ($password !== '') {
                $stmt = $pdo->prepare(
                    "UPDATE users
                     SET name = :name,
                         email = :email,
                         password = :password,
                         role = :role
                     WHERE id = :id"
                );

                $stmt->execute([
                    'name' => $name,
                    'email' => $email,
                    'password' => password_hash($password, PASSWORD_DEFAULT),
                    'role' => $role,
                    'id' => $id
                ]);
            } else {
                $stmt = $pdo->prepare(
                    "UPDATE users
                     SET name = :name,
                         email = :email,
                         role = :role
                     WHERE id = :id"
                );

                $stmt->execute([
                    'name' => $name,
                    'email' => $email,
                    'role' => $role,
                    'id' => $id
                ]);
            }

            $pdo->commit();

            if ($id === $currentUserId) {
                $_SESSION['user']['name'] = $name;
                $_SESSION['user']['email'] = $email;
            }

            $_SESSION['flash'] = 'Data pengguna berhasil diperbarui.';
            header('Location: users.php');
            exit;
        }

        // HAPUS PENGGUNA
        if ($action === 'delete') {
            $id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);

            if (!$id || $id < 1) {
                throw new Exception('ID pengguna tidak valid.');
            }

            if ($id === $currentUserId) {
                throw new Exception('Kamu tidak dapat menghapus akun sendiri.');
            }

            $pdo->beginTransaction();

            $accounts = $pdo->query(
                "SELECT id, role FROM users FOR UPDATE"
            )->fetchAll(PDO::FETCH_ASSOC);

            $target = null;
            $adminCount = 0;

            foreach ($accounts as $account) {
                if ($account['role'] === 'admin') {
                    $adminCount++;
                }

                if ((int) $account['id'] === $id) {
                    $target = $account;
                }
            }

            if (!$target) {
                throw new Exception('Pengguna tidak ditemukan.');
            }

            if ($target['role'] === 'admin' && $adminCount <= 1) {
                throw new Exception('Admin terakhir tidak boleh dihapus.');
            }

            $stmt = $pdo->prepare(
                "DELETE FROM users WHERE id = :id"
            );
            $stmt->execute(['id' => $id]);

            $pdo->commit();

            $_SESSION['flash'] = 'Pengguna berhasil dihapus.';
            header('Location: users.php');
            exit;
        }

        throw new Exception('Aksi tidak dikenal.');

    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        if (
            $exception instanceof PDOException &&
            $exception->getCode() === '23000'
        ) {
            $error = 'Email sudah digunakan atau data terkait tidak valid.';
        } else {
            $error = $exception->getMessage();
        }
    }
}

// Ambil semua pengguna
$users = $pdo->query(
    "SELECT id, name, email, role, created_at
     FROM users
     ORDER BY id DESC"
)->fetchAll(PDO::FETCH_ASSOC);

$totalUsers = count($users);
$totalAdmins = 0;
$totalStaff = 0;

foreach ($users as $user) {
    if ($user['role'] === 'admin') {
        $totalAdmins++;
    } elseif ($user['role'] === 'staff') {
        $totalStaff++;
    }
}

$pageTitle = 'Manajemen Pengguna | Ruang Stok';

require_once __DIR__ . '/../views/header.php';

?>

<style>
    .rs-users {
        width: 100%;
        color: #352B2D;
    }

    .rs-users * {
        box-sizing: border-box;
    }

    .rs-users .users-heading {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 15px;
        flex-wrap: wrap;
        margin-bottom: 20px;
    }

    .rs-users .users-heading h2 {
        color: #641F35;
        font-size: 21px;
        margin: 0 0 5px;
    }

    .rs-users .users-heading p {
        color: #887B7D;
        font-size: 13px;
        margin: 0;
    }

    .rs-users .btn-add-user {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
        padding: 10px 16px;
        background: #641F35;
        color: #fff;
        border: 0;
        border-radius: 8px;
        text-decoration: none;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
    }

    .rs-users .btn-add-user:hover {
        background: #481526;
    }

    .rs-users .user-stats {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 14px;
        margin-bottom: 20px;
    }

    .rs-users .stat-card {
        display: flex;
        align-items: center;
        gap: 13px;
        padding: 16px;
        background: #FAF8F3;
        border: 1px solid #E8DED2;
        border-radius: 11px;
    }

    .rs-users .stat-icon {
        width: 40px;
        height: 40px;
        flex-shrink: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #F1E5E7;
        color: #641F35;
        border-radius: 10px;
        font-size: 18px;
    }

    .rs-users .stat-label {
        color: #887B7D;
        font-size: 12px;
        margin-bottom: 4px;
    }

    .rs-users .stat-value {
        color: #641F35;
        font-size: 21px;
        font-weight: 700;
    }

    .rs-users .user-alert {
        padding: 12px 15px;
        border-radius: 8px;
        font-size: 13px;
        line-height: 1.5;
        margin-bottom: 16px;
    }

    .rs-users .alert-success {
        background: #EDF5EC;
        border: 1px solid #C9DEC7;
        color: #38623B;
    }

    .rs-users .alert-error {
        background: #FCEBEC;
        border: 1px solid #E9BFC5;
        color: #8B2635;
    }

    .rs-users .form-card {
        background: #FAF8F3;
        border: 1px solid #E8DED2;
        border-radius: 12px;
        margin-bottom: 20px;
        overflow: hidden;
    }

    .rs-users .form-toggle {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 15px 18px;
        color: #641F35;
        font-size: 14px;
        font-weight: 700;
        cursor: pointer;
        list-style: none;
    }

    .rs-users .form-toggle::-webkit-details-marker {
        display: none;
    }

    .rs-users .form-toggle::after {
        content: '+';
        width: 27px;
        height: 27px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #F1E5E7;
        border-radius: 7px;
        font-size: 19px;
        line-height: 1;
    }

    .rs-users details[open] .form-toggle::after {
        content: '−';
    }

    .rs-users .form-content {
        padding: 0 18px 18px;
        border-top: 1px solid #E8DED2;
    }

    .rs-users .form-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 14px;
        padding-top: 16px;
    }

    .rs-users .field {
        display: flex;
        flex-direction: column;
        gap: 6px;
        min-width: 0;
    }

    .rs-users .field.full {
        grid-column: 1 / -1;
    }

    .rs-users .field label {
        color: #514649;
        font-size: 12px;
        font-weight: 600;
    }

    .rs-users .field input,
    .rs-users .field select {
        width: 100%;
        min-height: 39px;
        padding: 9px 11px;
        border: 1px solid #DED1C3;
        border-radius: 7px;
        background: #fff;
        color: #352B2D;
        font-size: 13px;
        outline: none;
    }

    .rs-users .field input:focus,
    .rs-users .field select:focus {
        border-color: #8D465A;
        box-shadow: 0 0 0 3px rgba(141, 70, 90, .10);
    }

    .rs-users .field small {
        color: #887B7D;
        font-size: 11px;
        line-height: 1.5;
    }

    .rs-users .form-actions {
        display: flex;
        justify-content: flex-end;
        align-items: center;
        gap: 9px;
        margin-top: 17px;
    }

    .rs-users .btn-save,
    .rs-users .btn-cancel {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 38px;
        padding: 9px 16px;
        border-radius: 7px;
        font-size: 12px;
        font-weight: 600;
        text-decoration: none;
        cursor: pointer;
    }

    .rs-users .btn-save {
        background: #641F35;
        border: 1px solid #641F35;
        color: #fff;
    }

    .rs-users .btn-save:hover {
        background: #481526;
    }

    .rs-users .btn-cancel {
        background: #F5F0E6;
        border: 1px solid #DED1C3;
        color: #641F35;
    }

    .rs-users .table-card {
        background: #FAF8F3;
        border: 1px solid #E8DED2;
        border-radius: 12px;
        overflow: hidden;
    }

    .rs-users .table-heading {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 10px;
        padding: 16px 18px;
        border-bottom: 1px solid #E8DED2;
    }

    .rs-users .table-heading h3 {
        color: #641F35;
        font-size: 15px;
        margin: 0;
    }

    .rs-users .table-count {
        color: #887B7D;
        font-size: 12px;
    }

    .rs-users .table-scroll {
        width: 100%;
        overflow-x: auto;
    }

    .rs-users table {
        width: 100%;
        min-width: 720px;
        border-collapse: collapse;
        font-size: 12px;
    }

    .rs-users thead th {
        padding: 13px 12px;
        background: #F1E8E4;
        color: #641F35;
        text-align: left;
        font-size: 11px;
        font-weight: 700;
        white-space: nowrap;
        border-bottom: 1px solid #E8DED2;
    }

    .rs-users tbody td {
        padding: 13px 12px;
        color: #514649;
        border-bottom: 1px solid #EEE7DE;
        vertical-align: middle;
    }

    .rs-users tbody tr:last-child td {
        border-bottom: none;
    }

    .rs-users tbody tr:hover {
        background: #F7F1ED;
    }

    .rs-users .user-name {
        color: #352B2D;
        font-weight: 600;
    }

    .rs-users .user-email {
        color: #887B7D;
    }

    .rs-users .role-badge {
        display: inline-flex;
        align-items: center;
        padding: 5px 10px;
        border-radius: 20px;
        font-size: 10px;
        font-weight: 700;
    }

    .rs-users .role-admin {
        background: #F1E5E7;
        color: #641F35;
    }

    .rs-users .role-staff {
        background: #E9EFE9;
        color: #426346;
    }

    .rs-users .current-user {
        color: #887B7D;
        font-size: 10px;
        margin-left: 5px;
    }

    .rs-users .action-group {
        display: flex;
        align-items: center;
        gap: 7px;
        white-space: nowrap;
    }

    .rs-users .action-edit {
        color: #641F35;
        font-weight: 600;
        text-decoration: none;
    }

    .rs-users .action-edit:hover {
        text-decoration: underline;
    }

    .rs-users .action-delete {
        padding: 5px 9px;
        border: 1px solid #E9BFC5;
        border-radius: 6px;
        background: #FCEBEC;
        color: #963F4A;
        font-size: 11px;
        font-weight: 600;
        cursor: pointer;
    }

    .rs-users .action-delete:hover {
        background: #F6DADD;
    }

    .rs-users .empty-state {
        padding: 35px 15px;
        text-align: center;
        color: #887B7D;
    }

    .rs-users .empty-state strong {
        display: block;
        color: #641F35;
        margin-bottom: 5px;
    }

    @media (max-width: 700px) {
        .rs-users .user-stats {
            grid-template-columns: 1fr;
            gap: 9px;
        }

        .rs-users .form-grid {
            grid-template-columns: 1fr;
        }

        .rs-users .field.full {
            grid-column: auto;
        }

        .rs-users .users-heading {
            align-items: flex-start;
        }

        .rs-users .btn-add-user {
            width: 100%;
        }
    }
</style>

<div class="rs-users">

    <div class="users-heading">
        <div>
            <h2>Manajemen Pengguna</h2>
            <p>Kelola akun Admin dan Staff yang dapat mengakses sistem.</p>
        </div>

        <?php if (!$editUser): ?>
            <a href="#form-pengguna" class="btn-add-user"
               onclick="document.getElementById('form-pengguna').open = true">
                + Tambah Pengguna
            </a>
        <?php endif; ?>
    </div>

    <?php if ($success !== ''): ?>
        <div class="user-alert alert-success">
            <?= e($success) ?>
        </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="user-alert alert-error">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <div class="user-stats">

        <div class="stat-card">
            <div class="stat-icon">♙</div>
            <div>
                <div class="stat-label">Total Pengguna</div>
                <div class="stat-value">
                    <?= number_format($totalUsers, 0, ',', '.') ?>
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">◆</div>
            <div>
                <div class="stat-label">Admin</div>
                <div class="stat-value">
                    <?= number_format($totalAdmins, 0, ',', '.') ?>
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">♧</div>
            <div>
                <div class="stat-label">Staff</div>
                <div class="stat-value">
                    <?= number_format($totalStaff, 0, ',', '.') ?>
                </div>
            </div>
        </div>

    </div>

    <div class="form-card">

        <details id="form-pengguna" <?= $editUser || $error !== '' ? 'open' : '' ?>>

            <summary class="form-toggle">
                <?= $editUser ? 'Edit Data Pengguna' : 'Tambah Pengguna Baru' ?>
            </summary>

            <div class="form-content">

                <form method="POST">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= e($_SESSION['csrf_token']) ?>"
                    >

                    <input
                        type="hidden"
                        name="action"
                        value="<?= $editUser ? 'update' : 'create' ?>"
                    >

                    <?php if ($editUser): ?>
                        <input
                            type="hidden"
                            name="id"
                            value="<?= (int) $editUser['id'] ?>"
                        >
                    <?php endif; ?>

                    <div class="form-grid">

                        <div class="field">
                            <label for="user-name">Nama Lengkap</label>
                            <input
                                type="text"
                                id="user-name"
                                name="name"
                                maxlength="150"
                                required
                                placeholder="Masukkan nama pengguna"
                                value="<?= e($_POST['name'] ?? ($editUser['name'] ?? '')) ?>"
                            >
                        </div>

                        <div class="field">
                            <label for="user-email">Email</label>
                            <input
                                type="email"
                                id="user-email"
                                name="email"
                                maxlength="150"
                                required
                                placeholder="nama@email.com"
                                value="<?= e($_POST['email'] ?? ($editUser['email'] ?? '')) ?>"
                            >
                        </div>

                        <div class="field">
                            <label for="user-password">
                                Password <?= $editUser ? '(Opsional)' : '' ?>
                            </label>
                            <input
                                type="password"
                                id="user-password"
                                name="password"
                                <?= $editUser ? '' : 'required' ?>
                                minlength="8"
                                autocomplete="new-password"
                                placeholder="<?= $editUser ? 'Kosongkan jika tidak diganti' : 'Minimal 8 karakter' ?>"
                            >

                            <?php if ($editUser): ?>
                                <small>Kosongkan jika tidak ingin mengganti password.</small>
                            <?php else: ?>
                                <small>Password minimal 8 karakter.</small>
                            <?php endif; ?>
                        </div>

                        <div class="field">
                            <label for="user-role">Role Pengguna</label>
                            <select id="user-role" name="role" required>
                                <option value="">-- Pilih Role --</option>

                                <option
                                    value="admin"
                                    <?= (($_POST['role'] ?? ($editUser['role'] ?? '')) === 'admin') ? 'selected' : '' ?>
                                >
                                    Admin
                                </option>

                                <option
                                    value="staff"
                                    <?= (($_POST['role'] ?? ($editUser['role'] ?? '')) === 'staff') ? 'selected' : '' ?>
                                >
                                    Staff
                                </option>
                            </select>
                        </div>

                    </div>

                    <div class="form-actions">

                        <?php if ($editUser): ?>
                            <a href="users.php" class="btn-cancel">Batal</a>
                        <?php else: ?>
                            <button
                                type="reset"
                                class="btn-cancel"
                                onclick="setTimeout(() => {
                                    document.getElementById('form-pengguna').open = false;
                                }, 0)"
                            >
                                Batal
                            </button>
                        <?php endif; ?>

                        <button type="submit" class="btn-save">
                            <?= $editUser ? 'Simpan Perubahan' : 'Tambah Pengguna' ?>
                        </button>

                    </div>

                </form>

            </div>

        </details>

    </div>

    <div class="table-card">

        <div class="table-heading">
            <h3>Daftar Pengguna</h3>
            <span class="table-count">
                <?= number_format($totalUsers, 0, ',', '.') ?> akun
            </span>
        </div>

        <div class="table-scroll">

            <table>
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Nama</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Tanggal Dibuat</th>
                        <th>Aksi</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if (!empty($users)): ?>

                        <?php foreach ($users as $index => $user): ?>

                            <tr>
                                <td><?= $index + 1 ?></td>

                                <td>
                                    <span class="user-name">
                                        <?= e($user['name']) ?>
                                    </span>

                                    <?php if ((int) $user['id'] === $currentUserId): ?>
                                        <span class="current-user">(Kamu)</span>
                                    <?php endif; ?>
                                </td>

                                <td class="user-email">
                                    <?= e($user['email']) ?>
                                </td>

                                <td>
                                    <span class="role-badge <?= $user['role'] === 'admin' ? 'role-admin' : 'role-staff' ?>">
                                        <?= e(strtoupper($user['role'])) ?>
                                    </span>
                                </td>

                                <td>
                                    <?= e(
                                        !empty($user['created_at'])
                                            ? date('d-m-Y', strtotime($user['created_at']))
                                            : '-'
                                    ) ?>
                                </td>

                                <td>
                                    <div class="action-group">

                                        <a
                                            href="users.php?edit=<?= (int) $user['id'] ?>"
                                            class="action-edit"
                                        >
                                            Edit
                                        </a>

                                        <?php if ((int) $user['id'] !== $currentUserId): ?>

                                            <form
                                                method="POST"
                                                onsubmit="return confirm('Yakin ingin menghapus pengguna ini?')"
                                            >
                                                <input
                                                    type="hidden"
                                                    name="csrf_token"
                                                    value="<?= e($_SESSION['csrf_token']) ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="action"
                                                    value="delete"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="id"
                                                    value="<?= (int) $user['id'] ?>"
                                                >

                                                <button
                                                    type="submit"
                                                    class="action-delete"
                                                >
                                                    Hapus
                                                </button>
                                            </form>

                                        <?php endif; ?>

                                    </div>
                                </td>
                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="6">
                                <div class="empty-state">
                                    <strong>Belum ada pengguna</strong>
                                    Tambahkan akun pengguna untuk mulai mengelola akses.
                                </div>
                            </td>
                        </tr>

                    <?php endif; ?>

                </tbody>
            </table>

        </div>

    </div>

</div>

</main>
</div>
</body>
</html>
