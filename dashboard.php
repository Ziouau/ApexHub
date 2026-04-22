<?php
session_start();
if (!isset($_SESSION['utente_id'])) {
    header("Location: login.php");
    exit();
}
require_once 'db_config.php';

$username = $_SESSION['username'];
$email = $_SESSION['email'];


$stmt = $conn->prepare("SELECT COUNT(*) as totale FROM utenti");
$stmt->execute();
$result = $stmt->get_result();
$stats = $result->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ApexHub - Dashboard</title>
    <link rel="stylesheet" href="assets/css/apexhub.css">
</head>
<body>
    <button class="menu-toggle" onclick="document.querySelector('.sidebar').classList.toggle('open')">☰</button>
    
    <div class="dashboard-container">
        <div class="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">ApexHub</div>
                <div class="sidebar-user">Ciao, <?php echo htmlspecialchars($username); ?></div>
            </div>
            <div class="sidebar-nav">
                <a href="dashboard.php" class="active">Dashboard</a>
                <a href="#"> I miei tempi</a>
                <a href="#">Classifiche</a>
                <a href="#"> Impostazioni</a>
            </div>
        </div>
        
        <div class="main-content">
            <div class="top-bar">
                <h1 class="page-title">Dashboard</h1>
                <div class="user-info">
                    <div class="user-avatar"><?php echo strtoupper(substr($username, 0, 1)); ?></div>
                    <span><?php echo htmlspecialchars($username); ?></span>
                    <a href="logout.php" class="logout-btn"> Logout</a>
                </div>
            </div>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-title">Piloti Registrati</div>
                    <div class="stat-value"><?php echo $stats['totale']; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-title">Miglior Tempo</div>
                    <div class="stat-value">1:42.350</div>
                </div>
                <div class="stat-card">
                    <div class="stat-title">Circuiti</div>
                    <div class="stat-value">12</div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h3> Benvenuto su ApexHub!</h3>
                </div>
                <div class="card-body">
                    <p>Questa è la tua area personale. Qui potrai:</p>
                    <ul style="margin-top: 10px; margin-left: 20px;">
                        <li>Caricare i tuoi tempi sul giro</li>
                        <li>Visualizzare i tuoi progressi nel tempo</li>
                        <li>Confrontarti con altri piloti</li>
                        <li>Condividere i setup della tua auto</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</body>
</html>