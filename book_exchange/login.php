<?php
require_once __DIR__ . '/includes/security.php';

// Reuse the existing database connection.
require_once __DIR__ . '/config/db.php';

$error = '';
$email = '';
$successMessage = $_SESSION['success_message'] ?? '';
unset($_SESSION['success_message']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Please enter both your email address and password.';
    } else {
        // Find the user by email using a prepared statement.
        $statement = mysqli_prepare($conn, 'SELECT id, name, password FROM users WHERE email = ? LIMIT 1');

        if ($statement) {
            mysqli_stmt_bind_param($statement, 's', $email);
            mysqli_stmt_execute($statement);
            $result = mysqli_stmt_get_result($statement);
            $user = mysqli_fetch_assoc($result);
            mysqli_stmt_close($statement);

            // Check that the account exists and the stored password hash matches.
            if ($user && password_verify($password, $user['password'])) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];

                header('Location: dashboard.php');
                exit;
            }
        }

        $error = 'Incorrect email address or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Book Exchange Easy</title>
    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #e0f2fe, #ede9fe);
            color: #1e293b;
        }

        .login-card {
            width: 100%;
            max-width: 420px;
            padding: 38px 34px;
            background: #ffffff;
            border-radius: 18px;
            box-shadow: 0 18px 45px rgba(71, 85, 105, 0.18);
        }

        h1 { margin: 0 0 8px; color: #312e81; font-size: 28px; }
        .subtitle { margin: 0 0 28px; color: #64748b; }

        label { display: block; margin: 16px 0 7px; font-weight: bold; font-size: 14px; }

        input {
            width: 100%;
            padding: 13px 14px;
            border: 1px solid #cbd5e1;
            border-radius: 9px;
            font-size: 15px;
            outline: none;
        }

        input:focus { border-color: #4f46e5; box-shadow: 0 0 0 3px #e0e7ff; }

        button {
            width: 100%;
            margin-top: 24px;
            padding: 13px;
            border: 0;
            border-radius: 9px;
            background: #4f46e5;
            color: #fff;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
        }

        button:hover { background: #3730a3; }

        .error {
            margin-bottom: 18px;
            padding: 11px 13px;
            border-radius: 8px;
            background: #fef2f2;
            color: #b91c1c;
            font-size: 14px;
        }
        .success { margin-bottom: 18px; padding: 11px 13px; border-radius: 8px; background: #ecfdf5; color: #047857; font-size: 14px; }

        .register-link { margin: 22px 0 0; text-align: center; color: #64748b; font-size: 14px; }
        .register-link a { color: #4f46e5; font-weight: bold; text-decoration: none; }
    </style>
<?php require __DIR__ . "/includes/layout.php"; ?>
</head>
<body>
    <main class="login-card">
        <h1>Welcome back</h1>
        <p class="subtitle">Log in to Book Exchange Easy.</p>

        <?php if ($error !== ''): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($successMessage !== ''): ?>
            <div class="success"><?php echo htmlspecialchars($successMessage); ?></div>
        <?php endif; ?>

        <form method="POST" action="login.php"><?php echo csrf_field(); ?>
            <label for="email">Email address</label>
            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required autocomplete="email">

            <label for="password">Password</label>
            <input type="password" id="password" name="password" required autocomplete="current-password">

            <button type="submit">Log in</button>
        </form>

        <p class="register-link">Don't have an account? <a href="register.php">Create Account</a></p>
    </main>
</body>
</html>
