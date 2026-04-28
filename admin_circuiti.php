<?php
session_start();
require_once 'db_config.php';
require_once 'includes/auth.php';

requireRole('admin');

$username   = $_SESSION['username'];
$ruolo      = $_SESSION['ruolo'];
$page_title = 'Approva Circuiti';

$msg = '';
$msg_type = '';

// Azione approvazione / rifiuto
if (isset($_POST['azione'], $_POST['circuito_id'])) {
    $azione      = $_POST['azione'];
    $circuito_id = (int) $_POST['circuito_id'];

    if (in_array($azione, ['approvato', 'rifiutato'])) {
        $stmt = $conn->prepare("UPDATE circuiti SET stato = ? WHERE id = ?");
        $stmt->bind_param("si", $azione, $circuito_id);
        if ($stmt->execute()) {
            $label    = $azione === 'approvato' ? '✅ approvato' : '❌ rifiutato';
            $msg      = "Circuito $label con successo.";
            $msg_type = $azione === 'approvato' ? 'success' : 'warning';
        }
    }
}

// Filtro stato
$filtro = $_GET['filtro'] ?? 'in_attesa';
$stati_validi = ['in_attesa', 'approvato', 'rifiutato', 'tutti'];
if (!in_array($filtro, $stati_validi)) $filtro = 'in_attesa';

$where = $filtro !== 'tutti' ? "WHERE c.stato = '$filtro'" : '';

$query = "
    SELECT c.*, u.username as proposto_da_username
    FROM circuiti c
    JOIN utenti u ON u.id = c.proposto_da
    $where
    ORDER BY c.creato_il DESC
";
$risultati = $conn->query($query);

// Conteggi per tab
$conteggi = [];
foreach (['in_attesa', 'approvato', 'rifiutato'] as $s) {
    $r = $conn->query("SELECT COUNT(*) as t FROM circuiti WHERE stato = '$s'");
    $conteggi[$s] = $r->fetch_assoc()['t'];
}

include 'includes/dashboard_head.php';
?>
<style>
    .tab-bar { display:flex; gap:8px; margin-bottom:24px; flex-wrap:wrap; }
    .tab-btn { padding:8px 18px; border-radius:20px; font-size:13px; font-weight:600; text-decoration:none; border:2px solid transparent; transition:all 0.2s; }
    .tab-btn.attivo { background:#e94560; color:white; border-color:#e94560; }
    .tab-btn:not(.attivo) { background:white; color:#555; border-color:#ddd; }
    .tab-btn:not(.attivo):hover { border-color:#e94560; color:#e94560; }
    .count-pill { display:inline-block; background:rgba(255,255,255,0.25); border-radius:20px; padding:1px 7px; font-size:11px; margin-left:4px; }
    .tab-btn:not(.attivo) .count-pill { background:#f0f0f0; color:#999; }
    .action-btns { display:flex; gap:8px; }
    .btn-approva { background:#28a745; color:white; padding:6px 14px; border:none; border-radius:8px; cursor:pointer; font-size:13px; font-weight:600; }
    .btn-approva:hover { background:#218838; }
    .btn-rifiuta { background:#dc3545; color:white; padding:6px 14px; border:none; border-radius:8px; cursor:pointer; font-size:13px; font-weight:600; }
    .btn-rifiuta:hover { background:#c82333; }
    .circuito-note { font-size:12px; color:#999; margin-top:3px; }
</style>

<div class="dashboard-container">
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="top-bar">
            <h1 class="page-title">✅ Approva Circuiti</h1>
            <div class="user-info">
                <div class="user-avatar"><?php echo strtoupper(substr($username, 0, 1)); ?></div>
                <span><?php echo htmlspecialchars($username); ?></span>
                <span class="admin-badge-pill">⚙️ ADMIN</span>
            </div>
        </div>

        <?php if ($msg): ?>
            <div class="alert alert-<?php echo $msg_type; ?>"><?php echo $msg; ?></div>
        <?php endif; ?>

        <!-- TAB FILTRI -->
        <div class="tab-bar">
            <a href="?filtro=in_attesa" class="tab-btn <?php echo $filtro === 'in_attesa' ? 'attivo' : ''; ?>">
                ⏳ In attesa <span class="count-pill"><?php echo $conteggi['in_attesa']; ?></span>
            </a>
            <a href="?filtro=approvato" class="tab-btn <?php echo $filtro === 'approvato' ? 'attivo' : ''; ?>">
                ✅ Approvati <span class="count-pill"><?php echo $conteggi['approvato']; ?></span>
            </a>
            <a href="?filtro=rifiutato" class="tab-btn <?php echo $filtro === 'rifiutato' ? 'attivo' : ''; ?>">
                ❌ Rifiutati <span class="count-pill"><?php echo $conteggi['rifiutato']; ?></span>
            </a>
            <a href="?filtro=tutti" class="tab-btn <?php echo $filtro === 'tutti' ? 'attivo' : ''; ?>">
                📋 Tutti
            </a>
        </div>

        <div class="card">
            <div class="card-body" style="padding:0;">
                <?php if ($risultati->num_rows === 0): ?>
                    <div style="padding:40px; text-align:center; color:#999;">
                        Nessun circuito in questa categoria.
                    </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Circuito</th>
                                <th>Proposto da</th>
                                <th>Paese / Città</th>
                                <th>Lunghezza</th>
                                <th>Stato</th>
                                <th>Data</th>
                                <?php if ($filtro === 'in_attesa' || $filtro === 'tutti'): ?>
                                    <th>Azioni</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                        <?php while ($c = $risultati->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($c['nome']); ?></strong>
                                    <?php if ($c['configurazione']): ?>
                                        <div class="circuito-note"><?php echo htmlspecialchars($c['configurazione']); ?></div>
                                    <?php endif; ?>
                                    <?php if ($c['note']): ?>
                                        <div class="circuito-note" style="font-style:italic;"><?php echo htmlspecialchars(substr($c['note'], 0, 80)) . (strlen($c['note']) > 80 ? '...' : ''); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($c['proposto_da_username']); ?></td>
                                <td><?php echo htmlspecialchars($c['paese']); ?><?php echo $c['citta'] ? '<br><small style="color:#999;">' . htmlspecialchars($c['citta']) . '</small>' : ''; ?></td>
                                <td><?php echo $c['lunghezza_mt'] ? number_format($c['lunghezza_mt']) . ' m' : '—'; ?></td>
                                <td>
                                    <?php echo match($c['stato']) {
                                        'approvato' => '<span class="badge badge-success">✅ Approvato</span>',
                                        'in_attesa' => '<span class="badge badge-warning">⏳ In attesa</span>',
                                        'rifiutato' => '<span class="badge badge-danger">❌ Rifiutato</span>',
                                        default     => $c['stato']
                                    }; ?>
                                </td>
                                <td><?php echo date('d/m/Y', strtotime($c['creato_il'])); ?></td>
                                <?php if ($filtro === 'in_attesa' || $filtro === 'tutti'): ?>
                                <td>
                                    <?php if ($c['stato'] === 'in_attesa'): ?>
                                    <div class="action-btns">
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="circuito_id" value="<?php echo $c['id']; ?>">
                                            <input type="hidden" name="azione" value="approvato">
                                            <button type="submit" class="btn-approva">✅ Approva</button>
                                        </form>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="circuito_id" value="<?php echo $c['id']; ?>">
                                            <input type="hidden" name="azione" value="rifiutato">
                                            <button type="submit" class="btn-rifiuta">❌ Rifiuta</button>
                                        </form>
                                    </div>
                                    <?php else: ?>
                                        <span style="color:#ccc; font-size:12px;">—</span>
                                    <?php endif; ?>
                                </td>
                                <?php endif; ?>
                            </tr>
                        <?php endwhile; ?>
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