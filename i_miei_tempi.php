<?php
session_start();
require_once 'db_config.php';
require_once 'includes/auth.php';

requireRole(['pilota', 'team_manager', 'admin']);

$username   = $_SESSION['username'];
$ruolo      = $_SESSION['ruolo'];
$utente_id  = $_SESSION['utente_id'];
$page_title = 'I miei tempi';

// Elimina tempo
if (isset($_POST['elimina_tempo'])) {
    $tid = (int) $_POST['tempo_id'];
    $del = $conn->prepare("DELETE FROM tempi WHERE id = ? AND utente_id = ?");
    $del->bind_param("ii", $tid, $utente_id);
    $del->execute();
}

// Filtri
$filtro_circuito = (int) ($_GET['circuito_id'] ?? 0);
$filtro_veicolo  = (int) ($_GET['veicolo_id'] ?? 0);
$filtro_sessione = $_GET['tipo_sessione'] ?? '';
$filtro_meteo    = $_GET['meteo'] ?? '';

// Circuiti usati dall'utente (per il filtro)
$stmt_circ = $conn->prepare("
    SELECT DISTINCT c.id, c.nome
    FROM circuiti c
    JOIN tempi t ON t.circuito_id = c.id
    WHERE t.utente_id = ?
    ORDER BY c.nome
");
$stmt_circ->bind_param("i", $utente_id);
$stmt_circ->execute();
$circuiti_usati = $stmt_circ->get_result();

// Veicoli usati
$stmt_veic = $conn->prepare("
    SELECT DISTINCT v.id, v.marca, v.modello
    FROM veicoli v
    JOIN tempi t ON t.veicolo_id = v.id
    WHERE t.utente_id = ?
    ORDER BY v.marca, v.modello
");
$stmt_veic->bind_param("i", $utente_id);
$stmt_veic->execute();
$veicoli_usati = $stmt_veic->get_result();

// Costruisci query con filtri
$where_parts = ["t.utente_id = ?"];
$params      = [$utente_id];
$types       = "i";

if ($filtro_circuito) {
    $where_parts[] = "t.circuito_id = ?";
    $params[]      = $filtro_circuito;
    $types        .= "i";
}
if ($filtro_veicolo) {
    $where_parts[] = "t.veicolo_id = ?";
    $params[]      = $filtro_veicolo;
    $types        .= "i";
}
if ($filtro_sessione) {
    $where_parts[] = "t.tipo_sessione = ?";
    $params[]      = $filtro_sessione;
    $types        .= "s";
}
if ($filtro_meteo) {
    $where_parts[] = "t.meteo = ?";
    $params[]      = $filtro_meteo;
    $types        .= "s";
}

$where = "WHERE " . implode(" AND ", $where_parts);

$query = "
    SELECT t.*,
           c.nome as circuito_nome, c.paese as circuito_paese,
           v.marca, v.modello, v.anno,
           cat.nome as categoria_nome
    FROM tempi t
    JOIN circuiti c ON c.id = t.circuito_id
    JOIN veicoli v ON v.id = t.veicolo_id
    JOIN categorie cat ON cat.id = v.categoria_id
    $where
    ORDER BY t.data_sessione DESC, t.tempo_ms ASC
";

$stmt_tempi = $conn->prepare($query);
$stmt_tempi->bind_param($types, ...$params);
$stmt_tempi->execute();
$tempi = $stmt_tempi->get_result();

// Stats rapide
$stmt_stats = $conn->prepare("
    SELECT
        COUNT(*) as totale,
        MIN(tempo_ms) as best_ms,
        COUNT(DISTINCT circuito_id) as n_circuiti
    FROM tempi
    WHERE utente_id = ?
");
$stmt_stats->bind_param("i", $utente_id);
$stmt_stats->execute();
$stats = $stmt_stats->get_result()->fetch_assoc();

function msToStr(int $ms): string {
    $min = floor($ms / 60000);
    $sec = floor(($ms % 60000) / 1000);
    $mil = $ms % 1000;
    return sprintf("%d:%02d.%03d", $min, $sec, $mil);
}

include 'includes/dashboard_head.php';
?>
<style>
    .meteo-icon { font-size:16px; }
    .best-time { color:#e94560; font-weight:700; }
    .tempo-row td { vertical-align:middle; }
    .tempo-actions form { display:inline; }
    .btn-del { background:none; border:none; cursor:pointer; color:#dc3545; font-size:16px; padding:4px 8px; border-radius:6px; transition:background 0.2s; }
    .btn-del:hover { background:#f8d7da; }
    .stats-mini { display:flex; gap:16px; margin-bottom:24px; flex-wrap:wrap; }
    .stat-mini { background:white; border-radius:10px; padding:14px 20px; box-shadow:0 2px 8px rgba(0,0,0,0.05); flex:1; min-width:120px; text-align:center; }
    .stat-mini .val { font-size:26px; font-weight:700; color:#e94560; }
    .stat-mini .lbl { font-size:12px; color:#999; text-transform:uppercase; margin-top:2px; }
</style>

<div class="dashboard-container">
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="top-bar">
            <h1 class="page-title">📋 I miei tempi</h1>
            <div class="user-info">
                <div class="user-avatar"><?php echo strtoupper(substr($username, 0, 1)); ?></div>
                <span><?php echo htmlspecialchars($username); ?></span>
                <a href="inserisci_tempo.php" class="btn btn-primary" style="padding:8px 16px; font-size:14px; text-decoration:none;">⏱️ + Nuovo tempo</a>
            </div>
        </div>

        <!-- STATS MINI -->
        <div class="stats-mini">
            <div class="stat-mini">
                <div class="val"><?php echo $stats['totale']; ?></div>
                <div class="lbl">Tempi totali</div>
            </div>
            <div class="stat-mini">
                <div class="val" style="font-size:20px;"><?php echo $stats['best_ms'] ? msToStr($stats['best_ms']) : '—'; ?></div>
                <div class="lbl">Miglior tempo assoluto</div>
            </div>
            <div class="stat-mini">
                <div class="val"><?php echo $stats['n_circuiti']; ?></div>
                <div class="lbl">Circuiti percorsi</div>
            </div>
        </div>

        <!-- FILTRI -->
        <div class="filters-bar">
            <form method="GET" style="display:contents;">
                <div class="filter-group">
                    <label>Circuito</label>
                    <select name="circuito_id" onchange="this.form.submit()">
                        <option value="">Tutti</option>
                        <?php while ($c = $circuiti_usati->fetch_assoc()): ?>
                            <option value="<?php echo $c['id']; ?>" <?php echo $filtro_circuito == $c['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($c['nome']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Veicolo</label>
                    <select name="veicolo_id" onchange="this.form.submit()">
                        <option value="">Tutti</option>
                        <?php while ($v = $veicoli_usati->fetch_assoc()): ?>
                            <option value="<?php echo $v['id']; ?>" <?php echo $filtro_veicolo == $v['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($v['marca'] . ' ' . $v['modello']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Sessione</label>
                    <select name="tipo_sessione" onchange="this.form.submit()">
                        <option value="">Tutte</option>
                        <option value="pratica"   <?php echo $filtro_sessione === 'pratica'   ? 'selected' : ''; ?>>🔧 Pratica</option>
                        <option value="qualifica" <?php echo $filtro_sessione === 'qualifica' ? 'selected' : ''; ?>>⚡ Qualifica</option>
                        <option value="gara"      <?php echo $filtro_sessione === 'gara'      ? 'selected' : ''; ?>>🏁 Gara</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Meteo</label>
                    <select name="meteo" onchange="this.form.submit()">
                        <option value="">Tutti</option>
                        <option value="soleggiato" <?php echo $filtro_meteo === 'soleggiato' ? 'selected' : ''; ?>>☀️ Soleggiato</option>
                        <option value="nuvoloso"   <?php echo $filtro_meteo === 'nuvoloso'   ? 'selected' : ''; ?>>☁️ Nuvoloso</option>
                        <option value="pioggia"    <?php echo $filtro_meteo === 'pioggia'    ? 'selected' : ''; ?>>🌧️ Pioggia</option>
                        <option value="misto"      <?php echo $filtro_meteo === 'misto'      ? 'selected' : ''; ?>>⛅ Misto</option>
                    </select>
                </div>
                <?php if ($filtro_circuito || $filtro_veicolo || $filtro_sessione || $filtro_meteo): ?>
                    <div class="filter-group" style="display:flex; align-items:flex-end;">
                        <a href="i_miei_tempi.php" style="color:#e94560; font-size:13px; font-weight:600;">✕ Rimuovi filtri</a>
                    </div>
                <?php endif; ?>
            </form>
        </div>

        <!-- TABELLA TEMPI -->
        <div class="card">
            <div class="card-body" style="padding:0;">
                <?php if ($tempi->num_rows === 0): ?>
                    <div style="padding:50px; text-align:center; color:#999;">
                        <?php if ($filtro_circuito || $filtro_veicolo || $filtro_sessione || $filtro_meteo): ?>
                            Nessun tempo trovato con questi filtri.
                        <?php else: ?>
                            Non hai ancora registrato nessun tempo.<br>
                            <a href="inserisci_tempo.php" style="color:#e94560; font-weight:600;">⏱️ Inserisci il tuo primo tempo</a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>

                <?php
                // Calcola il best_ms tra i risultati filtrati per evidenziarlo
                $all_rows = $tempi->fetch_all(MYSQLI_ASSOC);
                $best_filtered = min(array_column($all_rows, 'tempo_ms'));
                ?>

                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Tempo</th>
                                <th>Circuito</th>
                                <th>Veicolo</th>
                                <th>Sessione</th>
                                <th>Condizioni</th>
                                <th>Data</th>
                                <th>Vis.</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($all_rows as $i => $t): ?>
                            <tr class="tempo-row">
                                <td style="color:#999; font-size:13px;"><?php echo $i + 1; ?></td>
                                <td>
                                    <span class="<?php echo $t['tempo_ms'] === $best_filtered ? 'best-time' : ''; ?>">
                                        <?php echo msToStr($t['tempo_ms']); ?>
                                    </span>
                                    <?php if ($t['tempo_ms'] === $best_filtered): ?>
                                        <span style="font-size:11px; color:#e94560;"> 🏆</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($t['circuito_nome']); ?></strong>
                                    <div style="font-size:12px; color:#999;"><?php echo htmlspecialchars($t['circuito_paese']); ?></div>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($t['marca'] . ' ' . $t['modello']); ?>
                                    <div style="font-size:12px; color:#999;"><?php echo htmlspecialchars($t['categoria_nome']); ?></div>
                                </td>
                                <td>
                                    <?php echo match($t['tipo_sessione']) {
                                        'pratica'   => '🔧 Pratica',
                                        'qualifica' => '⚡ Qualifica',
                                        'gara'      => '🏁 Gara',
                                        default     => $t['tipo_sessione']
                                    }; ?>
                                </td>
                                <td>
                                    <?php
                                    $meteo_icon = match($t['meteo']) {
                                        'soleggiato' => '☀️', 'nuvoloso' => '☁️',
                                        'pioggia'    => '🌧️', 'misto'   => '⛅', default => ''
                                    };
                                    echo $meteo_icon;
                                    if ($t['temperatura']) echo ' ' . $t['temperatura'] . '°C';
                                    if ($t['mescola']) echo '<div style="font-size:12px;color:#999;">' . htmlspecialchars($t['mescola']) . '</div>';
                                    ?>
                                </td>
                                <td><?php echo date('d/m/Y', strtotime($t['data_sessione'])); ?></td>
                                <td>
                                    <?php echo $t['pubblico'] ? '<span title="Pubblico">🌐</span>' : '<span title="Privato" style="color:#ccc;">🔒</span>'; ?>
                                </td>
                                <td class="tempo-actions">
                                    <form method="POST" onsubmit="return confirm('Eliminare questo tempo?')">
                                        <input type="hidden" name="tempo_id" value="<?php echo $t['id']; ?>">
                                        <button type="submit" name="elimina_tempo" class="btn-del" title="Elimina">🗑️</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>
</body>
</html>