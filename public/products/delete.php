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

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    exit('ID produk tidak valid.');
}

$stmt = $pdo->prepare("SELECT * FROM products WHERE id = :id");
$stmt->execute(['id' => $id]);

$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    exit('Produk tidak ditemukan.');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (
            !isset($_POST['csrf_token']) ||
            !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
        ) {
            throw new Exception('Permintaan tidak valid. Silakan coba kembali.');
        }

        if ((int) $product['stock'] > 0) {
            throw new Exception(
                'Produk tidak dapat dihapus karena masih memiliki stok. Kosongkan stok melalui transaksi stok terlebih dahulu.'
            );
        }

        $check = $pdo->prepare(
            "SELECT COUNT(*) FROM stock_movements WHERE product_id = :id"
        );
        $check->execute(['id' => $id]);

        if ((int) $check->fetchColumn() > 0) {
            throw new Exception(
                'Produk tidak dapat dihapus karena sudah memiliki riwayat stok.'
            );
        }

        $delete = $pdo->prepare(
            "DELETE FROM products WHERE id = :id"
        );
        $delete->execute(['id' => $id]);

        $_SESSION['flash'] = 'Produk berhasil dihapus.';

        header('Location: index.php');
        exit;

    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hapus Produk | Ruang Stok</title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        .rs-product-delete {
            max-width: 650px;
            margin: 35px auto;
            padding: 0 20px;
            color: #352B2D;
        }

        .rs-product-delete .delete-card {
            background: #FAF8F3;
            border: 1px solid #E8DED2;
            border-radius: 16px;
            padding: 28px;
            box-shadow: 0 5px 18px rgba(72, 21, 38, 0.06);
        }

        .rs-product-delete .delete-icon {
            width: 46px;
            height: 46px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            background: #F5E7E9;
            color: #641F35;
            font-size: 23px;
            margin-bottom: 16px;
        }

        .rs-product-delete h1 {
            margin: 0 0 8px;
            color: #641F35;
            font-size: 23px;
        }

        .rs-product-delete .delete-description {
            color: #887B7D;
            font-size: 14px;
            line-height: 1.6;
            margin: 0 0 22px;
        }

        .rs-product-delete .product-info {
            background: #F5F0E6;
            border: 1px solid #E8DED2;
            border-radius: 11px;
            padding: 17px 19px;
            margin-bottom: 20px;
        }

        .rs-product-delete .info-row {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            padding: 9px 0;
            border-bottom: 1px solid #E8DED2;
            font-size: 14px;
        }

        .rs-product-delete .info-row:last-child {
            border-bottom: none;
        }

        .rs-product-delete .info-label {
            color: #887B7D;
        }

        .rs-product-delete .info-value {
            color: #352B2D;
            font-weight: 600;
            text-align: right;
        }

        .rs-product-delete .delete-warning {
            background: #F5E7E9;
            border-left: 4px solid #8D465A;
            color: #641F35;
            padding: 12px 15px;
            border-radius: 7px;
            font-size: 13px;
            line-height: 1.6;
            margin-bottom: 20px;
        }

        .rs-product-delete .delete-error {
            background: #FCEBEC;
            border: 1px solid #E9BFC5;
            color: #8B2635;
            padding: 12px 15px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 18px;
            line-height: 1.5;
        }

        .rs-product-delete .delete-actions {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .rs-product-delete .btn-cancel,
        .rs-product-delete .btn-delete {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 40px;
            padding: 9px 17px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: 0.2s ease;
        }

        .rs-product-delete .btn-cancel {
            background: #E8DED2;
            color: #481526;
            border: 1px solid #E8DED2;
        }

        .rs-product-delete .btn-cancel:hover {
            background: #DED1C3;
        }

        .rs-product-delete .btn-delete {
            background: #641F35;
            color: #fff;
            border: 1px solid #641F35;
        }

        .rs-product-delete .btn-delete:hover {
            background: #481526;
        }

        @media (max-width: 520px) {
            .rs-product-delete {
                margin: 20px auto;
                padding: 0 12px;
            }

            .rs-product-delete .delete-card {
                padding: 20px;
            }

            .rs-product-delete .delete-actions {
                flex-direction: column-reverse;
                align-items: stretch;
            }

            .rs-product-delete .btn-cancel,
            .rs-product-delete .btn-delete {
                width: 100%;
                box-sizing: border-box;
            }
        }
    </style>
</head>

<body>

<div class="rs-product-delete">
    <div class="delete-card">

        <div class="delete-icon">!</div>

        <h1>Hapus Produk</h1>

        <p class="delete-description">
            Periksa kembali informasi produk sebelum melanjutkan penghapusan.
        </p>

        <?php if ($error !== ''): ?>
            <div class="delete-error">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <div class="product-info">
            <div class="info-row">
                <span class="info-label">SKU</span>
                <span class="info-value"><?= e($product['sku']) ?></span>
            </div>

            <div class="info-row">
                <span class="info-label">Nama Produk</span>
                <span class="info-value"><?= e($product['name']) ?></span>
            </div>

            <div class="info-row">
                <span class="info-label">Stok Saat Ini</span>
                <span class="info-value">
                    <?= number_format((int) $product['stock'], 0, ',', '.') ?>
                </span>
            </div>
        </div>

        <div class="delete-warning">
            Produk hanya dapat dihapus apabila stoknya sudah nol dan belum
            memiliki riwayat pergerakan stok. Tindakan ini tidak dapat dibatalkan.
        </div>

        <form method="POST">
            <input
                type="hidden"
                name="csrf_token"
                value="<?= e($_SESSION['csrf_token']) ?>"
            >

            <div class="delete-actions">
                <a href="index.php" class="btn-cancel">Batal</a>

                <button type="submit" class="btn-delete">
                    Ya, Hapus Produk
                </button>
            </div>
        </form>

    </div>
</div>

</body>
</html>