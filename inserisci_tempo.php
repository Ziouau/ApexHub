<?php
session_start();
require_once 'db_config.php';
require_once 'includes/auth.php';

requireRole(['pilota', 'team_manager', 'admin']);

$username   = $_SESSION['username'];
$ruolo      = $_SESSION['ruolo'];
$utente_id  = $_SESSION['utente_id'];
$page_title = 'Inserisci Tempo';

$msg = '';
$msg_type = '';

// Carica circuiti approvati
$circuiti = $conn->query("SELECT id, nome, paese, lunghezza_mt FROM circuiti WHERE stato = 'approvato' ORDER BY nome");

// Carica veicoli utente
$stmt_v = $conn->prepare("SELECT v.id, v.marca, v.modello, v.anno, c.nome as categoria FROM veicoli v JOIN categorie c ON c.id = v.categoria_id WHERE v.utente_id = ? ORDER BY v.creato_il DESC");
$stmt_v->bind_param("i", $utente_id);
$stmt_v->execute();
$veicoli = $stmt_v->get_result();

// Inserimento tempo
if (isset($_POST['salva'])) {
    $circuito_id   = (int) $_POST['circuito_id'];
    $veicolo_id    = (int) $_POST['veicolo_id'];
    $minuti        = (int) $_POST['minuti'];
    $secondi       = (int) $_POST['secondi'];
    $millisecondi  = (int) $_POST['millisecondi'];
    $meteo         = $_POST['meteo'];
    $temperatura   = $_POST['temperatura'] !== '' ? (int) $_POST['temperatura'] : null;
    $mescola       = trim($_POST['mescola']);
    $tipo_sessione = $_POST['tipo_sessione'];
    $data_sessione = $_POST['data_sessione'];
    $note          = trim($_POST['note']);
    $pubblico      = isset($_POST['pubblico']) ? 1 : 0;

    // Validazioni
    $errori = [];
    if (!$circuito_id) $errori[] = "Seleziona un circuito.";
    if (!$veicolo_id)  $errori[] = "Seleziona un veicolo.";
    if ($secondi >= 60) $errori[] = "I secondi devono essere < 60.";
    if ($millisecondi >= 1000) $errori[] = "I millisecondi devono essere < 1000.";
    if (empty($data_sessione)) $errori[] = "Inserisci la data della sessione.";

    // Verifica che il veicolo appartenga all'utente
    $check_v = $conn->prepare("SELECT id FROM veicoli WHERE id = ? AND utente_id = ?");
    $check_v->bind_param("ii", $veicolo_id, $utente_id);
    $check_v->execute();
    if ($check_v->get_result()->num_rows === 0) $errori[] = "Veicolo non valido.";

    if (!empty($errori)) {
        $msg      = implode('<br>', $errori);
        $msg_type = "error";
    } else {
        // Converti in millisecondi totali
        $tempo_ms = ($minuti * 60 * 1000) + ($secondi * 1000) + $millisecondi;

        $stmt = $conn->prepare("INSERT INTO tempi (utente_id, circuito_id, veicolo_id, tempo_ms, meteo, temperatura, mescola, tipo_sessione, data_sessione, note, pubblico) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iiiisiss ssi",
            $utente_id, $circuito_id, $veicolo_id, $tempo_ms,
            $meteo, $temperatura, $mescola,
            $tipo_sessione, $data_sessione, $note, $pubblico
        );

        // Bind corretto con parametri separati
        $stmt2 = $conn->prepare("INSERT INTO tempi (utente_id, circuito_id, veicolo_id, tempo_ms, meteo, temperatura, mescola, tipo_sessione, data_sessione, note, pubblico) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt2->bind_param("iiiisissssi",
            $utente_id,
            $circuito_id,
            $veicolo_id,
            $tempo_ms,
            $meteo,
            $temperatura,
            $mescola,
            $tipo_sessione,
            $data_sessione,
            $note,
            $pubblico
        );

        if ($stmt2->execute()) {
            $tempo_str = sprintf("%d:%02d.%03d", $minuti, $secondi, $millisecondi);
            $msg       = "✅ Tempo <strong>$tempo_str</strong> salvato con successo!";
            $msg_type  = "success";
            $_POST     = [];
        } else {
            $msg      = "Errore durante il salvataggio: " . $conn->error;
            $msg_type = "error";
        }
    }
}

include 'includes/dashboard_head.php';
?>
<style>
    .time-input-group { display:flex; align-items:center; gap:8px; }
    .time-input-group input { text-align:center; font-size:22px; font-weight:700; color:#1a1a2e; }
    .time-sep { font-size:24px; font-weight:700; color:#ccc; line-height:1; }
    .time-labels { display:flex; gap:8px; margin-top:6px; }
    .time-label { text-align:center; font-size:11px; color:#999; font-weight:600; text-transform:uppercase; }
    .time-label.min  { width: 80px; }
    .time-label.sec  { width: 70px; }
    .time-label.ms   { width: 80px; }
    .no-circuiti-warn { background:#fff3cd; border:1px solid #ffc107; border-radius:10px; padding:16px 20px; margin-bottom:24px; color:#856404; }
    .no-veicoli-warn  { background:#f8d7da; border:1px solid #dc3545; border-radius:10px; padding:16px 20px; margin-bottom:24px; color:#721c24; }
    .switch-row { display:flex; align-items:center; gap:12px; }
    .switch { position:relative; display:inline-block; width:44px; height:24px; }
    .switch input { opacity:0; width:0; height:0; }
    .slider { position:absolute; cursor:pointer; top:0; left:0; right:0; bottom:0; background:#ccc; border-radius:24px; transition:.3s; }
    .slider:before { position:absolute; content:""; height:18px; width:18px; left:3px; bottom:3px; background:white; border-radius:50%; transition:.3s; }
    input:checked + .slider { background:#28a745; }
    input:checked + .slider:before { transform:translateX(20px); }
</style>

<div class="dashboard-container">
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="top-bar">
            <h1 class="page-title">⏱️ Inserisci Tempo</h1>
            <div class="user-info">
                <div class="user-avatar"><?php echo strtoupper(substr($username, 0, 1)); ?></div>
                <span><?php echo htmlspecialchars($username); ?></span>
            </div>
        </div>

        <?php if ($msg): ?>
            <div class="alert alert-<?php echo $msg_type; ?>"><?php echo $msg; ?></div>
        <?php endif; ?>

        <?php if ($circuiti->num_rows === 0): ?>
            <div class="no-circuiti-warn">
                ⚠️ <strong>Nessun circuito disponibile.</strong>
                Prima devi <a href="proponi_circuito.php">proporre un circuito</a> e attendere l'approvazione.
            </div>
        <?php endif; ?>

        <?php if ($veicoli->num_rows === 0): ?>
            <div class="no-veicoli-warn">
                🚗 <strong>Nessun veicolo nel garage.</strong>
                <a href="garage.php">Aggiungi un veicolo</a> prima di inserire un tempo.
            </div>
        <?php endif; ?>

        <?php if ($circuiti->num_rows > 0 && $veicoli->num_rows > 0): ?>
        <div class="card">
            <div class="card-header">
                <h3>📝 Dati della sessione</h3>
            </div>
            <div class="card-body">
                <form method="POST">

                    <!-- CIRCUITO E VEICOLO -->
                    <div class="form-row">
                        <div class="form-group">
                            <label>Circuito *</label>
                            <select name="circuito_id" class="form-control" required>
                                <option value="">-- Seleziona circuito --</option>
                                <?php
                                $circuiti->data_seek(0);
                                while ($c = $circuiti->fetch_assoc()):
                                    $sel = (isset($_POST['circuito_id']) && $_POST['circuito_id'] == $c['id']) ? 'selected' : '';
                                    $label = htmlspecialchars($c['nome']) . ' (' . htmlspecialchars($c['paese']) . ')';
                                    if ($c['lunghezza_mt']) $label .= ' — ' . number_format($c['lunghezza_mt']) . 'm';
                                ?>
                                    <option value="<?php echo $c['id']; ?>" <?php echo $sel; ?>><?php echo $label; ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Veicolo *</label>
                            <select name="veicolo_id" class="form-control" required>
                                <option value="">-- Seleziona veicolo --</option>
                                <?php
                                $veicoli->data_seek(0);
                                while ($v = $veicoli->fetch_assoc()):
                                    $sel = (isset($_POST['veicolo_id']) && $_POST['veicolo_id'] == $v['id']) ? 'selected' : '';
                                    $label = htmlspecialchars($v['marca'] . ' ' . $v['modello']);
                                    if ($v['anno']) $label .= ' (' . $v['anno'] . ')';
                                    $label .= ' — ' . htmlspecialchars($v['categoria']);
                                ?>
                                    <option value="<?php echo $v['id']; ?>" <?php echo $sel; ?>><?php echo $label; ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>

                    <!-- TEMPO SUL GIRO -->
                    <div class="form-group">
                        <label>Tempo sul Giro *</label>
                        <div class="time-input-group">
                            <input type="number" name="minuti" class="form-control time-input-group"
                                   style="width:80px;" min="0" max="99"
                                   value="<?php echo $_POST['minuti'] ?? '1'; ?>"
                                   placeholder="0" required>
                            <span class="time-sep">:</span>
                            <input type="number" name="secondi" class="form-control"
                                   style="width:70px;" min="0" max="59"
                                   value="<?php echo $_POST['secondi'] ?? '40'; ?>"
                                   placeholder="00" required>
                            <span class="time-sep">.</span>
                            <input type="number" name="millisecondi" class="form-control"
                                   style="width:80px;" min="0" max="999"
                                   value="<?php echo $_POST['millisecondi'] ?? '000'; ?>"
                                   placeholder="000" required>
                        </div>
                        <div class="time-labels">
                            <span class="time-label min">Minuti</span>
                            <span style="width:16px;"></span>
                            <span class="time-label sec">Secondi</span>
                            <span style="width:16px;"></span>
                            <span class="time-label ms">Millesimi</span>
                        </div>
                    </div>

                    <!-- DATA E TIPO SESSIONE -->
                    <div class="form-row">
                        <div class="form-group">
                            <label>Data Sessione *</label>
                            <input type="date" name="data_sessione" class="form-control"
                                   value="<?php echo htmlspecialchars($_POST['data_sessione'] ?? date('Y-m-d')); ?>"
                                   max="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Tipo Sessione</label>
                            <select name="tipo_sessione" class="form-control">
                                <?php
                                $tipi = ['pratica' => '🔧 Pratica', 'qualifica' => '⚡ Qualifica', 'gara' => '🏁 Gara'];
                                foreach ($tipi as $val => $label):
                                    $sel = (($_POST['tipo_sessione'] ?? 'pratica') === $val) ? 'selected' : '';
                                ?>
                                    <option value="<?php echo $val; ?>" <?php echo $sel; ?>><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- CONDIZIONI -->
                    <div class="form-row">
                        <div class="form-group">
                            <label>Meteo</label>
                            <select name="meteo" class="form-control">
                                <?php
                                $meteo_opt = ['soleggiato' => '☀️ Soleggiato', 'nuvoloso' => '☁️ Nuvoloso', 'pioggia' => '🌧️ Pioggia', 'misto' => '⛅ Misto'];
                                foreach ($meteo_opt as $val => $label):
                                    $sel = (($_POST['meteo'] ?? 'soleggiato') === $val) ? 'selected' : '';
                                ?>
                                    <option value="<?php echo $val; ?>" <?php echo $sel; ?>><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Temperatura (°C)</label>
                            <input type="number" name="temperatura" class="form-control"
                                   value="<?php echo htmlspecialchars($_POST['temperatura'] ?? ''); ?>"
                                   placeholder="es. 22" min="-20" max="60">
                        </div>
                        <div class="form-group">
                            <label>Mescola / Pneumatico</label>
                            <input type="text" name="mescola" class="form-control"
                                   value="<?php echo htmlspecialchars($_POST['mescola'] ?? ''); ?>"
                                   placeholder="es. Pirelli Soft, Bridgestone Rain...">
                        </div>
                    </div>

                    <!-- NOTE -->
                    <div class="form-group">
                        <label>Note (opzionale)</label>
                        <textarea name="note" class="form-control" rows="2"
                                  placeholder="Commenti sulla sessione, setup usato..."><?php echo htmlspecialchars($_POST['note'] ?? ''); ?></textarea>
                    </div>

                    <!-- PUBBLICO -->
                    <div class="form-group">
                        <div class="switch-row">
                            <label class="switch">
                                <input type="checkbox" name="pubblico" value="1" <?php echo (!isset($_POST['salva']) || isset($_POST['pubblico'])) ? 'checked' : ''; ?>>
                                <span class="slider"></span>
                            </label>
                            <span style="font-size:14px; color:#555;">Rendi questo tempo visibile nelle classifiche pubbliche</span>
                        </div>
                    </div>

                    <button type="submit" name="salva" class="btn btn-primary" style="margin-top:10px;">
                        💾 Salva Tempo
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <!-- LINK RAPIDI -->
        <div style="display:flex; gap:12px; flex-wrap:wrap; margin-top:8px;">
            <a href="i_miei_tempi.php" class="btn btn-secondary" style="text-decoration:none;">📋 Vedi i miei tempi</a>
            <a href="garage.php" class="btn btn-secondary" style="text-decoration:none;">🚗 Gestisci garage</a>
            <a href="proponi_circuito.php" class="btn btn-secondary" style="text-decoration:none;">🗺️ Proponi circuito</a>
        </div>

    </div>
</div>
</body>
</html>