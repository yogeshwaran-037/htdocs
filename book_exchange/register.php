<?php
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/config/db.php';

$values = ['name' => '', 'email' => '', 'age' => '', 'phone' => '', 'address' => ''];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($values as $field => $unused) {
        $values[$field] = trim($_POST[$field] ?? '');
    }
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $age = $values['age'] === '' ? null : filter_var($values['age'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 120]]);

    if ($values['name'] === '') { $errors['name'] = 'Please enter your full name.'; }
    if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) { $errors['email'] = 'Please enter a valid email address.'; }
    if ($values['age'] !== '' && $age === false) { $errors['age'] = 'Enter an age between 1 and 120.'; }
    if ($values['phone'] !== '' && !preg_match('/^[0-9]{7,15}$/', $values['phone'])) { $errors['phone'] = 'Use 7 to 15 digits.'; }
    if (strlen($values['address']) > 255) { $errors['address'] = 'Address is too long.'; }
    if (strlen($password) < 6) { $errors['password'] = 'Password must be at least 6 characters.'; }
    if ($password !== $confirmPassword) { $errors['confirm_password'] = 'Passwords do not match.'; }

    if (!isset($errors['email'])) {
        $check = mysqli_prepare($conn, 'SELECT id FROM users WHERE email = ? LIMIT 1');
        if ($check) {
            mysqli_stmt_bind_param($check, 's', $values['email']);
            mysqli_stmt_execute($check);
            if (mysqli_fetch_assoc(mysqli_stmt_get_result($check))) { $errors['email'] = 'An account with this email already exists.'; }
            mysqli_stmt_close($check);
        } else { $errors['form'] = 'Unable to validate your email.'; }
    }

    $photoPath = null;
    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] !== UPLOAD_ERR_NO_FILE) {
        $upload = $_FILES['profile_photo'];
        $imageInfo = $upload['error'] === UPLOAD_ERR_OK ? @getimagesize($upload['tmp_name']) : false;
        $mime = $imageInfo['mime'] ?? '';
        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png'];
        if ($upload['error'] !== UPLOAD_ERR_OK || $upload['size'] > 2 * 1024 * 1024 || !isset($extensions[$mime])) {
            $errors['profile_photo'] = 'Upload a JPG, JPEG, or PNG image smaller than 2 MB.';
        } elseif (empty($errors)) {
            $fileName = 'profile_new_' . bin2hex(random_bytes(8)) . '.' . $extensions[$mime];
            if (move_uploaded_file($upload['tmp_name'], __DIR__ . '/uploads/profile/' . $fileName)) {
                $photoPath = 'uploads/profile/' . $fileName;
            } else { $errors['profile_photo'] = 'Unable to save the profile photo.'; }
        }
    }

    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $insert = mysqli_prepare($conn, 'INSERT INTO users (name, email, password, phone, address, age, profile_photo) VALUES (?, ?, ?, ?, ?, ?, ?)');
        if ($insert) {
            mysqli_stmt_bind_param($insert, 'sssssis', $values['name'], $values['email'], $hash, $values['phone'], $values['address'], $age, $photoPath);
            $saved = mysqli_stmt_execute($insert);
            mysqli_stmt_close($insert);
            if ($saved) {
                $_SESSION['success_message'] = 'Account created successfully.';
                header('Location: login.php');
                exit;
            }
        }
        $errors['form'] = 'Unable to create your account right now.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head><meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Create Account | Book Exchange Easy</title>
    <style>
        body { margin: 0; padding: 30px; font-family: Arial, sans-serif; background: #f5f7fb; color: #1e293b; }
        form { max-width: 620px; padding: 28px; background: white; border-radius: 14px; box-shadow: 0 5px 18px rgba(15,23,42,.08); }
        input, textarea { width: 100%; max-width: 540px; padding: 10px; border: 1px solid #cbd5e1; border-radius: 7px; font: inherit; }
        button { padding: 11px 16px; border: 0; border-radius: 8px; background: #4f46e5; color: white; font-weight: bold; cursor: pointer; }
        .error { color: #b91c1c; font-size: 13px; }
    </style>

<?php require __DIR__ . "/includes/layout.php"; ?>
</head>

<body class="register-page">

    <h1>📚 Book Exchange Easy</h1>

    <h2>Create Account</h2>

    <?php if (isset($errors['form'])): ?><p class="error"><?php echo htmlspecialchars($errors['form']); ?></p><?php endif; ?>

    <form method="POST" enctype="multipart/form-data"><?php echo csrf_field(); ?>

        <label>Name:</label>
        <br>
        <input type="text" name="name" maxlength="100" value="<?php echo htmlspecialchars($values['name']); ?>" required>
        <?php if (isset($errors['name'])): ?><p class="error"><?php echo htmlspecialchars($errors['name']); ?></p><?php endif; ?>

        <br><br>

        <label>Email:</label>
        <br>
        <input type="email" name="email" maxlength="100" value="<?php echo htmlspecialchars($values['email']); ?>" required>
        <?php if (isset($errors['email'])): ?><p class="error"><?php echo htmlspecialchars($errors['email']); ?></p><?php endif; ?>

        <br><br>

        <label>Age:</label>
        <br>
        <input type="number" name="age" min="1" max="120" value="<?php echo htmlspecialchars($values['age']); ?>">
        <?php if (isset($errors['age'])): ?><p class="error"><?php echo htmlspecialchars($errors['age']); ?></p><?php endif; ?>

        <br><br>

        <label>Password:</label>
        <br>
        <input type="password" name="password" minlength="6" required>
        <?php if (isset($errors['password'])): ?><p class="error"><?php echo htmlspecialchars($errors['password']); ?></p><?php endif; ?>

        <br><br>

        <label>Confirm Password:</label>
        <br>
        <input type="password" name="confirm_password" required>
        <?php if (isset($errors['confirm_password'])): ?><p class="error"><?php echo htmlspecialchars($errors['confirm_password']); ?></p><?php endif; ?>

        <br><br>

        <label>Phone:</label>
        <br>
        <input type="tel" name="phone" maxlength="15" value="<?php echo htmlspecialchars($values['phone']); ?>">
        <?php if (isset($errors['phone'])): ?><p class="error"><?php echo htmlspecialchars($errors['phone']); ?></p><?php endif; ?>

        <br><br>

        <label>Address:</label>
        <br>
        <textarea name="address" maxlength="255"><?php echo htmlspecialchars($values['address']); ?></textarea>
        <?php if (isset($errors['address'])): ?><p class="error"><?php echo htmlspecialchars($errors['address']); ?></p><?php endif; ?>

        <br><br>

        <label>Profile Photo (optional):</label>
        <br>
        <input type="file" name="profile_photo" accept=".jpg,.jpeg,.png,image/jpeg,image/png">
        <?php if (isset($errors['profile_photo'])): ?><p class="error"><?php echo htmlspecialchars($errors['profile_photo']); ?></p><?php endif; ?>

        <br><br>

        <button type="submit">
            Create Account
        </button>

    </form>
    <p>Already have an account? <a href="login.php">Log in</a></p>

</body>

</html>
