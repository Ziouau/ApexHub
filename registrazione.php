<?php
require_once 'db_config.php';

$msg = '';
$msg_type = '';

function validaPassword(string $pass): array {
    $errori = [];

    if (strlen($pass) < 8)
        $errori[] = "almeno 8 caratteri";
    if (!preg_match('/[A-Z]/', $pass))
        $errori[] = "almeno una lettera maiuscola";
    if (!preg_match('/[a-z]/', $pass))
        $errori[] = "almeno una lettera minuscola";
    if (!preg_match('/[0-9]/', $pass))
        $errori[] = "almeno un numero";
    if (!preg_match('/[\W_]/', $pass))
        $errori[] = "almeno un carattere speciale (!@#$%^&*...)";

    return $errori;
}

if (isset($_POST['register'])) {
    $user         = trim($_POST['username']);
    $email        = trim($_POST['email']);
    $pass         = $_POST['password'];
    $pass_confirm = $_POST['password_confirm'];

    if ($pass !== $pass_confirm) {
        $msg      = "Le password non coincidono!";
        $msg_type = "error";
    } else {
        $errori_pass = validaPassword($pass);
        if (!empty($errori_pass)) {
            $msg      = "La password non è abbastanza sicura. Deve contenere: " . implode(', ', $errori_pass) . ".";
            $msg_type = "error";
        } else {
            $stmt_check = $conn->prepare("SELECT id FROM utenti WHERE username = ? OR email = ?");
            $stmt_check->bind_param("ss", $user, $email);
            $stmt_check->execute();
            $stmt_check->store_result();

            if ($stmt_check->num_rows > 0) {
                $msg      = "Username o Email già registrati!";
                $msg_type = "error";
            } else {
                $pass_hash = password_hash($pass, PASSWORD_DEFAULT);

                $stmt = $conn->prepare("INSERT INTO utenti (username, email, password, attivo, tentativi_falliti, account_bloccato) VALUES (?, ?, ?, 0, 0, 0)");
                $stmt->bind_param("sss", $user, $email, $pass_hash);

                if ($stmt->execute()) {
                    $codice   = bin2hex(random_bytes(4));
                    $scadenza = date('Y-m-d H:i:s', strtotime('+24 hours'));

                    $update = $conn->prepare("UPDATE utenti SET codice_verifica = ?, data_scadenza_codice = ? WHERE email = ?");
                    $update->bind_param("sss", $codice, $scadenza, $email);
                    $update->execute();

                    $subject  = "ApexHub - Codice di verifica OTP";
                    $headers  = "MIME-Version: 1.0\r\n";
                    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
                    $headers .= "From: ApexHub <noreply@apexhub.com>\r\n";

                    $message = "
                    <html>
                    <body style='font-family: Arial, sans-serif;'>
                        <h2 style='color: #e94560;'>Benvenuto su ApexHub!</h2>
                        <p>Ciao <strong>$user</strong>,</p>
                        <p>Il tuo codice di verifica è:</p>
                        <h1 style='background: #f0f0f0; padding: 15px; text-align: center; letter-spacing: 5px;'>$codice</h1>
                        <p>Valido per 24 ore.</p>
                        <p><a href='http://" . $_SERVER['HTTP_HOST'] . "/verifica_codice.php?email=" . urlencode($email) . "'>Clicca qui per verificare il tuo account</a></p>
                    </body>
                    </html>
                    ";

                    mail($email, $subject, $message, $headers);

                    $msg      = "Registrazione completata! Ti abbiamo inviato un'email con il codice OTP a <strong>$email</strong><br><small>Controlla anche nello spam</small>";
                    $msg_type = "success";
                } else {
                    $msg      = "Errore durante la registrazione.";
                    $msg_type = "error";
                }
                $stmt->close();
            }
            $stmt_check->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ApexHub - Registrazione</title>
    <link rel="stylesheet" href="assets/css/apexhub.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <div class="auth-logo">🏁</div>
                <h2>ApexHub</h2>
                <p>Registrati per iniziare</p>
            </div>
            <div class="auth-body">
                <?php if ($msg): ?>
                    <div class="alert alert-<?php echo $msg_type; ?>"><?php echo $msg; ?></div>
                <?php endif; ?>
                <form method="POST">
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" name="username" class="form-control"
                               value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" class="form-control"
                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" class="form-control" required>
                        <small style="color:#999;">Min. 8 caratteri, maiuscola, minuscola, numero e carattere speciale.</small>
                    </div>
                    <div class="form-group">
                        <label>Conferma Password</label>
                        <input type="password" name="password_confirm" class="form-control" required>
                    </div>
                    <button type="submit" name="register" class="btn btn-primary">Registrati</button>
                </form>
            </div>
            <div class="auth-footer">
                <div class="links">
                    <a href="login.php">Hai già un account? Accedi</a>
                    <a href="verifica_codice.php">Verifica Codice</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>