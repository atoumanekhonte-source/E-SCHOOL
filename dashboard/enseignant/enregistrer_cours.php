<?php
// /dashboard/enseignant/enregistrer_cours.php

// PAS D'AUTHENTIFICATION COMPLEXE POUR CET EXEMPLE
require_once '../../config/db.php';

$database = new Database();
$conn = $database->connect();

// --- ID Enseignant Simulé ---
define('SIMULATED_ENSEIGNANT_ID_LIVE', 1); // !! IMPORTANT: ID enseignant existant

// --- Fonctions de récupération et de gestion ---
function get_enseignant_info_for_live($db_conn, $id_enseignant_table) {
    $stmt = $db_conn->prepare("SELECT u.nom, u.prenom, u.photo FROM enseignants ens JOIN utilisateurs u ON ens.id_utilisateur = u.id WHERE ens.id = :id_enseignant");
    $stmt->bindParam(':id_enseignant', $id_enseignant_table, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function get_matieres_enseignees_for_live_select($db_conn, $id_enseignant) {
    $stmt = $db_conn->prepare("
        SELECT m.id, m.nom_matiere, m.code_matiere, c.nom_classe 
        FROM matieres m
        JOIN classes c ON m.id_classe = c.id
        WHERE m.id_enseignant = :id_enseignant 
        ORDER BY m.nom_matiere ASC
    ");
    $stmt->bindParam(':id_enseignant', $id_enseignant, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_sessions_live_planifiees($db_conn, $id_enseignant, $id_matiere_filter = null) {
    $sql = "SELECT cel.id, cel.titre, cel.description, cel.lien_visionnage, cel.fichier_video, cel.date_cours, m.nom_matiere 
            FROM cours_en_ligne cel
            JOIN matieres m ON cel.id_matiere = m.id
            WHERE cel.id_enseignant = :id_enseignant ";
    
    $params = [':id_enseignant' => $id_enseignant];

    if ($id_matiere_filter) {
        $sql .= " AND cel.id_matiere = :id_matiere ";
        $params[':id_matiere'] = $id_matiere_filter;
    }
    
    $sql .= " ORDER BY cel.date_cours DESC";
    
    $stmt = $db_conn->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// --- Logique de la page ---
$enseignant_info_page = get_enseignant_info_for_live($conn, SIMULATED_ENSEIGNANT_ID_LIVE);
if (!$enseignant_info_page) die("Erreur enseignant simulé.");

$enseignant_avatar = !empty($enseignant_info_page['photo']) ? '../../uploads/profiles/' . htmlspecialchars($enseignant_info_page['photo']) : '../../assets/images/default_avatar_teacher.png';
$enseignant_nom_complet = htmlspecialchars($enseignant_info_page['prenom'] . ' ' . $enseignant_info_page['nom']);

$matieres_enseignant = get_matieres_enseignees_for_live_select($conn, SIMULATED_ENSEIGNANT_ID_LIVE);
$message = '';
$selected_matiere_id_filter_list = isset($_GET['id_matiere_filter']) ? (int)$_GET['id_matiere_filter'] : null;
$sessions_planifiees = get_sessions_live_planifiees($conn, SIMULATED_ENSEIGNANT_ID_LIVE, $selected_matiere_id_filter_list);

// Traitement du formulaire de planification de session live
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_session_live'])) {
    $id_matiere = filter_input(INPUT_POST, 'id_matiere_live', FILTER_VALIDATE_INT);
    $titre_session = trim(filter_input(INPUT_POST, 'titre_session_live', FILTER_SANITIZE_STRING));
    $description_session = trim(filter_input(INPUT_POST, 'description_session_live', FILTER_SANITIZE_STRING));
    $date_session = $_POST['date_session_live'] ?? ''; 
    $heure_session = $_POST['heure_session_live'] ?? ''; 
    $lien_visioconference = trim(filter_input(INPUT_POST, 'lien_visioconference', FILTER_SANITIZE_URL));

    if (empty($id_matiere) || empty($titre_session) || empty($date_session) || empty($heure_session) || empty($lien_visioconference)) {
        $message = "<div class='alert alert-danger'>Tous les champs marqués d'un * sont requis pour planifier une session.</div>";
    } elseif (!filter_var($lien_visioconference, FILTER_VALIDATE_URL)) {
        $message = "<div class='alert alert-danger'>Le lien de visioconférence n'est pas une URL valide.</div>";
    } else {
        $datetime_session_str = $date_session . ' ' . $heure_session . ':00';
        $datetime_session_obj = null;
        try {
            $datetime_session_obj = new DateTime($datetime_session_str);
            if ($datetime_session_obj < new DateTime()) {
                 $message = "<div class='alert alert-danger'>La date et l'heure de la session doivent être dans le futur.</div>";
                 $datetime_session_obj = null; 
            }
        } catch (Exception $e) {
             $message = "<div class='alert alert-danger'>Format de date ou d'heure invalide.</div>";
        }

        if (empty($message) && $datetime_session_obj) {
            $is_own_matiere = false;
            foreach ($matieres_enseignant as $mat) { if ($mat['id'] == $id_matiere) { $is_own_matiere = true; break; } }
            if (!$is_own_matiere) {
                 $message = "<div class='alert alert-danger'>Erreur: Matière non valide ou non assignée.</div>";
            } else {
                try {
                    $stmt_insert = $conn->prepare("
                        INSERT INTO cours_en_ligne (id_matiere, id_enseignant, titre, description, lien_visionnage, date_cours)
                        VALUES (:id_matiere, :id_enseignant, :titre, :description, :lien_visionnage, :date_cours)
                    ");
                    $stmt_insert->execute([
                        ':id_matiere' => $id_matiere, ':id_enseignant' => SIMULATED_ENSEIGNANT_ID_LIVE,
                        ':titre' => $titre_session, ':description' => $description_session,
                        ':lien_visionnage' => $lien_visioconference,
                        ':date_cours' => $datetime_session_obj->format('Y-m-d H:i:s')
                    ]);
                    header("Location: enregistrer_cours.php?id_matiere_filter=" . $id_matiere . "&succes_planif=1");
                    exit();
                } catch (PDOException $e) {
                    error_log("Erreur planification session: " . $e->getMessage());
                    $message = "<div class='alert alert-danger'>Une erreur technique est survenue lors de la planification.</div>";
                }
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_enregistrement_video'])) {
    $id_session_cours_post = filter_input(INPUT_POST, 'id_session_cours', FILTER_VALIDATE_INT);
    $fichier_video_nom = null;

    if (isset($_FILES['fichier_enregistrement_video']) && $_FILES['fichier_enregistrement_video']['error'] == UPLOAD_ERR_OK && $id_session_cours_post) {
        // Logique d'upload similaire à deposer_cours.php (à adapter pour le dossier /uploads/videos/)
        $upload_dir_videos = '../../uploads/videos/';
        if (!is_dir($upload_dir_videos)) { mkdir($upload_dir_videos, 0775, true); }

        $file_tmp_path = $_FILES['fichier_enregistrement_video']['tmp_name'];
        $file_name = basename($_FILES['fichier_enregistrement_video']['name']);
        $safe_file_name = preg_replace("/[^a-zA-Z0-9._-]/", "_", $file_name);
        $unique_file_name = time() . '_' . SIMULATED_ENSEIGNANT_ID_LIVE . '_recording_' . $safe_file_name;
        $dest_path = $upload_dir_videos . $unique_file_name;
        $allowed_extensions_video = ['mp4', 'mov', 'avi', 'mkv', 'webm']; // Adaptez
        $file_extension_video = strtolower(pathinfo($unique_file_name, PATHINFO_EXTENSION));
        $file_size_video = $_FILES['fichier_enregistrement_video']['size'];

        if (!in_array($file_extension_video, $allowed_extensions_video)) {
            $message = "<div class='alert alert-danger'>Type de fichier vidéo non autorisé.</div>";
        } elseif ($file_size_video > 100 * 1024 * 1024) { // Limite 100MB pour exemple
            $message = "<div class='alert alert-danger'>Le fichier vidéo est trop volumineux (max 100MB).</div>";
        } else {
            if (move_uploaded_file($file_tmp_path, $dest_path)) {
                $fichier_video_nom = $unique_file_name;
                // Mettre à jour la base de données
                try {
                    $stmt_update = $conn->prepare("UPDATE cours_en_ligne SET fichier_video = :fichier_video WHERE id = :id_session AND id_enseignant = :id_enseignant");
                    $stmt_update->execute([
                        ':fichier_video' => $fichier_video_nom,
                        ':id_session' => $id_session_cours_post,
                        ':id_enseignant' => SIMULATED_ENSEIGNANT_ID_LIVE
                    ]);
                     header("Location: enregistrer_cours.php?id_matiere_filter=" . $selected_matiere_id_filter_list . "&succes_upload_video=1");
                     exit();
                } catch (PDOException $e) {
                     error_log("Erreur upload enregistrement: " . $e->getMessage());
                     $message = "<div class='alert alert-danger'>Erreur lors de la mise à jour de l'enregistrement.</div>";
                }
            } else {
                $message = "<div class='alert alert-danger'>Erreur lors du téléchargement de la vidéo.</div>";
            }
        }
    } else {
        $message = "<div class='alert alert-danger'>Veuillez sélectionner un fichier vidéo pour l'enregistrement. Erreur code: ". ($_FILES['fichier_enregistrement_video']['error'] ?? 'N/A') ."</div>";
    }
}


if(isset($_GET['succes_planif']) && $_GET['succes_planif'] == 1 && empty($message)) {
    $message = "<div class='alert alert-success'>Session en direct planifiée avec succès !</div>";
}
if(isset($_GET['succes_upload_video']) && $_GET['succes_upload_video'] == 1 && empty($message)) {
    $message = "<div class='alert alert-success'>Enregistrement vidéo uploadé avec succès !</div>";
}

// Recharger les sessions après une action pour voir les changements
if ($selected_matiere_id_filter_list || isset($_GET['succes_planif']) || isset($_GET['succes_upload_video'])) {
    $sessions_planifiees = get_sessions_live_planifiees($conn, SIMULATED_ENSEIGNANT_ID_LIVE, $selected_matiere_id_filter_list);
}


$page_title = "Planifier / Gérer Cours en Direct";
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - JANGLITECH</title>
    <!-- Le lien vers style.css est conservé, mais les styles ci-dessous le surchargeront ou le compléteront pour cette page -->
    <link rel="stylesheet" href="../../assets/css/style.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* COPIEZ ICI L'INTÉGRALITÉ DU CSS DE LA PAGE INDEX.PHP ENSEIGNANT */
        /* C'est-à-dire, tout ce qui se trouvait dans la balise <style> de index.php enseignant */
        /* OU assurez-vous que votre ../../assets/css/style.css contient déjà ces styles. */
        
        /* --- Styles Généraux (Rappel) --- */
        :root {
    /* Palette Bleue */
    --primary-color: #0066FF;
    --primary-light: #EAF3FF;
    --primary-dark: #0052CC;

    --secondary-color: #F8FBFF;
    --page-bg: #FFFFFF;
    --sidebar-bg: #FFFFFF;
    --card-bg: #FFFFFF;

    --text-color: #101828;
    --text-light: #667085;
    --border-color: #D6E4FF;

    --accent-blue: #0066FF;
    --danger-color: #D92D20;
    --success-color: #12B76A;
    --warning-color: #F79009;
    --info-color: #0066FF;

    --font-family: 'Inter', sans-serif;

    --border-radius-md: 8px;
    --border-radius-sm: 6px;

    --shadow-sm: 0 1px 2px rgba(16,24,40,.05);
    --shadow-md: 0 4px 8px rgba(16,24,40,.08);
}

body.dark-theme {
    --primary-color: #4D94FF;
    --primary-light: #173B6E;
    --primary-dark: #66A3FF;

    --secondary-color: #13233D;
    --page-bg: #0A192F;
    --sidebar-bg: #10243D;
    --card-bg: #163357;

    --text-color: #F8FAFC;
    --text-light: #A8C7F0;
    --border-color: #294D78;

    --accent-blue: #4D94FF;
    --danger-color: #F97066;
    --success-color: #34D399;
    --warning-color: #FBBF24;
    --info-color: #4D94FF;
}
        body {
            font-family: var(--font-family); margin: 0; background-color: var(--page-bg);
            color: var(--text-color); display: flex; min-height: 100vh;
            transition: background-color 0.3s, color 0.3s; font-size: 14px;
            -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale;
        }
        .dashboard-container { display: flex; width: 100%; }
        .main-wrapper {
            flex-grow: 1; margin-left: 260px; display: flex; flex-direction: column;
            width: calc(100% - 260px); background-color: var(--page-bg);
            transition: background-color 0.3s;
        }
        .content-area { flex-grow: 1; padding: 24px 30px; display: flex; gap: 24px; overflow-y: auto;}
        .main-column { flex-grow: 1; display: flex; flex-direction: column; gap: 24px;}

        /* --- Sidebar & Topbar (Rappel des sélecteurs principaux) --- */
        .sidebar { /* ... styles complets ... */
            width: 260px; background-color: var(--sidebar-bg); padding: 24px 16px;
            border-right: 1px solid var(--border-color); display: flex; flex-direction: column;
            height: 100vh; position: fixed; left: 0; top: 0; z-index: 1000;
            transition: background-color 0.3s, border-color 0.3s; overflow-y: auto;
        }
        .sidebar-logo { display: flex; align-items: center; padding: 0 8px 24px 8px; font-size: 22px; font-weight: 700; color: var(--primary-color); }
        .sidebar-logo .logo-icon { margin-right: 10px; font-size: 24px; }
        .sidebar-nav ul { list-style: none; padding: 0; margin: 0; flex-grow: 1; }
        .sidebar-nav li a { display: flex; align-items: center; padding: 10px 12px; color: var(--text-light); text-decoration: none; border-radius: var(--border-radius-sm); margin-bottom: 4px; font-weight: 500; transition: background-color 0.2s, color 0.2s; font-size: 14px; }
        .sidebar-nav li a i { margin-right: 12px; width: 20px; text-align: center; font-size: 16px; }
        .sidebar-nav li a:hover { background-color: var(--primary-light); color: var(--primary-color); }
        .sidebar-nav li a.active { background-color: var(--primary-light); color: var(--primary-color); font-weight: 600; }
        body.dark-theme .sidebar-nav li a.active { color: var(--primary-color); }
        .sidebar .logout-link { margin-top: auto; padding-top: 16px; border-top: 1px solid var(--border-color); }

        .top-bar { /* ... styles complets ... */
            background-color: var(--page-bg); padding: 0 30px; display: flex;
            justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color);
            height: 70px; box-sizing: border-box; transition: background-color 0.3s, border-color 0.3s;
            position: sticky; top: 0; z-index: 999;
        }
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
        
        .ressource-item { background-color: var(--page-bg); padding: 16px; border-radius: var(--border-radius-sm); margin-bottom: 12px; border: 1px solid var(--border-color); display: flex; flex-wrap:wrap; justify-content: space-between; align-items: flex-start; gap: 10px; }
        body.dark-theme .ressource-item { background-color: var(--secondary-color); }
        .ressource-info { flex-grow:1; min-width: 200px; }
        .ressource-info .titre { font-weight: 600; color: var(--text-color); display: block; margin-bottom: 4px; font-size: 15px;}
        .ressource-info .description { font-size: 13px; color: var(--text-light); margin-bottom: 6px; line-height: 1.5;}
        .ressource-info .date-depot, .ressource-info .date-session { font-size: 12px; color: var(--text-light); } /* Renommé pour session */
        .ressource-info .lien, .ressource-info .fichier { font-size: 13px; display: block; margin-top: 6px;}
        .ressource-info .lien a, .ressource-info .fichier a { color: var(--primary-color); text-decoration: none; word-break: break-all; font-weight:500; }
        .ressource-info .lien a:hover, .ressource-info .fichier a:hover { text-decoration: underline; }
        .ressource-info .lien i, .ressource-info .fichier i { margin-right: 6px; }

        .ressource-actions { display:flex; gap:8px; flex-shrink:0; margin-top: 8px; align-items:center;}
        .btn-action-icon { text-decoration: none; font-size: 14px; padding: 8px; border-radius: 50%; display: inline-flex; align-items: center; justify-content:center; width: 32px; height:32px; border: 1px solid transparent; transition: background-color 0.2s, color 0.2s, border-color 0.2s; }
        .btn-action-icon.btn-edit { color: var(--accent-blue); }
        .btn-action-icon.btn-edit:hover { background-color: #EFF4FF; border-color: var(--accent-blue); }
        .btn-action-icon.btn-delete { color: var(--danger-color); }
        .btn-action-icon.btn-delete:hover { background-color: #FEF3F2; border-color: var(--danger-color); }
        .btn-action-icon.btn-lancer { background-color: var(--success-color); color:white; }
        .btn-action-icon.btn-lancer:hover { background-color: #027A48; }
        .btn-action-icon.btn-upload-video { background-color: var(--accent-blue); color:white; }
        .btn-action-icon.btn-upload-video:hover { background-color: #175CD3; }
        .form-upload-video input[type="file"] { font-size: 0.8em; max-width: 150px; padding: 5px; margin-right:5px;}


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
                    <li><a href="enregistrer_cours.php" class="<?php echo ($current_page == 'enregistrer_cours.php') ? 'active' : ''; ?>"><i class="fas fa-video"></i> Planifier/Enregistrer Live</a></li>
                    <li><a href="deposer_td.php"><i class="fas fa-tasks"></i> Déposer TD/TC</a></li>
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
                    <a href="enregistrer_cours.php" class="btn-add-new"><i class="fas fa-calendar-plus"></i> Planifier Session</a>
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
                            <h2><i class="fas fa-video"></i> Planifier et Gérer les Cours en Direct</h2>
                            <p>Créez de nouvelles sessions de cours en direct et gérez les enregistrements.</p>
                        </div>

                        <?php if ($message): ?>
                            <?php echo $message; ?>
                        <?php endif; ?>

                        <div class="form-section-container">
                            <h3><i class="fas fa-calendar-plus"></i> Planifier une Nouvelle Session en Direct</h3>
                            <form action="enregistrer_cours.php<?php echo $selected_matiere_id_filter_list ? '?id_matiere_filter='.$selected_matiere_id_filter_list : ''; ?>" method="POST">
                                <div class="form-group">
                                    <label for="id_matiere_live">Matière <span style="color:red;">*</span></label>
                                    <select name="id_matiere_live" id="id_matiere_live" required>
                                        <option value="">-- Sélectionner une matière --</option>
                                        <?php foreach ($matieres_enseignant as $matiere): ?>
                                            <option value="<?php echo $matiere['id']; ?>" <?php echo ($selected_matiere_id_filter_list == $matiere['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($matiere['nom_matiere'] . ' (' . $matiere['nom_classe'] . ')'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="titre_session_live">Titre de la session <span style="color:red;">*</span></label>
                                    <input type="text" id="titre_session_live" name="titre_session_live" required value="<?php echo isset($_POST['titre_session_live']) ? htmlspecialchars($_POST['titre_session_live']) : ''; ?>">
                                </div>
                                <div class="form-group">
                                    <label for="description_session_live">Description (optionnel)</label>
                                    <textarea id="description_session_live" name="description_session_live" rows="3"><?php echo isset($_POST['description_session_live']) ? htmlspecialchars($_POST['description_session_live']) : ''; ?></textarea>
                                </div>
                                <div style="display:flex; flex-wrap:wrap; gap: 15px; margin-bottom:16px;">
                                    <div class="form-group" style="flex:1; min-width: 200px;">
                                        <label for="date_session_live">Date de la session <span style="color:red;">*</span></label>
                                        <input type="date" id="date_session_live" name="date_session_live" required min="<?php echo date('Y-m-d'); ?>" value="<?php echo isset($_POST['date_session_live']) ? htmlspecialchars($_POST['date_session_live']) : ''; ?>">
                                    </div>
                                    <div class="form-group" style="flex:1; min-width: 150px;">
                                        <label for="heure_session_live">Heure de la session <span style="color:red;">*</span></label>
                                        <input type="time" id="heure_session_live" name="heure_session_live" required value="<?php echo isset($_POST['heure_session_live']) ? htmlspecialchars($_POST['heure_session_live']) : ''; ?>">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="lien_visioconference">Lien de la visioconférence (Zoom, Meet, etc.) <span style="color:red;">*</span></label>
                                    <input type="url" id="lien_visioconference" name="lien_visioconference" placeholder="https://us02web.zoom.us/j/..." required value="<?php echo isset($_POST['lien_visioconference']) ? htmlspecialchars($_POST['lien_visioconference']) : ''; ?>">
                                </div>
                                <button type="submit" name="submit_session_live" class="btn-submit-form">
                                    <i class="fas fa-calendar-check"></i> Planifier la Session
                                </button>
                            </form>
                        </div>

                        <div class="resource-list-container">
                            <h3><i class="fas fa-list-video"></i> Sessions en Direct Planifiées et Enregistrements</h3>
                             <form method="GET" action="enregistrer_cours.php" class="filter-form">
                                <div class="form-group" style="display:flex; align-items:center; gap:10px; margin-bottom:0;">
                                    <label for="id_matiere_filter" style="margin-bottom:0;">Filtrer par matière :</label>
                                    <select name="id_matiere_filter" id="id_matiere_filter" onchange="this.form.submit()">
                                        <option value="">-- Toutes mes matières --</option>
                                        <?php foreach ($matieres_enseignant as $matiere): ?>
                                            <option value="<?php echo $matiere['id']; ?>" <?php echo ($selected_matiere_id_filter_list == $matiere['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($matiere['nom_matiere'] . ' (' . $matiere['nom_classe'] . ')'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </form>

                            <?php if (empty($sessions_planifiees)): ?>
                                <p class="no-data-message">Aucune session planifiée ou enregistrée <?php echo $selected_matiere_id_filter_list ? 'pour cette matière' : ''; ?>.</p>
                            <?php else: ?>
                                <?php foreach ($sessions_planifiees as $session): 
                                    $is_past_session = strtotime($session['date_cours']) < time();
                                ?>
                                <div class="ressource-item">
                                    <div class="ressource-info">
                                        <span class="titre"><?php echo htmlspecialchars($session['titre']); ?></span>
                                        <span class="description">Matière: <?php echo htmlspecialchars($session['nom_matiere']); ?></span>
                                        <span class="date-session">
                                            <?php if ($is_past_session) echo "A eu lieu le: "; else echo "Prévu le: "; ?>
                                            <?php echo date('d/m/Y à H:i', strtotime($session['date_cours'])); ?>
                                        </span>
                                        <?php if(!empty($session['lien_visionnage']) && !$is_past_session): ?>
                                            <span class="lien"><i class="fas fa-external-link-alt"></i> Lien Direct: 
                                                <a href="<?php echo htmlspecialchars($session['lien_visionnage']); ?>" target="_blank">Rejoindre la session</a>
                                            </span>
                                        <?php endif; ?>
                                        <?php if(!empty($session['fichier_video'])): ?>
                                            <span class="fichier"><i class="fas fa-film"></i> Enregistrement: 
                                                <a href="../../uploads/videos/<?php echo htmlspecialchars($session['fichier_video']); ?>" download>Télécharger la vidéo</a>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="ressource-actions">
                                        <?php if (!$is_past_session && !empty($session['lien_visionnage'])): ?>
                                            <a href="<?php echo htmlspecialchars($session['lien_visionnage']); ?>" target="_blank" class="btn-action-icon btn-lancer" title="Lancer la session"><i class="fas fa-play-circle"></i></a>
                                        <?php endif; ?>
                                        <?php if ($is_past_session && empty($session['fichier_video'])): ?>
                                            <form action="enregistrer_cours.php<?php echo $selected_matiere_id_filter_list ? '?id_matiere_filter='.$selected_matiere_id_filter_list : ''; ?>" method="POST" enctype="multipart/form-data" class="form-upload-video">
                                                <input type="hidden" name="id_session_cours" value="<?php echo $session['id']; ?>">
                                                <input type="file" name="fichier_enregistrement_video" id="fichier_enregistrement_video_<?php echo $session['id']; ?>" required title="Choisir l'enregistrement vidéo">
                                                <button type="submit" name="submit_enregistrement_video" class="btn-action-icon btn-upload-video" title="Uploader l'enregistrement"><i class="fas fa-upload"></i></button>
                                            </form>
                                        <?php endif; ?>
                                        <a href="modifier_session_live.php?id_session=<?php echo $session['id']; ?>" class="btn-action-icon btn-edit" title="Modifier"><i class="fas fa-edit"></i></a>
                                        <a href="supprimer_session_live.php?id_session=<?php echo $session['id']; ?>&id_matiere_filter=<?php echo $selected_matiere_id_filter_list; ?>" 
                                           class="btn-action-icon btn-delete" title="Supprimer" 
                                           onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette session ?');">
                                           <i class="fas fa-trash-alt"></i>
                                        </a>
                                    </div>
                                </div>
                                <?php endforeach; ?>
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
                const storedTheme = localStorage.getItem('janglitech-theme') || 'light-theme';
                applyTheme(storedTheme);

                themeToggle.addEventListener('click', function (e) {
                    e.preventDefault();
                    let newTheme = body.classList.contains('dark-theme') ? 'light-theme' : 'dark-theme';
                    applyTheme(newTheme);
                    localStorage.setItem('janglitech-theme', newTheme);
                });
            }
        });
    </script>
</body>
</html>