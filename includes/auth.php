<?php
// includes/auth.php
// Da includere in OGNI pagina protetta dopo session_start()

/**
 * Redirige al login se l'utente non è autenticato.
 * Se autenticato ma senza ruolo → onboarding.
 */
function requireLogin(): void {
    if (!isset($_SESSION['utente_id'])) {
        header("Location: /login.php");
        exit();
    }
    // Ruolo non ancora scelto → onboarding (eccetto se siamo già lì)
    $pagina_corrente = basename($_SERVER['PHP_SELF']);
    $escluse = ['onboarding.php', 'logout.php'];
    if (!isset($_SESSION['ruolo']) && !in_array($pagina_corrente, $escluse)) {
        header("Location: /onboarding.php");
        exit();
    }
}

/**
 * Richiede un ruolo specifico (o array di ruoli ammessi).
 * Es: requireRole('admin')  oppure  requireRole(['pilota','team_manager'])
 */
function requireRole(string|array $ruoli_ammessi): void {
    requireLogin();
    $ruolo_utente = $_SESSION['ruolo'] ?? null;
    $ammessi = is_array($ruoli_ammessi) ? $ruoli_ammessi : [$ruoli_ammessi];
    if (!in_array($ruolo_utente, $ammessi)) {
        http_response_code(403);
        header("Location: /dashboard.php?errore=403");
        exit();
    }
}

/**
 * Controlla se l'utente corrente ha un determinato permesso.
 * Restituisce bool, utile per mostrare/nascondere elementi UI.
 */
function hasPermesso(string $azione): bool {
    $ruolo = $_SESSION['ruolo'] ?? null;

    $matrice = [
        // Classifiche e dati pubblici
        'vedi_classifiche'      => ['appassionato', 'pilota', 'team_manager', 'admin'],
        'vedi_circuiti'         => ['appassionato', 'pilota', 'team_manager', 'admin'],

        // Azioni pilota
        'aggiungi_veicolo'      => ['pilota', 'team_manager', 'admin'],
        'inserisci_tempo'       => ['pilota', 'team_manager', 'admin'],
        'proponi_circuito'      => ['pilota', 'team_manager', 'admin'],

        // Team Manager
        'gestisci_piloti_team'  => ['team_manager', 'admin'],

        // Admin only
        'approva_circuiti'      => ['admin'],
        'gestisci_utenti'       => ['admin'],
        'pannello_admin'        => ['admin'],
    ];

    if (!isset($matrice[$azione])) return false;
    return in_array($ruolo, $matrice[$azione]);
}

/**
 * Restituisce il label leggibile del ruolo.
 */
function labelRuolo(string $ruolo): string {
    return match($ruolo) {
        'pilota'        => '🏎️ Pilota',
        'team_manager'  => '🧑‍💼 Team Manager',
        'appassionato'  => '👀 Appassionato',
        'admin'         => '⚙️ Admin',
        default         => '?'
    };
}