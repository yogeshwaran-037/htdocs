<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Success Page</title>
    <link rel="icon" href="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTFBd0VSOxtfl8Ggbbep1iBPovH_FbW06X_bLfRgYCJZg&s=10">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    
 <?php
$hostname="localhost";
$uname="root";
$pass="";
$dbname="yoko";
$conn=new mysqli($hostname,$uname,$pass,$dbname);
if($conn->connect_error){
    die($conn->connect_error);
}
$username=$_POST["username"];
$phoneno=$_POST["phoneno"];
$email=$_POST["email"];
$password=$_POST["password"];

$sql="INSERT INTO users (username,phoneno,email,password) VAlUES ('$username','$phoneno','$email','$password')";

if($conn->query($sql)===TRUE){
    
    echo "<h1 class='success'> Registration Successful</h1>";
    echo "<h2 class='msg'>Welcome $username</h2>";
    header("refresh:3;url=login.php");
}else{
    echo "<h1 class='error'>Registration Failed</h1>";
}
?> 
</body>
</html>