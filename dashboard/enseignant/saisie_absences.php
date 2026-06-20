<?php
// /dashboard/enseignant/saisie_absences.php

// PAS D'AUTHENTIFICATION COMPLEXE POUR CET EXEMPLE
require_once '../../config/db.php';

$database = new Database();
$conn = $database->connect();

// --- ID Enseignant Simulé ---
define('SIMULATED_ENSEIGNANT_ID_ABSENCES', 1); // !! IMPORTANT: ID enseignant existant

// --- Fonctions ---
function get_enseignant_info_for_absences($db_conn, $id_enseignant_table) {
    $stmt = $db_conn->prepare("SELECT u.nom, u.prenom, u.photo FROM enseignants ens JOIN utilisateurs u ON ens.id_utilisateur = u.id WHERE ens.id = :id_enseignant");
    $stmt->bindParam(':id_enseignant', $id_enseignant_table, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function get_classes_enseignees_for_select($db_conn, $id_enseignant) {
    $stmt = $db_conn->prepare("
        SELECT DISTINCT c.id, c.nom_classe, c.niveau 
        FROM classes c
        JOIN matieres m ON c.id = m.id_classe
        WHERE m.id_enseignant = :id_enseignant
        ORDER BY c.nom_classe ASC
    ");
    $stmt->bindParam(':id_enseignant', $id_enseignant, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_matieres_by_enseignant_and_classe($db_conn, $id_enseignant, $id_classe) {
    if (empty($id_classe)) return [];
    $stmt = $db_conn->prepare("
        SELECT id, nom_matiere, code_matiere 
        FROM matieres 
        WHERE id_enseignant = :id_enseignant AND id_classe = :id_classe
        ORDER BY nom_matiere ASC
    ");
    $stmt->bindParam(':id_enseignant', $id_enseignant, PDO::PARAM_INT);
    $stmt->bindParam(':id_classe', $id_classe, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_etudiants_by_classe($db_conn, $id_classe) {
    if (empty($id_classe)) return [];
    $stmt = $db_conn->prepare("
        SELECT e.id as etudiant_id, u.nom, u.prenom, u.photo 
        FROM etudiants e
        JOIN utilisateurs u ON e.id_utilisateur = u.id
        WHERE e.id_classe = :id_classe
        ORDER BY u.nom ASC, u.prenom ASC
    ");
    $stmt->bindParam(':id_classe', $id_classe, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_recorded_absences($db_conn, $id_classe, $id_matiere, $date_absence) {
    if (empty($id_classe) || empty($id_matiere) || empty($date_absence)) return [];
    $stmt = $db_conn->prepare("
        SELECT id_etudiant, raison, est_justifie 
        FROM absences 
        WHERE id_matiere = :id_matiere 
          AND date_absence = :date_absence
          AND id_etudiant IN (SELECT id FROM etudiants WHERE id_classe = :id_classe) 
    ");
    $stmt->bindParam(':id_matiere', $id_matiere, PDO::PARAM_INT);
    $stmt->bindParam(':date_absence', $date_absence, PDO::PARAM_STR);
    $stmt->bindParam(':id_classe', $id_classe, PDO::PARAM_INT);
    $stmt->execute();
    $absences = [];
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)){
        $absences[$row['id_etudiant']] = $row;
    }
    return $absences;
}


// --- Logique de la Page ---
$enseignant_info_page = get_enseignant_info_for_absences($conn, SIMULATED_ENSEIGNANT_ID_ABSENCES);
if (!$enseignant_info_page) die("Erreur enseignant simulé.");

$enseignant_avatar = !empty($enseignant_info_page['photo']) ? '../../uploads/profiles/' . htmlspecialchars($enseignant_info_page['photo']) : '../../assets/images/default_avatar_teacher.png';
$enseignant_nom_complet = htmlspecialchars($enseignant_info_page['prenom'] . ' ' . $enseignant_info_page['nom']);

$message = '';
$classes_enseignant = get_classes_enseignees_for_select($conn, SIMULATED_ENSEIGNANT_ID_ABSENCES);
$matieres_pour_classe_selectionnee = [];
$etudiants_liste = [];
$absences_enregistrees = [];

$selected_classe_id = isset($_REQUEST['id_classe_selection']) ? (int)$_REQUEST['id_classe_selection'] : null;
$selected_matiere_id = isset($_REQUEST['id_matiere_selection']) ? (int)$_REQUEST['id_matiere_selection'] : null;
$selected_date = isset($_REQUEST['date_absence_selection']) ? $_REQUEST['date_absence_selection'] : date('Y-m-d'); 

if ($selected_classe_id) {
    $matieres_pour_classe_selectionnee = get_matieres_by_enseignant_and_classe($conn, SIMULATED_ENSEIGNANT_ID_ABSENCES, $selected_classe_id);
    if ($selected_matiere_id && $selected_date) { // Afficher la liste seulement si tous les filtres sont là
        $etudiants_liste = get_etudiants_by_classe($conn, $selected_classe_id);
        $absences_enregistrees = get_recorded_absences($conn, $selected_classe_id, $selected_matiere_id, $selected_date);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_absences'])) {
    $id_classe_form = filter_input(INPUT_POST, 'id_classe_selection', FILTER_VALIDATE_INT);
    $id_matiere_form = filter_input(INPUT_POST, 'id_matiere_selection', FILTER_VALIDATE_INT);
    $date_absence_form = $_POST['date_absence_selection'] ?? '';
    $absences_saisies = $_POST['absences'] ?? []; 
    $raisons_saisies = $_POST['raisons'] ?? [];  

    if (empty($id_classe_form) || empty($id_matiere_form) || empty($date_absence_form)) {
        $message = "<div class='alert alert-danger'>Veuillez sélectionner une classe, une matière et une date.</div>";
    } else {
        $etudiants_de_la_classe_pour_traitement = get_etudiants_by_classe($conn, $id_classe_form);
        $count_saved = 0; $count_updated = 0; $count_deleted_on_uncheck = 0;

        foreach ($etudiants_de_la_classe_pour_traitement as $etudiant) {
            $etudiant_id = $etudiant['etudiant_id'];
            $est_absent_saisi = isset($absences_saisies[$etudiant_id]);
            $raison_saisie = isset($raisons_saisies[$etudiant_id]) ? trim($raisons_saisies[$etudiant_id]) : null;

            $stmt_check = $conn->prepare("SELECT id, est_justifie FROM absences WHERE id_etudiant = :id_etudiant AND id_matiere = :id_matiere AND date_absence = :date_absence");
            $stmt_check->execute([':id_etudiant' => $etudiant_id, ':id_matiere' => $id_matiere_form, ':date_absence' => $date_absence_form]);
            $absence_existante = $stmt_check->fetch(PDO::FETCH_ASSOC);

            try {
                if ($est_absent_saisi) { 
                    if ($absence_existante) { 
                        if ($absence_existante['raison'] != $raison_saisie && !$absence_existante['est_justifie']) {
                            $stmt_update = $conn->prepare("UPDATE absences SET raison = :raison WHERE id = :id_absence");
                            $stmt_update->execute([':raison' => $raison_saisie, ':id_absence' => $absence_existante['id']]);
                            $count_updated++;
                        }
                    } else { 
                        $stmt_insert = $conn->prepare("INSERT INTO absences (id_etudiant, id_matiere, date_absence, heure_debut, heure_fin, raison, est_justifie) VALUES (:id_etudiant, :id_matiere, :date_absence, NULL, NULL, :raison, 0)");
                        $stmt_insert->execute([':id_etudiant' => $etudiant_id, ':id_matiere' => $id_matiere_form, ':date_absence' => $date_absence_form, ':raison' => $raison_saisie]);
                        $count_saved++;
                    }
                } else { 
                    if ($absence_existante && !$absence_existante['est_justifie']) { 
                        $stmt_delete = $conn->prepare("DELETE FROM absences WHERE id = :id_absence");
                        $stmt_delete->execute([':id_absence' => $absence_existante['id']]);
                        $count_deleted_on_uncheck++;
                    }
                }
            } catch (PDOException $e) { /* ... gestion erreur ... */ }
        }
        if (empty($message)) {
            $msg_parts = [];
            if($count_saved > 0) $msg_parts[] = "$count_saved nouvelle(s) absence(s) enregistrée(s)";
            if($count_updated > 0) $msg_parts[] = "$count_updated raison(s) mise(s) à jour";
            if($count_deleted_on_uncheck > 0) $msg_parts[] = "$count_deleted_on_uncheck absence(s) annulée(s)";
            if(!empty($msg_parts)){
                 $message = "<div class='alert alert-success'>Saisie des absences traitée : " . implode(', ', $msg_parts) . ".</div>";
            } else {
                 $message = "<div class='alert alert-info'>Aucune modification apportée aux absences.</div>";
            }
        }
        // Recharger les absences pour la vue actuelle
        $selected_classe_id = $id_classe_form; // S'assurer que les filtres restent après POST
        $selected_matiere_id = $id_matiere_form;
        $selected_date = $date_absence_form;
        $matieres_pour_classe_selectionnee = get_matieres_by_enseignant_and_classe($conn, SIMULATED_ENSEIGNANT_ID_ABSENCES, $selected_classe_id); // Recharger pour le select
        $etudiants_liste = get_etudiants_by_classe($conn, $selected_classe_id); // Recharger pour la table
        $absences_enregistrees = get_recorded_absences($conn, $selected_classe_id, $selected_matiere_id, $selected_date);
    }
}

$page_title = "Saisie des Absences";
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
        /* --- CSS COMPLET INTÉGRÉ DU DASHBOARD ENSEIGNANT --- */
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
    --info-color: #3B82F6;

    --success-color: #10B981;
    --warning-color: #F59E0B;
    --danger-color: #EF4444;

    --font-family: 'Inter', sans-serif;

    --border-radius-md: 10px;
    --border-radius-sm: 8px;

    --shadow-sm: 0 4px 12px rgba(37, 99, 235, 0.08);
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
        .form-section-container { margin-bottom: 24px; padding: 20px; border-radius: var(--border-radius-md); background-color: var(--card-bg); border: 1px solid var(--border-color); }
        .form-section-container h3 { color: var(--primary-color); margin-top: 0; margin-bottom: 20px; font-size: 18px; font-weight: 600; display: flex; align-items: center; gap: 10px; padding-bottom: 10px; border-bottom: 1px solid var(--primary-light); }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 500; font-size: 14px; color: var(--text-color); }
        .form-group label span[style*="color:red"] { color: var(--danger-color) !important; }
        .form-group input[type="text"], .form-group input[type="url"], .form-group input[type="date"], .form-group input[type="time"], .form-group textarea, .form-group select { width: 100%; padding: 10px 12px; border-radius: var(--border-radius-sm); border: 1px solid var(--border-color); font-size: 14px; background-color: var(--page-bg); color: var(--text-color); box-sizing: border-box; }
        .form-group input:focus, .form-group textarea:focus, .form-group select:focus { border-color: var(--primary-color); box-shadow: 0 0 0 3px var(--primary-light); outline: none; }
        .btn-submit-form { background-color: var(--primary-color); color: white; padding: 10px 20px; border: none; border-radius: var(--border-radius-md); font-size: 14px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; }
        .btn-submit-form:hover { background-color: var(--primary-dark); }
        .alert { padding: 12px 16px; margin-bottom: 20px; border-radius: var(--border-radius-sm); border-width:1px; border-style:solid; font-size:14px;}
        .alert-success { background-color: #ECFDF3; color: #027A48; border-color: #ABEFC6; }
        .alert-danger { background-color: #FEF3F2; color: #B42318; border-color: #FECDCA; }
        .alert-info { background-color: #EFF4FF; color: #2970FF; border-color: #B2CCFF; }
        .no-data-message { text-align:center; padding:20px; color:var(--text-light); font-style: italic; }

        /* Styles spécifiques pour la saisie des absences */
        .filters-absences { display: flex; flex-wrap: wrap; gap: 15px; margin-bottom: 20px; padding: 15px; background-color: var(--secondary-color); border-radius: var(--border-radius-md); border: 1px solid var(--border-color); }
        .filters-absences .form-group { margin-bottom: 0; flex: 1 1 200px; }
        .filters-absences button { align-self: flex-end; padding:10px 15px !important;} /* Important pour surcharger btn-submit-form */

        .absences-saisie-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .absences-saisie-table th, .absences-saisie-table td { padding: 10px 12px; text-align: left; border-bottom: 1px solid var(--border-color); vertical-align: middle; }
        .absences-saisie-table thead th { background-color: var(--secondary-color); color: var(--text-light); font-size: 12px; text-transform: uppercase; font-weight: 500; }
        .absences-saisie-table tbody tr:hover { background-color: var(--primary-light); }
        .absences-saisie-table td { font-size: 14px; color: var(--text-color); }
        .absences-saisie-table .etudiant-avatar-small { width: 30px; height: 30px; border-radius: 50%; object-fit: cover; margin-right: 10px; vertical-align: middle; }
        .absences-saisie-table .etudiant-name-cell { display: flex; align-items: center; }
        .absences-saisie-table input[type="checkbox"] { width: 18px; height: 18px; accent-color: var(--primary-color); cursor:pointer;}
        .absences-saisie-table input[type="text"].raison-input { width: 95%; padding: 6px 8px; font-size:13px; border-radius:var(--border-radius-sm); border:1px solid var(--border-color);}
        .absences-saisie-table .status-badge { padding: 4px 8px; border-radius: 12px; font-size: 0.8em; font-weight: 500; color: white; }
        .absences-saisie-table .status-badge.justifiee { background-color: var(--success-color); }
        .absences-saisie-table .status-badge.non-justifiee { background-color: var(--danger-color); }
        .submit-absences-btn-container { text-align:right; margin-top:20px; padding-top:20px; border-top:1px solid var(--border-color);}
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
                    <li><a href="messagerie.php"><i class="fas fa-envelope"></i> Messagerie</a></li>
                    <li><a href="saisie_absences.php" class="<?php echo ($current_page == 'saisie_absences.php') ? 'active' : ''; ?>"><i class="fas fa-user-check"></i> Saisir Absences</a></li>
                </ul>
            </nav>
            <div class="sidebar-nav logout-link"><ul><li><a href="../../logout.php"><i class="fas fa-sign-out-alt"></i> Déconnexion</a></li></ul></div>
        </aside>
        
        <div class="main-wrapper">
            <header class="top-bar">
                <div class="search-bar"><i class="fas fa-search"></i><input type="text" placeholder="Rechercher..."></div>
                <div class="top-bar-actions">
                    <a href="#" class="btn-add-new" onclick="document.getElementById('formSaisieAbsences').scrollIntoView({behavior:'smooth'}); return false;"><i class="fas fa-user-plus"></i> Saisir Absences</a>
                    <a href="#" class="icon-btn" id="theme-toggle" title="Changer de thème"><i class="fas fa-moon"></i></a>
                    <a href="#" class="icon-btn" title="Notifications"><i class="fas fa-bell"></i></a>
                    <div class="user-profile-widget">
                        <img src="<?php echo $enseignant_avatar; ?>" alt="Avatar">
                        <div class="user-info"><span class="user-name"><?php echo $enseignant_nom_complet; ?></span><span class="user-role">Enseignant</span></div>
                    </div>
                </div>
            </header>

            <main class="content-area">
                <div class="main-column">
                     <div class="page-container">
                        <div class="page-header">
                            <h2><i class="fas fa-user-clock"></i> Saisie des Absences</h2>
                            <p>Sélectionnez une classe, une matière et une date pour enregistrer les absences.</p>
                        </div>

                        <?php if ($message): ?>
                            <?php echo $message; ?>
                        <?php endif; ?>

                        <form action="saisie_absences.php" method="POST" class="filters-absences" id="formSaisieAbsencesFilters">
                            <div class="form-group">
                                <label for="id_classe_selection">Classe <span style="color:red;">*</span></label>
                                <select name="id_classe_selection" id="id_classe_selection" required onchange="this.form.submit()">
                                    <option value="">-- Choisir une classe --</option>
                                    <?php foreach ($classes_enseignant as $classe): ?>
                                        <option value="<?php echo $classe['id']; ?>" <?php echo ($selected_classe_id == $classe['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($classe['nom_classe'] . ' (' . $classe['niveau'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="id_matiere_selection">Matière <span style="color:red;">*</span></label>
                                <select name="id_matiere_selection" id="id_matiere_selection" required <?php echo empty($matieres_pour_classe_selectionnee) ? 'disabled' : '';?>>
                                    <option value="">-- Choisir une matière --</option>
                                    <?php foreach ($matieres_pour_classe_selectionnee as $matiere): ?>
                                        <option value="<?php echo $matiere['id']; ?>" <?php echo ($selected_matiere_id == $matiere['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($matiere['nom_matiere']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="date_absence_selection">Date de l'absence <span style="color:red;">*</span></label>
                                <input type="date" id="date_absence_selection" name="date_absence_selection" value="<?php echo htmlspecialchars($selected_date); ?>" required max="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <!-- Le name="afficher_etudiants" permet de distinguer ce POST de celui de la saisie -->
                            <button type="submit" name="afficher_etudiants" class="btn-submit-form"><i class="fas fa-users"></i> Afficher Étudiants</button>
                        </form>

                        <?php if ($selected_classe_id && $selected_matiere_id && $selected_date && !empty($etudiants_liste)): ?>
                            <div class="form-section-container" style="margin-top:20px;">
                                <h3><i class="fas fa-list-check"></i> Liste des Étudiants pour la saisie du <?php echo date('d/m/Y', strtotime($selected_date)); ?></h3>
                                <form action="saisie_absences.php" method="POST">
                                    <!-- Champs cachés pour renvoyer les filtres lors de la soumission des absences -->
                                    <input type="hidden" name="id_classe_selection" value="<?php echo $selected_classe_id; ?>">
                                    <input type="hidden" name="id_matiere_selection" value="<?php echo $selected_matiere_id; ?>">
                                    <input type="hidden" name="date_absence_selection" value="<?php echo $selected_date; ?>">

                                    <table class="absences-saisie-table">
                                        <thead>
                                            <tr>
                                                <th>Étudiant</th>
                                                <th style="text-align:center;">Absent(e) ?</th>
                                                <th>Raison de l'absence (optionnel)</th>
                                                <th>Statut Actuel</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($etudiants_liste as $etudiant): 
                                                $avatar_etu = !empty($etudiant['photo']) ? '../../uploads/profiles/' . htmlspecialchars($etudiant['photo']) : '../../assets/images/default_avatar.png';
                                                $absence_etu_enregistree = $absences_enregistrees[$etudiant['etudiant_id']] ?? null;
                                                $is_checked = $absence_etu_enregistree && !$absence_etu_enregistree['est_justifie'];
                                                $raison_value = $absence_etu_enregistree['raison'] ?? '';
                                                $is_justified_already = $absence_etu_enregistree && $absence_etu_enregistree['est_justifie'];
                                            ?>
                                            <tr>
                                                <td class="etudiant-name-cell">
                                                    <img src="<?php echo $avatar_etu; ?>" alt="Avatar" class="etudiant-avatar-small">
                                                    <?php echo htmlspecialchars($etudiant['prenom'] . ' ' . $etudiant['nom']); ?>
                                                </td>
                                                <td style="text-align:center;">
                                                    <?php if ($is_justified_already): ?>
                                                        <span class="status-badge justifiee" title="Cette absence a déjà été justifiée et ne peut être modifiée ici.">Justifiée</span>
                                                    <?php else: ?>
                                                        <input type="checkbox" name="absences[<?php echo $etudiant['etudiant_id']; ?>]" id="absent_<?php echo $etudiant['etudiant_id']; ?>"
                                                               <?php echo $is_checked ? 'checked' : ''; ?>>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                     <?php if (!$is_justified_already): ?>
                                                        <input type="text" name="raisons[<?php echo $etudiant['etudiant_id']; ?>]" class="raison-input" 
                                                               value="<?php echo htmlspecialchars($raison_value); ?>" placeholder="Ex: Malade, Retard...">
                                                     <?php else: ?>
                                                        <em><?php echo !empty($raison_value) ? htmlspecialchars($raison_value) : 'Motif de justification non éditable ici'; ?></em>
                                                     <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($absence_etu_enregistree): ?>
                                                        <?php if ($is_justified_already): ?>
                                                            <span class="status-badge justifiee">Déjà Justifiée</span>
                                                        <?php else: ?>
                                                            <span class="status-badge non-justifiee">Déjà Marqué Absent</span>
                                                        <?php endif; ?>
                                                    <?php else: echo "Présent(e)"; endif; ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                    <div class="submit-absences-btn-container">
                                        <button type="submit" name="submit_absences" class="btn-submit-form">
                                            <i class="fas fa-save"></i> Enregistrer les Absences
                                        </button>
                                    </div>
                                </form>
                            </div>
                        <?php elseif ($selected_classe_id && $selected_matiere_id && $selected_date && empty($etudiants_liste)): ?>
                            <p class="no-data-message" style="margin-top:20px;">Aucun étudiant trouvé pour la classe sélectionnée.</p>
                        <?php elseif ($selected_classe_id && $selected_matiere_id && empty($selected_date)): ?>
                             <p class="no-data-message" style="margin-top:20px;">Veuillez sélectionner une date.</p>
                        <?php elseif ($selected_classe_id && empty($selected_matiere_id)): ?>
                             <p class="no-data-message" style="margin-top:20px;">Veuillez sélectionner une matière pour cette classe.</p>
                        <?php elseif (empty($selected_classe_id) && !empty($classes_enseignant)): ?>
                            <p class="no-data-message" style="margin-top:20px;">Veuillez sélectionner une classe pour commencer.</p>
                        <?php elseif (empty($classes_enseignant)): ?>
                            <p class="no-data-message" style="margin-top:20px;">Aucune classe ne vous est assignée. Impossible de saisir des absences.</p>
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

            const classeSelect = document.getElementById('id_classe_selection');
            const matiereSelect = document.getElementById('id_matiere_selection');

            // Si une classe est sélectionnée, on soumet le formulaire pour charger les matières associées
            // Ceci est déjà géré par onchange="this.form.submit()" sur le select de la classe.
            // On pourrait ajouter une logique JS plus complexe pour charger les matières via AJAX
            // si on ne veut pas recharger toute la page, mais pour l'instant, le rechargement est simple.

            // Mettre le focus sur le premier champ de raison si la table est affichée
            const firstRaisonInput = document.querySelector('.absences-saisie-table input[type="text"].raison-input');
            if (firstRaisonInput) {
                // Optionnel: Mettre le focus au premier champ raison si la table est visible
                // firstRaisonInput.focus();
            }
        });
    </script>
</body>
</html>