<?php
require_once __DIR__ . '/includes/security.php';

// Only signed-in users may browse books shared by other users.
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/config/db.php';

$userId = (int) $_SESSION['user_id'];
$search = trim($_GET['search'] ?? '');
$category = trim($_GET['category'] ?? '');
$selectedBookId = filter_input(INPUT_GET, 'book_id', FILTER_VALIDATE_INT);
$books = [];
$categories = [];
$selectedBook = null;
$error = '';

// Get category choices from the categories table.
$categoryStatement = mysqli_prepare($conn, 'SELECT name FROM categories ORDER BY name ASC');

if ($categoryStatement) {
    mysqli_stmt_execute($categoryStatement);
    $categoryResult = mysqli_stmt_get_result($categoryStatement);

    while ($categoryRow = mysqli_fetch_assoc($categoryResult)) {
        $categories[] = $categoryRow['name'];
    }

    mysqli_stmt_close($categoryStatement);
} else {
    $error = 'Unable to load book categories right now.';
}

// A View Details button stays on this page and loads the selected book safely.
if ($selectedBookId) {
    $detailStatement = mysqli_prepare(
        $conn,
        "SELECT id, title, author, category, description, condition_status, exchange_status FROM books WHERE id = ? AND user_id != ? AND exchange_status = 'Available' LIMIT 1"
    );

    if ($detailStatement) {
        mysqli_stmt_bind_param($detailStatement, 'ii', $selectedBookId, $userId);
        mysqli_stmt_execute($detailStatement);
        $detailResult = mysqli_stmt_get_result($detailStatement);
        $selectedBook = mysqli_fetch_assoc($detailResult);
        mysqli_stmt_close($detailStatement);
    }
}

// Search by title or author, with an optional category filter.
$bookSql = "SELECT books.id, books.user_id, books.title, books.author, books.category, books.condition_status, books.book_image, books.exchange_status, users.name AS owner_name FROM books INNER JOIN users ON books.user_id = users.id WHERE ? > 0 AND books.exchange_status = 'Available'";
$searchTerm = '%' . $search . '%';

if ($search !== '') {
    $bookSql .= ' AND (books.title LIKE ? OR books.author LIKE ?)';
}

if ($category !== '') {
    $bookSql .= ' AND books.category = ?';
}

$bookSql .= ' ORDER BY books.created_at DESC';
$bookStatement = mysqli_prepare($conn, $bookSql);

if ($bookStatement) {
    if ($search !== '' && $category !== '') {
        mysqli_stmt_bind_param($bookStatement, 'isss', $userId, $searchTerm, $searchTerm, $category);
    } elseif ($search !== '') {
        mysqli_stmt_bind_param($bookStatement, 'iss', $userId, $searchTerm, $searchTerm);
    } elseif ($category !== '') {
        mysqli_stmt_bind_param($bookStatement, 'is', $userId, $category);
    } else {
        mysqli_stmt_bind_param($bookStatement, 'i', $userId);
    }

    mysqli_stmt_execute($bookStatement);
    $bookResult = mysqli_stmt_get_result($bookStatement);

    while ($book = mysqli_fetch_assoc($bookResult)) {
        $books[] = $book;
    }

    mysqli_stmt_close($bookStatement);
} else {
    $error = 'Unable to load available books right now.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Books | Book Exchange Easy</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, sans-serif; background: #f5f7fb; color: #1e293b; }
        .navbar { display: flex; justify-content: space-between; align-items: center; gap: 16px; padding: 16px 5%; background: #312e81; color: white; }
        .navbar a { color: white; text-decoration: none; font-weight: bold; }
        .container { width: min(1100px, 90%); margin: 40px auto; }
        h1, h2 { color: #312e81; }
        h1 { margin: 0 0 8px; }
        .intro { margin: 0 0 24px; color: #64748b; }
        .filters, .details, .book-card { background: white; border-radius: 14px; box-shadow: 0 5px 18px rgba(15, 23, 42, .08); }
        .filters { display: grid; grid-template-columns: 1fr 220px auto; gap: 12px; padding: 18px; margin-bottom: 28px; }
        input, select, button, .button { padding: 11px 13px; border: 1px solid #cbd5e1; border-radius: 8px; font: inherit; }
        input:focus, select:focus { outline: none; border-color: #4f46e5; box-shadow: 0 0 0 3px #e0e7ff; }
        button, .button { border: 0; background: #4f46e5; color: white; font-weight: bold; cursor: pointer; text-decoration: none; text-align: center; }
        button:hover, .button:hover { background: #3730a3; }
        .details { padding: 24px; margin-bottom: 28px; border-left: 5px solid #4f46e5; }
        .details h2 { margin-top: 0; }
        .details p { line-height: 1.6; }
        .muted { color: #64748b; }
        .book-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 20px; }
        .book-card { display: flex; flex-direction: column; padding: 22px; }
        .book-card h2 { margin: 0 0 14px; font-size: 21px; }
        .book-card p { margin: 6px 0; color: #475569; line-height: 1.45; }
        .book-card strong { color: #1e293b; }
        .owner-link { display: inline-block; margin: 12px 0 0; color: #4338ca; font-size: 14px; font-weight: bold; text-decoration: none; }
        .owner-link:hover { text-decoration: underline; }
        .status { display: inline-block; margin: 14px 0 18px; padding: 5px 10px; border-radius: 20px; background: #dcfce7; color: #166534; font-size: 13px; font-weight: bold; }
        .book-card .button { margin-top: auto; }
        .empty, .error { padding: 28px; border-radius: 14px; text-align: center; }
        .empty { background: white; color: #64748b; box-shadow: 0 5px 18px rgba(15, 23, 42, .08); }
        .error { margin-bottom: 20px; background: #fef2f2; color: #b91c1c; }
        @media (max-width: 800px) { .filters, .book-grid { grid-template-columns: 1fr; } }
        @media (max-width: 500px) { .navbar { align-items: flex-start; flex-direction: column; } }
    </style>
<?php require __DIR__ . "/includes/layout.php"; ?>
</head>
<body><?php require __DIR__ . "/includes/sidebar.php"; ?>

    <main class="container app-main">
        <h1>Browse Available Books</h1>
        <p class="intro">Find books shared by other students for exchange.</p>

        <?php if ($error !== ''): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form class="filters" method="GET" action="browse_books.php">
            <input type="search" name="search" placeholder="Search by title or author" value="<?php echo htmlspecialchars($search); ?>">
            <select name="category">
                <option value="">All Categories</option>
                <?php foreach ($categories as $categoryOption): ?>
                    <option value="<?php echo htmlspecialchars($categoryOption); ?>" <?php echo $category === $categoryOption ? 'selected' : ''; ?>><?php echo htmlspecialchars($categoryOption); ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit">Search</button>
        </form>

        <?php if ($selectedBook): ?>
            <section class="details">
                <h2><?php echo htmlspecialchars($selectedBook['title']); ?></h2>
                <p><strong>Author:</strong> <?php echo htmlspecialchars($selectedBook['author']); ?></p>
                <p><strong>Category:</strong> <?php echo htmlspecialchars($selectedBook['category'] ?: 'Not specified'); ?></p>
                <p><strong>Condition:</strong> <?php echo htmlspecialchars($selectedBook['condition_status']); ?></p>
                <p><strong>Exchange Status:</strong> <?php echo htmlspecialchars($selectedBook['exchange_status']); ?></p>
                <p><strong>Description:</strong> <?php echo htmlspecialchars($selectedBook['description'] ?: 'No description provided.'); ?></p>
            </section>
        <?php endif; ?>

        <?php if (count($books) > 0): ?>
            <section class="book-grid">
                <?php foreach ($books as $book): ?>
                    <article class="book-card"><img class="book-cover" src="<?php echo book_image($book); ?>" alt="Book cover"><img class="book-cover" src="<?php echo book_image($book); ?>" alt="Book cover">
                        <h2><?php echo htmlspecialchars($book['title']); ?></h2>
                        <p><strong>Author:</strong> <?php echo htmlspecialchars($book['author']); ?></p>
                        <p><strong>Category:</strong> <?php echo htmlspecialchars($book['category'] ?: 'Not specified'); ?></p>
                        <p><strong>Condition:</strong> <?php echo htmlspecialchars($book['condition_status']); ?></p>
                        <p><strong>Owner:</strong> <a class="owner-link" href="profile.php?id=<?php echo (int) $book['user_id']; ?>"><?php echo htmlspecialchars($book['owner_name']); ?></a></p>
                        <a class="owner-link" href="profile.php?id=<?php echo (int) $book['user_id']; ?>">View Owner Profile</a>
                        <span class="status"><?php echo htmlspecialchars($book['exchange_status']); ?></span>
                        <a class="button" href="book_details.php?id=<?php echo (int) $book['id']; ?>">View Details</a><?php if ((int) $book["user_id"] !== $userId): ?><a class="owner-link" href="exchange_request.php?book_id=<?php echo (int) $book["id"]; ?>">Send Exchange Request</a><?php endif; ?><?php if ((int) $book["user_id"] !== $userId): ?><a class="owner-link" href="exchange_request.php?book_id=<?php echo (int) $book["id"]; ?>">Send Exchange Request</a><?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php else: ?>
            <div class="empty">No available books match your search right now.</div>
        <?php endif; ?>
    </main>
</body>
</html>
