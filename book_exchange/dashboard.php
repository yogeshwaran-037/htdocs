<?php
require_once __DIR__ . '/includes/security.php';

// Protect this page: only logged-in users can see it.
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/config/db.php';

$userName = $_SESSION['user_name'] ?? 'Student';
$userId = (int) $_SESSION['user_id'];
$sentPendingCount = 0;
$receivedPendingCount = 0;

$sentCountStatement = mysqli_prepare($conn, "SELECT COUNT(*) AS request_count FROM exchange_requests WHERE requester_id = ? AND status = 'Pending'");
if ($sentCountStatement) {
    mysqli_stmt_bind_param($sentCountStatement, 'i', $userId);
    mysqli_stmt_execute($sentCountStatement);
    $sentCountResult = mysqli_stmt_get_result($sentCountStatement);
    $sentPendingCount = (int) (mysqli_fetch_assoc($sentCountResult)['request_count'] ?? 0);
    mysqli_stmt_close($sentCountStatement);
}

$receivedCountStatement = mysqli_prepare($conn, "SELECT COUNT(*) AS request_count FROM exchange_requests WHERE owner_id = ? AND status = 'Pending'");
if ($receivedCountStatement) {
    mysqli_stmt_bind_param($receivedCountStatement, 'i', $userId);
    mysqli_stmt_execute($receivedCountStatement);
    $receivedCountResult = mysqli_stmt_get_result($receivedCountStatement);
    $receivedPendingCount = (int) (mysqli_fetch_assoc($receivedCountResult)['request_count'] ?? 0);
    mysqli_stmt_close($receivedCountStatement);
}
$statsStatement = $conn->prepare("SELECT (SELECT COUNT(*) FROM books WHERE user_id = ?) AS my_books, (SELECT COUNT(*) FROM books WHERE exchange_status = 'Available') AS available_books");
$statsStatement->bind_param('i', $userId); $statsStatement->execute();
$stats = $statsStatement->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Book Exchange Easy</title>
    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f5f7fb;
            color: #1e293b;
        }

        .navbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
            padding: 16px 5%;
            background: #312e81;
            color: white;
        }

        .brand { margin: 0; font-size: 21px; white-space: nowrap; }

        .nav-links {
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-end;
            gap: 8px;
        }

        .nav-links a {
            padding: 8px 10px;
            border-radius: 7px;
            color: white;
            text-decoration: none;
            font-size: 14px;
        }

        .nav-links a:hover { background: rgba(255, 255, 255, 0.18); }
        .nav-links .logout { background: #dc2626; }
        .nav-links .logout:hover { background: #b91c1c; }

        .container { width: min(1100px, 90%); margin: 0 auto; padding: 42px 0; }

        .welcome {
            padding: 32px;
            border-radius: 16px;
            color: white;
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            box-shadow: 0 12px 30px rgba(79, 70, 229, 0.22);
        }

        .welcome h1 { margin: 0 0 8px; font-size: 30px; }
        .welcome p { margin: 0; color: #e0e7ff; }

        .section-title { margin: 36px 0 18px; font-size: 22px; }

        .cards {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 20px;
        }

        .card {
            padding: 24px;
            border-radius: 14px;
            background: white;
            box-shadow: 0 5px 18px rgba(15, 23, 42, 0.08);
        }

        .card-icon { font-size: 30px; }
        .card h2 { margin: 14px 0 8px; font-size: 20px; color: #312e81; }
        .card p { margin: 0 0 18px; color: #64748b; line-height: 1.5; }

        .card a {
            display: inline-block;
            padding: 9px 13px;
            border-radius: 7px;
            background: #eef2ff;
            color: #4338ca;
            font-size: 14px;
            font-weight: bold;
            text-decoration: none;
        }

        .card a:hover { background: #e0e7ff; }

        @media (max-width: 800px) {
            .navbar { align-items: flex-start; flex-direction: column; }
            .nav-links { justify-content: flex-start; }
            .cards { grid-template-columns: 1fr; }
        }
    </style>
<?php require __DIR__ . "/includes/layout.php"; ?>
</head>
<body><?php require __DIR__ . "/includes/sidebar.php"; ?>

    <main class="container app-main">
        <section class="welcome">
            <h1>Welcome to Book Exchange Easy</h1>
            <p>Hello, <?php echo htmlspecialchars($userName); ?>! Find books, share your own, and make exchanges with fellow students.</p>
        <a class="button" href="add_book.php">+ Share a book</a> <a class="button" href="browse_books.php">Find your next read</a></section>

        <section class="stats"><a class="stat" href="my_books.php">My Books<strong><?php echo (int) $stats['my_books']; ?></strong></a><a class="stat" href="browse_books.php">Available Books<strong><?php echo (int) $stats['available_books']; ?></strong></a><a class="stat" href="my_requests.php">Pending sent<strong><?php echo $sentPendingCount; ?></strong></a><a class="stat" href="received_requests.php">Pending received<strong><?php echo $receivedPendingCount; ?></strong></a></section><h2 class="section-title">Your reading corner</h2>

        <section class="cards">
            <article class="card" id="my-books">
                <div class="card-icon">📚</div>
                <h2>My Books</h2>
                <p>Manage the books you have added for exchange.</p>
                <a href="my_books.php">View My Books</a>
            </article>

            <article class="card" id="available-books">
                <div class="card-icon">🔎</div>
                <h2>Available Books</h2>
                <p>Explore books shared by other students.</p>
                <a href="browse_books.php">Browse Books</a>
            </article>

            <article class="card" id="exchange-requests">
                <div class="card-icon">🔄</div>
                <h2>My Requests</h2>
                <p>Track exchange requests you have sent.</p>
                <a href="my_requests.php">View My Requests<?php echo $sentPendingCount > 0 ? ' (' . $sentPendingCount . ')' : ''; ?></a>
            </article>
            <article class="card" id="received-requests">
                <div class="card-icon">Requests</div>
                <h2>Received Requests</h2>
                <p>Review requests for books you own.</p>
                <a href="received_requests.php">View Received Requests<?php echo $receivedPendingCount > 0 ? ' (' . $receivedPendingCount . ')' : ''; ?></a>
            </article>
        </section>
    </main>
</body>
</html>
