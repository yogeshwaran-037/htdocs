<?php
require_once __DIR__ . '/includes/security.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/config/db.php';

$userId = (int) $_SESSION['user_id'];
$error = '';
$profile = null;

$profileStatement = mysqli_prepare($conn, 'SELECT name, age, phone, address, profile_photo FROM users WHERE id = ? LIMIT 1');
if ($profileStatement) {
    mysqli_stmt_bind_param($profileStatement, 'i', $userId);
    mysqli_stmt_execute($profileStatement);
    $profileResult = mysqli_stmt_get_result($profileStatement);
    $profile = mysqli_fetch_assoc($profileResult);
    mysqli_stmt_close($profileStatement);
}

if (!$profile) {
    $error = 'Unable to find your profile.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $profile) {
    $name = trim($_POST['name'] ?? '');
    $ageInput = trim($_POST['age'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $age = $ageInput === '' ? null : filter_var($ageInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 120]]);
    $photoPath = $profile['profile_photo'];

    if ($name === '') {
        $error = 'Please enter your name.';
    } elseif ($ageInput !== '' && $age === false) {
        $error = 'Please enter a valid age between 1 and 120.';
    } elseif ($phone !== '' && !preg_match('/^[0-9]{7,15}$/', $phone)) {
        $error = 'Phone number must contain 7 to 15 digits.';
    } elseif (strlen($address) > 255) {
        $error = 'Address must be 255 characters or fewer.';
    }

    if ($error === '' && isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] !== UPLOAD_ERR_NO_FILE) {
        $upload = $_FILES['profile_photo'];
        $allowedTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png'];

        if ($upload['error'] !== UPLOAD_ERR_OK || $upload['size'] > 2 * 1024 * 1024) {
            $error = 'Please upload a JPG, JPEG, or PNG image smaller than 2 MB.';
        } else {
            $imageInfo = @getimagesize($upload['tmp_name']);
            $mimeType = $imageInfo['mime'] ?? '';

            if (!isset($allowedTypes[$mimeType])) {
                $error = 'Only JPG, JPEG, and PNG profile photos are allowed.';
            } else {
                $fileName = 'profile_' . $userId . '_' . bin2hex(random_bytes(8)) . '.' . $allowedTypes[$mimeType];
                $destination = __DIR__ . '/uploads/profile/' . $fileName;

                if (move_uploaded_file($upload['tmp_name'], $destination)) {
                    $photoPath = 'uploads/profile/' . $fileName;
                } else {
                    $error = 'Unable to save the uploaded profile photo.';
                }
            }
        }
    }

    if ($error === '') {
        $updateStatement = mysqli_prepare($conn, 'UPDATE users SET name = ?, age = ?, phone = ?, address = ?, profile_photo = ? WHERE id = ?');

        if ($updateStatement) {
            mysqli_stmt_bind_param($updateStatement, 'sisssi', $name, $age, $phone, $address, $photoPath, $userId);

            if (mysqli_stmt_execute($updateStatement)) {
                mysqli_stmt_close($updateStatement);
                $_SESSION['user_name'] = $name;
                header('Location: profile.php?id=' . $userId);
                exit;
            }

            mysqli_stmt_close($updateStatement);
        }

        $error = 'Unable to update your profile right now.';
    }

    $profile['name'] = $name;
    $profile['age'] = $age === false ? $ageInput : $age;
    $profile['phone'] = $phone;
    $profile['address'] = $address;
    $profile['profile_photo'] = $photoPath;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile | Book Exchange Easy</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, sans-serif; background: #f5f7fb; color: #1e293b; }
        .navbar { padding: 16px 5%; background: #312e81; }
        .navbar a { color: white; text-decoration: none; font-weight: bold; }
        .container { width: min(680px, 90%); margin: 40px auto; }
        .card { padding: 32px; background: white; border-radius: 16px; box-shadow: 0 5px 18px rgba(15, 23, 42, .08); }
        h1 { margin-top: 0; color: #312e81; }
        .intro { color: #64748b; }
        label { display: block; margin: 18px 0 7px; font-weight: bold; }
        input { width: 100%; padding: 12px; border: 1px solid #cbd5e1; border-radius: 8px; font: inherit; }
        input:focus { outline: none; border-color: #4f46e5; box-shadow: 0 0 0 3px #e0e7ff; }
        .help { margin: 7px 0 0; color: #64748b; font-size: 13px; }
        .error { margin: 18px 0; padding: 12px; border-radius: 8px; background: #fef2f2; color: #b91c1c; }
        .actions { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 24px; }
        button, .button { display: inline-block; padding: 11px 16px; border: 0; border-radius: 8px; background: #4f46e5; color: white; font: inherit; font-weight: bold; text-decoration: none; cursor: pointer; }
        button:hover, .button:hover { background: #3730a3; }
        .cancel { background: #e2e8f0; color: #334155; }
        .cancel:hover { background: #cbd5e1; }
    </style>
<?php require __DIR__ . "/includes/layout.php"; ?>
</head>
<body><?php require __DIR__ . "/includes/sidebar.php"; ?>
    <main class="container app-main">
        <section class="card">
            <h1>Edit My Profile</h1>
            <p class="intro">Update the public details you want to share with other users.</p>

            <?php if ($error !== ''): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($profile): ?>
                <form method="POST" action="edit_profile.php" enctype="multipart/form-data"><?php echo csrf_field(); ?>
                    <label for="name">Name</label>
                    <input type="text" id="name" name="name" maxlength="100" value="<?php echo htmlspecialchars($profile['name']); ?>" required>

                    <label for="age">Age</label>
                    <input type="number" id="age" name="age" min="1" max="120" value="<?php echo htmlspecialchars((string) ($profile['age'] ?? '')); ?>">

                    <label for="phone">Phone</label>
                    <input type="tel" id="phone" name="phone" maxlength="15" value="<?php echo htmlspecialchars($profile['phone'] ?? ''); ?>">

                    <label for="address">Address</label>
                    <input type="text" id="address" name="address" maxlength="255" value="<?php echo htmlspecialchars($profile['address'] ?? ''); ?>">

                    <label for="profile_photo">Profile Photo</label>
                    <input type="file" id="profile_photo" name="profile_photo" accept=".jpg,.jpeg,.png,image/jpeg,image/png">
                    <p class="help">Optional. JPG, JPEG, or PNG only; maximum 2 MB.</p>

                    <div class="actions">
                        <button type="submit">Save Profile</button>
                        <a class="button cancel" href="profile.php?id=<?php echo $userId; ?>">Cancel</a>
                    </div>
                </form>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
