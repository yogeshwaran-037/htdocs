<?php
require_once __DIR__ . '/includes/security.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/config/db.php';

$error = '';
$title = '';
$author = '';
$category = '';
$description = '';
$conditionStatus = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $author = trim($_POST['author'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $conditionStatus = trim($_POST['condition_status'] ?? '');
    $userId = (int) $_SESSION['user_id'];
    $imagePath = null;

    if ($title === '' || $author === '' || strlen($title) > 200 || strlen($author) > 150 || strlen($category) > 100 || !in_array($conditionStatus, ['Like New', 'Good', 'Fair'], true)) {
        $error = 'Enter a title (up to 200 characters), author (up to 150), category (up to 100), and a valid condition.';
    } else {
        try { $imagePath = upload_book_image(); } catch (RuntimeException $exception) { $error = $exception->getMessage(); }
        if ($error === '') {
        $exchangeStatus = 'Available';
        $statement = mysqli_prepare(
            $conn,
            'INSERT INTO books (user_id, title, author, category, description, condition_status, exchange_status, book_image) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );

        if ($statement) {
            mysqli_stmt_bind_param(
                $statement,
                'isssssss',
                $userId,
                $title,
                $author,
                $category,
                $description,
                $conditionStatus,
                $exchangeStatus,
                $imagePath
            );

            if (mysqli_stmt_execute($statement)) {
                mysqli_stmt_close($statement);
                $_SESSION['success_message'] = 'Your book was added successfully.';
                header('Location: my_books.php');
                exit;
            }

            mysqli_stmt_close($statement);
        }

        if ($imagePath) { @unlink(__DIR__ . '/' . $imagePath); }
        $error = 'Unable to add the book right now. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Book | Book Exchange Easy</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, sans-serif; background: #f5f7fb; color: #1e293b; }
        .navbar { padding: 16px 5%; background: #312e81; }
        .navbar a { color: white; text-decoration: none; font-weight: bold; }
        .container { width: min(700px, 90%); margin: 40px auto; }
        .card { padding: 32px; background: white; border-radius: 16px; box-shadow: 0 5px 18px rgba(15, 23, 42, .08); }
        h1 { margin-top: 0; color: #312e81; }
        .intro { color: #64748b; }
        label { display: block; margin: 18px 0 7px; font-weight: bold; }
        input, textarea, select { width: 100%; padding: 12px; border: 1px solid #cbd5e1; border-radius: 8px; font: inherit; }
        textarea { min-height: 110px; resize: vertical; }
        input:focus, textarea:focus, select:focus { outline: none; border-color: #4f46e5; box-shadow: 0 0 0 3px #e0e7ff; }
        .required { color: #dc2626; }
        .error { margin: 18px 0; padding: 12px; border-radius: 8px; background: #fef2f2; color: #b91c1c; }
        button { margin-top: 24px; padding: 12px 20px; border: 0; border-radius: 8px; background: #4f46e5; color: white; font-weight: bold; cursor: pointer; }
        button:hover { background: #3730a3; }
    </style>
<?php require __DIR__ . "/includes/layout.php"; ?>
</head>
<body><?php require __DIR__ . "/includes/sidebar.php"; ?>
    <main class="container app-main">
        <section class="card">
            <h1>Add a Book</h1>
            <p class="intro">Share a book that you would like to exchange with other students.</p>

            <?php if ($error !== ''): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" action="add_book.php" enctype="multipart/form-data"><?php echo csrf_field(); ?>
                <label for="title">Book Title <span class="required">*</span></label>
                <input type="text" id="title" name="title" maxlength="200" value="<?php echo htmlspecialchars($title); ?>" required>

                <label for="author">Author <span class="required">*</span></label>
                <input type="text" id="author" name="author" maxlength="150" value="<?php echo htmlspecialchars($author); ?>" required>

                <label for="category">Category</label>
                <input type="text" id="category" name="category" maxlength="100" placeholder="Example: Fiction, Science, History" value="<?php echo htmlspecialchars($category); ?>">

                <label for="description">Description</label>
                <textarea id="description" name="description" placeholder="Write a short description of the book."><?php echo htmlspecialchars($description); ?></textarea>

                <label for="condition_status">Book Condition <span class="required">*</span></label>
                <select id="condition_status" name="condition_status" required>
                    <option value="">Select condition</option>
                    <option value="Like New" <?php echo $conditionStatus === 'Like New' ? 'selected' : ''; ?>>Like New</option>
                    <option value="Good" <?php echo $conditionStatus === 'Good' ? 'selected' : ''; ?>>Good</option>
                    <option value="Fair" <?php echo $conditionStatus === 'Fair' ? 'selected' : ''; ?>>Fair</option>
                </select>

                <label for="book_image">Book image (optional)</label><input type="file" id="book_image" name="book_image" accept="image/jpeg,image/png,image/webp"><p>JPG, PNG or WebP, up to 2 MB.</p><button type="submit">Add Book</button>
            </form>
        </section>
    </main>
</body>
</html>
