<?php
session_start();
require_once 'db_config.php';
require_once 'includes/auth.php';

requireLogin(); // Controlla login + redirect onboarding se ruolo NULL

$username = $_SESSION['username'];
$email    = $_SESSION['email'];
$ruolo    = $_SESSION['ruolo'];

// Messaggio errore 403
$errore = $_GET['errore'] ?? null;

// --- Stats generali ---
$stmt = $conn->prepare("SELECT COUNT(*) as totale FROM utenti WHERE attivo = 1");
$stmt->execute();
$totale_utenti = $stmt->get_result()->fetch_assoc()['totale'];

// Miglior tempo personale (se pilota/team_manager)
$miglior_tempo_str = '—';
if (hasPermesso('inserisci_tempo')) {
    $stmt2 = $conn->prepare("SELECT MIN(tempo_ms) as best FROM tempi WHERE utente_id = ?");
    $stmt2->bind_param("i", $_SESSION['utente_id']);
    $stmt2->execute();
    $best_ms = $stmt2->get_result()->fetch_assoc()['best'];
    if ($best_ms) {
        $min  = floor($best_ms / 60000);
        $sec  = floor(($best_ms % 60000) / 1000);
        $ms   = $best_ms % 1000;
        $miglior_tempo_str = sprintf("%d:%02d.%03d", $min, $sec, $ms);
    }
}

// Circuiti approvati
$stmt3 = $conn->prepare("SELECT COUNT(*) as totale FROM circuiti WHERE stato = 'approvato'");
$stmt3->execute();
$totale_circuiti = $stmt3->get_result()->fetch_assoc()['totale'];

// Admin: circuiti in attesa
$circuiti_in_attesa = 0;
if ($ruolo === 'admin') {
    $stmt4 = $conn->prepare("SELECT COUNT(*) as totale FROM circuiti WHERE stato = 'in_attesa'");
    $stmt4->execute();
    $circuiti_in_attesa = $stmt4->get_result()->fetch_assoc()['totale'];
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ApexHub - Dashboard</title>
    <link rel="stylesheet" href="assets/css/apexhub.css">
    <style>
        .role-badge {
            display: inline-block;
            font-size: 11px;
            padding: 3px 10px;
            border-radius: 20px;
            font-weight: 600;
            margin-top: 6px;
        }
        .role-pilota       { background: rgba(233,69,96,0.2);  color: #e94560; }
        .role-team_manager { background: rgba(255,193,7,0.2);  color: #ffc107; }
        .role-appassionato { background: rgba(23,162,184,0.2); color: #17a2b8; }
        .role-admin        { background: rgba(40,167,69,0.2);  color: #28a745; }

        .alert-403 {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
            padding: 14px 18px;
            border-radius: 10px;
            margin-bottom: 24px;
        }

        .notice-card {
            background: linear-gradient(135deg, rgba(233,69,96,0.08), rgba(199,62,86,0.04));
            border: 1px solid rgba(233,69,96,0.2);
            border-radius: 12px;
            padding: 20px 24px;
            margin-bottom: 24px;
        }
        .notice-card p { color: #555; font-size: 14px; line-height: 1.6; }

        .admin-badge-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(40,167,69,0.12);
            color: #28a745;
            border: 1px solid rgba(40,167,69,0.25);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
        }

        .pending-badge {
            display: inline-block;
            background: #e94560;
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 11px;
            line-height: 18px;
            text-align: center;
            margin-left: 6px;
            font-weight: 700;
        }
    </style>
</head>
<body>
    <button class="menu-toggle" onclick="document.querySelector('.sidebar').classList.toggle('open')">☰</button>

    <div class="dashboard-container">
        <!-- SIDEBAR -->
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
                <a href="dashboard.php" class="active">🏠 Dashboard</a>

                <?php if (hasPermesso('vedi_classifiche')): ?>
                    <a href="#">🏆 Classifiche</a>
                <?php endif; ?>

                <?php if (hasPermesso('vedi_circuiti')): ?>
                    <a href="#">🗺️ Circuiti</a>
                <?php endif; ?>

                <?php if (hasPermesso('inserisci_tempo')): ?>
                    <a href="#">⏱️ Inserisci Tempo</a>
                    <a href="#">📋 I miei tempi</a>
                <?php endif; ?>

                <?php if (hasPermesso('aggiungi_veicolo')): ?>
                    <a href="#">🚗 Il mio Garage</a>
                <?php endif; ?>

                <?php if (hasPermesso('proponi_circuito')): ?>
                    <a href="#">➕ Proponi Circuito</a>
                <?php endif; ?>

                <?php if (hasPermesso('gestisci_piloti_team')): ?>
                    <a href="#">👥 Il mio Team</a>
                <?php endif; ?>

                <?php if ($ruolo === 'admin'): ?>
                    <div style="border-top: 1px solid rgba(255,255,255,0.08); margin: 10px 0;"></div>
                    <a href="#">
                        ⚙️ Pannello Admin
                    </a>
                    <a href="#">
                        ✅ Approva Circuiti
                        <?php if ($circuiti_in_attesa > 0): ?>
                            <span class="pending-badge"><?php echo $circuiti_in_attesa; ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="#">👤 Gestisci Utenti</a>
                <?php endif; ?>

                <div style="border-top: 1px solid rgba(255,255,255,0.08); margin: 10px 0;"></div>
                <a href="#">⚙️ Impostazioni</a>
            </div>
        </div>

        <!-- MAIN CONTENT -->
        <div class="main-content">
            <div class="top-bar">
                <h1 class="page-title">Dashboard</h1>
                <div class="user-info">
                    <div class="user-avatar"><?php echo strtoupper(substr($username, 0, 1)); ?></div>
                    <span><?php echo htmlspecialchars($username); ?></span>
                    <?php if ($ruolo === 'admin'): ?>
                        <span class="admin-badge-pill">⚙️ ADMIN</span>
                    <?php endif; ?>
                    <a href="logout.php" class="logout-btn">Logout</a>
                </div>
            </div>

            <?php if ($errore === '403'): ?>
                <div class="alert-403">
                    🚫 <strong>Accesso negato.</strong> Non hai i permessi per visualizzare quella pagina.
                </div>
            <?php endif; ?>

            <!-- STATS -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-title">Piloti Registrati</div>
                    <div class="stat-value"><?php echo $totale_utenti; ?></div>
                </div>

                <?php if (hasPermesso('inserisci_tempo')): ?>
                <div class="stat-card">
                    <div class="stat-title">Il tuo Miglior Tempo</div>
                    <div class="stat-value" style="font-size: 24px;"><?php echo $miglior_tempo_str; ?></div>
                </div>
                <?php endif; ?>

                <div class="stat-card">
                    <div class="stat-title">Circuiti Approvati</div>
                    <div class="stat-value"><?php echo $totale_circuiti; ?></div>
                </div>

                <?php if ($ruolo === 'admin' && $circuiti_in_attesa > 0): ?>
                <div class="stat-card" style="border-left: 4px solid #e94560;">
                    <div class="stat-title">⏳ Circuiti in Attesa</div>
                    <div class="stat-value"><?php echo $circuiti_in_attesa; ?></div>
                </div>
                <?php endif; ?>
            </div>

            <!-- CARD BENVENUTO (contestuale al ruolo) -->
            <div class="card">
                <div class="card-header">
                    <h3>
                        <?php echo match($ruolo) {
                            'pilota'        => '🏎️ Pronto a scendere in pista?',
                            'team_manager'  => '🧑‍💼 Il tuo team ti aspetta',
                            'appassionato'  => '👀 Esplora le performance',
                            'admin'         => '⚙️ Pannello di controllo',
                            default         => 'Benvenuto su ApexHub'
                        }; ?>
                    </h3>
                </div>
                <div class="card-body">
                    <?php if ($ruolo === 'pilota'): ?>
                        <p>Inizia aggiungendo il tuo veicolo al garage, poi potrai caricare i tuoi tempi sul giro.</p>
                        <ul style="margin-top:12px; margin-left:20px; line-height:2;">
                            <li>➕ Aggiungi un veicolo al <strong>Garage</strong></li>
                            <li>🗺️ Cerca o proponi un <strong>Circuito</strong></li>
                            <li>⏱️ Carica il tuo primo <strong>Tempo sul giro</strong></li>
                        </ul>

                    <?php elseif ($ruolo === 'team_manager'): ?>
                        <p>Monitora le performance dei tuoi piloti e confronta i dati di sessione.</p>
                        <ul style="margin-top:12px; margin-left:20px; line-height:2;">
                            <li>👥 Crea il tuo <strong>Team</strong> e invita i piloti</li>
                            <li>📊 Visualizza la <strong>Dashboard del Team</strong></li>
                            <li>⏱️ Carica tempi per i tuoi piloti</li>
                        </ul>

                    <?php elseif ($ruolo === 'appassionato'): ?>
                        <p>Esplora le classifiche per circuito e categoria. Puoi consultare i setup resi pubblici dai piloti.</p>
                        <ul style="margin-top:12px; margin-left:20px; line-height:2;">
                            <li>🏆 Sfoglia le <strong>Classifiche</strong> per categoria</li>
                            <li>🗺️ Esplora i <strong>Circuiti</strong> disponibili</li>
                            <li>🔧 Consulta i <strong>Setup</strong> pubblici</li>
                        </ul>

                    <?php elseif ($ruolo === 'admin'): ?>
                        <p>Hai accesso completo alla piattaforma.</p>
                        <ul style="margin-top:12px; margin-left:20px; line-height:2;">
                            <?php if ($circuiti_in_attesa > 0): ?>
                                <li>⚠️ <strong><?php echo $circuiti_in_attesa; ?> circuiti</strong> in attesa di approvazione</li>
                            <?php else: ?>
                                <li>✅ Nessun circuito in attesa di approvazione</li>
                            <?php endif; ?>
                            <li>👤 Gestisci gli utenti registrati</li>
                            <li>📊 Monitora le statistiche globali</li>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</body>
</html>