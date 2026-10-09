<?php
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'book_exchange';
try {
    $conn = mysqli_connect($host, $username, $password, $database);
    mysqli_set_charset($conn, 'utf8mb4');
} catch (mysqli_sql_exception $exception) {
    error_log('Book Exchange database connection failed.');
    http_response_code(503);
    exit('Database unavailable. Please check that MySQL is running in XAMPP.');
}
