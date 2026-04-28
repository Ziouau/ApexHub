<?php
// includes/dashboard_head.php
// Parametri: $page_title (string)
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ApexHub - <?php echo htmlspecialchars($page_title ?? 'Dashboard'); ?></title>
    <link rel="stylesheet" href="assets/css/apexhub.css">
    <style>
        /* Role badges */
        .role-badge { display:inline-block; font-size:11px; padding:3px 10px; border-radius:20px; font-weight:600; margin-top:6px; }
        .role-pilota       { background:rgba(233,69,96,0.2);  color:#e94560; }
        .role-team_manager { background:rgba(255,193,7,0.2);  color:#ffc107; }
        .role-appassionato { background:rgba(23,162,184,0.2); color:#17a2b8; }
        .role-admin        { background:rgba(40,167,69,0.2);  color:#28a745; }
        /* Pending badge nella sidebar */
        .pending-badge { display:inline-block; background:#e94560; color:white; border-radius:50%; width:18px; height:18px; font-size:11px; line-height:18px; text-align:center; margin-left:6px; font-weight:700; }
        /* Top bar extras */
        .admin-badge-pill { display:inline-flex; align-items:center; gap:6px; background:rgba(40,167,69,0.12); color:#28a745; border:1px solid rgba(40,167,69,0.25); padding:4px 12px; border-radius:20px; font-size:12px; font-weight:700; }
    </style>
</head>
<body>