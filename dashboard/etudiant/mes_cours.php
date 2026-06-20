<?php
// /dashboard/etudiant/mes_cours.php
// VERSION: Affichage de TOUS les cours/matières, sans filtre initial par classe étudiante.

session_start();

require_once '../../config/db.php';
$database = new Database();
$conn = $database->connect();

// --- Fonctions de récupération des données ---

function get_all_matieres_with_details($db_conn) {
    $stmt = $db_conn->prepare("
        SELECT m.id, m.nom_matiere, m.code_matiere, 
               c.nom_classe, c.niveau,
               CONCAT(u_ens.prenom, ' ', u_ens.nom) AS nom_complet_enseignant
        FROM matieres m
        JOIN classes c ON m.id_classe = c.id
        LEFT JOIN enseignants ens ON m.id_enseignant = ens.id
        LEFT JOIN utilisateurs u_ens ON ens.id_utilisateur = u_ens.id
        ORDER BY c.nom_classe ASC, m.nom_matiere ASC
    ");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_matiere_details($db_conn, $id_matiere) {
    $stmt = $db_conn->prepare("
        SELECT m.id, m.nom_matiere, m.code_matiere, m.id_classe, 
               c.nom_classe AS nom_classe_matiere,
               CONCAT(u.prenom, ' ', u.nom) AS nom_complet_enseignant,
               u.prenom AS prenom_enseignant, u.nom AS nom_enseignant
        FROM matieres m
        JOIN classes c ON m.id_classe = c.id
        LEFT JOIN enseignants ens ON m.id_enseignant = ens.id
        LEFT JOIN utilisateurs u ON ens.id_utilisateur = u.id
        WHERE m.id = :id_matiere
    ");
    $stmt->bindParam(':id_matiere', $id_matiere, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function get_cours_deposes_for_matiere($db_conn, $id_matiere) {
    $stmt = $db_conn->prepare("
        SELECT id, titre, description, fichier_support AS fichier_ressource, lien_visionnage, date_depot
        FROM cours_deposes
        WHERE id_matiere = :id_matiere
        ORDER BY date_depot DESC
    ");
    $stmt->bindParam(':id_matiere', $id_matiere, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_td_for_matiere_public($db_conn, $id_matiere) {
    $stmt = $db_conn->prepare("
        SELECT d.id, d.titre, d.fichier, d.date_limite
        FROM devoirs d
        WHERE d.id_matiere = :id_matiere AND d.type = 'TD'
        ORDER BY d.date_depot DESC
    ");
    $stmt->bindParam(':id_matiere', $id_matiere, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_tc_for_matiere_public($db_conn, $id_matiere) {
    $stmt = $db_conn->prepare("
        SELECT d.id, d.titre, d.fichier, d.date_limite
        FROM devoirs d
        WHERE d.id_matiere = :id_matiere AND d.type = 'TC'
        ORDER BY d.date_depot DESC
    ");
    $stmt->bindParam(':id_matiere', $id_matiere, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_live_sessions_for_matiere($db_conn, $id_matiere) {
    $stmt = $db_conn->prepare("
        SELECT id, titre, description, lien_visionnage, date_cours
        FROM cours_en_ligne
        WHERE id_matiere = :id_matiere 
          AND lien_visionnage IS NOT NULL 
          AND lien_visionnage != '' 
          AND date_cours >= CURDATE()
        ORDER BY date_cours ASC
    ");
    $stmt->bindParam(':id_matiere', $id_matiere, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// --- Récupération des infos de session éventuelles ---

$student_avatar = '../../assets/images/default_avatar.png';
$student_name   = 'Visiteur';
$id_classe_etudiant        = null;
$student_session_id        = $_SESSION['user_id'] ?? null;

// Si l’utilisateur connecté est un étudiant, on récupère sa classe
if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'etudiant') {
    $stmt_user_tpl = $conn->prepare("
        SELECT u.prenom, u.nom, u.photo, e.id_classe
        FROM etudiants e
        JOIN utilisateurs u ON e.id_utilisateur = u.id
        WHERE e.id = :etudiant_id_session
    ");
    $stmt_user_tpl->bindParam(':etudiant_id_session', $_SESSION['user_id'], PDO::PARAM_INT);
    $stmt_user_tpl->execute();
    $user_info_tpl = $stmt_user_tpl->fetch(PDO::FETCH_ASSOC);
    if ($user_info_tpl) {
        if (!empty($user_info_tpl['photo'])) {
            $student_avatar = '../../uploads/profiles/' . htmlspecialchars($user_info_tpl['photo']);
        }
        $student_name        = htmlspecialchars($user_info_tpl['prenom'] . ' ' . $user_info_tpl['nom']);
        $id_classe_etudiant  = $user_info_tpl['id_classe'];
    }
}

// --- Variables ajoutées pour le contexte du catalogue ---
$id_classe_pour_contexte          = $id_classe_etudiant;
$student_id_for_catalogue_context = $student_session_id;

// --- Logique de la Page ---

$selected_matiere_id     = isset($_GET['id_matiere']) ? (int) $_GET['id_matiere'] : null;
$matieres_list           = [];
$matiere_details_page    = null;
$cours_deposes           = [];
$td_list                 = [];
$tc_list                 = [];
$live_sessions           = [];
$error_message           = '';

if ($selected_matiere_id) {
    $matiere_details_page = get_matiere_details($conn, $selected_matiere_id);
    if ($matiere_details_page) {
        $cours_deposes = get_cours_deposes_for_matiere($conn, $selected_matiere_id);
        $td_list       = get_td_for_matiere_public($conn, $selected_matiere_id);
        $tc_list       = get_tc_for_matiere_public($conn, $selected_matiere_id);
        $live_sessions = get_live_sessions_for_matiere($conn, $selected_matiere_id);
    } else {
        $error_message = "Matière sélectionnée non trouvée (ID: " . htmlspecialchars($selected_matiere_id) . ").";
        $selected_matiere_id  = null;
    }
}

// Si aucune matière n'est sélectionnée, on charge toutes les matières
if (!$selected_matiere_id) {
    $matieres_list = get_all_matieres_with_details($conn);
}

$page_title   = $selected_matiere_id && $matiere_details_page
    ? "Cours : " . htmlspecialchars($matiere_details_page['nom_matiere'])
    : "Catalogue des Cours";
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - JANGLITECH</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Styles CSS du Dashboard Étudiant (inspiré de SkillSet, adapté pour JANGLITECH) */
        :root {
    --primary-color-student: #0D6EFD;
    --primary-light-student: #D6E4FF;

    --secondary-color-student: #F8FAFC;
    --page-bg-student: #EEF4FF;

    --sidebar-bg-student: #FFFFFF;
    --card-bg-student: #FFFFFF;

    --text-color-student: #1E293B;
    --text-light-student: #64748B;

    --border-color-student: #D6E4FF;

    --accent-color-student: #0D6EFD;

    --font-family-student: 'Inter', sans-serif;

    --border-radius-md: 8px;
    --border-radius-lg: 12px;

    --shadow-sm: 0 1px 2px rgba(0,0,0,.05);
    --shadow-md: 0 4px 8px rgba(0,0,0,.10);

    --success-color: #16A34A;
    --danger-color: #DC2626;
}

body.dark-theme {
    --primary-color-student: #3B82F6;
    --primary-light-student: #1E3A8A;

    --secondary-color-student: #1E293B;
    --page-bg-student: #0F172A;

    --sidebar-bg-student: #162033;
    --card-bg-student: #1E293B;

    --text-color-student: #F8FAFC;
    --text-light-student: #CBD5E1;

    --border-color-student: #334155;

    --accent-color-student: #3B82F6;

    --success-color: #22C55E;
    --danger-color: #EF4444;
}
        body.dark-theme { 
            --primary-color-student: #9B77ED; --primary-light-student: #4A3279;
            --secondary-color-student: #2C2F33; --page-bg-student: #1E1E1E;
            --sidebar-bg-student: #25282C; --card-bg-student: #2D3035;
            --text-color-student: #EAEAEA; --text-light-student: #A0A0A0;
            --border-color-student: #404040; --accent-color-student: #FF8787;
            --success-color: #34D399; --danger-color: #F97066;
        }
        body { font-family: var(--font-family-student); margin: 0; background-color: var(--page-bg-student); color: var(--text-color-student); display: flex; min-height: 100vh; font-size:14px;}
        .dashboard-container { display: flex; width: 100%; }
        .main-wrapper { flex-grow: 1; margin-left: 260px; display: flex; flex-direction: column; width: calc(100% - 260px); background-color: var(--page-bg-student); }
        .content-area { flex-grow: 1; padding: 24px 30px; display: flex; gap: 24px; overflow-y: auto;} /* Retiré margin-left: 30px; */
        .main-column { flex-grow: 1; display: flex; flex-direction: column; gap: 24px;}
        .sidebar { width: 260px; background-color: var(--sidebar-bg-student); padding: 24px 16px; border-right: 1px solid var(--border-color-student); display: flex; flex-direction: column; height: 100vh; position: fixed; left: 0; top: 0; z-index: 1000; overflow-y: auto; }
        .sidebar-logo { display: flex; align-items: center; padding: 0 8px 24px 8px; font-size: 22px; font-weight: 700; color: var(--primary-color-student); }
        .sidebar-logo .logo-icon { margin-right: 10px; font-size: 24px; }
        .sidebar-nav ul { list-style: none; padding: 0; margin: 0; flex-grow: 1; }
        .sidebar-nav li a { display: flex; align-items: center; padding: 10px 12px; color: var(--text-light-student); text-decoration: none; border-radius: var(--border-radius-md); margin-bottom: 6px; font-weight: 500; font-size: 14px; }
        .sidebar-nav li a i { margin-right: 12px; width: 20px; text-align: center; font-size: 16px; }
        .sidebar-nav li a:hover { background-color: var(--primary-light-student); color: var(--primary-color-student); }
        .sidebar-nav li a.active { background-color: var(--primary-color-student); color: white; }
        body.dark-theme .sidebar-nav li a.active { background-color: var(--primary-light-student); color: var(--primary-color-student); }
        .sidebar .sidebar-upgrade { margin-top: auto; padding: 15px; background-color: var(--secondary-color-student); border-radius: var(--border-radius-lg); text-align:center; }
        .sidebar .sidebar-upgrade h4 { margin-top:0; margin-bottom:5px; color:var(--text-color-student); font-size:14px;}
        .sidebar .sidebar-upgrade p { font-size:12px; color:var(--text-light-student); margin-bottom:15px;}
        .sidebar .sidebar-upgrade .btn-upgrade { background-color:var(--primary-color-student); color:white; padding:10px 15px; border-radius:var(--border-radius-md); text-decoration:none; display:block; font-weight:500;}
        .top-bar { background-color: var(--sidebar-bg-student); padding: 0 30px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color-student); height: 70px; box-sizing: border-box; position: sticky; top: 0; z-index: 999; }
        .search-bar { position: relative; }
        .search-bar input { padding: 10px 15px 10px 40px; border-radius: var(--border-radius-md); border: 1px solid var(--border-color-student); width: 300px; background-color: var(--secondary-color-student); color: var(--text-color-student); font-size: 14px;}
        .search-bar i.fa-search { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: var(--text-light-student); }
        .top-bar-actions { display: flex; align-items: center; gap: 16px; }
        .btn-live { background-color: var(--accent-color-student); color: white; padding: 8px 16px; text-decoration: none; border-radius: var(--border-radius-md); font-weight: 500; font-size: 14px; display: flex; align-items: center; gap: 8px;}
        .top-bar-actions .icon-btn { color: var(--text-light-student); font-size: 20px; cursor: pointer; padding: 8px; border-radius: 50%;}
        .top-bar-actions .icon-btn:hover { color: var(--primary-color-student); background-color: var(--primary-light-student); }
        .user-profile-widget { display: flex; align-items: center; gap: 12px; }
        .user-profile-widget img { width: 40px; height: 40px; border-radius: 50%; object-fit: cover;}
        .page-container { background-color: var(--card-bg-student); padding: 24px; border-radius: var(--border-radius-lg); box-shadow: var(--shadow-sm); border: 1px solid var(--border-color-student); width: 100%; }
        .page-header { margin-bottom: 24px; padding-bottom:16px; border-bottom: 1px solid var(--border-color-student);}
        .page-header h2 { color: var(--text-color-student); font-size: 20px; font-weight: 600; margin: 0; display: flex; align-items:center; gap:10px;}
        .page-header p { color: var(--text-light-student); font-size: 14px; margin-top: 4px; margin-bottom:0; }
        .course-list-container h2, .course-detail-header h2 { color: var(--primary-color-student); margin-bottom: 5px;}
        .course-list-container p, .course-detail-header p { color: var(--text-light-student); margin-top: 0; margin-bottom: 20px;}
        .course-item-card { background-color: var(--secondary-color-student); padding: 20px; border-radius: var(--border-radius-md); margin-bottom: 15px; border-left: 5px solid var(--primary-color-student); transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out; box-shadow: var(--shadow-sm); }
        .course-item-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); }
        .course-item-card h3 { margin-top: 0; color: var(--primary-color-student); font-size:1.1em; }
        .course-item-card .matiere-classe-info { font-size: 0.85em; color: var(--text-light-student); margin-bottom: 5px; }
        .course-item-card .enseignant-name { margin-bottom: 5px; font-size: 0.9em; color: var(--text-light-student); }
        .course-item-card a { text-decoration: none; }
        .back-to-courses-link { display: inline-block; margin-bottom: 20px; color: var(--primary-color-student); text-decoration: none; font-weight: 500; }
        .back-to-courses-link i { margin-right: 5px; }
        .tabs-container { display: flex; border-bottom: 1px solid var(--border-color-student); margin-bottom: 20px; flex-wrap:wrap;}
        .tab-link { padding: 10px 15px; cursor: pointer; border: none; background-color: transparent; color: var(--text-light-student); font-weight: 500; border-bottom: 3px solid transparent; font-size:0.95em; white-space:nowrap;}
        .tab-link.active { color: var(--primary-color-student); border-bottom-color: var(--primary-color-student); }
        .tab-link i { margin-right: 6px; }
        .tab-content { display: none; padding-top: 15px; }
        .tab-content.active { display: block; }
        .tab-content h4 { font-size: 1.1em; color: var(--text-color-student); margin-top: 0; margin-bottom: 15px; padding-bottom:8px; border-bottom:1px solid var(--primary-light-student);}
        .resource-list .resource-item { padding: 12px 0; border-bottom: 1px dashed var(--border-color-student); display: flex; flex-wrap:wrap; justify-content: space-between; align-items: center; gap: 10px; }
        .resource-list .resource-item:last-child { border-bottom: none; }
        .resource-item .item-info { flex-grow: 1; }
        .resource-item .item-title { font-weight: 500; color: var(--text-color-student); display: block; margin-bottom: 3px; }
        .resource-item .item-description { font-size: 0.9em; color: var(--text-light-student); display: block; margin-bottom: 4px;}
        .resource-item .item-date { font-size: 0.8em; color: var(--text-light-student); display: block;}
        .resource-item .item-actions { display: flex; gap: 8px; flex-shrink: 0; margin-top: 5px;}
        .resource-item .item-actions a { text-decoration: none; color: var(--primary-color-student); font-size: 0.9em; padding: 6px 12px; border: 1px solid var(--primary-light-student); border-radius: var(--border-radius-sm); display: inline-flex; align-items: center; gap: 5px; }
        .resource-item .item-actions a:hover { background-color: var(--primary-light-student); }
        .resource-item .item-actions .btn-submit { background-color: var(--primary-color-student); color: white;}
        .resource-item .item-actions .btn-submit:hover {
    background-color: #084298;
}        .live-session-link { display: block; padding: 10px; background-color: var(--primary-light-student); color: var(--primary-color-student); border-radius: var(--border-radius-sm); text-decoration: none; margin-bottom: 10px; font-weight: 500; }
        .live-session-link:hover { background-color: var(--primary-color-student); color:white; }
        .live-session-link i { margin-right: 8px; }
        .alert { padding: 12px 16px; margin-bottom: 20px; border-radius: var(--border-radius-sm); border-width:1px; border-style:solid; font-size:14px;}
        .alert-success { background-color: #ECFDF3; color: #027A48; border-color: #ABEFC6; }
        .alert-danger { background-color: #FEF3F2; color: #B42318; border-color: #FECDCA; }
        .no-data-message { text-align:center; padding:20px; color:var(--text-light-student); font-style: italic; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include_once '../templates/sidebar_etudiant.php'; // Adaptez si le nom du template change ?>
        
        <div class="main-wrapper">
            <?php include_once '../templates/topbar_etudiant.php'; // Adaptez si le nom du template change ?>

            <main class="content-area">
                <div class="main-column"> 
                
                <?php if (!empty($error_message)): ?>
                    <div class="page-container">
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
                         <a href="mes_cours.php" class="back-to-courses-link" style="margin-top:15px; display:inline-block;"><i class="fas fa-arrow-left"></i> Retour au catalogue</a>
                    </div>
                <?php elseif ($selected_matiere_id && $matiere_details_page): ?>
                    <!-- Vue Détail d'une Matière -->
                    <div class="page-container course-detail-container">
                        <a href="mes_cours.php" class="back-to-courses-link"><i class="fas fa-arrow-left"></i> Retour au catalogue des cours</a>
                        <div class="course-detail-header">
                            <h2><?php echo htmlspecialchars($matiere_details_page['nom_matiere']); ?> <small>(<?php echo htmlspecialchars($matiere_details_page['code_matiere']); ?>) - Classe: <?php echo htmlspecialchars($matiere_details_page['nom_classe_matiere']); ?></small></h2>
                            <p>Enseignant: <?php echo htmlspecialchars($matiere_details_page['nom_complet_enseignant'] ?? 'N/A'); ?></p>
                        </div>

                        <div class="tabs-container">
                            <button class="tab-link active" data-tab="tab-cours"><i class="fas fa-book-open"></i> Supports de Cours</button>
                            <button class="tab-link" data-tab="tab-td"><i class="fas fa-pencil-ruler"></i> TD</button>
                            <button class="tab-link" data-tab="tab-tc"><i class="fas fa-chalkboard"></i> TC</button>
                            <button class="tab-link" data-tab="tab-live"><i class="fas fa-video"></i> Sessions Live</button>
                        </div>

                        <div id="tab-cours" class="tab-content active">
                            <h4><i class="fas fa-folder-open"></i> Liste des Supports</h4>
                            <div class="resource-list">
                            <?php if (!empty($cours_deposes)): ?>
                                <?php foreach ($cours_deposes as $cours): ?>
                                    <div class="resource-item">
                                        <div class="item-info">
                                            <span class="item-title"><?php echo htmlspecialchars($cours['titre']); ?></span>
                                            <?php if($cours['description']): ?><span class="item-description"><?php echo nl2br(htmlspecialchars(substr($cours['description'],0,120))).'...'; ?></span><?php endif; ?>
                                            <span class="item-date">Déposé le: <?php echo date('d/m/Y H:i', strtotime($cours['date_depot'])); ?></span>
                                        </div>
                                        <div class="item-actions">
                                            <?php if ($cours['fichier_ressource']): ?>
                                                <a href="../../uploads/cours/<?php echo htmlspecialchars($cours['fichier_ressource']); ?>" download><i class="fas fa-download"></i> Télécharger</a>
                                            <?php endif; ?>
                                            <?php if ($cours['lien_visionnage']): ?>
                                                <a href="<?php echo htmlspecialchars($cours['lien_visionnage']); ?>" target="_blank"><i class="fas fa-external-link-alt"></i> Voir lien</a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="no-data-message">Aucun support de cours déposé pour cette matière.</p>
                            <?php endif; ?>
                            </div>
                        </div>

                        <div id="tab-td" class="tab-content">
                             <h4><i class="fas fa-pencil-ruler"></i> Travaux Dirigés (TD)</h4>
                             <div class="resource-list">
                            <?php if (!empty($td_list)): ?>
                                <?php foreach ($td_list as $td): ?>
                                    <div class="resource-item">
                                        <div class="item-info">
                                            <span class="item-title"><?php echo htmlspecialchars($td['titre']); ?></span>
                                            <span class="item-date">Date limite: <?php echo date('d/m/Y', strtotime($td['date_limite'])); ?></span>
                                        </div>
                                        <div class="item-actions">
                                            <?php if ($td['fichier']): ?>
                                            <a href="../../uploads/devoirs/<?php echo htmlspecialchars($td['fichier']); ?>" download><i class="fas fa-download"></i> Sujet</a>
                                            <?php endif; ?>
                                            <a href="remettre_td.php?id_devoir=<?php echo $td['id']; ?>" class="btn-submit"><i class="fas fa-upload"></i> Soumettre</a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="no-data-message">Aucun TD disponible pour cette matière.</p>
                            <?php endif; ?>
                            </div>
                        </div>
                        
                        <div id="tab-tc" class="tab-content">
                             <h4><i class="fas fa-chalkboard"></i> Travaux de Classe (TC)</h4>
                             <div class="resource-list">
                             <?php if (!empty($tc_list)): ?>
                                <?php foreach ($tc_list as $tc): ?>
                                     <div class="resource-item">
                                        <div class="item-info">
                                            <span class="item-title"><?php echo htmlspecialchars($tc['titre']); ?></span>
                                            <span class="item-date">Date limite: <?php echo date('d/m/Y', strtotime($tc['date_limite'])); ?></span>
                                        </div>
                                        <div class="item-actions">
                                            <?php if ($tc['fichier']): ?>
                                            <a href="../../uploads/devoirs/<?php echo htmlspecialchars($tc['fichier']); ?>" download><i class="fas fa-download"></i> Sujet</a>
                                            <?php endif; ?>
                                            <a href="remettre_td.php?id_devoir=<?php echo $tc['id']; ?>" class="btn-submit"><i class="fas fa-upload"></i> Soumettre</a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="no-data-message">Aucun TC disponible pour cette matière.</p>
                            <?php endif; ?>
                            </div>
                        </div>

                        <div id="tab-live" class="tab-content">
                             <h4><i class="fas fa-broadcast-tower"></i> Sessions de Cours en Direct à Venir</h4>
                            <?php if (!empty($live_sessions)): ?>
                                <?php foreach ($live_sessions as $session): ?>
                                    <a href="<?php echo htmlspecialchars($session['lien_visionnage']); ?>" target="_blank" class="live-session-link">
                                        <i class="fas fa-video"></i> <?php echo htmlspecialchars($session['titre']); ?> 
                                        <br><small>Prévu le <?php echo date('d/m/Y \à H:i', strtotime($session['date_cours'])); ?></small>
                                    </a>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="no-data-message">Aucune session de cours en direct n'est programmée pour cette matière actuellement.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                <?php else: // Afficher la liste de toutes les matières (ou celles de la classe de l'étudiant simulé si $id_classe_pour_contexte est défini) ?>
                    <div class="page-container course-list-container">
                        <div class="page-header">
                            <h2><i class="fas fa-graduation-cap"></i> <?php echo ($id_classe_pour_contexte) ? 'Cours de votre Classe (Simulé)' : 'Catalogue des Cours'; ?></h2>
                            <p>
                                <?php if ($id_classe_pour_contexte): ?>
                                    Voici les matières disponibles pour la classe de l'étudiant simulé ID <?php echo $student_id_for_catalogue_context; ?>.
                                <?php else: ?>
                                    Explorez toutes les matières disponibles dans l'établissement.
                                <?php endif; ?>
                            </p>
                        </div>
                        <?php if (empty($matieres_list)): ?>
                            <p class="no-data-message">
                                <?php if ($id_classe_pour_contexte): ?>
                                    Aucun cours n'est disponible pour la classe de l'étudiant simulé. Vérifiez la configuration des matières pour cette classe.
                                <?php else: ?>
                                    Aucun cours n'est disponible dans le catalogue pour le moment.
                                <?php endif; ?>
                            </p>
                        <?php else: ?>
                            <?php foreach ($matieres_list as $matiere_item): ?>
                                <a href="mes_cours.php?id_matiere=<?php echo $matiere_item['id']; ?>">
                                    <div class="course-item-card">
                                        <h3><?php echo htmlspecialchars($matiere_item['nom_matiere']); ?> 
                                            <?php if(!empty($matiere_item['code_matiere'])): ?>
                                                <small>(<?php echo htmlspecialchars($matiere_item['code_matiere']); ?>)</small>
                                            <?php endif; ?>
                                        </h3>
                                        <p class="matiere-classe-info">
                                           <i class="fas fa-school"></i> Classe: <?php echo htmlspecialchars($matiere_item['nom_classe'] . ' - ' . $matiere_item['niveau']); ?>
                                        </p>
                                        <p class="enseignant-name">
                                            <i class="fas fa-chalkboard-teacher"></i> Enseignant: <?php echo htmlspecialchars($matiere_item['nom_complet_enseignant'] ?? 'N/A'); ?>
                                        </p>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const themeToggle = document.getElementById('theme-toggle');
            if (themeToggle) { 
                const body = document.body;
                const themeIcon = themeToggle.querySelector('i');
                const applyTheme = (theme) => {
                    body.classList.remove('light-theme', 'dark-theme'); 
                    body.classList.add(theme);
                    if (theme === 'dark-theme') {
                        themeIcon.classList.remove('fa-moon'); themeIcon.classList.add('fa-sun');
                    } else {
                        themeIcon.classList.remove('fa-sun'); themeIcon.classList.add('fa-moon');
                    }
                };
                const storedTheme = localStorage.getItem('janglitech-student-theme') || 'light-theme';
                applyTheme(storedTheme);

                themeToggle.addEventListener('click', function (e) {
                    e.preventDefault();
                    let newTheme = body.classList.contains('dark-theme') ? 'light-theme' : 'dark-theme';
                    applyTheme(newTheme);
                    localStorage.setItem('janglitech-student-theme', newTheme);
                });
             }

            const tabLinks = document.querySelectorAll('.tab-link');
            const tabContents = document.querySelectorAll('.tab-content');
            const activeTabKey = 'activeCourseTab_<?php echo $selected_matiere_id ?? 'list'; ?>'; 
            const activeTabFromStorage = localStorage.getItem(activeTabKey);

            function activateTab(tabId) {
                tabLinks.forEach(l => l.classList.remove('active'));
                tabContents.forEach(c => c.classList.remove('active'));
                const linkToActivate = document.querySelector(`.tab-link[data-tab="${tabId}"]`);
                const contentToActivate = document.getElementById(tabId);
                if (linkToActivate && contentToActivate) {
                    linkToActivate.classList.add('active');
                    contentToActivate.classList.add('active');
                    localStorage.setItem(activeTabKey, tabId);
                }
            }
            
            tabLinks.forEach(link => {
                link.addEventListener('click', () => {
                    const tabId = link.getAttribute('data-tab');
                    activateTab(tabId);
                });
            });

            if (activeTabFromStorage && document.getElementById(activeTabFromStorage)) {
                 activateTab(activeTabFromStorage);
            } else if (tabLinks.length > 0) { 
                activateTab(tabLinks[0].getAttribute('data-tab'));
            }
        });
    </script>
</body>
</html>