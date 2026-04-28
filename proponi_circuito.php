<?php
session_start();
require_once 'db_config.php';
require_once 'includes/auth.php';

requireRole(['pilota', 'team_manager', 'admin']);

$username  = $_SESSION['username'];
$ruolo     = $_SESSION['ruolo'];
$utente_id = $_SESSION['utente_id'];
$page_title = 'Proponi Circuito';

$msg = '';
$msg_type = '';

if (isset($_POST['proponi'])) {
    $nome          = trim($_POST['nome']);
    $paese         = trim($_POST['paese']);
    $citta         = trim($_POST['citta']);
    $lunghezza     = (int) $_POST['lunghezza_mt'];
    $configurazione = trim($_POST['configurazione']);
    $note          = trim($_POST['note']);

    if (empty($nome) || empty($paese)) {
        $msg      = "Nome e Paese sono obbligatori.";
        $msg_type = "error";
    } else {
        // Admin: approvazione automatica
        $stato = ($ruolo === 'admin') ? 'approvato' : 'in_attesa';

        $stmt = $conn->prepare("INSERT INTO circuiti (proposto_da, nome, paese, citta, lunghezza_mt, configurazione, note, stato) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssisss", $utente_id, $nome, $paese, $citta, $lunghezza, $configurazione, $note, $stato);

        if ($stmt->execute()) {
            if ($stato === 'approvato') {
                $msg      = "✅ Circuito <strong>" . htmlspecialchars($nome) . "</strong> aggiunto e approvato.";
            } else {
                $msg      = "✅ Proposta inviata! Il circuito sarà visibile dopo l'approvazione dell'admin.";
            }
            $msg_type = "success";
            // Svuota i campi dopo successo
            $_POST = [];
        } else {
            $msg      = "Errore durante l'inserimento.";
            $msg_type = "error";
        }
    }
}

// Lista circuiti già proposti dall'utente
$stmt_miei = $conn->prepare("
    SELECT id, nome, paese, citta, lunghezza_mt, stato, creato_il
    FROM circuiti
    WHERE proposto_da = ?
    ORDER BY creato_il DESC
");
$stmt_miei->bind_param("i", $utente_id);
$stmt_miei->execute();
$miei_circuiti = $stmt_miei->get_result();

include 'includes/dashboard_head.php';
?>
<div class="dashboard-container">
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <!-- TOP BAR -->
        <div class="top-bar">
            <h1 class="page-title">➕ Proponi Circuito</h1>
            <div class="user-info">
                <div class="user-avatar"><?php echo strtoupper(substr($username, 0, 1)); ?></div>
                <span><?php echo htmlspecialchars($username); ?></span>
                <?php if ($ruolo === 'admin'): ?>
                    <span class="admin-badge-pill">⚙️ ADMIN</span>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($msg): ?>
            <div class="alert alert-<?php echo $msg_type; ?>"><?php echo $msg; ?></div>
        <?php endif; ?>

        <!-- FORM PROPOSTA -->
        <div class="card">
            <div class="card-header">
                <h3>🗺️ Dati del Circuito</h3>
            </div>
            <div class="card-body">
                <?php if ($ruolo !== 'admin'): ?>
                    <div class="alert alert-info" style="margin-bottom:20px;">
                        ℹ️ La tua proposta sarà visibile a tutti solo dopo l'approvazione dell'amministratore.
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Nome Circuito *</label>
                            <input type="text" name="nome" class="form-control"
                                   value="<?php echo htmlspecialchars($_POST['nome'] ?? ''); ?>"
                                   placeholder="es. Autodromo di Vallelunga" required>
                        </div>
                        <div class="form-group">
                            <label>Paese *</label>
                            <input type="text" name="paese" class="form-control"
                                   value="<?php echo htmlspecialchars($_POST['paese'] ?? ''); ?>"
                                   placeholder="es. Italia" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Città</label>
                            <input type="text" name="citta" class="form-control"
                                   value="<?php echo htmlspecialchars($_POST['citta'] ?? ''); ?>"
                                   placeholder="es. Campagnano di Roma">
                        </div>
                        <div class="form-group">
                            <label>Lunghezza (metri)</label>
                            <input type="number" name="lunghezza_mt" class="form-control"
                                   value="<?php echo htmlspecialchars($_POST['lunghezza_mt'] ?? ''); ?>"
                                   placeholder="es. 3200" min="100" max="30000">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Configurazione</label>
                        <input type="text" name="configurazione" class="form-control"
                               value="<?php echo htmlspecialchars($_POST['configurazione'] ?? ''); ?>"
                               placeholder="es. Layout GP, Chicane Nord, Layout Kart...">
                    </div>
                    <div class="form-group">
                        <label>Note aggiuntive</label>
                        <textarea name="note" class="form-control" rows="3"
                                  placeholder="Informazioni utili sul tracciato, particolarità..."><?php echo htmlspecialchars($_POST['note'] ?? ''); ?></textarea>
                    </div>
                    <button type="submit" name="proponi" class="btn btn-primary">
                        <?php echo $ruolo === 'admin' ? '✅ Aggiungi e Approva' : '📤 Invia Proposta'; ?>
                    </button>
                </form>
            </div>
        </div>

        <!-- LE MIE PROPOSTE -->
        <?php if ($miei_circuiti->num_rows > 0): ?>
        <div class="card">
            <div class="card-header">
                <h3>📋 Le mie proposte</h3>
            </div>
            <div class="card-body" style="padding:0;">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Circuito</th>
                                <th>Paese / Città</th>
                                <th>Lunghezza</th>
                                <th>Stato</th>
                                <th>Data</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php while ($c = $miei_circuiti->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($c['nome']); ?></strong></td>
                                <td><?php echo htmlspecialchars($c['paese']); ?><?php echo $c['citta'] ? ' / ' . htmlspecialchars($c['citta']) : ''; ?></td>
                                <td><?php echo $c['lunghezza_mt'] ? number_format($c['lunghezza_mt']) . ' m' : '—'; ?></td>
                                <td>
                                    <?php
                                    $badge = match($c['stato']) {
                                        'approvato'  => '<span class="badge badge-success">✅ Approvato</span>',
                                        'in_attesa'  => '<span class="badge badge-warning">⏳ In attesa</span>',
                                        'rifiutato'  => '<span class="badge badge-danger">❌ Rifiutato</span>',
                                        default      => $c['stato']
                                    };
                                    echo $badge;
                                    ?>
                                </td>
                                <td><?php echo date('d/m/Y', strtotime($c['creato_il'])); ?></td>
                            </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>
</body>
</html>