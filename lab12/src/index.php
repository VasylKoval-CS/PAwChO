<?php
echo "<h1>Stack LEMP dziala!</h1>";

$conn = new mysqli('mysql', 'root', 'rootpassword', 'test_db');

if ($conn->connect_error) {
    echo "Blad polaczenia z MySQL: " . $conn->connect_error;
} else {
    echo "Polaczenie z MySQL dziala poprawnie!";
}
?>