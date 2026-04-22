<?php
session_start();
require_once 'db_config.php';
$msg = '';
$msg_type = '';

$email_prefill = isset($_GET['email']) ? urldecode($_GET['email']) : '';

if (isset($_POST['verifica'])) {
    $email = $_POST['email'];
    $codice = $_POST['codice'];

    $stmt = $conn->prepare("SELECT id FROM utenti WHERE email = ? AND codice_verifica = ? AND data_scadenza_codice > NOW() AND attivo = 0");
    $stmt->bind_param("ss", $email, $codice);
    $stmt->execute();
    $risultato = $stmt->get_result();

    if ($risultato->num_rows > 0) {
        $update = $conn->prepare("UPDATE utenti SET attivo = 1, codice_verifica = NULL, data_scadenza_codice = NULL WHERE email = ?");
        $update->bind_param("s", $email);
        $update->execute();
        $msg = "Account attivato con successo! <a href='login.php'>Accedi ora</a>";
        $msg_type = "success";
    } else {
        $msg = "Codice non valido, scaduto o già usato.";
        $msg_type = "error";
    }
    $stmt->close();
}

if (isset($_POST['richiedi_nuovo_codice'])) {
    $email = $_POST['email'];
    
    $stmt = $conn->prepare("SELECT id, username FROM utenti WHERE email = ? AND attivo = 0");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $risultato = $stmt->get_result();
    
    if ($utente = $risultato->fetch_assoc()) {
        $nuovo_codice = bin2hex(random_bytes(4));
        $scadenza = date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        $update = $conn->prepare("UPDATE utenti SET codice_verifica = ?, data_scadenza_codice = ? WHERE email = ?");
        $update->bind_param("sss", $nuovo_codice, $scadenza, $email);
        $update->execute();
        
        $subject = "ApexHub - Nuovo codice di verifica";
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8" . "\r\n";
        $headers .= "From: ApexHub <noreply@apexhub.com>" . "\r\n";
        
        $message = "<html><body><h2>Nuovo codice: <strong>$nuovo_codice</strong></h2><p>Valido 24 ore.</p></body></html>";
        mail($email, $subject, $message, $headers);
        
        $msg = "📧 Nuovo codice inviato all'email <strong>$email</strong>";
        $msg_type = "success";
    } else {
        $msg = "Email non trovata o account già attivo.";
        $msg_type = "error";
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ApexHub - Verifica Email</title>
    <link rel="stylesheet" href="assets/css/apexhub.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <div class="auth-logo"></div>
                <h2>Verifica Account</h2>
                <p>Inserisci il codice OTP ricevuto via email</p>
            </div>
            <div class="auth-body">
                <?php if($msg): ?>
                    <div class="alert alert-<?php echo $msg_type; ?>"><?php echo $msg; ?></div>
                <?php endif; ?>
                <form method="POST">
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($email_prefill); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Codice OTP</label>
                        <input type="text" name="codice" class="form-control" placeholder="Inserisci il codice" maxlength="8" required>
                    </div>
                    <button type="submit" name="verifica" class="btn btn-primary">Verifica Account</button>
                </form>
                <hr style="margin: 20px 0;">
                <form method="POST">
                    <div class="form-group">
                        <label>Email per nuovo codice</label>
                        <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($email_prefill); ?>" required>
                    </div>
                    <button type="submit" name="richiedi_nuovo_codice" class="btn btn-secondary">Richiedi nuovo codice</button>
                </form>
            </div>
            <div class="auth-footer">
                <div class="links">
                    <a href="registrazione.php">Registrati</a>
                    <a href="login.php">Login</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>