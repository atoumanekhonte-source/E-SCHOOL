<?php
// /dashboard/enseignant/deposer_td.php

require_once '../../config/db.php';

$database = new Database();
$conn = $database->connect();

// ID Enseignant simulé
define('SIMULATED_ENSEIGNANT_ID_DEVOIRS', 1);

// --- Fonctions ---
function get_enseignant_info_for_devoirs($db_conn, $id_enseignant_table) {
    $stmt = $db_conn->prepare("SELECT u.nom, u.prenom, u.photo FROM enseignants ens JOIN utilisateurs u ON ens.id_utilisateur = u.id WHERE ens.id = :id_enseignant");
    $stmt->bindParam(':id_enseignant', $id_enseignant_table, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function get_matieres_enseignees_for_devoirs_select($db_conn, $id_enseignant) {
    $stmt = $db_conn->prepare("
        SELECT m.id, m.nom_matiere, m.code_matiere, c.nom_classe, m.id_classe 
        FROM matieres m
        JOIN classes c ON m.id_classe = c.id
        WHERE m.id_enseignant = :id_enseignant 
        ORDER BY m.nom_matiere ASC
    ");
    $stmt->bindParam(':id_enseignant', $id_enseignant, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_devoirs_deposes_par_enseignant($db_conn, $id_enseignant, $id_matiere_filter = null) {
    $sql = "SELECT d.id, d.titre, d.type, d.fichier, d.date_depot, d.date_limite, m.nom_matiere, c.nom_classe
            FROM devoirs d
            JOIN matieres m ON d.id_matiere = m.id
            JOIN classes c ON m.id_classe = c.id
            WHERE d.id_enseignant = :id_enseignant ";
    
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

// --- Logique de la page ---
$enseignant_info_page = get_enseignant_info_for_devoirs($conn, SIMULATED_ENSEIGNANT_ID_DEVOIRS);
if (!$enseignant_info_page) die("Erreur enseignant simulé.");

$enseignant_avatar = !empty($enseignant_info_page['photo']) ? '../../uploads/profiles/' . htmlspecialchars($enseignant_info_page['photo']) : '../../assets/images/default_avatar_teacher.png';
$enseignant_nom_complet = htmlspecialchars($enseignant_info_page['prenom'] . ' ' . $enseignant_info_page['nom']);

$matieres_enseignant_options = get_matieres_enseignees_for_devoirs_select($conn, SIMULATED_ENSEIGNANT_ID_DEVOIRS);
$devoirs_deposes = [];
$message = '';
$selected_matiere_id_for_list = isset($_GET['id_matiere_filter']) ? (int)$_GET['id_matiere_filter'] : null;

// Traitement du formulaire de dépôt de devoir
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_devoir'])) {
    $id_matiere_selected = filter_input(INPUT_POST, 'id_matiere_devoir', FILTER_VALIDATE_INT);
    $type_devoir = $_POST['type_devoir'] ?? ''; // 'TD' ou 'TC'
    $titre_devoir = htmlspecialchars(trim($_POST['titre_devoir']));
    $date_limite_str = $_POST['date_limite_devoir'] ?? '';
    $fichier_devoir_nom = null;
    $id_classe_devoir = null;

    // Valider les entrées
    if (empty($id_matiere_selected) || !in_array($type_devoir, ['TD', 'TC']) || empty($titre_devoir) || empty($date_limite_str)) {
        $message = "<div class='alert alert-danger'>Veuillez remplir tous les champs requis (*).</div>";
    } else {
        // Récupérer id_classe à partir de id_matiere
        foreach ($matieres_enseignant_options as $mat) {
            if ($mat['id'] == $id_matiere_selected) {
                $id_classe_devoir = $mat['id_classe'];
                break;
            }
        }
        if (!$id_classe_devoir) {
             $message = "<div class='alert alert-danger'>Erreur: Matière sélectionnée invalide.</div>";
        } else {
            // Valider la date limite
            try {
                $date_limite_obj = new DateTime($date_limite_str);
                if ($date_limite_obj < new DateTime(date('Y-m-d'))) {
                     $message = "<div class='alert alert-danger'>La date limite ne peut pas être dans le passé.</div>";
                     $date_limite_obj = null;
                }
            } catch (Exception $e) {
                 $message = "<div class='alert alert-danger'>Format de date limite invalide.</div>";
                 $date_limite_obj = null;
            }

            // Gestion de l'upload du fichier sujet
            if (empty($message) && isset($_FILES['fichier_devoir']) && $_FILES['fichier_devoir']['error'] == UPLOAD_ERR_OK) {
                $upload_dir_devoirs = '../../uploads/devoirs/';
                if (!is_dir($upload_dir_devoirs)) { mkdir($upload_dir_devoirs, 0775, true); }

                $file_tmp_path = $_FILES['fichier_devoir']['tmp_name'];
                $file_name = basename($_FILES['fichier_devoir']['name']);
                $safe_file_name = preg_replace("/[^a-zA-Z0-9._-]/", "_", $file_name);
                $unique_file_name = time() . '_' . SIMULATED_ENSEIGNANT_ID_DEVOIRS . '_sujet_' . $safe_file_name;
                $dest_path = $upload_dir_devoirs . $unique_file_name;
                $allowed_extensions_devoir = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'txt', 'jpg', 'png', 'zip'];
                $file_extension_devoir = strtolower(pathinfo($unique_file_name, PATHINFO_EXTENSION));
                $file_size_devoir = $_FILES['fichier_devoir']['size'];

                if (!in_array($file_extension_devoir, $allowed_extensions_devoir)) {
                    $message = "<div class='alert alert-danger'>Type de fichier non autorisé pour le sujet du devoir.</div>";
                } elseif ($file_size_devoir > 10 * 1024 * 1024) {
                    $message = "<div class='alert alert-danger'>Le fichier du sujet est trop volumineux (max 10MB).</div>";
                } else {
                    if (move_uploaded_file($file_tmp_path, $dest_path)) {
                        $fichier_devoir_nom = $unique_file_name;
                    } else {
                        $message = "<div class='alert alert-danger'>Erreur lors du téléchargement du fichier sujet.</div>";
                    }
                }
            } elseif (empty($message) && (!isset($_FILES['fichier_devoir']) || $_FILES['fichier_devoir']['error'] != UPLOAD_ERR_NO_FILE)) {
                 if(!isset($_FILES['fichier_devoir']) || $_FILES['fichier_devoir']['error'] != UPLOAD_ERR_NO_FILE && $_FILES['fichier_devoir']['error'] != UPLOAD_ERR_OK) {
                    $message = "<div class='alert alert-danger'>Erreur avec le fichier du sujet. Code: " .$_FILES['fichier_devoir']['error']. "</div>";
                 }
            }

            if (empty($message) && $date_limite_obj) {
                try {
                    $stmt_insert = $conn->prepare("
                        INSERT INTO devoirs (id_matiere, id_enseignant, type, titre, fichier, date_depot, date_limite)
                        VALUES (:id_matiere, :id_enseignant, :type, :titre, :fichier, CURDATE(), :date_limite)
                    ");
                    $stmt_insert->execute([
                        ':id_matiere' => $id_matiere_selected,
                        ':id_enseignant' => SIMULATED_ENSEIGNANT_ID_DEVOIRS,
                        ':type' => $type_devoir,
                        ':titre' => $titre_devoir,
                        ':fichier' => $fichier_devoir_nom,
                        ':date_limite' => $date_limite_obj->format('Y-m-d')
                    ]);
                    header("Location: deposer_td.php?id_matiere_filter=" . $id_matiere_selected . "&succes_depot_td=1");
                    exit();
                } catch (PDOException $e) {
                    error_log("Erreur dépôt TD/TC: " . $e->getMessage());
                    $message = "<div class='alert alert-danger'>Une erreur technique est survenue lors du dépôt du devoir.</div>";
                }
            }
        }
    }
}

if(isset($_GET['succes_depot_td']) && empty($message)) {
    $message = "<div class='alert alert-success'>Devoir (TD/TC) déposé avec succès !</div>";
}

$devoirs_deposes = get_devoirs_deposes_par_enseignant($conn, SIMULATED_ENSEIGNANT_ID_DEVOIRS, $selected_matiere_id_for_list);

$page_title = "Déposer Devoirs (TD/TC)";
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
        /* C'est-à-dire, tout ce qui se trouvait dans la balise <style> de index.php enseignant */
        /* OU assurez-vous que votre ../../assets/css/style.css contient déjà ces styles. */
        
        /* --- Styles Généraux (Rappel) --- */
        :root {
    /* Couleurs principales BLEUES */
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

    --accent-blue: #2563EB;
    --danger-color: #DC2626;
    --success-color: #16A34A;
    --warning-color: #F59E0B;
    --info-color: #2563EB;

    --font-family: 'Inter', sans-serif;
    --border-radius-md: 8px;
    --border-radius-sm: 6px;

    --shadow-sm: 0 1px 2px 0 rgba(15, 23, 42, 0.05);
    --shadow-md: 0px 4px 8px -2px rgba(15, 23, 42, 0.10),
                 0px 2px 4px -2px rgba(15, 23, 42, 0.06);
}

/* Mode sombre bleu */
body.dark-theme {
    --primary-color: #3B82F6;
    --primary-light: #1E3A8A;
    --primary-dark: #60A5FA;

    --secondary-color: #1E293B;
    --page-bg: #0F172A;
    --sidebar-bg: #111827;
    --card-bg: #1E293B;

    --text-color: #F8FAFC;
    --text-light: #94A3B8;
    --border-color: #334155;

    --accent-blue: #60A5FA;
    --danger-color: #F87171;
    --success-color: #4ADE80;
    --warning-color: #FBBF24;
    --info-color: #60A5FA;
}
        body { font-family: var(--font-family); margin: 0; background-color: var(--page-bg); color: var(--text-color); display: flex; min-height: 100vh; transition: background-color 0.3s, color 0.3s; font-size: 14px; -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale; }
        .dashboard-container { display: flex; width: 100%; }
        .main-wrapper { flex-grow: 1; margin-left: 260px; display: flex; flex-direction: column; width: calc(100% - 260px); background-color: var(--page-bg); transition: background-color 0.3s; }
        .content-area { flex-grow: 1; padding: 24px 30px; display: flex; gap: 24px; overflow-y: auto;}
        .main-column { flex-grow: 1; display: flex; flex-direction: column; gap: 24px;}

        /* --- Sidebar & Topbar (Styles complets de index.php enseignant) --- */
        .sidebar { width: 260px; background-color: var(--sidebar-bg); padding: 24px 16px; border-right: 1px solid var(--border-color); display: flex; flex-direction: column; height: 100vh; position: fixed; left: 0; top: 0; z-index: 1000; transition: background-color 0.3s, border-color 0.3s; overflow-y: auto; }
        .sidebar-logo { display: flex; align-items: center; padding: 0 8px 24px 8px; font-size: 22px; font-weight: 700; color: var(--primary-color); }
        .sidebar-logo .logo-icon { margin-right: 10px; font-size: 24px; }
        .sidebar-nav ul { list-style: none; padding: 0; margin: 0; flex-grow: 1; }
        .sidebar-nav li a { display: flex; align-items: center; padding: 10px 12px; color: var(--text-light); text-decoration: none; border-radius: var(--border-radius-sm); margin-bottom: 4px; font-weight: 500; transition: background-color 0.2s, color 0.2s; font-size: 14px; }
        .sidebar-nav li a i { margin-right: 12px; width: 20px; text-align: center; font-size: 16px; }
        .sidebar-nav li a:hover { background-color: var(--primary-light); color: var(--primary-color); }
        .sidebar-nav li a.active { background-color: var(--primary-light); color: var(--primary-color); font-weight: 600; }
        body.dark-theme .sidebar-nav li a.active { color: var(--primary-color); }
        .sidebar .logout-link { margin-top: auto; padding-top: 16px; border-top: 1px solid var(--border-color); }

        .top-bar { background-color: var(--page-bg); padding: 0 30px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); height: 70px; box-sizing: border-box; transition: background-color 0.3s, border-color 0.3s; position: sticky; top: 0; z-index: 999; }
        .search-bar { position: relative; flex-grow:1; max-width: 400px;}
        .search-bar input { padding: 10px 15px 10px 40px; border-radius: var(--border-radius-md); border: 1px solid var(--border-color); width: 100%; background-color: var(--secondary-color); color: var(--text-color); font-size: 14px;}
        .search-bar input::placeholder { color: var(--text-light); }
        .search-bar i.fa-search { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: var(--text-light); }
        .top-bar-actions { display: flex; align-items: center; gap: 16px; }
        .btn-add-new { background-color: var(--primary-color); color: white; padding: 10px 16px; text-decoration: none; border-radius: var(--border-radius-md); font-weight: 600; font-size: 14px; display: flex; align-items: center; gap: 8px; transition: background-color 0.2s;}
        .btn-add-new:hover { background-color: var(--primary-dark); }
        .btn-add-new i.fa-plus { font-size: 12px; }
        .top-bar-actions .icon-btn { color: var(--text-light); font-size: 20px; cursor: pointer; padding: 8px; border-radius: 50%; transition: color 0.2s, background-color 0.2s;}
        .top-bar-actions .icon-btn:hover { color: var(--primary-color); background-color: var(--primary-light); }
        .user-profile-widget { display: flex; align-items: center; gap: 12px; }
        .user-profile-widget img { width: 40px; height: 40px; border-radius: 50%; object-fit: cover;}
        .user-profile-widget .user-info .user-name { font-weight: 600; color: var(--text-color); display: block;}
        .user-profile-widget .user-info .user-role { font-size: 0.85em; color: var(--text-light); }

        /* --- Styles Génériques pour les Pages de Contenu (doivent être dans style.css) --- */
        .page-container { background-color: var(--card-bg); padding: 24px; border-radius: var(--border-radius-md); box-shadow: var(--shadow-sm); border: 1px solid var(--border-color); width: 100%; transition: background-color 0.3s, border-color 0.3s;}
        .page-header { margin-bottom: 24px; padding-bottom:16px; border-bottom: 1px solid var(--border-color);}
        .page-header h2 { color: var(--text-color); font-size: 20px; font-weight: 600; margin: 0; display: flex; align-items:center; gap:10px;}
        .page-header p { color: var(--text-light); font-size: 14px; margin-top: 4px; margin-bottom:0; }

        .form-section-container { margin-bottom: 24px; padding: 20px; border-radius: var(--border-radius-md); background-color: var(--card-bg); border: 1px solid var(--border-color); }
        body.dark-theme .form-section-container { background-color: var(--secondary-color); }
        .form-section-container h3 { color: var(--primary-color); margin-top: 0; margin-bottom: 20px; font-size: 18px; font-weight: 600; display: flex; align-items: center; gap: 10px; padding-bottom: 10px; border-bottom: 1px solid var(--primary-light); }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 500; font-size: 14px; color: var(--text-color); }
        .form-group label span[style*="color:red"] { color: var(--danger-color) !important; }
        .form-group input[type="text"], .form-group input[type="url"], .form-group input[type="email"], .form-group input[type="password"], .form-group input[type="date"], .form-group input[type="time"], .form-group textarea, .form-group select { width: 100%; padding: 10px 12px; border-radius: var(--border-radius-sm); border: 1px solid var(--border-color); font-size: 14px; background-color: var(--page-bg); color: var(--text-color); box-sizing: border-box; transition: border-color 0.2s, box-shadow 0.2s; }
        body.dark-theme .form-group input[type="text"], body.dark-theme .form-group input[type="url"], body.dark-theme .form-group input[type="email"], body.dark-theme .form-group input[type="password"], body.dark-theme .form-group input[type="date"], body.dark-theme .form-group input[type="time"], body.dark-theme .form-group textarea, body.dark-theme .form-group select { background-color: var(--secondary-color); }
        .form-group input:focus, .form-group textarea:focus, .form-group select:focus { border-color: var(--primary-color); box-shadow: 0 0 0 3px var(--primary-light); outline: none; }
        .form-group textarea { min-height: 80px; resize: vertical; }
        .form-group input[type="file"] { padding: 8px; border: 1px solid var(--border-color); border-radius: var(--border-radius-sm); background-color: var(--page-bg); color: var(--text-color); display: block; width:100%; }
        .btn-submit-form { background-color: var(--primary-color); color: white; padding: 10px 20px; border: none; border-radius: var(--border-radius-md); font-size: 14px; font-weight: 600; cursor: pointer; transition: background-color 0.2s; display: inline-flex; align-items: center; gap: 8px; }
        .btn-submit-form:hover { background-color: var(--primary-dark); }

        .resource-list-container { margin-bottom: 24px; padding: 20px; border-radius: var(--border-radius-md); background-color: var(--card-bg); border: 1px solid var(--border-color); }
        .resource-list-container h3 { color: var(--primary-color); margin-top: 0; margin-bottom: 20px; font-size: 18px; font-weight: 600; display: flex; align-items: center; gap: 10px; padding-bottom: 10px; border-bottom: 1px solid var(--primary-light); }
        .filter-form { margin-bottom: 20px; display:flex; gap:10px; align-items:center; padding: 10px; background-color: var(--secondary-color); border-radius: var(--border-radius-md); }
        .filter-form label { margin-bottom:0; white-space:nowrap; color:var(--text-light); font-weight:500; }
        .filter-form select { flex-grow:1; padding:8px 10px; border-radius:var(--border-radius-sm); border:1px solid var(--border-color); background-color: var(--card-bg); color: var(--text-color); }
        
        /* Styles pour la table des devoirs déposés */
        .devoirs-table { width: 100%; border-collapse: collapse; }
        .devoirs-table th, .devoirs-table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid var(--border-color); }
        .devoirs-table thead th { background-color: var(--secondary-color); color: var(--text-light); font-size: 12px; text-transform: uppercase; font-weight: 500; }
        .devoirs-table tbody tr:hover { background-color: var(--primary-light); }
        .devoirs-table td { font-size: 14px; color: var(--text-color); }
        .devoirs-table .type-badge { padding: 3px 8px; border-radius: var(--border-radius-sm); font-size: 12px; font-weight: 500; color: white; text-transform: uppercase;}
        .devoirs-table .type-badge.td { background-color: var(--primary-color); }
        .devoirs-table .type-badge.tc { background-color: var(--accent-blue); } /* Couleur différente pour TC */
        .devoirs-table .devoir-titre { font-weight: 500; }
        .devoirs-table .actions-cell a { margin-right: 8px; }

        .btn-action-icon { text-decoration: none; font-size: 14px; padding: 8px; border-radius: 50%; display: inline-flex; align-items: center; justify-content:center; width: 32px; height:32px; border: 1px solid transparent; transition: background-color 0.2s, color 0.2s, border-color 0.2s; }
        .btn-action-icon.btn-edit { color: var(--accent-blue); }
        .btn-action-icon.btn-edit:hover { background-color: #EFF4FF; border-color: var(--accent-blue); }
        .btn-action-icon.btn-delete { color: var(--danger-color); }
        .btn-action-icon.btn-delete:hover { background-color: #FEF3F2; border-color: var(--danger-color); }
        .btn-action-icon.btn-view-submissions { color: var(--success-color); }
        .btn-action-icon.btn-view-submissions:hover { background-color: #ECFDF3; border-color: var(--success-color); }


        .alert { padding: 12px 16px; margin-bottom: 20px; border-radius: var(--border-radius-sm); border-width:1px; border-style:solid; font-size:14px;}
        .alert-success { background-color: #ECFDF3; color: #027A48; border-color: #ABEFC6; }
        .alert-danger { background-color: #FEF3F2; color: #B42318; border-color: #FECDCA; }
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
                    <li><a href="deposer_td.php" class="<?php echo ($current_page == 'deposer_td.php') ? 'active' : ''; ?>"><i class="fas fa-tasks"></i> Déposer TD/TC</a></li>
                    <li><a href="noter_etudiants.php"><i class="fas fa-edit"></i> Noter Étudiants</a></li>
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
                            <h2><i class="fas fa-tasks"></i> Gérer les Devoirs (TD/TC)</h2>
                            <p>Créez, assignez et suivez les travaux dirigés et travaux de classe.</p>
                        </div>

                        <?php if ($message): ?>
                            <?php echo $message; ?>
                        <?php endif; ?>

                        <div class="form-section-container">
                            <h3><i class="fas fa-plus-circle"></i> Déposer un nouveau devoir</h3>
                            <form action="deposer_td.php<?php echo $selected_matiere_id_for_list ? '?id_matiere_filter='.$selected_matiere_id_for_list : ''; ?>" method="POST" enctype="multipart/form-data">
                                <div class="form-group">
                                    <label for="id_matiere_devoir">Matière (et Classe associée) <span style="color:red;">*</span></label>
                                    <select name="id_matiere_devoir" id="id_matiere_devoir" required>
                                        <option value="">-- Sélectionner une matière --</option>
                                        <?php foreach ($matieres_enseignant_options as $matiere): ?>
                                            <option value="<?php echo $matiere['id']; ?>" <?php echo ($selected_matiere_id_for_list == $matiere['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($matiere['nom_matiere'] . ' - Classe: ' . $matiere['nom_classe']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div style="display:flex; flex-wrap:wrap; gap: 15px;">
                                    <div class="form-group" style="flex:1; min-width: 200px;">
                                        <label for="type_devoir">Type de devoir <span style="color:red;">*</span></label>
                                        <select name="type_devoir" id="type_devoir" required>
                                            <option value="TD">TD (Travail Dirigé)</option>
                                            <option value="TC">TC (Travail de Classe)</option>
                                        </select>
                                    </div>
                                    <div class="form-group" style="flex:2; min-width: 250px;">
                                        <label for="titre_devoir">Titre du devoir <span style="color:red;">*</span></label>
                                        <input type="text" id="titre_devoir" name="titre_devoir" required>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="date_limite_devoir">Date limite de soumission <span style="color:red;">*</span></label>
                                    <input type="date" id="date_limite_devoir" name="date_limite_devoir" required min="<?php echo date('Y-m-d'); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="fichier_devoir">Fichier du sujet (PDF, DOC, etc. - Max 10MB)</label>
                                    <input type="file" id="fichier_devoir" name="fichier_devoir">
                                    <small style="color:var(--text-light); font-size:12px;">Optionnel si le sujet est dans la description.</small>
                                </div>
                                <button type="submit" name="submit_devoir" class="btn-submit-form">
                                    <i class="fas fa-save"></i> Déposer le Devoir
                                </button>
                            </form>
                        </div>

                        <div class="resource-list-container"> <!-- Réutilisation de la classe pour la section liste -->
                            <h3><i class="fas fa-list-alt"></i> Devoirs déjà déposés</h3>
                            <form method="GET" action="deposer_td.php" class="filter-form">
                                 <div class="form-group" style="display:flex; align-items:center; gap:10px; margin-bottom:0;">
                                    <label for="id_matiere_filter" style="margin-bottom:0;">Filtrer par matière :</label>
                                    <select name="id_matiere_filter" id="id_matiere_filter" onchange="this.form.submit()">
                                        <option value="">-- Toutes mes matières --</option>
                                        <?php foreach ($matieres_enseignant_options as $matiere): ?>
                                            <option value="<?php echo $matiere['id']; ?>" <?php echo ($selected_matiere_id_for_list == $matiere['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($matiere['nom_matiere'] . ' (' . $matiere['nom_classe'] . ')'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </form>

                            <?php if (empty($devoirs_deposes)): ?>
                                <p class="no-data-message">Aucun devoir déposé <?php echo $selected_matiere_id_for_list ? 'pour cette matière' : ''; ?>.</p>
                            <?php else: ?>
                                <table class="devoirs-table">
                                    <thead>
                                        <tr>
                                            <th>Titre</th>
                                            <th>Type</th>
                                            <th>Matière</th>
                                            <th>Classe</th>
                                            <th>Date Limite</th>
                                            <th>Sujet</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($devoirs_deposes as $devoir): ?>
                                        <tr>
                                            <td class="devoir-titre"><?php echo htmlspecialchars($devoir['titre']); ?></td>
                                            <td><span class="type-badge <?php echo strtolower($devoir['type']); ?>"><?php echo htmlspecialchars($devoir['type']); ?></span></td>
                                            <td><?php echo htmlspecialchars($devoir['nom_matiere']); ?></td>
                                            <td><?php echo htmlspecialchars($devoir['nom_classe']); ?></td>
                                            <td><?php echo date('d/m/Y', strtotime($devoir['date_limite'])); ?></td>
                                            <td>
                                                <?php if ($devoir['fichier']): ?>
                                                    <a href="../../uploads/devoirs/<?php echo htmlspecialchars($devoir['fichier']); ?>" download title="Télécharger le sujet">
                                                        <i class="fas fa-file-download"></i> <?php echo htmlspecialchars(substr($devoir['fichier'], strpos($devoir['fichier'], "_sujet_") + 7)); ?>
                                                    </a>
                                                <?php else: echo "N/A"; endif; ?>
                                            </td>
                                            <td class="actions-cell">
                                                <a href="voir_devoirs.php?id_devoir=<?php echo $devoir['id']; ?>" class="btn-action-icon btn-view-submissions" title="Voir les soumissions"><i class="fas fa-users"></i></a>
                                                <a href="modifier_td.php?id_devoir=<?php echo $devoir['id']; ?>" class="btn-action-icon btn-edit" title="Modifier ce devoir"><i class="fas fa-edit"></i></a>
                                                <a href="supprimer_td.php?id_devoir=<?php echo $devoir['id']; ?>&id_matiere_filter=<?php echo $selected_matiere_id_for_list; ?>" 
                                                   class="btn-action-icon btn-delete" title="Supprimer ce devoir"
                                                   onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce devoir ?');">
                                                   <i class="fas fa-trash-alt"></i>
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
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