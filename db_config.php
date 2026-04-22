<?php
$host = "localhost";
$user = "cxwrkjhb_proc";
$password = "0earningforthelore";
$database = "cxwrkjhb_ApexHub";

$conn = new mysqli($host, $user, $password, $database);

if ($conn->connect_error) {
    die("Connessione fallita: " . $conn->connect_error);
}
?>