<?php
// includes/sidebar.php
// Richiede che session_start(), db_config.php e auth.php siano già stati inclusi
// e che $circuiti_in_attesa sia definita (o la calcola da sola)

if (!isset($circuiti_in_attesa)) {
    $circuiti_in_attesa = 0;
    if (($_SESSION['ruolo'] ?? '') === 'admin') {
        $s = $conn->prepare("SELECT COUNT(*) as t FROM circuiti WHERE stato = 'in_attesa'");
        $s->execute();
        $circuiti_in_attesa = $s->get_result()->fetch_assoc()['t'];
    }
}

$ruolo    = $_SESSION['ruolo'] ?? '';
$username = $_SESSION['username'] ?? '';
$pagina   = basename($_SERVER['PHP_SELF']);
?>
<button class="menu-toggle" onclick="document.querySelector('.sidebar').classList.toggle('open')">☰</button>

<div class="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo">🏁 ApexHub</div>
        <div class="sidebar-user"><?php echo htmlspecialchars($username); ?></div>
        <div>
            <span class="role-badge role-<?php echo $ruolo; ?>">
                <?php echo match($ruolo) {
                    'pilota'        => '🏎️ Pilota',
                    'team_manager'  => '🧑‍💼 Team Manager',
                    'appassionato'  => '👀 Appassionato',
                    'admin'         => '⚙️ Admin',
                    default         => $ruolo
                }; ?>
            </span>
        </div>
    </div>

    <div class="sidebar-nav">
        <a href="dashboard.php" <?php echo $pagina === 'dashboard.php' ? 'class="active"' : ''; ?>>🏠 Dashboard</a>

        <?php if (hasPermesso('vedi_classifiche')): ?>
            <a href="classifiche.php" <?php echo $pagina === 'classifiche.php' ? 'class="active"' : ''; ?>>🏆 Classifiche</a>
        <?php endif; ?>

        <?php if (hasPermesso('vedi_circuiti')): ?>
            <a href="circuiti.php" <?php echo $pagina === 'circuiti.php' ? 'class="active"' : ''; ?>>🗺️ Circuiti</a>
        <?php endif; ?>

        <?php if (hasPermesso('inserisci_tempo')): ?>
            <a href="inserisci_tempo.php" <?php echo $pagina === 'inserisci_tempo.php' ? 'class="active"' : ''; ?>>⏱️ Inserisci Tempo</a>
            <a href="i_miei_tempi.php" <?php echo $pagina === 'i_miei_tempi.php' ? 'class="active"' : ''; ?>>📋 I miei tempi</a>
        <?php endif; ?>

        <?php if (hasPermesso('aggiungi_veicolo')): ?>
            <a href="garage.php" <?php echo $pagina === 'garage.php' ? 'class="active"' : ''; ?>>🚗 Il mio Garage</a>
        <?php endif; ?>

        <?php if (hasPermesso('proponi_circuito')): ?>
            <a href="proponi_circuito.php" <?php echo $pagina === 'proponi_circuito.php' ? 'class="active"' : ''; ?>>➕ Proponi Circuito</a>
        <?php endif; ?>

        <?php if (hasPermesso('gestisci_piloti_team')): ?>
            <a href="team.php" <?php echo $pagina === 'team.php' ? 'class="active"' : ''; ?>>👥 Il mio Team</a>
        <?php endif; ?>

        <?php if ($ruolo === 'admin'): ?>
            <div style="border-top:1px solid rgba(255,255,255,0.08);margin:10px 0;"></div>
            <a href="admin_circuiti.php" <?php echo $pagina === 'admin_circuiti.php' ? 'class="active"' : ''; ?>>
                ✅ Approva Circuiti
                <?php if ($circuiti_in_attesa > 0): ?>
                    <span class="pending-badge"><?php echo $circuiti_in_attesa; ?></span>
                <?php endif; ?>
            </a>
            <a href="admin_utenti.php" <?php echo $pagina === 'admin_utenti.php' ? 'class="active"' : ''; ?>>👤 Gestisci Utenti</a>
        <?php endif; ?>

        <div style="border-top:1px solid rgba(255,255,255,0.08);margin:10px 0;"></div>
        <a href="impostazioni.php" <?php echo $pagina === 'impostazioni.php' ? 'class="active"' : ''; ?>>⚙️ Impostazioni</a>
        <a href="logout.php">🚪 Logout</a>
    </div>
</div>