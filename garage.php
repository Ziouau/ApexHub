<?php
session_start();
require_once 'db_config.php';
require_once 'includes/auth.php';

requireRole(['pilota', 'team_manager', 'admin']);

$username   = $_SESSION['username'];
$ruolo      = $_SESSION['ruolo'];
$utente_id  = $_SESSION['utente_id'];
$page_title = 'Il mio Garage';

$msg = '';
$msg_type = '';

// Elimina veicolo (solo se appartiene all'utente e non ha tempi associati)
if (isset($_POST['elimina_veicolo'])) {
    $vid = (int) $_POST['veicolo_id'];
    // Controlla che il veicolo appartenga all'utente
    $check = $conn->prepare("SELECT id FROM veicoli WHERE id = ? AND utente_id = ?");
    $check->bind_param("ii", $vid, $utente_id);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        // Controlla se ha tempi associati
        $tempi_check = $conn->prepare("SELECT COUNT(*) as t FROM tempi WHERE veicolo_id = ?");
        $tempi_check->bind_param("i", $vid);
        $tempi_check->execute();
        $n_tempi = $tempi_check->get_result()->fetch_assoc()['t'];
        if ($n_tempi > 0) {
            $msg      = "Non puoi eliminare questo veicolo: ha $n_tempi tempo/i registrati.";
            $msg_type = "error";
        } else {
            $del = $conn->prepare("DELETE FROM veicoli WHERE id = ? AND utente_id = ?");
            $del->bind_param("ii", $vid, $utente_id);
            $del->execute();
            $msg      = "✅ Veicolo eliminato.";
            $msg_type = "success";
        }
    }
}

// Aggiungi veicolo
if (isset($_POST['aggiungi'])) {
    $categoria_id = (int) $_POST['categoria_id'];
    $marca        = trim($_POST['marca']);
    $modello      = trim($_POST['modello']);
    $anno         = (int) $_POST['anno'];
    $note         = trim($_POST['note']);

    if (empty($marca) || empty($modello) || !$categoria_id) {
        $msg      = "Categoria, marca e modello sono obbligatori.";
        $msg_type = "error";
    } else {
        $stmt = $conn->prepare("INSERT INTO veicoli (utente_id, categoria_id, marca, modello, anno, note) VALUES (?, ?, ?, ?, ?, ?)");
        $anno_val = $anno ?: null;
        $stmt->bind_param("iissss", $utente_id, $categoria_id, $marca, $modello, $anno_val, $note);
        if ($stmt->execute()) {
            $msg      = "✅ <strong>" . htmlspecialchars("$marca $modello") . "</strong> aggiunto al garage!";
            $msg_type = "success";
            $_POST    = [];
        } else {
            $msg      = "Errore durante l'inserimento.";
            $msg_type = "error";
        }
    }
}

// Categorie per il select
$categorie = $conn->query("SELECT id, nome FROM categorie ORDER BY nome");

// I miei veicoli
$stmt_veicoli = $conn->prepare("
    SELECT v.*, c.nome as categoria_nome,
           (SELECT COUNT(*) FROM tempi t WHERE t.veicolo_id = v.id) as n_tempi
    FROM veicoli v
    JOIN categorie c ON c.id = v.categoria_id
    WHERE v.utente_id = ?
    ORDER BY v.creato_il DESC
");
$stmt_veicoli->bind_param("i", $utente_id);
$stmt_veicoli->execute();
$miei_veicoli = $stmt_veicoli->get_result();

include 'includes/dashboard_head.php';
?>
<style>
    .garage-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(280px, 1fr)); gap:20px; margin-bottom:30px; }
    .veicolo-card { background:white; border-radius:14px; box-shadow:0 2px 12px rgba(0,0,0,0.07); overflow:hidden; transition:transform 0.2s; }
    .veicolo-card:hover { transform:translateY(-3px); }
    .veicolo-card-top { background:linear-gradient(135deg,#1a1a2e,#16213e); padding:20px; color:white; }
    .veicolo-categoria { font-size:11px; text-transform:uppercase; letter-spacing:1px; color:#e94560; font-weight:700; margin-bottom:6px; }
    .veicolo-nome { font-size:20px; font-weight:700; }
    .veicolo-anno { font-size:13px; color:rgba(255,255,255,0.5); margin-top:4px; }
    .veicolo-card-bottom { padding:16px 20px; display:flex; justify-content:space-between; align-items:center; }
    .veicolo-tempi { font-size:13px; color:#666; }
    .veicolo-tempi strong { color:#e94560; }
    .btn-elimina { background:none; border:1px solid #dc3545; color:#dc3545; padding:5px 12px; border-radius:8px; font-size:12px; cursor:pointer; transition:all 0.2s; }
    .btn-elimina:hover { background:#dc3545; color:white; }
    .empty-garage { text-align:center; padding:50px 20px; color:#999; }
    .empty-garage .icon { font-size:64px; margin-bottom:16px; }
</style>

<div class="dashboard-container">
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="top-bar">
            <h1 class="page-title">🚗 Il mio Garage</h1>
            <div class="user-info">
                <div class="user-avatar"><?php echo strtoupper(substr($username, 0, 1)); ?></div>
                <span><?php echo htmlspecialchars($username); ?></span>
            </div>
        </div>

        <?php if ($msg): ?>
            <div class="alert alert-<?php echo $msg_type; ?>"><?php echo $msg; ?></div>
        <?php endif; ?>

        <!-- GRIGLIA VEICOLI -->
        <?php if ($miei_veicoli->num_rows > 0): ?>
            <div class="garage-grid">
            <?php while ($v = $miei_veicoli->fetch_assoc()): ?>
                <div class="veicolo-card">
                    <div class="veicolo-card-top">
                        <div class="veicolo-categoria"><?php echo htmlspecialchars($v['categoria_nome']); ?></div>
                        <div class="veicolo-nome"><?php echo htmlspecialchars($v['marca'] . ' ' . $v['modello']); ?></div>
                        <div class="veicolo-anno"><?php echo $v['anno'] ?: 'Anno non specificato'; ?></div>
                        <?php if ($v['note']): ?>
                            <div style="font-size:12px; color:rgba(255,255,255,0.4); margin-top:8px;"><?php echo htmlspecialchars($v['note']); ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="veicolo-card-bottom">
                        <div class="veicolo-tempi">
                            <strong><?php echo $v['n_tempi']; ?></strong> tempo<?php echo $v['n_tempi'] != 1 ? 'i' : ''; ?> registrati
                        </div>
                        <?php if ($v['n_tempi'] == 0): ?>
                        <form method="POST" onsubmit="return confirm('Eliminare questo veicolo?')">
                            <input type="hidden" name="veicolo_id" value="<?php echo $v['id']; ?>">
                            <button type="submit" name="elimina_veicolo" class="btn-elimina">🗑️ Elimina</button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="empty-garage">
                <div class="icon">🚗</div>
                <p>Il tuo garage è vuoto.<br>Aggiungi il tuo primo veicolo qui sotto.</p>
            </div>
        <?php endif; ?>

        <!-- FORM AGGIUNGI VEICOLO -->
        <div class="card">
            <div class="card-header">
                <h3>➕ Aggiungi Veicolo</h3>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Categoria *</label>
                            <select name="categoria_id" class="form-control" required>
                                <option value="">-- Seleziona --</option>
                                <?php
                                $categorie->data_seek(0);
                                while ($cat = $categorie->fetch_assoc()):
                                    $sel = (isset($_POST['categoria_id']) && $_POST['categoria_id'] == $cat['id']) ? 'selected' : '';
                                ?>
                                    <option value="<?php echo $cat['id']; ?>" <?php echo $sel; ?>><?php echo htmlspecialchars($cat['nome']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Anno</label>
                            <input type="number" name="anno" class="form-control"
                                   value="<?php echo htmlspecialchars($_POST['anno'] ?? ''); ?>"
                                   placeholder="es. 2022" min="1950" max="<?php echo date('Y'); ?>">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Marca *</label>
                            <input type="text" name="marca" class="form-control"
                                   value="<?php echo htmlspecialchars($_POST['marca'] ?? ''); ?>"
                                   placeholder="es. Ferrari, Tony Kart..." required>
                        </div>
                        <div class="form-group">
                            <label>Modello *</label>
                            <input type="text" name="modello" class="form-control"
                                   value="<?php echo htmlspecialchars($_POST['modello'] ?? ''); ?>"
                                   placeholder="es. 488 GT3, Racer EV30..." required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Note (opzionale)</label>
                        <input type="text" name="note" class="form-control"
                               value="<?php echo htmlspecialchars($_POST['note'] ?? ''); ?>"
                               placeholder="es. Preparazione specifica, numero di gara...">
                    </div>
                    <button type="submit" name="aggiungi" class="btn btn-primary">🚗 Aggiungi al Garage</button>
                </form>
            </div>
        </div>

    </div>
</div>
</body>
</html>