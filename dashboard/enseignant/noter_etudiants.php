<?php
// /dashboard/enseignant/noter_etudiants.php

// PAS D'AUTHENTIFICATION COMPLEXE POUR CET EXEMPLE
require_once '../../config/db.php';

$database = new Database();
$conn = $database->connect();

// --- ID Enseignant Simulé ---
define('SIMULATED_ENSEIGNANT_ID_NOTATION', 1); // !! IMPORTANT: ID enseignant existant

// --- Fonctions ---
function get_enseignant_info_for_notation_page($db_conn, $id_enseignant_table) {
    $stmt = $db_conn->prepare("SELECT u.nom, u.prenom, u.photo FROM enseignants ens JOIN utilisateurs u ON ens.id_utilisateur = u.id WHERE ens.id = :id_enseignant");
    $stmt->bindParam(':id_enseignant', $id_enseignant_table, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function get_devoir_details_for_notation_page($db_conn, $id_devoir, $id_enseignant) {
    $stmt = $db_conn->prepare("
        SELECT 
            d.id,
            d.titre,
            d.type,
            d.id_matiere,
            m.id_classe,
            m.nom_matiere,
            c.nom_classe
        FROM devoirs d
        JOIN matieres m ON d.id_matiere = m.id
        JOIN classes c  ON m.id_classe = c.id
        WHERE d.id = :id_devoir
          AND d.id_enseignant = :id_enseignant
    ");
    $stmt->bindParam(':id_devoir', $id_devoir, PDO::PARAM_INT);
    $stmt->bindParam(':id_enseignant', $id_enseignant, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// MODIFIÉ: Récupère UNIQUEMENT les étudiants qui ont remis le devoir spécifié
function get_submitted_etudiants_for_devoir_notation($db_conn, $id_devoir) {
    $stmt = $db_conn->prepare("
        SELECT 
            etu.id as etudiant_id, 
            u.nom as etudiant_nom, 
            u.prenom as etudiant_prenom, 
            u.photo as etudiant_photo,
            dr.id as devoir_remis_id, -- ID de l'enregistrement dans devoirs_remis
            dr.fichier_remis, 
            dr.date_remise,
            n.note,
            n.id as note_id,
            d.type as type_devoir_pour_note -- Pour la jointure sur notes.type_eval
        FROM devoirs_remis dr
        JOIN etudiants etu ON dr.id_etudiant = etu.id
        JOIN utilisateurs u ON etu.id_utilisateur = u.id
        JOIN devoirs d ON dr.id_devoir = d.id -- Jointure pour obtenir le type du devoir
        LEFT JOIN notes n ON etu.id = n.id_etudiant AND d.id_matiere = n.id_matiere AND d.type = n.type_eval
        WHERE dr.id_devoir = :id_devoir
        ORDER BY u.nom ASC, u.prenom ASC
    ");
    $stmt->bindParam(':id_devoir', $id_devoir, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


// --- Logique de la page ---
$enseignant_info_page = get_enseignant_info_for_notation_page($conn, SIMULATED_ENSEIGNANT_ID_NOTATION);
if (!$enseignant_info_page) die("Erreur enseignant simulé.");

$enseignant_avatar = !empty($enseignant_info_page['photo']) ? '../../uploads/profiles/' . htmlspecialchars($enseignant_info_page['photo']) : '../../assets/images/default_avatar_teacher.png';
$enseignant_nom_complet = htmlspecialchars($enseignant_info_page['prenom'] . ' ' . $enseignant_info_page['nom']);

$message = '';
$id_devoir_get = isset($_GET['id_devoir']) ? (int)$_GET['id_devoir'] : null;
$focus_etudiant_id = isset($_GET['id_etudiant']) ? (int)$_GET['id_etudiant'] : null; 
$devoir_details = null;
$etudiants_ayant_remis = []; // Renommé pour plus de clarté

if (!$id_devoir_get) {
    $message = "<div class='alert alert-danger'>Aucun devoir sélectionné pour la notation. Veuillez <a href='voir_devoirs.php'>sélectionner un devoir</a>.</div>";
} else {
    $devoir_details = get_devoir_details_for_notation_page($conn, $id_devoir_get, SIMULATED_ENSEIGNANT_ID_NOTATION);
    if (!$devoir_details) {
        $message = "<div class='alert alert-danger'>Devoir non trouvé ou vous n'êtes pas autorisé à noter ce devoir.</div>";
    } else {
        // Charger uniquement les étudiants qui ont remis ce devoir
        $etudiants_ayant_remis = get_submitted_etudiants_for_devoir_notation($conn, $id_devoir_get);
    }
}

// Traitement du formulaire de notation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_notes']) && $devoir_details) {
    // ... (La logique de traitement des notes reste la même que dans la version précédente) ...
    // ... Elle itère sur $_POST['notes'] qui contiendra les etudiant_id de ceux affichés (donc ceux qui ont remis)
    $notes_soumises = $_POST['notes'] ?? [];
    $id_matiere_devoir = $devoir_details['id_matiere'];
    $type_eval_devoir = $devoir_details['type']; 
    $notes_enregistrees_count = 0;
    $notes_mises_a_jour_count = 0;

    foreach ($notes_soumises as $etudiant_id_post => $note_value_post) {
        $etudiant_id = (int)$etudiant_id_post;
        
        if ($note_value_post !== '' && (is_numeric($note_value_post) || $note_value_post === null || empty(trim($note_value_post)) ) ) {
            $note_decimal = ($note_value_post === '' || $note_value_post === null) ? null : (float)$note_value_post;

            if ($note_decimal !== null && ($note_decimal < 0 || $note_decimal > 20)) {
                $message .= "<div class='alert alert-danger'>Note invalide pour l'étudiant ID $etudiant_id. La note doit être entre 0 et 20.</div>";
                continue; 
            }

            $stmt_check = $conn->prepare("SELECT id FROM notes WHERE id_etudiant = :id_etudiant AND id_matiere = :id_matiere AND type_eval = :type_eval");
            $stmt_check->execute([
                ':id_etudiant' => $etudiant_id,
                ':id_matiere' => $id_matiere_devoir,
                ':type_eval' => $type_eval_devoir
            ]);
            $existing_note_id = $stmt_check->fetchColumn();

            try {
                if ($existing_note_id) {
                    if ($note_decimal === null) { 
                        $stmt_delete = $conn->prepare("DELETE FROM notes WHERE id = :id_note");
                        if ($stmt_delete->execute([':id_note' => $existing_note_id])) {
                            // $notes_mises_a_jour_count++; // Plutôt un compteur de suppression si besoin
                        }
                    } else { 
                        $stmt_update = $conn->prepare("UPDATE notes SET note = :note, date_saisie = CURDATE() WHERE id = :id_note");
                        if ($stmt_update->execute([':note' => $note_decimal, ':id_note' => $existing_note_id])) {
                            $notes_mises_a_jour_count++;
                        }
                    }
                } elseif ($note_decimal !== null) { 
                    $stmt_insert = $conn->prepare("
                        INSERT INTO notes (id_etudiant, id_matiere, type_eval, note, date_saisie)
                        VALUES (:id_etudiant, :id_matiere, :type_eval, :note, CURDATE())
                    ");
                    if ($stmt_insert->execute([
                        ':id_etudiant' => $etudiant_id,
                        ':id_matiere' => $id_matiere_devoir,
                        ':type_eval' => $type_eval_devoir,
                        ':note' => $note_decimal
                    ])) {
                        $notes_enregistrees_count++;
                    }
                }
            } catch (PDOException $e) {
                error_log("Erreur notation étudiant ID $etudiant_id: " . $e->getMessage());
                $message .= "<div class='alert alert-danger'>Erreur lors de l'enregistrement de la note pour l'étudiant ID $etudiant_id.</div>";
            }

        } elseif ($note_value_post !== '') { 
             $message .= "<div class='alert alert-danger'>Note non numérique fournie pour l'étudiant ID $etudiant_id.</div>";
        }
    }
    if (empty($message)) { 
        if ($notes_enregistrees_count > 0 || $notes_mises_a_jour_count > 0) {
             $message = "<div class='alert alert-success'>Notation terminée. $notes_enregistrees_count nouvelle(s) note(s) enregistrée(s), $notes_mises_a_jour_count note(s) mise(s) à jour.</div>";
        } else {
            $message = "<div class='alert alert-info'>Aucune note n'a été modifiée ou enregistrée.</div>";
        }
    }
     // Recharger la liste des étudiants APRÈS la mise à jour des notes
    if ($devoir_details) {
        $etudiants_ayant_remis = get_submitted_etudiants_for_devoir_notation($conn, $id_devoir_get);
    }
}


$page_title = $devoir_details ? "Noter: " . htmlspecialchars($devoir_details['titre']) : "Notation des Étudiants";
$current_page = basename($_SERVER['PHP_SELF']);
?>
<<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - E-SCHOOL</title>
    <link rel="stylesheet" href="../../assets/css/style.css"> <!-- CSS Global -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Styles CSS du Dashboard Enseignant (inspiré de Hireism) */
        /* COPIEZ L'INTÉGRALITÉ DU CSS DE VOTRE DASHBOARD ENSEIGNANT (index.php) ICI */
        /* Si votre ../../assets/css/style.css est déjà complet avec ces styles, cette section est redondante. */
        :root {
    --primary-color: #2563EB;
    --primary-light: #DBEAFE;
    --primary-dark: #1D4ED8;

    --secondary-color: #F8FAFC;
    --page-bg: #FFFFFF;
    --sidebar-bg: #FFFFFF;
    --card-bg: #FFFFFF;

    --text-color: #0F172A;
    --text-light: #64748B;
    --border-color: #E2E8F0;

    --accent-blue: #3B82F6;
    --danger-color: #DC2626;
    --success-color: #16A34A;
    --warning-color: #F59E0B;
    --info-color: #2563EB;

    --font-family: 'Inter', sans-serif;
    --border-radius-md: 8px;
    --border-radius-sm: 6px;

    --shadow-sm: 0 1px 2px rgba(0,0,0,.05);
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
        .page-header { margin-bottom: 24px; padding-bottom:16px; border-bottom: 1px solid var(--border-color);}
        .page-header h2 { color: var(--text-color); font-size: 20px; font-weight: 600; margin: 0; display: flex; align-items:center; gap:10px;}
        .page-header p { color: var(--text-light); font-size: 14px; margin-top: 4px; margin-bottom:0; }
        .page-header .back-link { color: var(--primary-color); text-decoration: none; font-weight: 500; display: inline-block; margin-bottom: 15px;}
        .page-header .back-link i { margin-right: 5px; }
        .devoir-info-notation { margin-bottom: 25px; padding: 15px; background-color: var(--secondary-color); border-radius: var(--border-radius-md); }
        .devoir-info-notation p { margin: 5px 0; font-size:0.95em; }
        .devoir-info-notation strong { color: var(--text-color); }
        .devoir-info-notation .type-badge { padding: 3px 8px; border-radius: var(--border-radius-sm); font-size: 12px; font-weight: 500; color: white; text-transform: uppercase; margin-left:5px;}
        .devoir-info-notation .type-badge.td { background-color: var(--primary-color); }
        .devoir-info-notation .type-badge.tc { background-color: var(--accent-blue); }
        .notation-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .notation-table th, .notation-table td { padding: 10px 12px; text-align: left; border-bottom: 1px solid var(--border-color); vertical-align: middle; }
        .notation-table thead th { background-color: var(--secondary-color); color: var(--text-light); font-size: 12px; text-transform: uppercase; font-weight: 500; }
        .notation-table tbody tr:hover { background-color: var(--primary-light); }
        .notation-table td { font-size: 14px; color: var(--text-color); }
        .notation-table .etudiant-avatar-small { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; margin-right: 10px; vertical-align: middle; }
        .notation-table .etudiant-name-cell { display: flex; align-items: center; }
        .notation-table input[type="number"] { width: 80px; padding: 8px; border-radius: var(--border-radius-sm); border: 1px solid var(--border-color); text-align: center; font-size: 14px; background-color: var(--page-bg); color: var(--text-color); }
        .notation-table input[type="number"]:focus { border-color: var(--primary-color); box-shadow: 0 0 0 3px var(--primary-light); outline: none;}
        .notation-table .fichier-remis-link { display: inline-block; color: var(--primary-color); font-size: 0.9em; }
        .notation-table .fichier-remis-link i { margin-right: 3px;}
        .no-submission { font-style: italic; color: var(--text-light); font-size: 0.9em; }
        .submit-notes-btn-container { text-align:right; margin-top:20px; padding-top:20px; border-top:1px solid var(--border-color);}
        .btn-submit-form { background-color: var(--primary-color); color: white; padding: 10px 20px; border: none; border-radius: var(--border-radius-md); font-size: 14px; font-weight: 600; cursor: pointer; transition: background-color 0.2s; display: inline-flex; align-items: center; gap: 8px; }
        .btn-submit-form:hover { background-color: var(--primary-dark); }
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
                    <li><a href="noter_etudiants.php" class="<?php echo ($current_page == 'noter_etudiants.php') ? 'active' : ''; ?>"><i class="fas fa-edit"></i> Noter Étudiants</a></li>
                    <li><a href="voir_devoirs.php"><i class="fas fa-eye"></i> Voir Devoirs Remis</a></li>
                    <li><a href="messagerie.php"><i class="fas fa-envelope"></i> Messagerie</a></li>
                    <li><a href="saisie_absences.php"><i class="fas fa-user-check"></i> Saisir Absences</a></li>
                </ul>
            </nav>
            <div class="sidebar-nav logout-link"><ul><li><a href="../../logout.php"><i class="fas fa-sign-out-alt"></i> Déconnexion</a></li></ul></div>
        </aside>
        
        <div class="main-wrapper">
            <header class="top-bar">
                <div class="search-bar"><i class="fas fa-search"></i><input type="text" placeholder="Rechercher..."></div>
                <div class="top-bar-actions">
                    <a href="deposer_td.php" class="btn-add-new"><i class="fas fa-plus"></i> Nouveau Devoir</a>
                    <a href="#" class="icon-btn" id="theme-toggle" title="Changer de thème"><i class="fas fa-moon"></i></a>
                    <a href="#" class="icon-btn" title="Notifications"><i class="fas fa-bell"></i></a>
                    <div class="user-profile-widget">
                        <img src="<?php echo $enseignant_avatar; ?>" alt="Avatar de <?php echo $enseignant_nom_complet; ?>">
                        <div class="user-info"><span class="user-name"><?php echo $enseignant_nom_complet; ?></span><span class="user-role">Enseignant</span></div>
                    </div>
                </div>
            </header>

            <main class="content-area">
                <div class="main-column">
                     <div class="page-container">
                        <div class="page-header">
                             <?php if ($devoir_details): ?>
                                <a href="voir_devoirs.php?id_devoir=<?php echo $devoir_details['id']; ?>" class="back-link">
                                    <i class="fas fa-arrow-left"></i> Retour aux soumissions du devoir
                                </a>
                            <?php else: ?>
                                <a href="voir_devoirs.php" class="back-link">
                                    <i class="fas fa-arrow-left"></i> Retour à la liste des devoirs
                                </a>
                            <?php endif; ?>
                            <h2><i class="fas fa-edit"></i> Notation des Étudiants</h2>
                        </div>

                        <?php if ($message): ?>
                            <?php echo $message; ?>
                        <?php endif; ?>

                        <?php if ($devoir_details): ?>
                            <div class="devoir-info-notation">
                                <p><strong>Devoir :</strong> <?php echo htmlspecialchars($devoir_details['titre']); ?> 
                                    <span class="type-badge <?php echo strtolower($devoir_details['type']); ?>"><?php echo htmlspecialchars($devoir_details['type']); ?></span>
                                </p>
                                <p><strong>Matière :</strong> <?php echo htmlspecialchars($devoir_details['nom_matiere']); ?></p>
                                <p><strong>Classe :</strong> <?php echo htmlspecialchars($devoir_details['nom_classe']); ?></p>
                            </div>

                            <?php if (empty($etudiants_ayant_remis)): ?>
                                <p class="no-data-message">Aucun étudiant n'a encore remis ce devoir.</p>
                            <?php else: ?>
                                <form action="noter_etudiants.php?id_devoir=<?php echo $id_devoir_get; ?><?php echo $focus_etudiant_id ? '&id_etudiant='.$focus_etudiant_id : ''; ?>" method="POST">
                                    <table class="notation-table">
                                        <thead>
                                            <tr>
                                                <th>Étudiant</th>
                                                <th>Fichier Remis</th>
                                                <th>Note /20</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($etudiants_ayant_remis as $etudiant): 
                                                $avatar_etudiant = !empty($etudiant['etudiant_photo']) ? '../../uploads/profiles/' . htmlspecialchars($etudiant['etudiant_photo']) : '../../assets/images/default_avatar.png';
                                            ?>
                                            <tr id="etudiant-row-<?php echo $etudiant['etudiant_id']; ?>">
                                                <td class="etudiant-name-cell">
                                                    <img src="<?php echo $avatar_etudiant; ?>" alt="Avatar" class="etudiant-avatar-small">
                                                    <?php echo htmlspecialchars($etudiant['etudiant_prenom'] . ' ' . $etudiant['etudiant_nom']); ?>
                                                </td>
                                                <td>
                                                    <?php if ($etudiant['fichier_remis']): ?>
                                                        <a href="../../uploads/devoirs_remis/<?php echo htmlspecialchars($etudiant['fichier_remis']); ?>" download class="fichier-remis-link" title="Télécharger la soumission de <?php echo htmlspecialchars($etudiant['etudiant_prenom']); ?>">
                                                            <i class="fas fa-file-download"></i> <?php /* echo htmlspecialchars(substr($etudiant['fichier_remis'],0,20)).(strlen($etudiant['fichier_remis'])>20 ? '...' : ''); */ ?>
                                                            Remis le: <?php echo date('d/m/y H:i', strtotime($etudiant['date_remise'])); ?>
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="no-submission">Pas de fichier (ou soumission non trouvée)</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <input type="number" 
                                                           name="notes[<?php echo $etudiant['etudiant_id']; ?>]" 
                                                           value="<?php echo isset($etudiant['note']) ? htmlspecialchars(number_format((float)$etudiant['note'], 2, '.', '')) : ''; ?>" 
                                                           min="0" max="20" step="0.01" 
                                                           placeholder="ex: 15.50">
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                    <div class="submit-notes-btn-container">
                                        <button type="submit" name="submit_notes" class="btn-submit-form">
                                            <i class="fas fa-save"></i> Enregistrer les Notes
                                        </button>
                                    </div>
                                </form>
                            <?php endif; ?>
                        <?php else: ?>
                             <?php if (!empty($id_devoir_get) && empty($message)): ?>
                                <div class="alert alert-danger">Impossible de charger les détails du devoir sélectionné. Vérifiez l'ID du devoir ou vos droits.</div>
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

            <?php if ($focus_etudiant_id): ?>
            const targetRow = document.getElementById('etudiant-row-<?php echo $focus_etudiant_id; ?>');
            if (targetRow) {
                targetRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
                const inputField = targetRow.querySelector('input[type="number"]');
                if(inputField) inputField.focus();
                targetRow.style.backgroundColor = 'var(--primary-light)';
                setTimeout(() => { targetRow.style.backgroundColor = ''; }, 3000);
            }
            <?php endif; ?>
        });
    </script>
</body>
</html>