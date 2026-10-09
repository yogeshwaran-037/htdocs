<?php
require_once __DIR__ . '/includes/security.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/config/db.php';

$userId = (int) $_SESSION['user_id'];
$requests = [];
$error = '';
$successMessage = $_SESSION['success_message'] ?? '';
unset($_SESSION['success_message']);

// Get requests for books owned by the signed-in user and the requester's details.
$statement = mysqli_prepare(
    $conn,
    'SELECT exchange_requests.id, exchange_requests.message, exchange_requests.status, exchange_requests.created_at,
            books.id AS book_id, books.title, books.author, books.book_image,
            users.id AS requester_id, users.name AS requester_name, users.age AS requester_age, users.profile_photo AS requester_photo
     FROM exchange_requests
     INNER JOIN books ON exchange_requests.book_id = books.id
     INNER JOIN users ON exchange_requests.requester_id = users.id
     WHERE exchange_requests.owner_id = ? AND books.user_id = ?
     ORDER BY exchange_requests.created_at DESC'
);

if ($statement) {
    mysqli_stmt_bind_param($statement, 'ii', $userId, $userId);
    mysqli_stmt_execute($statement);
    $result = mysqli_stmt_get_result($statement);

    while ($request = mysqli_fetch_assoc($result)) {
        $requests[] = $request;
    }

    mysqli_stmt_close($statement);
} else {
    $error = 'Unable to load received exchange requests right now.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Received Requests | Book Exchange Easy</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, sans-serif; background: #f5f7fb; color: #1e293b; }
        .navbar { padding: 16px 5%; background: #312e81; }
        .navbar a { color: white; text-decoration: none; font-weight: bold; }
        .container { width: min(1150px, 90%); margin: 40px auto; }
        h1 { margin: 0 0 8px; color: #312e81; }
        .intro { margin: 0 0 24px; color: #64748b; }
        .table-card { overflow-x: auto; background: white; border-radius: 14px; box-shadow: 0 5px 18px rgba(15, 23, 42, .08); }
        table { width: 100%; min-width: 900px; border-collapse: collapse; }
        th, td { padding: 15px; border-bottom: 1px solid #e2e8f0; text-align: left; vertical-align: top; }
        th { background: #eef2ff; color: #312e81; }
        .status { display: inline-block; padding: 5px 10px; border-radius: 20px; background: #fef3c7; color: #92400e; font-size: 13px; font-weight: bold; }
        .status.accepted { background: #dcfce7; color: #166534; }
        .status.rejected { background: #fee2e2; color: #b91c1c; }
        .requester { display: flex; align-items: center; gap: 10px; }
        .avatar { width: 42px; height: 42px; border-radius: 50%; object-fit: cover; }
        .avatar-placeholder { display: grid; place-items: center; width: 42px; height: 42px; border-radius: 50%; background: #c7d2fe; color: #312e81; font-weight: bold; }
        .person-link { color: #4338ca; font-weight: bold; text-decoration: none; }
        .person-link:hover { text-decoration: underline; }
        .actions { display: flex; flex-wrap: wrap; gap: 8px; }
        .action { display: inline-block; padding: 8px 11px; border: 0; border-radius: 7px; color: white; font: inherit; font-size: 13px; font-weight: bold; text-decoration: none; cursor: pointer; }
        .view { background: #4338ca; }
        .accept { background: #16a34a; }
        .reject { background: #dc2626; }
        .accept:hover { background: #15803d; }
        .reject:hover { background: #b91c1c; }
        .empty, .error { padding: 30px; border-radius: 14px; text-align: center; }
        .empty { background: white; color: #64748b; box-shadow: 0 5px 18px rgba(15, 23, 42, .08); }
        .error { background: #fef2f2; color: #b91c1c; }
        .button { display: inline-block; margin-top: 24px; padding: 11px 16px; border-radius: 8px; background: #4f46e5; color: white; font-weight: bold; text-decoration: none; }
        .button:hover { background: #3730a3; }
    </style>
<?php require __DIR__ . "/includes/layout.php"; ?>
</head>
<body><?php require __DIR__ . "/includes/sidebar.php"; ?>
    <main class="container app-main">
        <h1>Received Exchange Requests</h1>
        <p class="intro">Review requests from students interested in your books.</p>

        <?php if ($successMessage !== ''): ?>
            <p class="status accepted"><?php echo htmlspecialchars($successMessage); ?></p>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php elseif (count($requests) > 0): ?>
            <section class="table-card">
                <table>
                    <thead><tr><th>Requester</th><th>Book Requested</th><th>Owner</th><th>Message</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php foreach ($requests as $request): ?>
                            <?php $statusClass = strtolower($request['status']); ?>
                            <tr>
                                <td>
                                    <div class="requester">
                                        <?php $hasPhoto = !empty($request['requester_photo']) && file_exists(__DIR__ . '/' . $request['requester_photo']); ?>
                                        <?php if ($hasPhoto): ?><img class="avatar" src="<?php echo htmlspecialchars($request['requester_photo']); ?>" alt="">
                                        <?php else: ?><span class="avatar-placeholder"><?php echo htmlspecialchars(strtoupper(substr($request['requester_name'], 0, 1))); ?></span><?php endif; ?>
                                        <div><a class="person-link" href="profile.php?id=<?php echo (int) $request['requester_id']; ?>"><?php echo htmlspecialchars($request['requester_name']); ?></a><br><small>Age: <?php echo $request['requester_age'] !== null ? (int) $request['requester_age'] : 'Not specified'; ?></small></div>
                                    </div>
                                </td>
                                <td><img class="request-cover" src="<?php echo book_image($request); ?>" alt="Book cover"><strong><?php echo htmlspecialchars($request['title']); ?></strong><br><small><?php echo htmlspecialchars($request['author']); ?></small></td>
                                <td><a href="profile.php?id=<?php echo $userId; ?>"><?php echo htmlspecialchars($_SESSION["user_name"] ?? "Your profile"); ?></a></td><td><?php echo htmlspecialchars($request['message'] ?: 'No message'); ?></td>
                                <td><span class="status <?php echo htmlspecialchars($statusClass); ?>"><?php echo htmlspecialchars($request['status']); ?></span></td>
                                <td><?php echo htmlspecialchars($request['created_at']); ?></td>
                                <td>
                                    <?php if ($request['status'] === 'Pending'): ?><p><small>Waiting for book owner to accept the request.</small></p>
                                        <div class="actions">
                                            <a class="action view" href="profile.php?id=<?php echo (int) $request['requester_id']; ?>">View Profile</a>
                                            <a class="action view" href="book_details.php?id=<?php echo (int) $request['book_id']; ?>">View Book</a>
                                            <form method="POST" action="update_request.php"><?php echo csrf_field(); ?><input type="hidden" name="request_id" value="<?php echo (int) $request['id']; ?>"><input type="hidden" name="action" value="accept"><button class="action accept" type="submit">Accept Request</button></form>
                                            <form method="POST" action="update_request.php"><?php echo csrf_field(); ?><input type="hidden" name="request_id" value="<?php echo (int) $request['id']; ?>"><input type="hidden" name="action" value="reject"><button class="action reject" type="submit">Reject Request</button></form>
                                        </div>
                                    <?php else: ?>
                                        <div class="actions"><a class="action view" href="profile.php?id=<?php echo (int) $request['requester_id']; ?>">View Requester Profile</a><a class="action view" href="book_details.php?id=<?php echo (int) $request['book_id']; ?>">View Book</a><?php if ($request['status'] === 'Accepted'): ?>
<a class="action view" href="exchange_chat.php?request_id=<?php echo (int) $request['id']; ?>">Open Exchange Chat</a>
<?php elseif ($request['status'] === 'Pending'): ?><small>Waiting for book owner to accept the request.</small>
<?php else: ?><small>Request rejected.</small><?php endif; ?></div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </section>
        <?php else: ?>
            <div class="empty">You have not received any exchange requests yet.</div>
        <?php endif; ?>

        <a class="button" href="dashboard.php">Back to Dashboard</a>
        <a class="button" href="my_books.php">My Books</a>
    </main>
</body>
</html>
