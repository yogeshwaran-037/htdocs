<?php
require_once __DIR__ . '/includes/security.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/config/db.php';

$userId = (int) $_SESSION['user_id'];
$books = [];
$error = '';
$successMessage = $_SESSION['success_message'] ?? '';
unset($_SESSION['success_message']);

$statement = mysqli_prepare($conn, 'SELECT books.*, users.name AS owner_name FROM books JOIN users ON users.id = books.user_id WHERE books.user_id = ? ORDER BY books.created_at DESC');

if ($statement) {
    mysqli_stmt_bind_param($statement, 'i', $userId);
    mysqli_stmt_execute($statement);
    $result = mysqli_stmt_get_result($statement);

    while ($book = mysqli_fetch_assoc($result)) {
        $books[] = $book;
    }

    mysqli_stmt_close($statement);
} else {
    $error = 'Unable to load your books right now.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Books | Book Exchange Easy</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, sans-serif; background: #f5f7fb; color: #1e293b; }
        .navbar { display: flex; justify-content: space-between; align-items: center; padding: 16px 5%; background: #312e81; color: white; }
        .navbar a { color: white; text-decoration: none; font-weight: bold; }
        .container { width: min(1100px, 90%); margin: 40px auto; }
        .heading { display: flex; justify-content: space-between; align-items: center; gap: 16px; margin-bottom: 22px; }
        h1 { margin: 0; color: #312e81; }
        .add-button, .edit-button, .delete-button { padding: 10px 14px; border: 0; border-radius: 8px; font: inherit; font-weight: bold; text-decoration: none; cursor: pointer; }
        .add-button { background: #4f46e5; color: white; }
        .add-button:hover { background: #3730a3; }
        .message { margin-bottom: 20px; padding: 12px; border-radius: 8px; }
        .success { background: #ecfdf5; color: #047857; }
        .error { background: #fef2f2; color: #b91c1c; }
        .table-card { overflow-x: auto; background: white; border-radius: 14px; box-shadow: 0 5px 18px rgba(15, 23, 42, .08); }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 16px; border-bottom: 1px solid #e2e8f0; text-align: left; }
        th { background: #eef2ff; color: #312e81; }
        .status { display: inline-block; padding: 5px 9px; border-radius: 20px; background: #dcfce7; color: #166534; font-size: 13px; font-weight: bold; }
        .actions { display: flex; gap: 8px; align-items: center; }
        .edit-button { background: #e0e7ff; color: #3730a3; }
        .delete-button { background: #dc2626; color: white; }
        .delete-button:hover { background: #b91c1c; }
        .empty { padding: 42px; text-align: center; color: #64748b; }
        @media (max-width: 600px) { .navbar, .heading { align-items: flex-start; flex-direction: column; } }
    </style>
<?php require __DIR__ . "/includes/layout.php"; ?>
</head>
<body><?php require __DIR__ . "/includes/sidebar.php"; ?>

    <main class="container app-main">
        <div class="heading">
            <h1>My Books</h1>
            <a class="add-button" href="add_book.php">Add a New Book</a>
        </div>

        <?php if ($successMessage !== ''): ?>
            <div class="message success"><?php echo htmlspecialchars($successMessage); ?></div>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <section class="table-card">
            <?php if (count($books) > 0): ?>
<div class="book-grid"><?php foreach ($books as $book): ?><article class="book-card">
<img class="book-cover" src="<?php echo book_image($book); ?>" alt="Book cover"><h2><?php echo htmlspecialchars($book['title']); ?></h2><p><?php echo htmlspecialchars($book['author']); ?></p><p><?php echo htmlspecialchars($book['category'] ?: 'Uncategorized'); ?> &middot; <?php echo htmlspecialchars($book['condition_status']); ?></p><p>Owner: <?php echo htmlspecialchars($book['owner_name']); ?></p><span class="status"><?php echo htmlspecialchars($book['exchange_status']); ?></span><div class="actions"><a class="button" href="book_details.php?id=<?php echo (int) $book['id']; ?>">View Details</a><a class="edit-button" href="edit_book.php?id=<?php echo (int) $book['id']; ?>">Edit</a><form method="POST" action="delete_book.php" onsubmit="return confirm('Delete this book?');"><?php echo csrf_field(); ?><input type="hidden" name="id" value="<?php echo (int) $book['id']; ?>"><button class="delete-button" type="submit">Delete</button></form></div></article><?php endforeach; ?></div>
            <?php else: ?>
                <div class="empty">You have not added any books yet. <a href="add_book.php">Add your first book</a>.</div>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
