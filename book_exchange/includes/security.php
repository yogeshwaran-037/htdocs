<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start(['cookie_httponly' => true, 'cookie_samesite' => 'Lax']);
}
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') . '">';
}
if (!in_array(basename($_SERVER['SCRIPT_NAME']), ['login.php', 'register.php'], true) && empty($_SESSION['user_id'])) { header('Location: login.php'); exit; }
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!is_string($_POST['csrf_token'] ?? null) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']))) {
    http_response_code(403);
    exit('Your form expired. Reload the page and try again.');
}
function book_image(array $book): string {
    $path = $book['book_image'] ?? '';
    if (!is_string($path) || !preg_match('~^uploads/books/[a-zA-Z0-9_-]+\.(?:jpg|jpeg|png|webp)$~i', $path) || !is_file(__DIR__ . '/../' . $path)) {
        return 'assets/book-placeholder.svg';
    }
    return htmlspecialchars($path, ENT_QUOTES, 'UTF-8');
}
function upload_book_image(): ?string {
    $upload = $_FILES['book_image'] ?? null;
    if (!$upload || $upload['error'] === UPLOAD_ERR_NO_FILE) { return null; }
    if ($upload['error'] !== UPLOAD_ERR_OK || $upload['size'] > 2 * 1024 * 1024) {
        throw new RuntimeException('Choose a book image smaller than 2 MB.');
    }
    $types = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']);
    if (!isset($types[$mime]) || !@getimagesize($upload['tmp_name'])) {
        throw new RuntimeException('Book images must be JPG, PNG, or WebP.');
    }
    $path = 'uploads/books/' . bin2hex(random_bytes(16)) . '.' . $types[$mime];
    if (!move_uploaded_file($upload['tmp_name'], __DIR__ . '/../' . $path)) {
        throw new RuntimeException('Unable to save the book image.');
    }
    return $path;
}
