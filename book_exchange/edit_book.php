<?php
require_once __DIR__ . '/includes/security.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/config/db.php';

$bookId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$userId = (int) $_SESSION['user_id'];
$error = '';
$book = null;

if (!$bookId) {
    $error = 'Invalid book selected.';
} else {
    // Fetch the book only when it belongs to the logged-in user.
    $statement = mysqli_prepare(
        $conn,
        'SELECT id, title, author, category, description, condition_status FROM books WHERE id = ? AND user_id = ? LIMIT 1'
    );

    if ($statement) {
        mysqli_stmt_bind_param($statement, 'ii', $bookId, $userId);
        mysqli_stmt_execute($statement);
        $result = mysqli_stmt_get_result($statement);
        $book = mysqli_fetch_assoc($result);
        mysqli_stmt_close($statement);
    }

    if (!$book) {
        $error = 'This book does not exist or you do not have permission to edit it.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $book) {
    $title = trim($_POST['title'] ?? '');
    $author = trim($_POST['author'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $conditionStatus = trim($_POST['condition_status'] ?? '');

    if ($title === '' || $author === '' || strlen($title) > 200 || strlen($author) > 150 || strlen($category) > 100 || !in_array($conditionStatus, ['Like New', 'Good', 'Fair'], true)) {
        $error = 'Enter a title (up to 200 characters), author (up to 150), category (up to 100), and a valid condition.';
        $book['title'] = $title;
        $book['author'] = $author;
        $book['category'] = $category;
        $book['description'] = $description;
        $book['condition_status'] = $conditionStatus;
    } else {
        // user_id is deliberately not included in this update.
        $statement = mysqli_prepare(
            $conn,
            'UPDATE books SET title = ?, author = ?, category = ?, description = ?, condition_status = ? WHERE id = ? AND user_id = ?'
        );

        if ($statement) {
            mysqli_stmt_bind_param($statement, 'sssssii', $title, $author, $category, $description, $conditionStatus, $bookId, $userId);

            if (mysqli_stmt_execute($statement)) {
                mysqli_stmt_close($statement);
                $_SESSION['success_message'] = 'Your book was updated successfully.';
                header('Location: my_books.php');
                exit;
            }

            mysqli_stmt_close($statement);
        }

        $error = 'Unable to update the book. Please try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Book | Book Exchange Easy</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, sans-serif; background: #f5f7fb; color: #1e293b; }
        .navbar { padding: 16px 5%; background: #312e81; }
        .navbar a { color: white; text-decoration: none; font-weight: bold; }
        .container { width: min(700px, 90%); margin: 40px auto; }
        .card { padding: 32px; background: white; border-radius: 16px; box-shadow: 0 5px 18px rgba(15, 23, 42, .08); }
        h1 { margin-top: 0; color: #312e81; }
        label { display: block; margin: 18px 0 7px; font-weight: bold; }
        input, textarea, select { width: 100%; padding: 12px; border: 1px solid #cbd5e1; border-radius: 8px; font: inherit; }
        textarea { min-height: 110px; resize: vertical; }
        input:focus, textarea:focus, select:focus { outline: none; border-color: #4f46e5; box-shadow: 0 0 0 3px #e0e7ff; }
        .required { color: #dc2626; }
        .error { margin: 18px 0; padding: 12px; border-radius: 8px; background: #fef2f2; color: #b91c1c; }
        button, .back-button { display: inline-block; margin-top: 24px; padding: 12px 20px; border: 0; border-radius: 8px; font: inherit; font-weight: bold; text-decoration: none; cursor: pointer; }
        button { background: #4f46e5; color: white; }
        button:hover { background: #3730a3; }
        .back-button { margin-left: 8px; background: #e2e8f0; color: #334155; }
    </style>
<?php require __DIR__ . "/includes/layout.php"; ?>
</head>
<body><?php require __DIR__ . "/includes/sidebar.php"; ?>
    <main class="container app-main">
        <section class="card">
            <h1>Edit Book</h1>

            <?php if ($error !== ''): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($book): ?>
                <form method="POST" action="edit_book.php?id=<?php echo (int) $bookId; ?>"><?php echo csrf_field(); ?>
                    <label for="title">Book Title <span class="required">*</span></label>
                    <input type="text" id="title" name="title" maxlength="200" value="<?php echo htmlspecialchars($book['title']); ?>" required>

                    <label for="author">Author <span class="required">*</span></label>
                    <input type="text" id="author" name="author" maxlength="150" value="<?php echo htmlspecialchars($book['author']); ?>" required>

                    <label for="category">Category</label>
                    <input type="text" id="category" name="category" maxlength="100" value="<?php echo htmlspecialchars($book['category']); ?>">

                    <label for="description">Description</label>
                    <textarea id="description" name="description"><?php echo htmlspecialchars($book['description']); ?></textarea>

                    <label for="condition_status">Book Condition <span class="required">*</span></label>
                    <select id="condition_status" name="condition_status" required>
                        <option value="">Select condition</option>
                        <option value="Like New" <?php echo $book['condition_status'] === 'Like New' ? 'selected' : ''; ?>>Like New</option>
                        <option value="Good" <?php echo $book['condition_status'] === 'Good' ? 'selected' : ''; ?>>Good</option>
                        <option value="Fair" <?php echo $book['condition_status'] === 'Fair' ? 'selected' : ''; ?>>Fair</option>
                    </select>

                    <button type="submit">Save Changes</button>
                    <a class="back-button" href="my_books.php">Cancel</a>
                </form>
            <?php else: ?>
                <a class="back-button" href="my_books.php">Return to My Books</a>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
