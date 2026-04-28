<?php
session_start();
require_once 'db_config.php';

// Se non loggato → login
if (!isset($_SESSION['utente_id'])) {
    header("Location: login.php");
    exit();
}

// Se ha già un ruolo → dashboard
if (isset($_SESSION['ruolo']) && $_SESSION['ruolo'] !== null) {
    header("Location: dashboard.php");
    exit();
}

$msg = '';
$ruoli_validi = ['pilota', 'team_manager', 'appassionato'];

if (isset($_POST['scegli_ruolo'])) {
    $ruolo_scelto = $_POST['ruolo'] ?? '';

    if (!in_array($ruolo_scelto, $ruoli_validi)) {
        $msg = "Selezione non valida.";
    } else {
        $stmt = $conn->prepare("UPDATE utenti SET ruolo = ?, onboarding_completato = 1 WHERE id = ?");
        $stmt->bind_param("si", $ruolo_scelto, $_SESSION['utente_id']);
        if ($stmt->execute()) {
            $_SESSION['ruolo'] = $ruolo_scelto;
            header("Location: dashboard.php");
            exit();
        } else {
            $msg = "Errore nel salvataggio. Riprova.";
        }
    }
}

$username = $_SESSION['username'];
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ApexHub - Chi sei?</title>
    <link rel="stylesheet" href="assets/css/apexhub.css">
    <style>
        .onboarding-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
        }

        .onboarding-box {
            width: 100%;
            max-width: 760px;
        }

        .onboarding-header {
            text-align: center;
            margin-bottom: 48px;
        }

        .onboarding-header h1 {
            font-size: 32px;
            color: #fff;
            margin-bottom: 10px;
            font-weight: 700;
        }

        .onboarding-header p {
            color: rgba(255,255,255,0.6);
            font-size: 16px;
        }

        .onboarding-header .username-highlight {
            color: #e94560;
            font-weight: 600;
        }

        .ruoli-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 32px;
        }

        @media (max-width: 600px) {
            .ruoli-grid { grid-template-columns: 1fr; }
        }

        .ruolo-card {
            background: rgba(255,255,255,0.04);
            border: 2px solid rgba(255,255,255,0.08);
            border-radius: 16px;
            padding: 32px 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.25s ease;
            position: relative;
        }

        .ruolo-card:hover {
            border-color: #e94560;
            background: rgba(233,69,96,0.08);
            transform: translateY(-4px);
        }

        .ruolo-card.selezionato {
            border-color: #e94560;
            background: rgba(233,69,96,0.12);
            box-shadow: 0 0 0 3px rgba(233,69,96,0.2);
        }

        .ruolo-card .check {
            display: none;
            position: absolute;
            top: 14px;
            right: 14px;
            width: 22px;
            height: 22px;
            background: #e94560;
            border-radius: 50%;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            color: white;
        }

        .ruolo-card.selezionato .check {
            display: flex;
        }

        .ruolo-icon {
            font-size: 48px;
            margin-bottom: 16px;
            display: block;
        }

        .ruolo-nome {
            font-size: 18px;
            font-weight: 700;
            color: #fff;
            margin-bottom: 10px;
        }

        .ruolo-desc {
            font-size: 13px;
            color: rgba(255,255,255,0.5);
            line-height: 1.5;
        }

        .permessi-list {
            margin-top: 14px;
            text-align: left;
            font-size: 12px;
            color: rgba(255,255,255,0.4);
        }

        .permessi-list li {
            list-style: none;
            padding: 3px 0;
        }

        .permessi-list li::before {
            content: "✓ ";
            color: #e94560;
        }

        .btn-conferma {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #e94560 0%, #c73e56 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 17px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            opacity: 0.4;
            pointer-events: none;
        }

        .btn-conferma.attivo {
            opacity: 1;
            pointer-events: auto;
        }

        .btn-conferma.attivo:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(233,69,96,0.35);
        }

        .msg-errore {
            background: rgba(220,53,69,0.15);
            border: 1px solid rgba(220,53,69,0.4);
            color: #ff6b7a;
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="onboarding-container">
        <div class="onboarding-box">
            <div class="onboarding-header">
                <div style="font-size:40px; margin-bottom:16px;">🏁</div>
                <h1>Benvenuto su ApexHub, <span class="username-highlight"><?php echo htmlspecialchars($username); ?></span>!</h1>
                <p>Prima di entrare, dicci come utilizzerai la piattaforma. Potrai cambiarlo in seguito.</p>
            </div>

            <?php if ($msg): ?>
                <div class="msg-errore"><?php echo htmlspecialchars($msg); ?></div>
            <?php endif; ?>

            <form method="POST" id="onboardingForm">
                <input type="hidden" name="ruolo" id="ruoloInput" value="">

                <div class="ruoli-grid">

                    <div class="ruolo-card" data-ruolo="pilota" onclick="seleziona(this)">
                        <div class="check">✓</div>
                        <span class="ruolo-icon">🏎️</span>
                        <div class="ruolo-nome">Pilota</div>
                        <div class="ruolo-desc">Corri e vuoi tracciare le tue performance sul giro.</div>
                        <ul class="permessi-list">
                            <li>Carica tempi sul giro</li>
                            <li>Gestisci il tuo garage</li>
                            <li>Proponi circuiti</li>
                            <li>Vedi classifiche</li>
                        </ul>
                    </div>

                    <div class="ruolo-card" data-ruolo="team_manager" onclick="seleziona(this)">
                        <div class="check">✓</div>
                        <span class="ruolo-icon">🧑‍💼</span>
                        <div class="ruolo-nome">Team Manager</div>
                        <div class="ruolo-desc">Gestisci un piccolo team e monitora i tuoi piloti.</div>
                        <ul class="permessi-list">
                            <li>Tutto del Pilota</li>
                            <li>Gestisci piloti del team</li>
                            <li>Dashboard team</li>
                        </ul>
                    </div>

                    <div class="ruolo-card" data-ruolo="appassionato" onclick="seleziona(this)">
                        <div class="check">✓</div>
                        <span class="ruolo-icon">👀</span>
                        <div class="ruolo-nome">Appassionato</div>
                        <div class="ruolo-desc">Segui i tempi, le classifiche e i setup senza gareggiare.</div>
                        <ul class="permessi-list">
                            <li>Vedi classifiche</li>
                            <li>Esplora circuiti</li>
                            <li>Consulta setup pubblici</li>
                        </ul>
                    </div>

                </div>

                <button type="submit" name="scegli_ruolo" class="btn-conferma" id="btnConferma">
                    Entra su ApexHub →
                </button>
            </form>
        </div>
    </div>

    <script>
        function seleziona(card) {
            // Deseleziona tutte
            document.querySelectorAll('.ruolo-card').forEach(c => c.classList.remove('selezionato'));
            // Seleziona questa
            card.classList.add('selezionato');
            // Aggiorna input hidden
            document.getElementById('ruoloInput').value = card.dataset.ruolo;
            // Attiva bottone
            document.getElementById('btnConferma').classList.add('attivo');
        }
    </script>
</body>
</html>