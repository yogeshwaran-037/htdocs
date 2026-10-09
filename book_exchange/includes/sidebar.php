<?php
$sidebarPage = basename($_SERVER['PHP_SELF']);
$sidebarUser = ['name' => $_SESSION['user_name'] ?? 'Student', 'email' => '', 'profile_photo' => ''];
if (isset($conn)) {
    $sidebarStatement = mysqli_prepare($conn, 'SELECT name, email, profile_photo FROM users WHERE id = ? LIMIT 1');
    if ($sidebarStatement) {
        $sidebarId = (int) $_SESSION['user_id'];
        mysqli_stmt_bind_param($sidebarStatement, 'i', $sidebarId);
        mysqli_stmt_execute($sidebarStatement);
        $sidebarResult = mysqli_stmt_get_result($sidebarStatement);
        $sidebarUser = mysqli_fetch_assoc($sidebarResult) ?: $sidebarUser;
        mysqli_stmt_close($sidebarStatement);
    }
}
$sidebarPhoto = !empty($sidebarUser['profile_photo']) && file_exists(__DIR__ . '/../' . $sidebarUser['profile_photo']);
$sidebarInitial = strtoupper(substr($sidebarUser['name'], 0, 1));
$sidebarActive = static function (array $pages) use ($sidebarPage): string { return in_array($sidebarPage, $pages, true) ? 'active' : ''; };
?>
<button class="sidebar-toggle" type="button" aria-label="Open menu" onclick="document.querySelector('.app-sidebar').classList.toggle('open')">Menu</button>
<aside class="app-sidebar">
    <div class="app-brand">BOOK EXCHANGE</div>
    <nav class="app-nav">
        <a class="<?php echo $sidebarActive(['dashboard.php']); ?>" href="dashboard.php"><span>Dashboard</span></a>
        <a class="<?php echo $sidebarActive(['browse_books.php', 'book_details.php', 'exchange_request.php']); ?>" href="browse_books.php"><span>Browse Books</span></a>
        <a class="<?php echo $sidebarActive(['add_book.php']); ?>" href="add_book.php"><span>Add Book</span></a>
        <a class="<?php echo $sidebarActive(['my_books.php', 'edit_book.php']); ?>" href="my_books.php"><span>My Books</span></a>
        <a class="<?php echo $sidebarActive(['my_requests.php']); ?>" href="my_requests.php"><span>My Requests</span></a>
        <a class="<?php echo $sidebarActive(['received_requests.php', 'update_request.php']); ?>" href="received_requests.php"><span>Received Requests</span></a>
        <a class="<?php echo $sidebarActive(['exchange_chats.php', 'exchange_chat.php']); ?>" href="exchange_chats.php"><span>Exchange Chats</span></a>
        <a class="<?php echo $sidebarActive(['profile.php', 'edit_profile.php']); ?>" href="profile.php?id=<?php echo (int) $_SESSION['user_id']; ?>"><span>My Profile</span></a>
    </nav>
    <div class="sidebar-spacer"></div><div class="sidebar-divider"></div>
    <nav class="app-nav"><a class="sidebar-logout" href="logout.php"><span>Logout</span></a></nav>
    <a class="sidebar-user" href="profile.php?id=<?php echo (int) $_SESSION['user_id']; ?>">
        <?php if ($sidebarPhoto): ?><img class="sidebar-avatar" src="<?php echo htmlspecialchars($sidebarUser['profile_photo']); ?>" alt="">
        <?php else: ?><span class="sidebar-avatar"><?php echo htmlspecialchars($sidebarInitial); ?></span><?php endif; ?>
        <span><strong><?php echo htmlspecialchars($sidebarUser['name']); ?></strong><small><?php echo htmlspecialchars($sidebarUser['email']); ?></small></span>
    </a>
</aside>
