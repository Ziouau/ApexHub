<?php
session_start();
require_once 'db_config.php';

$msg = '';
$msg_type = '';

if (isset($_POST['login'])) {
    $email = $_POST['email'];
    $pass_inserita = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, username, password, tentativi_falliti, account_bloccato, attivo FROM utenti WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $risultato = $stmt->get_result();

    if ($utente = $risultato->fetch_assoc()) {
        if ($utente['account_bloccato'] == 1) {
            $msg = "Account bloccato per troppi tentativi falliti.";
            $msg_type = "error";
        } elseif ($utente['attivo'] == 0) {
            $msg = "Account non attivato. Controlla la tua email per il codice OTP.";
            $msg_type = "error";
        } elseif (password_verify($pass_inserita, $utente['password'])) {
            $conn->query("UPDATE utenti SET tentativi_falliti = 0 WHERE id = " . $utente['id']);
            $_SESSION['utente_id'] = $utente['id'];
            $_SESSION['username'] = $utente['username'];
            $_SESSION['email'] = $email;
            header("Location: dashboard.php");
            exit();
        } else {
            $nuovi_tentativi = $utente['tentativi_falliti'] + 1;
            $blocco = ($nuovi_tentativi >= 3) ? 1 : 0;
            $upd = $conn->prepare("UPDATE utenti SET tentativi_falliti = ?, account_bloccato = ? WHERE id = ?");
            $upd->bind_param("iii", $nuovi_tentativi, $blocco, $utente['id']);
            $upd->execute();
            $msg = "Password errata. Tentativi: $nuovi_tentativi/3";
            $msg_type = "error";
        }
    } else {
        $msg = "Email non trovata.";
        $msg_type = "error";
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ApexHub - Login</title>
    <link rel="stylesheet" href="assets/css/apexhub.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <div class="auth-logo">🏁</div>
                <h2>ApexHub</h2>
                <p>Accedi al tuo account</p>
            </div>
            <div class="auth-body">
                <?php if($msg): ?>
                    <div class="alert alert-<?php echo $msg_type; ?>"><?php echo $msg; ?></div>
                <?php endif; ?>
                <form method="POST">
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <button type="submit" name="login" class="btn btn-primary">Accedi</button>
                </form>
            </div>
            <div class="auth-footer">
                <div class="links">
                    <a href="registrazione.php"> Registrati</a>
                    <a href="verifica_codice.php" >Verifica Codice</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>