
<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Email dan password wajib diisi.';
    } else {
        $stmt = $pdo->prepare(
            "SELECT id, name, email, password, role
             FROM users
             WHERE email = :email
             LIMIT 1"
        );

        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);

            $_SESSION['user'] = [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $user['role']
            ];

            header('Location: dashboard.php');
            exit;
        }

        $error = 'Email atau password salah.';
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | RUANG STOK</title>

    <style>
        :root {
            --maroon: #641F35;
            --maroon-dark: #481526;
            --wine: #8D465A;
            --ivory: #F5F0E6;
            --cream: #FAF8F3;
            --beige: #E8DED2;
            --text: #352B2D;
            --muted: #887B7D;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Segoe UI", Arial, sans-serif;
            background: var(--ivory);
            color: var(--text);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        .login-wrapper {
            width: 100%;
            max-width: 960px;
            min-height: 550px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            background: white;
            border-radius: 22px;
            overflow: hidden;
            box-shadow: 0 18px 55px rgba(72, 21, 38, 0.12);
        }

        .login-brand {
            background: var(--maroon);
            color: white;
            padding: 55px 45px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
        }

        .brand-mark {
            width: 54px;
            height: 54px;
            border: 1px solid rgba(255,255,255,.55);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 25px;
            font-weight: 700;
            margin-bottom: 28px;
        }

        .brand-content {
            position: relative;
            z-index: 1;
        }

        .brand-content h1 {
            font-size: 36px;
            letter-spacing: 2px;
            margin: 0 0 14px;
        }

        .brand-content p {
            color: rgba(255,255,255,.78);
            line-height: 1.8;
            font-size: 14px;
            max-width: 330px;
        }

        .brand-footer {
            font-size: 12px;
            color: rgba(255,255,255,.65);
            position: relative;
            z-index: 1;
        }

        .brand-decoration {
            position: absolute;
            width: 280px;
            height: 280px;
            border: 1px solid rgba(255,255,255,.10);
            border-radius: 50%;
            right: -100px;
            bottom: -90px;
        }

        .brand-decoration::before {
            content: "";
            position: absolute;
            width: 190px;
            height: 190px;
            border: 1px solid rgba(255,255,255,.10);
            border-radius: 50%;
            top: 43px;
            left: 43px;
        }

        .login-form-area {
            padding: 65px 55px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .form-heading {
            margin-bottom: 35px;
        }

        .form-heading .eyebrow {
            color: var(--wine);
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
        }

        .form-heading h2 {
            font-size: 28px;
            margin: 10px 0 8px;
            color: var(--maroon-dark);
        }

        .form-heading p {
            color: var(--muted);
            font-size: 13px;
            margin: 0;
        }

        .form-group {
            margin-bottom: 21px;
        }

        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 9px;
            color: var(--text);
        }

        .input-wrap {
            position: relative;
        }

        .input-wrap input {
            width: 100%;
            height: 48px;
            border: 1px solid var(--beige);
            border-radius: 9px;
            padding: 0 15px;
            font-size: 13px;
            color: var(--text);
            background: var(--cream);
            outline: none;
            transition: .2s;
        }

        .input-wrap input:focus {
            border-color: var(--wine);
            background: white;
            box-shadow: 0 0 0 3px rgba(141,70,90,.10);
        }

        .password-input {
            padding-right: 65px !important;
        }

        .toggle-password {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            border: none;
            background: none;
            color: var(--wine);
            font-size: 11px;
            font-weight: 600;
            cursor: pointer;
        }

        .error-message {
            padding: 12px 14px;
            margin-bottom: 22px;
            border-radius: 8px;
            background: #FFF0F0;
            border: 1px solid #F1CCCC;
            color: #A13E45;
            font-size: 12px;
        }

        .login-button {
            width: 100%;
            height: 48px;
            border: none;
            border-radius: 9px;
            background: var(--maroon);
            color: white;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 8px;
            transition: .2s;
        }

        .login-button:hover {
            background: var(--maroon-dark);
            transform: translateY(-1px);
        }

        .form-note {
            text-align: center;
            margin-top: 25px;
            font-size: 11px;
            color: var(--muted);
        }

        @media (max-width: 700px) {
            body {
                padding: 15px;
            }

            .login-wrapper {
                grid-template-columns: 1fr;
                max-width: 430px;
                min-height: auto;
            }

            .login-brand {
                padding: 28px 30px;
                min-height: 190px;
            }

            .brand-mark {
                width: 42px;
                height: 42px;
                font-size: 20px;
                margin-bottom: 18px;
            }

            .brand-content h1 {
                font-size: 25px;
            }

            .brand-content p {
                font-size: 12px;
                margin-bottom: 0;
            }

            .brand-footer {
                display: none;
            }

            .login-form-area {
                padding: 35px 28px;
            }

            .form-heading {
                margin-bottom: 27px;
            }
        }
    </style>
</head>

<body>

<div class="login-wrapper">

    <section class="login-brand">
        <div class="brand-content">
            <div class="brand-mark">R</div>

            <h1>RUANG STOK</h1>

            <p>
                Sistem manajemen persediaan barang yang membantu
                pengelolaan produk, stok, dan transaksi menjadi
                lebih teratur dan efisien.
            </p>
        </div>

        <div class="brand-footer">
            Inventory Management System
        </div>

        <div class="brand-decoration"></div>
    </section>

    <section class="login-form-area">

        <div class="form-heading">
            <span class="eyebrow">Selamat Datang</span>
            <h2>Masuk ke Akun</h2>
            <p>Silakan masukkan email dan password Anda.</p>
        </div>

        <?php if ($error !== ''): ?>
            <div class="error-message">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <form method="POST" autocomplete="on">

            <div class="form-group">
                <label for="email">Alamat Email</label>

                <div class="input-wrap">
                    <input
                        type="email"
                        id="email"
                        name="email"
                        placeholder="nama@email.com"
                        autocomplete="username"
                        required
                        value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                    >
                </div>
            </div>

            <div class="form-group">
                <label for="password">Password</label>

                <div class="input-wrap">
                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="password-input"
                        placeholder="Masukkan password"
                        autocomplete="current-password"
                        required
                    >

                    <button
                        type="button"
                        class="toggle-password"
                        id="togglePassword"
                    >
                        Lihat
                    </button>
                </div>
            </div>

            <button type="submit" class="login-button">
                Masuk
            </button>

        </form>

        <div class="form-note">
            © <?= date('Y') ?> RUANG STOK · Inventory Management System
        </div>

    </section>

</div>

<script>
    const passwordInput = document.getElementById('password');
    const toggleButton = document.getElementById('togglePassword');

    toggleButton.addEventListener('click', function () {
        const isHidden = passwordInput.type === 'password';

        passwordInput.type = isHidden ? 'text' : 'password';
        toggleButton.textContent = isHidden ? 'Sembunyikan' : 'Lihat';
    });
</script>

</body>
</html>
