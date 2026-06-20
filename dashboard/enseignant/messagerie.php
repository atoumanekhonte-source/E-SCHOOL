<?php
// /dashboard/enseignant/messagerie.php
// VERSION "ACCÈS LIBRE" : Affiche tous les messages, permet de choisir l'expéditeur.
// ATTENTION : NON SÉCURISÉ POUR LA PRODUCTION SANS AUTHENTIFICATION ROBUSTE.

require_once '../../config/db.php';
$database = new Database();
$conn = $database->connect();

session_start();

// --- Récupération de l'utilisateur courant (enseignant) pour l’affichage du widget ---
$current_user_id = $_SESSION['user_id'] ?? null;

// Valeurs par défaut si on ne trouve pas l’enseignant
$user_avatar = '../../assets/images/default_avatar_teacher.png';
$user_nom_complet = 'Utilisateur';

if ($current_user_id) {
    $stmt_user = $conn->prepare("
        SELECT u.nom, u.prenom, u.photo
        FROM enseignants ens
        JOIN utilisateurs u ON ens.id_utilisateur = u.id
        WHERE ens.id = :id_enseignant
    ");
    $stmt_user->bindParam(':id_enseignant', $current_user_id, PDO::PARAM_INT);
    $stmt_user->execute();
    $info_user = $stmt_user->fetch(PDO::FETCH_ASSOC);
    if ($info_user) {
        if (!empty($info_user['photo'])) {
            $user_avatar = '../../uploads/profiles/' . htmlspecialchars($info_user['photo']);
        }
        $user_nom_complet = htmlspecialchars($info_user['prenom'] . ' ' . $info_user['nom']);
    }
}

// --- Fonctions ---

// Récupère les infos d'un utilisateur (pour affichage)
function get_user_info_for_display($db_conn, $id_utilisateur) {
    $stmt = $db_conn->prepare("SELECT id, nom, prenom, photo, role FROM utilisateurs WHERE id = :id_utilisateur");
    $stmt->bindParam(':id_utilisateur', $id_utilisateur, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Récupère TOUS les utilisateurs pour les listes déroulantes (expéditeur, destinataire)
function get_all_users_for_select($db_conn) {
    $stmt = $db_conn->prepare("SELECT id, prenom, nom, role FROM utilisateurs ORDER BY role, nom, prenom");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Récupère TOUS les messages du système
function get_all_messages($db_conn) {
    $stmt = $db_conn->prepare("
        SELECT 
            msg.id, msg.sujet, msg.contenu, msg.date_envoi,
            msg.expediteur_id, msg.destinataire_id,
            exp.nom as exp_nom, exp.prenom as exp_prenom, exp.role as exp_role, exp.photo as exp_photo,
            dest.nom as dest_nom, dest.prenom as dest_prenom, dest.role as dest_role, dest.photo as dest_photo
        FROM messages msg
        JOIN utilisateurs exp ON msg.expediteur_id = exp.id
        JOIN utilisateurs dest ON msg.destinataire_id = dest.id
        ORDER BY msg.date_envoi DESC
    ");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Récupère les détails d'un message spécifique (sans vérifier l'utilisateur courant)
function get_single_message_details_public($db_conn, $id_message) {
    $stmt = $db_conn->prepare("
        SELECT 
            msg.id, msg.sujet, msg.contenu, msg.date_envoi,
            msg.expediteur_id, msg.destinataire_id,
            exp.nom as exp_nom, exp.prenom as exp_prenom, exp.role as exp_role, exp.photo as exp_photo,
            dest.nom as dest_nom, dest.prenom as dest_prenom, dest.role as dest_role, dest.photo as dest_photo
        FROM messages msg
        JOIN utilisateurs exp ON msg.expediteur_id = exp.id
        JOIN utilisateurs dest ON msg.destinataire_id = dest.id
        WHERE msg.id = :id_message
    ");
    $stmt->bindParam(':id_message', $id_message, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// --- Logique de la Page ---

// Simulation d'un enseignant pour le template (si besoin)
$enseignant_simule_pour_template_id = 102;
$enseignant_info_template = get_user_info_for_display($conn, $enseignant_simule_pour_template_id);
$enseignant_avatar = ($enseignant_info_template && !empty($enseignant_info_template['photo']))
    ? '../../uploads/profiles/' . htmlspecialchars($enseignant_info_template['photo'])
    : '../../assets/images/default_avatar_teacher.png';
$enseignant_nom_complet = ($enseignant_info_template)
    ? htmlspecialchars($enseignant_info_template['prenom'] . ' ' . $enseignant_info_template['nom'])
    : 'Enseignant';

$action = $_GET['action'] ?? 'liste';
$message_id_action = isset($_GET['id_message']) ? (int) $_GET['id_message'] : null;
$message_feedback = '';

$tous_les_utilisateurs = get_all_users_for_select($conn);
$message_a_voir = null;

// Traitement des actions
if ($action === 'envoyer' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // IMPORTANT : Choix de l'expéditeur dans la version "accès libre"
    $expediteur_id = filter_input(INPUT_POST, 'expediteur_id', FILTER_VALIDATE_INT);
    $destinataire_id = filter_input(INPUT_POST, 'destinataire_id', FILTER_VALIDATE_INT);
    $sujet   = trim(filter_input(INPUT_POST, 'sujet',   FILTER_SANITIZE_FULL_SPECIAL_CHARS));
    $contenu = trim(filter_input(INPUT_POST, 'contenu', FILTER_SANITIZE_FULL_SPECIAL_CHARS));

    if (empty($expediteur_id) || empty($destinataire_id) || empty($sujet) || empty($contenu)) {
        $message_feedback = "<div class='alert alert-danger'>Expéditeur, destinataire, sujet et message sont requis.</div>";
        $action = 'composer';
    } elseif ($expediteur_id == $destinataire_id) {
        $message_feedback = "<div class='alert alert-danger'>L'expéditeur et le destinataire ne peuvent pas être identiques.</div>";
        $action = 'composer';
    } else {
        $exp_exists_stmt = $conn->prepare("SELECT id FROM utilisateurs WHERE id = :id_exp");
        $exp_exists_stmt->execute([':id_exp' => $expediteur_id]);
        $dest_exists_stmt = $conn->prepare("SELECT id FROM utilisateurs WHERE id = :id_dest");
        $dest_exists_stmt->execute([':id_dest' => $destinataire_id]);

        if (!$exp_exists_stmt->fetch() || !$dest_exists_stmt->fetch()) {
            $message_feedback = "<div class='alert alert-danger'>Expéditeur ou destinataire invalide.</div>";
            $action = 'composer';
        } else {
            try {
                $stmt_send = $conn->prepare("
                    INSERT INTO messages (expediteur_id, destinataire_id, sujet, contenu, date_envoi)
                    VALUES (:exp, :dest, :sujet, :contenu, NOW())
                ");
                $stmt_send->execute([
                    ':exp' => $expediteur_id,
                    ':dest' => $destinataire_id,
                    ':sujet' => $sujet,
                    ':contenu' => $contenu
                ]);
                $message_feedback = "<div class='alert alert-success'>Message envoyé avec succès !</div>";
                $action = 'liste';
            } catch (PDOException $e) {
                $message_feedback = "<div class='alert alert-danger'>Erreur technique : " . htmlspecialchars($e->getMessage()) . "</div>";
                $action = 'composer';
            }
        }
    }
} elseif ($action === 'supprimer' && $message_id_action) {
    // Suppression globale (dangereux en prod sans droits)
    try {
        $stmt_delete = $conn->prepare("DELETE FROM messages WHERE id = :id_message");
        if ($stmt_delete->execute([':id_message' => $message_id_action])) {
            $message_feedback = "<div class='alert alert-success'>Message ID $message_id_action supprimé.</div>";
        } else {
            $message_feedback = "<div class='alert alert-danger'>Erreur lors de la suppression.</div>";
        }
    } catch (PDOException $e) {
        $message_feedback = "<div class='alert alert-danger'>Erreur technique : " . htmlspecialchars($e->getMessage()) . "</div>";
    }
    $action = 'liste';
} elseif ($action === 'voir' && $message_id_action) {
    $message_a_voir = get_single_message_details_public($conn, $message_id_action);
    if (!$message_a_voir) {
        $message_feedback = "<div class='alert alert-danger'>Message non trouvé.</div>";
        $action = 'liste';
    }
}

$conversations = get_all_messages($conn);

$page_title = "Messagerie Système";
if ($action === 'composer')   $page_title = "Composer un Message";
if ($action === 'voir' && $message_a_voir) {
    $page_title = "Message : " . htmlspecialchars($message_a_voir['sujet']);
}
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - E-SCHOOL</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* CSS INTÉGRÉ (identique à celui du dashboard enseignant) */
        :root { /* ... */ } body.dark-theme { /* ... */ } body { /* ... */ }
        /* ... tous les styles de base, sidebar, topbar ... */
        /* ... .page-container, .page-header, .form-section-container ... */
        /* ... .alert, .no-data-message ... */
        /* ... styles spécifiques à la messagerie (message-list, message-item, etc.) ... */
        
        /* Copiez l'intégralité du bloc CSS de la réponse précédente ici */
        /* --- Styles Généraux (Rappel) --- */
        :root {
    /* Couleurs principales bleues */
    --primary-color: #2563EB;
    --primary-light: #DBEAFE;
    --primary-dark: #1D4ED8;

    /* Couleurs générales */
    --secondary-color: #F8FAFC;
    --page-bg: #FFFFFF;
    --sidebar-bg: #FFFFFF;
    --card-bg: #FFFFFF;

    /* Texte */
    --text-color: #0F172A;
    --text-light: #64748B;

    /* Bordures */
    --border-color: #E2E8F0;

    /* Couleurs d'accentuation */
    --accent-blue: #3B82F6;
    --info-color: #3B82F6;

    /* Statuts */
    --success-color: #10B981;
    --warning-color: #F59E0B;
    --danger-color: #EF4444;

    /* Police */
    --font-family: 'Inter', sans-serif;

    /* Arrondis */
    --border-radius-md: 10px;
    --border-radius-sm: 8px;

    /* Ombres */
    --shadow-sm: 0 2px 8px rgba(37, 99, 235, 0.08);
}
        body.dark-theme { /* ... variables dark ... */ }
        body { font-family: var(--font-family); margin: 0; background-color: var(--page-bg); color: var(--text-color); display: flex; min-height: 100vh; font-size:14px;}
        .dashboard-container { display: flex; width: 100%; }
        .main-wrapper { flex-grow: 1; margin-left: 260px; display: flex; flex-direction: column; width: calc(100% - 260px); background-color: var(--page-bg); }
        .content-area { flex-grow: 1; padding: 24px 30px; display: flex; gap: 24px; overflow-y: auto;}
        .main-column { flex-grow: 1; display: flex; flex-direction: column; gap: 24px;}
        .sidebar { width: 260px; background-color: var(--sidebar-bg); padding: 24px 16px; border-right: 1px solid var(--border-color); display: flex; flex-direction: column; height: 100vh; position: fixed; left: 0; top: 0; z-index: 1000; overflow-y: auto; }
        .sidebar-logo { display: flex; align-items: center; padding: 0 8px 24px 8px; font-size: 22px; font-weight: 700; color: var(--primary-color); }
        .sidebar-logo .logo-icon { margin-right: 10px; font-size: 24px; }
        .sidebar-nav ul { list-style: none; padding: 0; margin: 0; flex-grow: 1; }
        .sidebar-nav li a { display: flex; align-items: center; padding: 10px 12px; color: var(--text-light); text-decoration: none; border-radius: var(--border-radius-sm); margin-bottom: 4px; font-weight: 500; font-size: 14px; }
        .sidebar-nav li a i { margin-right: 12px; width: 20px; text-align: center; font-size: 16px; }
        .sidebar-nav li a:hover { background-color: var(--primary-light); color: var(--primary-color); }
        .sidebar-nav li a.active { background-color: var(--primary-light); color: var(--primary-color); font-weight: 600; }
        .sidebar .logout-link { margin-top: auto; padding-top: 16px; border-top: 1px solid var(--border-color); }
        .top-bar { background-color: var(--page-bg); padding: 0 30px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); height: 70px; box-sizing: border-box; position: sticky; top: 0; z-index: 999; }
        .search-bar { position: relative; flex-grow:1; max-width: 400px;}
        .search-bar input { padding: 10px 15px 10px 40px; border-radius: var(--border-radius-md); border: 1px solid var(--border-color); width: 100%; background-color: var(--secondary-color); color: var(--text-color); font-size: 14px;}
        .search-bar i.fa-search { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: var(--text-light); }
        .top-bar-actions { display: flex; align-items: center; gap: 16px; }
        .btn-add-new { background-color: var(--primary-color); color: white; padding: 10px 16px; text-decoration: none; border-radius: var(--border-radius-md); font-weight: 600; font-size: 14px; display: flex; align-items: center; gap: 8px;}
        .btn-add-new:hover { background-color: var(--primary-dark); }
        .btn-add-new i.fa-plus { font-size: 12px; }
        .top-bar-actions .icon-btn { color: var(--text-light); font-size: 20px; cursor: pointer; padding: 8px; border-radius: 50%;}
        .top-bar-actions .icon-btn:hover { color: var(--primary-color); background-color: var(--primary-light); }
        .user-profile-widget { display: flex; align-items: center; gap: 12px; }
        .user-profile-widget img { width: 40px; height: 40px; border-radius: 50%; object-fit: cover;}
        .user-profile-widget .user-info .user-name { font-weight: 600; color: var(--text-color); display: block;}
        .user-profile-widget .user-info .user-role { font-size: 0.85em; color: var(--text-light); }
        
        .page-container { background-color: var(--card-bg); padding: 24px; border-radius: var(--border-radius-md); box-shadow: var(--shadow-sm); border: 1px solid var(--border-color); width: 100%; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; padding-bottom:16px; border-bottom: 1px solid var(--border-color);}
        .page-header h2 { color: var(--text-color); font-size: 20px; font-weight: 600; margin: 0; display: flex; align-items:center; gap:10px;}
        .page-header p { color: var(--text-light); font-size: 14px; margin-top: 4px; margin-bottom:0; }
        .form-section-container { margin-bottom: 24px; padding: 20px; border-radius: var(--border-radius-md); background-color: var(--card-bg); border: 1px solid var(--border-color); }
        .form-section-container h3 { color: var(--primary-color); margin-top: 0; margin-bottom: 20px; font-size: 18px; font-weight: 600; display: flex; align-items: center; gap: 10px; padding-bottom: 10px; border-bottom: 1px solid var(--primary-light); }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 500; font-size: 14px; color: var(--text-color); }
        .form-group label span[style*="color:red"] { color: var(--danger-color) !important; }
        .form-group input[type="text"], .form-group input[type="url"], .form-group textarea, .form-group select { width: 100%; padding: 10px 12px; border-radius: var(--border-radius-sm); border: 1px solid var(--border-color); font-size: 14px; background-color: var(--page-bg); color: var(--text-color); box-sizing: border-box; }
        .form-group input:focus, .form-group textarea:focus, .form-group select:focus { border-color: var(--primary-color); box-shadow: 0 0 0 3px var(--primary-light); outline: none; }
        .form-group textarea { min-height: 100px; resize: vertical; }
        .btn-submit-form { background-color: var(--primary-color); color: white; padding: 10px 20px; border: none; border-radius: var(--border-radius-md); font-size: 14px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; }
        .btn-submit-form:hover { background-color: var(--primary-dark); }
        .btn-nouveau-message { background-color: var(--primary-color); color: white; padding: 10px 18px; text-decoration: none; border-radius: var(--border-radius-md); font-weight: 500; font-size: 14px; display:inline-flex; align-items:center; gap:8px;}
        .btn-nouveau-message:hover { background-color: var(--primary-dark); }
        .message-list { list-style-type: none; padding: 0; margin: 0; }
        .message-item { border: 1px solid var(--border-color); border-radius: var(--border-radius-md); margin-bottom: 15px; overflow: hidden; background-color: var(--page-bg); }
        .message-item.clickable:hover { box-shadow: var(--shadow-md); cursor:pointer; }
        .message-item.unread .message-header, .message-item.unread .message-subject { font-weight: 600; }
        .message-item.unread .message-header { background-color: var(--primary-light); }
        .message-header { padding: 12px 18px; background-color: var(--secondary-color); display: flex; flex-wrap:wrap; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); }
        .message-participants { font-size: 13px; display:flex; align-items:center; gap:8px; margin-bottom: 5px; flex-basis: 70%; }
        .message-participants img { width:24px; height:24px; border-radius:50%; object-fit:cover;}
        .message-participants .sender, .message-participants .recipient { color: var(--text-color); font-weight:500; }
        .message-participants .arrow { color: var(--text-light); }
        .message-participants .role-badge { font-size: 11px; padding: 2px 6px; border-radius: var(--border-radius-sm); background-color: var(--primary-light); color: var(--primary-color); text-transform: capitalize;}
        .message-date { font-size: 12px; color: var(--text-light); flex-basis: 25%; text-align:right; min-width:120px; }
        .message-subject { padding: 10px 18px; font-weight: 500; color: var(--text-color); font-size:15px;}
        .message-content-full { padding: 15px 18px; font-size: 14px; color: var(--text-color); white-space: pre-wrap; line-height:1.6; border-top: 1px solid var(--border-color); margin-top:10px; }
        .message-actions-bar { padding: 10px 18px; border-top: 1px solid var(--border-color); text-align: right; background-color: var(--secondary-color);}
        .btn-action-msg { background-color: transparent; color:var(--text-light); border:1px solid var(--border-color); padding: 6px 12px; border-radius:var(--border-radius-sm); font-size:13px; font-weight:500; cursor:pointer; margin-left:10px; }
        .btn-action-msg.reply { color:var(--primary-color); border-color:var(--primary-color); }
        .btn-action-msg.reply:hover { background-color:var(--primary-light); }
        .btn-action-msg.delete { color:var(--danger-color); border-color:var(--danger-color); }
        .btn-action-msg.delete:hover { background-color: #FEF3F2; }
        .alert { padding: 12px 16px; margin-bottom: 20px; border-radius: var(--border-radius-sm); border-width:1px; border-style:solid; font-size:14px;}
        .alert-success { background-color: #ECFDF3; color: #027A48; border-color: #ABEFC6; }
        .alert-danger { background-color: #FEF3F2; color: #B42318; border-color: #FECDCA; }
        .alert-info { background-color: #EFF4FF; color: #2970FF; border-color: #B2CCFF; }
        .no-data-message { text-align:center; padding:20px; color:var(--text-light); font-style: italic; }
    </style>
</head>
<body>
    <div class="dashboard-container">
         <aside class="sidebar">
            <div class="sidebar-logo"><i class="fas fa-chalkboard-teacher logo-icon"></i> E-SCHOOL</div>
            <nav class="sidebar-nav">
                <ul>
                    <li><a href="index.php"><i class="fas fa-th-large"></i> Tableau de Bord</a></li>
                    <li><a href="deposer_cours.php"><i class="fas fa-file-medical"></i> Déposer Cours</a></li>
                    <li><a href="enregistrer_cours.php"><i class="fas fa-video"></i> Planifier/Enregistrer Live</a></li>
                    <li><a href="deposer_td.php"><i class="fas fa-tasks"></i> Déposer TD/TC</a></li>
                    <li><a href="noter_etudiants.php"><i class="fas fa-edit"></i> Noter Étudiants</a></li>
                    <li><a href="voir_devoirs.php"><i class="fas fa-eye"></i> Voir Devoirs Remis</a></li>
                    <li><a href="messagerie.php" class="<?php echo ($current_page == 'messagerie.php') ? 'active' : ''; ?>"><i class="fas fa-envelope"></i> Messagerie</a></li>
                    <li><a href="saisie_absences.php"><i class="fas fa-user-check"></i> Saisir Absences</a></li>
                </ul>
            </nav>
            <div class="sidebar-nav logout-link"><ul><li><a href="../../logout.php"><i class="fas fa-sign-out-alt"></i> Déconnexion</a></li></ul></div>
        </aside>
        
        <div class="main-wrapper">
            <header class="top-bar">
                <div class="search-bar"><i class="fas fa-search"></i><input type="text" placeholder="Rechercher messages..."></div>
                <div class="top-bar-actions">
                    <a href="messagerie.php?action=composer" class="btn-add-new"><i class="fas fa-paper-plane"></i> Nouveau Message</a>
                    <a href="#" class="icon-btn" id="theme-toggle" title="Changer de thème"><i class="fas fa-moon"></i></a>
                    <a href="#" class="icon-btn" title="Notifications"><i class="fas fa-bell"></i></a>
                    <div class="user-profile-widget">
                        <img src="<?php echo $user_avatar; ?>" alt="Avatar">
                        <div class="user-info">
                            <span class="user-name"><?php echo $user_nom_complet; ?></span>
                            <span class="user-role"><?php echo ucfirst($current_user_info['role'] ?? 'Utilisateur'); ?></span>
                        </div>
                    </div>
                </div>
            </header>

            <main class="content-area">
                <div class="main-column">
                     <div class="page-container">
                        <div class="page-header">
                            <h2><i class="fas fa-comments"></i> <?php echo $page_title; ?></h2>
                        </div>
                        
                        <?php if ($message_feedback): ?>
                            <?php echo $message_feedback; ?>
                        <?php endif; ?>

                        <?php if ($action === 'composer' || ($action === 'repondre' && $message_a_voir)): ?>
                            <div class="form-section-container">
                                <h3>
                                    <?php if ($action === 'repondre' && $message_a_voir): ?>
                                        <i class="fas fa-reply"></i> Répondre à: <?php echo htmlspecialchars($message_a_voir['exp_prenom'] . ' ' . $message_a_voir['exp_nom']); ?>
                                    <?php else: ?>
                                        <i class="fas fa-pen-alt"></i> Composer un nouveau message
                                    <?php endif; ?>
                                </h3>
                                <form action="messagerie.php?action=envoyer" method="POST">
                                    <div class="form-group">
                                        <label for="expediteur_id">Expéditeur <span style="color:red;">*</span></label>
                                        <select name="expediteur_id" id="expediteur_id" required>
                                            <option value="">-- Choisir un expéditeur --</option>
                                            <?php foreach ($tous_les_utilisateurs as $user): ?>
                                                <option value="<?php echo $user['id']; ?>" <?php echo ($action === 'repondre' && $message_a_voir && $message_a_voir['destinataire_id'] == $user['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($user['prenom'] . ' ' . $user['nom'] . ' (' . ucfirst($user['role']) . ')'); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="destinataire_id">Destinataire <span style="color:red;">*</span></label>
                                        <select name="destinataire_id" id="destinataire_id" required>
                                            <option value="">-- Sélectionner un destinataire --</option>
                                            <?php foreach ($tous_les_utilisateurs as $dest): 
                                                $selected = ($action === 'repondre' && $message_a_voir && $message_a_voir['expediteur_id'] == $dest['id']) ? 'selected' : '';
                                            ?>
                                                <option value="<?php echo $dest['id']; ?>" <?php echo $selected; ?>>
                                                    <?php echo htmlspecialchars($dest['prenom'] . ' ' . $dest['nom'] . ' (' . ucfirst($dest['role']) . ')'); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="sujet">Sujet <span style="color:red;">*</span></label>
                                        <input type="text" id="sujet" name="sujet" required 
                                               value="<?php echo ($action === 'repondre' && $message_a_voir) ? 'Re: ' . htmlspecialchars($message_a_voir['sujet']) : (isset($_POST['sujet']) ? htmlspecialchars($_POST['sujet']) : ''); ?>">
                                    </div>
                                    <div class="form-group">
                                        <label for="contenu">Message <span style="color:red;">*</span></label>
                                        <textarea id="contenu" name="contenu" rows="8" required><?php 
                                            if ($action === 'repondre' && $message_a_voir) {
                                                echo "\n\n\n-----------------\nLe " . date('d/m/Y à H:i', strtotime($message_a_voir['date_envoi'])) . ", " . htmlspecialchars($message_a_voir['exp_prenom'] . ' ' . $message_a_voir['exp_nom']) . " a écrit :\n" . htmlspecialchars($message_a_voir['contenu']);
                                            } else {
                                                echo isset($_POST['contenu']) ? htmlspecialchars($_POST['contenu']) : '';
                                            } 
                                        ?></textarea>
                                    </div>
                                    <button type="submit" class="btn-submit-form"><i class="fas fa-paper-plane"></i> Envoyer le Message</button>
                                     <a href="messagerie.php" style="margin-left:10px; color:var(--text-light); text-decoration:none;">Annuler</a>
                                </form>
                            </div>

                        <?php elseif ($action === 'voir' && $message_a_voir): ?>
                             <div class="message-item" style="cursor:default;">
                                <div class="message-header">
                                     <span class="message-participants">
                                        <?php 
                                        $exp_avatar = !empty($message_a_voir['exp_photo']) ? '../../uploads/profiles/'.htmlspecialchars($message_a_voir['exp_photo']) : '../../assets/images/default_avatar.png';
                                        $dest_avatar = !empty($message_a_voir['dest_photo']) ? '../../uploads/profiles/'.htmlspecialchars($message_a_voir['dest_photo']) : '../../assets/images/default_avatar.png';
                                        ?>
                                        <img src="<?php echo $exp_avatar; ?>" alt="Expéditeur">
                                        <span class="sender"><?php echo htmlspecialchars($message_a_voir['exp_prenom'] . ' ' . $message_a_voir['exp_nom']); ?></span>
                                        <span class="role-badge"><?php echo htmlspecialchars($message_a_voir['exp_role']); ?></span>
                                        <span class="arrow"><i class="fas fa-long-arrow-alt-right"></i></span>
                                        <img src="<?php echo $dest_avatar; ?>" alt="Destinataire">
                                        <span class="recipient"><?php echo htmlspecialchars($message_a_voir['dest_prenom'] . ' ' . $message_a_voir['dest_nom']); ?></span>
                                        <span class="role-badge"><?php echo htmlspecialchars($message_a_voir['dest_role']); ?></span>
                                    </span>
                                    <span class="message-date"><?php echo date('d M Y, H:i', strtotime($message_a_voir['date_envoi'])); ?></span>
                                </div>
                                <div class="message-subject">
                                    Sujet: <?php echo htmlspecialchars($message_a_voir['sujet']); ?>
                                </div>
                                <div class="message-content-full">
                                    <?php echo nl2br(htmlspecialchars($message_a_voir['contenu'])); ?>
                                </div>
                                <div class="message-actions-bar">
                                     <a href="messagerie.php?action=repondre&id_message=<?php echo $message_a_voir['id']; ?>" class="btn-action-msg reply"><i class="fas fa-reply"></i> Répondre</a>
                                     <a href="messagerie.php?action=supprimer&id_message=<?php echo $message_a_voir['id']; ?>" class="btn-action-msg delete" onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce message ?');"><i class="fas fa-trash"></i> Supprimer</a>
                                </div>
                            </div>
                            <a href="messagerie.php" class="back-link" style="display:inline-block; margin-top:20px;"><i class="fas fa-arrow-left"></i> Retour à la boîte de réception</a>

                        <?php else: // Action 'liste' par défaut ?>
                            <?php if (empty($conversations)): ?>
                                <div class="no-data-message">
                                    <i class="fas fa-comment-dots fa-3x" style="margin-bottom:10px;"></i><br>
                                    Aucun message dans le système.
                                </div>
                            <?php else: ?>
                                <ul class="message-list">
                                    <?php foreach ($conversations as $msg): 
                                        $exp_avatar_list = !empty($msg['exp_photo']) ? '../../uploads/profiles/'.htmlspecialchars($msg['exp_photo']) : '../../assets/images/default_avatar.png';
                                        $dest_avatar_list = !empty($msg['dest_photo']) ? '../../uploads/profiles/'.htmlspecialchars($msg['dest_photo']) : '../../assets/images/default_avatar.png';
                                    ?>
                                        <li class="message-item clickable" onclick="window.location.href='messagerie.php?action=voir&id_message=<?php echo $msg['id']; ?>'">
                                            <div class="message-header">
                                                <span class="message-participants">
                                                    <img src="<?php echo $exp_avatar_list; ?>" alt="Exp.">
                                                    <span class="sender"><?php echo htmlspecialchars($msg['exp_prenom'] . ' ' . $msg['exp_nom']); ?></span>
                                                    <span class="role-badge"><?php echo htmlspecialchars($msg['exp_role']); ?></span>
                                                    <span class="arrow"><i class="fas fa-long-arrow-alt-right"></i></span>
                                                    <img src="<?php echo $dest_avatar_list; ?>" alt="Dest.">
                                                    <span class="recipient"><?php echo htmlspecialchars($msg['dest_prenom'] . ' ' . $msg['dest_nom']); ?></span>
                                                    <span class="role-badge"><?php echo htmlspecialchars($msg['dest_role']); ?></span>
                                                </span>
                                                <span class="message-date"><?php echo date('d M Y, H:i', strtotime($msg['date_envoi'])); ?></span>
                                            </div>
                                            <div class="message-subject">
                                                <?php echo htmlspecialchars($msg['sujet']); ?>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const themeToggle = document.getElementById('theme-toggle');
            if (themeToggle) { /* ... (code du thème identique) ... */ }
        });
    </script>
</body>
</html>