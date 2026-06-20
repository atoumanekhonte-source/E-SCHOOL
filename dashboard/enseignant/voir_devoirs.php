<?php
// /dashboard/enseignant/voir_devoirs.php

// PAS D'AUTHENTIFICATION COMPLEXE POUR CET EXEMPLE
require_once '../../config/db.php';

$database = new Database();
$conn = $database->connect();

// --- ID Enseignant Simulé ---
define('SIMULATED_ENSEIGNANT_ID_VOIR_DEVOIRS', 1); // !! IMPORTANT: ID enseignant existant

// --- Fonctions ---
function get_enseignant_info_for_voir_devoirs($db_conn, $id_enseignant_table) {
    $stmt = $db_conn->prepare("SELECT u.nom, u.prenom, u.photo FROM enseignants ens JOIN utilisateurs u ON ens.id_utilisateur = u.id WHERE ens.id = :id_enseignant");
    $stmt->bindParam(':id_enseignant', $id_enseignant_table, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function get_devoirs_par_enseignant_detailed($db_conn, $id_enseignant, $id_matiere_filter = null) {
    $sql = "
        SELECT 
            d.id           AS devoir_id,
            d.titre        AS devoir_titre,
            d.type         AS devoir_type,
            d.date_limite,
            d.fichier      AS fichier_sujet,
            m.nom_matiere,
            c.id           AS classe_id,
            c.nom_classe
        FROM devoirs d
        JOIN matieres m ON d.id_matiere = m.id
        -- On joint maintenant classes via m.id_classe, pas via d.id_classe
        JOIN classes c  ON m.id_classe = c.id
        WHERE d.id_enseignant = :id_enseignant
    ";

    $params = [':id_enseignant' => $id_enseignant];

    if ($id_matiere_filter) {
        $sql .= " AND d.id_matiere = :id_matiere ";
        $params[':id_matiere'] = $id_matiere_filter;
    }

    $sql .= " ORDER BY d.date_depot DESC, d.id DESC";

    $stmt = $db_conn->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


// Récupère les étudiants d'une classe avec le statut de leur soumission pour un devoir donné
function get_etudiants_soumissions_pour_devoir($db_conn, $id_devoir, $id_classe) {
    $stmt = $db_conn->prepare("
        SELECT 
            e.id as etudiant_id, 
            u.nom as etudiant_nom, 
            u.prenom as etudiant_prenom, 
            u.photo as etudiant_photo,
            dr.id as remise_id, 
            dr.fichier_remis, 
            dr.date_remise,
            n.note,
            d.type as type_eval_devoir -- Pour correspondre à notes.type_eval
        FROM etudiants e
        JOIN utilisateurs u ON e.id_utilisateur = u.id
        JOIN devoirs d ON d.id = :id_devoir_main_query -- Pour obtenir le type du devoir
        LEFT JOIN devoirs_remis dr ON e.id = dr.id_etudiant AND dr.id_devoir = :id_devoir
        LEFT JOIN notes n ON e.id = n.id_etudiant AND d.id_matiere = n.id_matiere AND d.type = n.type_eval
        WHERE e.id_classe = :id_classe AND d.id = :id_devoir_where_clause
        ORDER BY u.nom ASC, u.prenom ASC
    ");
    $stmt->bindParam(':id_devoir', $id_devoir, PDO::PARAM_INT);
    $stmt->bindParam(':id_devoir_main_query', $id_devoir, PDO::PARAM_INT);
    $stmt->bindParam(':id_devoir_where_clause', $id_devoir, PDO::PARAM_INT);
    $stmt->bindParam(':id_classe', $id_classe, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Pour le filtre par matière
function get_matieres_enseignees_for_filter_devoirs($db_conn, $id_enseignant) {
    $stmt = $db_conn->prepare("
        SELECT m.id, m.nom_matiere, c.nom_classe 
        FROM matieres m
        JOIN classes c ON m.id_classe = c.id
        WHERE m.id_enseignant = :id_enseignant 
        ORDER BY m.nom_matiere ASC
    ");
    $stmt->bindParam(':id_enseignant', $id_enseignant, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


// --- Logique de la page ---
$enseignant_info_page = get_enseignant_info_for_voir_devoirs($conn, SIMULATED_ENSEIGNANT_ID_VOIR_DEVOIRS);
if (!$enseignant_info_page) die("Erreur enseignant simulé.");

$enseignant_avatar = !empty($enseignant_info_page['photo']) ? '../../uploads/profiles/' . htmlspecialchars($enseignant_info_page['photo']) : '../../assets/images/default_avatar_teacher.png';
$enseignant_nom_complet = htmlspecialchars($enseignant_info_page['prenom'] . ' ' . $enseignant_info_page['nom']);

$message = '';
$selected_devoir_id = isset($_GET['id_devoir']) ? (int)$_GET['id_devoir'] : null;
$selected_matiere_id_for_list = isset($_GET['id_matiere_filter']) ? (int)$_GET['id_matiere_filter'] : null;

$devoirs_liste = [];
$etudiants_soumissions = [];
$devoir_concerne_details = null;

if ($selected_devoir_id) {
    // Afficher les soumissions pour un devoir spécifique
    $stmt_devoir = $conn->prepare("SELECT * FROM devoirs WHERE id = :id_devoir AND id_enseignant = :id_enseignant");
    $stmt_devoir->execute([':id_devoir' => $selected_devoir_id, ':id_enseignant' => SIMULATED_ENSEIGNANT_ID_VOIR_DEVOIRS]);
    $devoir_concerne_details = $stmt_devoir->fetch(PDO::FETCH_ASSOC);

    if ($devoir_concerne_details) {
        $etudiants_soumissions = get_etudiants_soumissions_pour_devoir($conn, $selected_devoir_id, $devoir_concerne_details['id_classe']);
    } else {
        $message = "<div class='alert alert-danger'>Devoir non trouvé ou non autorisé.</div>";
        $selected_devoir_id = null; // Revenir à la liste des devoirs
    }
}

// Si aucun devoir spécifique n'est sélectionné, ou si la sélection était invalide, on charge la liste des devoirs
if (!$selected_devoir_id) {
    $devoirs_liste = get_devoirs_par_enseignant_detailed($conn, SIMULATED_ENSEIGNANT_ID_VOIR_DEVOIRS, $selected_matiere_id_for_list);
}

$matieres_enseignant_options = get_matieres_enseignees_for_filter_devoirs($conn, SIMULATED_ENSEIGNANT_ID_VOIR_DEVOIRS);


$page_title = $selected_devoir_id && $devoir_concerne_details ? "Soumissions pour: " . htmlspecialchars($devoir_concerne_details['titre']) : "Voir Devoirs Remis";
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - E-SCHOOL</title>
    <link rel="stylesheet" href="../../assets/css/style.css"> <!-- CSS Global -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* COPIEZ ICI L'INTÉGRALITÉ DU CSS DE LA PAGE INDEX.PHP ENSEIGNANT */
        /* Si votre ../../assets/css/style.css est déjà complet avec ces styles, cette section est redondante. */
        :root {
    /* Bleu principal */
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

    /* Couleurs secondaires */
    --accent-blue: #3B82F6;
    --info-color: #3B82F6;

    /* Statuts */
    --success-color: #10B981;
    --warning-color: #F59E0B;
    --danger-color: #EF4444;

    /* Police */
    --font-family: 'Inter', sans-serif;

    /* Rayons */
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
        .page-header { margin-bottom: 24px; padding-bottom:16px; border-bottom: 1px solid var(--border-color);}
        .page-header h2 { color: var(--text-color); font-size: 20px; font-weight: 600; margin: 0; display: flex; align-items:center; gap:10px;}
        .page-header p { color: var(--text-light); font-size: 14px; margin-top: 4px; margin-bottom:0; }
        .page-header .back-link { color: var(--primary-color); text-decoration: none; font-weight: 500; display: inline-block; margin-bottom: 15px;}
        .page-header .back-link i { margin-right: 5px; }
        
        .table-container { /* Pour la liste des devoirs ou la liste des soumissions */
            margin-top: 20px;
        }
        .custom-table { width: 100%; border-collapse: collapse; }
        .custom-table th, .custom-table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid var(--border-color); }
        .custom-table thead th { background-color: var(--secondary-color); color: var(--text-light); font-size: 12px; text-transform: uppercase; font-weight: 500; }
        .custom-table tbody tr:hover { background-color: var(--primary-light); }
        .custom-table td { font-size: 14px; color: var(--text-color); }
        .custom-table .type-badge { padding: 3px 8px; border-radius: var(--border-radius-sm); font-size: 12px; font-weight: 500; color: white; text-transform: uppercase;}
        .custom-table .type-badge.td { background-color: var(--primary-color); }
        .custom-table .type-badge.tc { background-color: var(--accent-blue); }
        .custom-table .devoir-titre, .custom-table .etudiant-name { font-weight: 500; }
        .custom-table .actions-cell a, .custom-table .actions-cell button { margin-right: 8px; text-decoration:none; }
        .btn-action-icon { text-decoration: none; font-size: 14px; padding: 8px; border-radius: 50%; display: inline-flex; align-items: center; justify-content:center; width: 32px; height:32px; border: 1px solid transparent; transition: background-color 0.2s, color 0.2s, border-color 0.2s; background:none; cursor:pointer; }
        .btn-action-icon.btn-view-submissions { color: var(--success-color); }
        .btn-action-icon.btn-view-submissions:hover { background-color: #ECFDF3; border-color: var(--success-color); }
        .btn-action-icon.btn-download-submission { color: var(--primary-color); }
        .btn-action-icon.btn-download-submission:hover { background-color: var(--primary-light); border-color: var(--primary-color); }
        .btn-action-icon.btn-grade { color: var(--accent-blue); }
        .btn-action-icon.btn-grade:hover { background-color: #EFF4FF; border-color: var(--accent-blue); }
        .submission-status { font-size: 0.9em; }
        .submission-status.remis { color: var(--success-color); }
        .submission-status.non-remis { color: var(--text-light); font-style: italic; }
        .etudiant-avatar-small { width: 28px; height: 28px; border-radius: 50%; object-fit: cover; margin-right: 8px; vertical-align: middle; }

        .filter-form { margin-bottom: 20px; display:flex; gap:10px; align-items:center; padding: 10px; background-color: var(--secondary-color); border-radius: var(--border-radius-md); }
        .filter-form label { margin-bottom:0; white-space:nowrap; color:var(--text-light); font-weight:500; }
        .filter-form select { flex-grow:1; padding:8px 10px; border-radius:var(--border-radius-sm); border:1px solid var(--border-color); background-color: var(--card-bg); color: var(--text-color); }
        .alert { padding: 12px 16px; margin-bottom: 20px; border-radius: var(--border-radius-sm); border-width:1px; border-style:solid; font-size:14px;}
        .alert-success { background-color: #ECFDF3; color: #027A48; border-color: #ABEFC6; }
        .alert-danger { background-color: #FEF3F2; color: #B42318; border-color: #FECDCA; }
        .no-data-message { text-align:center; padding:20px; color:var(--text-light); font-style: italic; }
    </style>
</head>
<body>
    <div class="dashboard-container">
         <aside class="sidebar">
            <div class="sidebar-logo"><i class="fas fa-chalkboard-teacher logo-icon"></i>E-SCHOOL</div>
            <nav class="sidebar-nav">
                <ul>
                    <li><a href="index.php"><i class="fas fa-th-large"></i> Tableau de Bord</a></li>
                    <li><a href="deposer_cours.php"><i class="fas fa-file-medical"></i> Déposer Cours</a></li>
                    <li><a href="enregistrer_cours.php"><i class="fas fa-video"></i> Planifier/Enregistrer Live</a></li>
                    <li><a href="deposer_td.php"><i class="fas fa-tasks"></i> Déposer TD/TC</a></li>
                    <li><a href="noter_etudiants.php"><i class="fas fa-edit"></i> Noter Étudiants</a></li>
                    <li><a href="voir_devoirs.php" class="<?php echo ($current_page == 'voir_devoirs.php') ? 'active' : ''; ?>"><i class="fas fa-eye"></i> Voir Devoirs Remis</a></li>
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
                            <?php if ($selected_devoir_id && $devoir_concerne_details): ?>
                                <a href="voir_devoirs.php" class="back-link"><i class="fas fa-arrow-left"></i> Retour à la liste des devoirs</a>
                                <h2><i class="fas fa-user-graduate"></i> Soumissions pour: <?php echo htmlspecialchars($devoir_concerne_details['devoir_titre']); ?></h2>
                                <p>Matière: <?php echo htmlspecialchars($devoir_concerne_details['nom_matiere']); ?> - Classe: <?php echo htmlspecialchars($devoir_concerne_details['nom_classe']); ?></p>
                            <?php else: ?>
                                <h2><i class="fas fa-eye"></i> Voir les Devoirs Remis</h2>
                                <p>Consultez les soumissions des étudiants pour les devoirs que vous avez assignés.</p>
                            <?php endif; ?>
                        </div>

                        <?php if ($message): ?>
                            <?php echo $message; ?>
                        <?php endif; ?>

                        <?php if ($selected_devoir_id && $devoir_concerne_details): ?>
                            <!-- Affichage des soumissions pour un devoir spécifique -->
                            <div class="table-container">
                                <?php if (empty($etudiants_soumissions)): ?>
                                    <p class="no-data-message">Aucun étudiant dans cette classe ou aucune soumission pour ce devoir.</p>
                                <?php else: ?>
                                    <table class="custom-table">
                                        <thead>
                                            <tr>
                                                <th>Étudiant</th>
                                                <th>Statut Soumission</th>
                                                <th>Date Remise</th>
                                                <th>Fichier Remis</th>
                                                <th>Note Actuelle</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($etudiants_soumissions as $soumission): 
                                                $avatar_etu = !empty($soumission['etudiant_photo']) ? '../../uploads/profiles/' . htmlspecialchars($soumission['etudiant_photo']) : '../../assets/images/default_avatar.png';
                                            ?>
                                            <tr>
                                                <td class="etudiant-name">
                                                    <img src="<?php echo $avatar_etu; ?>" alt="avatar" class="etudiant-avatar-small">
                                                    <?php echo htmlspecialchars($soumission['etudiant_prenom'] . ' ' . $soumission['etudiant_nom']); ?>
                                                </td>
                                                <td>
                                                    <?php if ($soumission['remise_id']): ?>
                                                        <span class="submission-status remis"><i class="fas fa-check-circle"></i> Remis</span>
                                                    <?php else: ?>
                                                        <span class="submission-status non-remis">Non Remis</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo $soumission['date_remise'] ? date('d/m/Y H:i', strtotime($soumission['date_remise'])) : '-'; ?></td>
                                                <td>
                                                    <?php if ($soumission['fichier_remis']): ?>
                                                        <a href="../../uploads/devoirs_remis/<?php echo htmlspecialchars($soumission['fichier_remis']); ?>" download class="btn-action-icon btn-download-submission" title="Télécharger la soumission">
                                                            <i class="fas fa-download"></i>
                                                        </a>
                                                        <?php /* echo htmlspecialchars(substr($soumission['fichier_remis'],0,20)).'...'; */ ?>
                                                    <?php else: echo '-'; endif; ?>
                                                </td>
                                                <td><?php echo isset($soumission['note']) ? htmlspecialchars(number_format($soumission['note'],2)) . '/20' : 'N/A'; ?></td>
                                                <td class="actions-cell">
                                                    <a href="noter_etudiants.php?id_devoir=<?php echo $selected_devoir_id; ?>&id_etudiant=<?php echo $soumission['etudiant_id']; ?>" class="btn-action-icon btn-grade" title="Noter cet étudiant">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <!-- Affichage de la liste des devoirs de l'enseignant -->
                            <form method="GET" action="voir_devoirs.php" class="filter-form">
                                <label for="id_matiere_filter">Filtrer par matière :</label>
                                <select name="id_matiere_filter" id="id_matiere_filter" onchange="this.form.submit()">
                                    <option value="">-- Toutes mes matières --</option>
                                    <?php foreach ($matieres_enseignant_options as $matiere): ?>
                                        <option value="<?php echo $matiere['id']; ?>" <?php echo ($selected_matiere_id_for_list == $matiere['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($matiere['nom_matiere'] . ' (' . $matiere['nom_classe'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </form>

                            <div class="table-container">
                                <?php if (empty($devoirs_liste)): ?>
                                    <p class="no-data-message">Aucun devoir déposé <?php echo $selected_matiere_id_for_list ? 'pour cette matière' : ''; ?>.</p>
                                <?php else: ?>
                                    <table class="custom-table">
                                        <thead>
                                            <tr>
                                                <th>Titre du Devoir</th>
                                                <th>Type</th>
                                                <th>Matière</th>
                                                <th>Classe</th>
                                                <th>Date Limite</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($devoirs_liste as $devoir): ?>
                                            <tr>
                                                <td class="devoir-titre"><?php echo htmlspecialchars($devoir['devoir_titre']); ?></td>
                                                <td><span class="type-badge <?php echo strtolower($devoir['devoir_type']); ?>"><?php echo htmlspecialchars($devoir['devoir_type']); ?></span></td>
                                                <td><?php echo htmlspecialchars($devoir['nom_matiere']); ?></td>
                                                <td><?php echo htmlspecialchars($devoir['nom_classe']); ?></td>
                                                <td><?php echo date('d/m/Y', strtotime($devoir['date_limite'])); ?></td>
                                                <td class="actions-cell">
                                                    <a href="voir_devoirs.php?id_devoir=<?php echo $devoir['devoir_id']; ?>" class="btn-action-icon btn-view-submissions" title="Voir les soumissions"><i class="fas fa-users"></i></a>
                                                    <a href="modifier_td.php?id_devoir=<?php echo $devoir['devoir_id']; ?>" class="btn-action-icon btn-edit" title="Modifier ce devoir"><i class="fas fa-edit"></i></a>
                                                    <!-- Supprimer lien à ajouter ici -->
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                            </div>
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