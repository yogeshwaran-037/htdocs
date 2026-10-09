<?php
require_once __DIR__ . '/includes/security.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/config/db.php';

$profileId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$profile = null;
$listedBooks = [];
$exchangeHistory = [];
$booksListed = 0;
$completedExchanges = 0;
$error = '';

if ($profileId) {
    $profileStatement = mysqli_prepare($conn, 'SELECT id, name, age, profile_photo FROM users WHERE id = ? LIMIT 1');

    if ($profileStatement) {
        mysqli_stmt_bind_param($profileStatement, 'i', $profileId);
        mysqli_stmt_execute($profileStatement);
        $profileResult = mysqli_stmt_get_result($profileStatement);
        $profile = mysqli_fetch_assoc($profileResult);
        mysqli_stmt_close($profileStatement);
    }

    if (!$profile) {
        $error = 'User not found.';
    }
} else {
    $error = 'User not found.';
}

if ($profile) {
    // Currently listed books are books that are still available for exchange.
    $booksStatement = mysqli_prepare(
        $conn,
        "SELECT id, title, author, category, condition_status, book_image, exchange_status FROM books WHERE user_id = ? ORDER BY created_at DESC"
    );

    if ($booksStatement) {
        mysqli_stmt_bind_param($booksStatement, 'i', $profileId);
        mysqli_stmt_execute($booksStatement);
        $booksResult = mysqli_stmt_get_result($booksStatement);

        while ($book = mysqli_fetch_assoc($booksResult)) {
            $listedBooks[] = $book;
        }

        mysqli_stmt_close($booksStatement);
    }

    $booksListed = count($listedBooks);

    // An Accepted request represents a completed exchange involving this user.
    $historyStatement = mysqli_prepare(
        $conn,
        "SELECT exchange_requests.created_at, books.title, books.author,
                requester.name AS requester_name, owner.name AS owner_name
         FROM exchange_requests
         INNER JOIN books ON exchange_requests.book_id = books.id
         INNER JOIN users AS requester ON exchange_requests.requester_id = requester.id
         INNER JOIN users AS owner ON exchange_requests.owner_id = owner.id
         WHERE exchange_requests.status = 'Accepted'
           AND (exchange_requests.requester_id = ? OR exchange_requests.owner_id = ?)
         ORDER BY exchange_requests.created_at DESC"
    );

    if ($historyStatement) {
        mysqli_stmt_bind_param($historyStatement, 'ii', $profileId, $profileId);
        mysqli_stmt_execute($historyStatement);
        $historyResult = mysqli_stmt_get_result($historyStatement);

        while ($exchange = mysqli_fetch_assoc($historyResult)) {
            $exchangeHistory[] = $exchange;
        }

        mysqli_stmt_close($historyStatement);
    }

    $completedExchanges = count($exchangeHistory);
}

$hasPhoto = $profile && !empty($profile['profile_photo']) && file_exists(__DIR__ . '/' . $profile['profile_photo']);
$initial = $profile ? strtoupper(substr($profile['name'], 0, 1)) : '?';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Public Profile | Book Exchange Easy</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, sans-serif; background: #f5f7fb; color: #1e293b; }
        .navbar { padding: 16px 5%; background: #312e81; }
        .navbar a { color: white; text-decoration: none; font-weight: bold; }
        .container { width: min(1000px, 90%); margin: 40px auto; }
        .profile-card, .section-card, .empty { background: white; border-radius: 16px; box-shadow: 0 5px 18px rgba(15, 23, 42, .08); }
        .profile-card { display: flex; align-items: center; gap: 24px; padding: 30px; }
        .photo, .placeholder { width: 104px; height: 104px; flex: 0 0 104px; border-radius: 50%; object-fit: cover; }
        .placeholder { display: grid; place-items: center; background: #c7d2fe; color: #312e81; font-size: 42px; font-weight: bold; }
        h1, h2 { color: #312e81; }
        h1 { margin: 0 0 8px; }
        h2 { margin: 0 0 18px; font-size: 22px; }
        .profile-card p { margin: 6px 0; color: #64748b; }
        .stats { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px; margin: 26px 0; }
        .stat { padding: 22px; border-radius: 14px; background: #eef2ff; color: #4338ca; }
        .stat strong { display: block; margin-top: 5px; font-size: 30px; color: #312e81; }
        .section-card { padding: 26px; margin-top: 24px; }
        .book-list, .history-list { display: grid; gap: 12px; }
        .book, .history { padding: 15px; border-radius: 10px; background: #f8fafc; border: 1px solid #e2e8f0; }
        .book h3 { margin: 0 0 7px; color: #312e81; font-size: 17px; }
        .book p, .history p { margin: 4px 0; color: #475569; }
        .empty { padding: 24px; color: #64748b; text-align: center; box-shadow: none; border: 1px solid #e2e8f0; }
        .error { padding: 22px; border-radius: 12px; background: #fef2f2; color: #b91c1c; text-align: center; }
        .button { display: inline-block; margin-top: 24px; padding: 11px 16px; border-radius: 8px; background: #4f46e5; color: white; font-weight: bold; text-decoration: none; }
        .button:hover { background: #3730a3; }
        .edit-button { margin-left: 8px; background: #e0e7ff; color: #3730a3; }
        @media (max-width: 600px) { .profile-card { align-items: flex-start; flex-direction: column; } .stats { grid-template-columns: 1fr; } }
    </style>
<?php require __DIR__ . "/includes/layout.php"; ?>
</head>
<body><?php require __DIR__ . "/includes/sidebar.php"; ?>
    <main class="container app-main">
        <?php if ($error !== ''): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php else: ?>
            <section class="profile-card">
                <?php if ($hasPhoto): ?>
                    <img class="photo" src="<?php echo htmlspecialchars($profile['profile_photo']); ?>" alt="<?php echo htmlspecialchars($profile['name']); ?>'s profile photo">
                <?php else: ?>
                    <div class="placeholder" aria-label="Default profile photo"><?php echo htmlspecialchars($initial); ?></div>
                <?php endif; ?>
                <div>
                    <h1><?php echo htmlspecialchars($profile['name']); ?></h1>
                    <p><strong>Age:</strong> <?php echo $profile['age'] !== null ? (int) $profile['age'] : 'Not specified'; ?></p>
                </div>
            </section>

            <section class="stats">
                <div class="stat">Books Listed<strong><?php echo $booksListed; ?></strong></div>
                <div class="stat">Accepted Exchanges<strong><?php echo $completedExchanges; ?></strong></div>
            </section>

            <section class="section-card">
                <h2>Book Collection</h2>
                <?php if (count($listedBooks) > 0): ?>
                    <div class="book-list">
                        <?php foreach ($listedBooks as $book): ?>
                            <article class="book"><img class="request-cover" src="<?php echo book_image($book); ?>" alt="Book cover">
                                <h3><?php echo htmlspecialchars($book['title']); ?></h3>
                                <p><?php echo htmlspecialchars($book['author']); ?> · <?php echo htmlspecialchars($book['category'] ?: 'Uncategorized'); ?></p>
                                <p>Condition: <?php echo htmlspecialchars($book['condition_status']); ?></p>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty">No books are currently listed.</div>
                <?php endif; ?>
            </section>

            <section class="section-card">
                <h2>Previous Book Collection / Exchange History</h2>
                <?php if (count($exchangeHistory) > 0): ?>
                    <div class="history-list">
                        <?php foreach ($exchangeHistory as $exchange): ?>
                            <article class="history">
                                <p><strong><?php echo htmlspecialchars($exchange['title']); ?></strong> by <?php echo htmlspecialchars($exchange['author']); ?></p>
                                <p>Exchange accepted between <?php echo htmlspecialchars($exchange['requester_name']); ?> and <?php echo htmlspecialchars($exchange['owner_name']); ?>.</p>
                                <p>Requested: <?php echo htmlspecialchars($exchange['created_at']); ?></p>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty">No accepted exchanges yet.</div>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <a class="button" href="dashboard.php">Back to Dashboard</a>
        <a class="button" href="browse_books.php">Browse Books</a>
        <?php if ($profile && (int) $profile['id'] === (int) $_SESSION['user_id']): ?>
            <a class="button edit-button" href="edit_profile.php">Edit My Profile</a>
        <?php endif; ?>
    </main>
</body>
</html>
