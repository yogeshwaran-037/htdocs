<?php
require_once __DIR__ . '/includes/security.php';

// Only signed-in users may view book details.
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/config/db.php';

$userId = (int) $_SESSION['user_id'];
$bookId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$book = null;

if ($bookId) {
    // Join users to show the name of the person who added this book.
    $statement = mysqli_prepare(
        $conn,
        'SELECT books.id, books.user_id, books.title, books.author, books.category, books.description, books.book_image, books.condition_status, books.exchange_status, users.name AS owner_name
         FROM books
         INNER JOIN users ON books.user_id = users.id
         WHERE books.id = ?
         LIMIT 1'
    );

    if ($statement) {
        mysqli_stmt_bind_param($statement, 'i', $bookId);
        mysqli_stmt_execute($statement);
        $result = mysqli_stmt_get_result($statement);
        $book = mysqli_fetch_assoc($result);
        mysqli_stmt_close($statement);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Details | Book Exchange Easy</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, sans-serif; background: #f5f7fb; color: #1e293b; }
        .navbar { padding: 16px 5%; background: #312e81; }
        .navbar a { color: white; text-decoration: none; font-weight: bold; }
        .container { width: min(760px, 90%); margin: 40px auto; }
        .card { padding: 32px; background: white; border-radius: 16px; box-shadow: 0 5px 18px rgba(15, 23, 42, .08); }
        h1 { margin: 0 0 24px; color: #312e81; }
        .detail { padding: 14px 0; border-bottom: 1px solid #e2e8f0; }
        .detail:last-of-type { border-bottom: 0; }
        .label { display: block; margin-bottom: 5px; color: #64748b; font-size: 14px; font-weight: bold; }
        .value { margin: 0; line-height: 1.6; white-space: pre-wrap; }
        .status { display: inline-block; padding: 5px 10px; border-radius: 20px; background: #dcfce7; color: #166534; font-size: 13px; font-weight: bold; }
        .notice { margin: 24px 0 0; padding: 12px; border-radius: 8px; background: #eef2ff; color: #3730a3; font-weight: bold; }
        .actions { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 26px; }
        .button { display: inline-block; padding: 11px 16px; border-radius: 8px; background: #4f46e5; color: white; font-weight: bold; text-decoration: none; }
        .button:hover { background: #3730a3; }
        .back-button { background: #e2e8f0; color: #334155; }
        .back-button:hover { background: #cbd5e1; }
        .not-found { text-align: center; color: #64748b; }
    </style>
<?php require __DIR__ . "/includes/layout.php"; ?>
</head>
<body><?php require __DIR__ . "/includes/sidebar.php"; ?>

    <main class="container app-main">
        <section class="card">
            <?php if ($book): ?><img class="book-cover detail-cover" src="<?php echo book_image($book); ?>" alt="Book cover">
                <h1><?php echo htmlspecialchars($book['title']); ?></h1>

                <div class="detail">
                    <span class="label">Author</span>
                    <p class="value"><?php echo htmlspecialchars($book['author']); ?></p>
                </div>
                <div class="detail">
                    <span class="label">Category</span>
                    <p class="value"><?php echo htmlspecialchars($book['category'] ?: 'Not specified'); ?></p>
                </div>
                <div class="detail">
                    <span class="label">Description</span>
                    <p class="value"><?php echo htmlspecialchars($book['description'] ?: 'No description provided.'); ?></p>
                </div>
                <div class="detail">
                    <span class="label">Book Condition</span>
                    <p class="value"><?php echo htmlspecialchars($book['condition_status']); ?></p>
                </div>
                <div class="detail">
                    <span class="label">Exchange Status</span>
                    <span class="status"><?php echo htmlspecialchars($book['exchange_status']); ?></span>
                </div>
                <div class="detail">
                    <span class="label">Book Owner</span>
                    <p class="value"><?php echo htmlspecialchars($book['owner_name']); ?></p>
                    <a href="profile.php?id=<?php echo (int) $book['user_id']; ?>">View Owner Profile</a>
                </div>

                <?php if ((int) $book['user_id'] === $userId): ?>
                    <div class="notice">This is your book</div>
                <?php endif; ?>

                <div class="actions">
                    <?php if ((int) $book['user_id'] !== $userId && $book['exchange_status'] === 'Available'): ?>
                        <a class="button" href="exchange_request.php?book_id=<?php echo (int) $book['id']; ?>">Send Exchange Request</a>
                    <?php endif; ?>
                    <a class="button back-button" href="browse_books.php">Back to Browse Books</a>
                    <a class="button back-button" href="dashboard.php">Back to Dashboard</a>
                </div>
            <?php else: ?>
                <h1>Book Details</h1>
                <p class="not-found">Book not found.</p>
                <div class="actions"><a class="button back-button" href="browse_books.php">Back to Browse Books</a><a class="button back-button" href="dashboard.php">Back to Dashboard</a></div>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
