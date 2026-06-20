<?php
// /dashboard/enseignant/deposer_cours.php

require_once '../../config/db.php';

$database = new Database();
$conn = $database->connect();

// --- ID Enseignant Simulé ---
define('SIMULATED_ENSEIGNANT_ID_DEPOT', 1); // !! IMPORTANT: ID enseignant existant

// --- Fonctions de récupération et de gestion ---
function get_enseignant_info_for_depot($db_conn, $id_enseignant_table) {
    $stmt = $db_conn->prepare("SELECT u.nom, u.prenom, u.photo FROM enseignants ens JOIN utilisateurs u ON ens.id_utilisateur = u.id WHERE ens.id = :id_enseignant");
    $stmt->bindParam(':id_enseignant', $id_enseignant_table, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function get_matieres_enseignees_for_select($db_conn, $id_enseignant) {
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

function get_ressources_deposees_par_matiere($db_conn, $id_matiere, $id_enseignant) {
    $stmt = $db_conn->prepare("
        SELECT id, titre, description, fichier_support as fichier_ressource, lien_visionnage, date_depot as date_cours 
        FROM cours_deposes
        WHERE id_matiere = :id_matiere AND id_enseignant = :id_enseignant
        ORDER BY date_depot DESC
    ");
    $stmt->bindParam(':id_matiere', $id_matiere, PDO::PARAM_INT);
    $stmt->bindParam(':id_enseignant', $id_enseignant, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// --- Logique de la page ---
$enseignant_info_page = get_enseignant_info_for_depot($conn, SIMULATED_ENSEIGNANT_ID_DEPOT);
if (!$enseignant_info_page) die("Erreur enseignant simulé: ID " . SIMULATED_ENSEIGNANT_ID_DEPOT . " non trouvé ou l'enseignant n'est pas lié à un utilisateur.");

$enseignant_avatar = !empty($enseignant_info_page['photo']) ? '../../uploads/profiles/' . htmlspecialchars($enseignant_info_page['photo']) : '../../assets/images/default_avatar_teacher.png';
$enseignant_nom_complet = htmlspecialchars($enseignant_info_page['prenom'] . ' ' . $enseignant_info_page['nom']);

$matieres_enseignant = get_matieres_enseignees_for_select($conn, SIMULATED_ENSEIGNANT_ID_DEPOT);
$ressources_deposees = [];
$message = '';
$selected_matiere_id_filter = null;
if (isset($_GET['id_matiere_filter'])) {
    $selected_matiere_id_filter = (int)$_GET['id_matiere_filter'];
} elseif (isset($_POST['id_matiere'])) {
    $selected_matiere_id_filter = (int)$_POST['id_matiere'];
}

// Traitement du formulaire de dépôt
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_cours'])) {
    $id_matiere_post = filter_input(INPUT_POST, 'id_matiere', FILTER_VALIDATE_INT);
    $titre = trim(filter_input(INPUT_POST, 'titre_cours', FILTER_SANITIZE_STRING));
    $description = trim(filter_input(INPUT_POST, 'description_cours', FILTER_SANITIZE_STRING));
    $lien_externe = trim(filter_input(INPUT_POST, 'lien_externe', FILTER_SANITIZE_URL));
    $fichier_ressource_nom = null;

    if (empty($id_matiere_post) || empty($titre)) {
        $message = "<div class='alert alert-danger'>Veuillez sélectionner une matière et fournir un titre pour la ressource.</div>";
    } else {
        $is_own_matiere = false;
        foreach ($matieres_enseignant as $mat) {
            if ($mat['id'] == $id_matiere_post) { $is_own_matiere = true; break; }
        }
        if (!$is_own_matiere) {
             $message = "<div class='alert alert-danger'>Erreur: Matière non valide ou non assignée.</div>";
        } else {
            if (isset($_FILES['fichier_cours']) && $_FILES['fichier_cours']['error'] == UPLOAD_ERR_OK) {
                $upload_dir_cours = '../../uploads/cours/';
                if (!is_dir($upload_dir_cours)) { mkdir($upload_dir_cours, 0775, true); }

                $file_tmp_path = $_FILES['fichier_cours']['tmp_name'];
                $file_name = basename($_FILES['fichier_cours']['name']);
                $safe_file_name = preg_replace("/[^a-zA-Z0-9._-]/", "_", $file_name);
                $unique_file_name = time() . '_' . SIMULATED_ENSEIGNANT_ID_DEPOT . '_' . $safe_file_name;
                $dest_path = $upload_dir_cours . $unique_file_name;
                $allowed_extensions_cours = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'txt', 'jpg', 'jpeg', 'png', 'gif', 'mp4', 'mov', 'avi', 'mp3', 'zip', 'rar'];
                $file_extension_cours = strtolower(pathinfo($unique_file_name, PATHINFO_EXTENSION));
                $file_size_cours = $_FILES['fichier_cours']['size'];

                if (!in_array($file_extension_cours, $allowed_extensions_cours)) {
                    $message = "<div class='alert alert-danger'>Type de fichier non autorisé. Permis: " . implode(', ', $allowed_extensions_cours) . "</div>";
                } elseif ($file_size_cours > 20 * 1024 * 1024) { // 20MB
                    $message = "<div class='alert alert-danger'>Le fichier est trop volumineux (max 20MB).</div>";
                } else {
                    if (move_uploaded_file($file_tmp_path, $dest_path)) {
                        $fichier_ressource_nom = $unique_file_name;
                    } else {
                        $message = "<div class='alert alert-danger'>Erreur lors du téléchargement du fichier de cours. Code: ".$_FILES['fichier_cours']['error']."</div>";
                    }
                }
            }
            
            if (!empty($lien_externe) && !filter_var($lien_externe, FILTER_VALIDATE_URL)) {
                 $message = "<div class='alert alert-danger'>Le lien externe fourni n'est pas une URL valide.</div>";
            }

            if (empty($message) && ($fichier_ressource_nom !== null || (!empty($lien_externe) && filter_var($lien_externe, FILTER_VALIDATE_URL)))) {
                try {
                    $stmt_insert = $conn->prepare("
                        INSERT INTO cours_deposes (id_matiere, id_enseignant, titre, description, fichier_support, lien_visionnage, date_depot)
                        VALUES (:id_matiere, :id_enseignant, :titre, :description, :fichier_ressource, :lien_externe, NOW())
                    ");
                    $stmt_insert->execute([
                        ':id_matiere' => $id_matiere_post, ':id_enseignant' => SIMULATED_ENSEIGNANT_ID_DEPOT,
                        ':titre' => $titre, ':description' => $description,
                        ':fichier_ressource' => $fichier_ressource_nom,
                        ':lien_externe' => !empty($lien_externe) ? $lien_externe : null,
                    ]);
                    header("Location: deposer_cours.php?id_matiere_filter=" . $id_matiere_post . "&succes=1");
                    exit();
                } catch (PDOException $e) {
                    error_log("Erreur dépôt cours: " . $e->getMessage());
                    $message = "<div class='alert alert-danger'>Une erreur technique est survenue lors du dépôt.</div>";
                }
            } elseif (empty($message) && $fichier_ressource_nom === null && empty($lien_externe)) {
                 $message = "<div class='alert alert-danger'>Veuillez uploader un fichier OU fournir un lien externe valide.</div>";
            }
        }
    }
}

// Messages GET après redirection
if(isset($_GET['succes']) && $_GET['succes'] == 1 && empty($message)) {
    $message = "<div class='alert alert-success'>Support de cours déposé avec succès !</div>";
}
if(isset($_GET['delete_succes']) && $_GET['delete_succes'] == 1 && empty($message)) {
    $message = "<div class='alert alert-success'>Ressource supprimée avec succès !</div>";
}
if(isset($_GET['delete_error']) && empty($message)) {
    $message = "<div class='alert alert-danger'>Erreur lors de la suppression de la ressource.</div>";
}

// Charger les ressources pour affichage
if ($selected_matiere_id_filter) {
    $ressources_deposees = get_ressources_deposees_par_matiere($conn, $selected_matiere_id_filter, SIMULATED_ENSEIGNANT_ID_DEPOT);
}

$page_title = "Déposer un Support de Cours";
$current_page = basename($_SERVER['PHP_SELF']);
?>


<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - JANGLITECH</title>
    <link rel="stylesheet" href="../../assets/css/style.css"> <!-- LIEN VERS LE CSS GLOBAL -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-qC+/IJ7Qe7FBAHg07bAAc6rHyJ+UoeU3k4L7F6gJkF/96jHzFkaWcUYZvFZtAn3P" crossorigin="anonymous">

    <style>
        /* ../../assets/css/style.css */

/* Styles inspirés de "Hireism" et adaptés pour JANGLITECH Enseignant */
:root {
    --primary-color: #0066FF;
    --primary-light: #E6F0FF;
    --primary-dark: #004FCC;

    --secondary-color: #F8FAFC;
    --page-bg: #F4F8FF;
    --sidebar-bg: #FFFFFF;
    --card-bg: #FFFFFF;

    --text-color: #0F172A;
    --text-light: #64748B;
    --border-color: #DCE6F5;

    --accent-blue: #0066FF;
    --danger-color: #EF4444;
    --success-color: #10B981;
    --warning-color: #F59E0B;
    --info-color: #3B82F6;

    --font-family: 'Inter', sans-serif;
    --border-radius-md: 8px;
    --border-radius-sm: 6px;

    --shadow-sm: 0 1px 3px rgba(0,102,255,0.08);
    --shadow-md: 0 4px 12px rgba(0,102,255,0.12);
}

body.dark-theme {
    --primary-color: #3B82F6;
    --primary-light: #1E3A8A;
    --primary-dark: #2563EB;

    --secondary-color: #1E293B;
    --page-bg: #0F172A;
    --sidebar-bg: #111827;
    --card-bg: #1E293B;

    --text-color: #F8FAFC;
    --text-light: #CBD5E1;
    --border-color: #334155;

    --accent-blue: #60A5FA;
    --danger-color: #F87171;
    --success-color: #34D399;
    --warning-color: #FBBF24;
    --info-color: #60A5FA;
}
/* --- Styles Généraux --- */
body {
    font-family: var(--font-family); margin: 0; background-color: var(--page-bg);
    color: var(--text-color); display: flex; min-height: 100vh;
    transition: background-color 0.3s, color 0.3s; font-size: 14px;
    -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale;
}
.dashboard-container { display: flex; width: 100%; }
.main-wrapper {
    flex-grow: 1; margin-left: 260px; /* Largeur de la sidebar */
    display: flex; flex-direction: column;
    width: calc(100% - 260px); background-color: var(--page-bg);
    transition: background-color 0.3s;
}
.content-area {
    flex-grow: 1; padding: 24px 30px; display: flex; gap: 24px; overflow-y: auto;
}
.main-column { flex-grow: 1; display: flex; flex-direction: column; gap: 24px;}
.right-column-dashboard { width: 320px; flex-shrink: 0; display: flex; flex-direction: column; gap: 24px; }

/* --- Barre Latérale Gauche --- */
.sidebar {
    width: 260px; background-color: var(--sidebar-bg); padding: 24px 16px;
    border-right: 1px solid var(--border-color);
    display: flex; flex-direction: column; height: 100vh; position: fixed;
    left: 0; top: 0; z-index: 1000; transition: background-color 0.3s, border-color 0.3s;
    overflow-y: auto;
}
.sidebar-logo {
    display: flex; align-items: center; padding: 0 8px 24px 8px;
    font-size: 22px; font-weight: 700; color: var(--primary-color);
}
.sidebar-logo .logo-icon { margin-right: 10px; font-size: 24px; }
.sidebar-nav ul { list-style: none; padding: 0; margin: 0; flex-grow: 1; }
.sidebar-nav li a {
    display: flex; align-items: center; padding: 10px 12px; color: var(--text-light);
    text-decoration: none; border-radius: var(--border-radius-sm); margin-bottom: 4px; font-weight: 500;
    transition: background-color 0.2s, color 0.2s; font-size: 14px;
}
.sidebar-nav li a i { margin-right: 12px; width: 20px; text-align: center; font-size: 16px; }
.sidebar-nav li a:hover { background-color: var(--primary-light); color: var(--primary-color); }
.sidebar-nav li a.active {
    background-color: var(--primary-light); color: var(--primary-color); font-weight: 600;
}
body.dark-theme .sidebar-nav li a.active {
    color: var(--primary-color); /* En mode sombre, le texte actif peut rester la couleur primaire pour le contraste */
}
.sidebar .logout-link { margin-top: auto; padding-top: 16px; border-top: 1px solid var(--border-color); }

/* --- Barre Supérieure --- */
.top-bar {
    background-color: var(--page-bg); padding: 0 30px; display: flex;
    justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color);
    height: 70px; box-sizing: border-box; transition: background-color 0.3s, border-color 0.3s;
    position: sticky; top: 0; z-index: 999;
}
.search-bar { position: relative; flex-grow:1; max-width: 400px;}
.search-bar input {
    padding: 10px 15px 10px 40px; border-radius: var(--border-radius-md); border: 1px solid var(--border-color);
    width: 100%; background-color: var(--secondary-color); color: var(--text-color); font-size: 14px;
}
.search-bar input::placeholder { color: var(--text-light); }
.search-bar i.fa-search { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: var(--text-light); }
.top-bar-actions { display: flex; align-items: center; gap: 16px; }
.btn-add-new {
    background-color: var(--primary-color); color: white; padding: 10px 16px; text-decoration: none;
    border-radius: var(--border-radius-md); font-weight: 600; font-size: 14px; display: flex; align-items: center; gap: 8px;
    transition: background-color 0.2s;
}
/* Bouton principal */
.btn-add-new,
.btn-submit-form {
    background: linear-gradient(135deg, #0066FF, #3399FF);
    border: none;
}

.btn-add-new:hover,
.btn-submit-form:hover {
    background: linear-gradient(135deg, #0052CC, #007BFF);
}

/* Bannière dashboard */
.welcome-banner-teacher {
    background: linear-gradient(135deg, #0066FF, #3399FF);
    box-shadow: 0 10px 30px rgba(0,102,255,0.25);
}

/* Logo */
.sidebar-logo {
    color: #0066FF;
}

/* Menu actif */
.sidebar-nav li a.active {
    background: #E6F0FF;
    color: #0066FF;
    border-left: 4px solid #0066FF;
}

/* Hover menu */
.sidebar-nav li a:hover {
    background: #E6F0FF;
    color: #0066FF;
}

/* Cartes */
.page-container,
.form-section-container,
.resource-list-container,
.stat-card,
.widget-card {
    box-shadow: 0 4px 15px rgba(0,102,255,0.08);
    border: 1px solid #DCE6F5;
}

/* Champs formulaire */
.form-group input:focus,
.form-group textarea:focus,
.form-group select:focus {
    border-color: #0066FF;
    box-shadow: 0 0 0 4px rgba(0,102,255,0.15);
}

/* Boutons édition */
.btn-action-icon.btn-edit {
    color: #0066FF;
}

.btn-action-icon.btn-edit:hover {
    background: #E6F0FF;
    border-color: #0066FF;
}

/* Titres */
.page-header h2,
.form-section-container h3,
.resource-list-container h3 {
    color: #0066FF;
}
.top-bar-actions .icon-btn:hover { color: var(--primary-color); background-color: var(--primary-light); }
.user-profile-widget { display: flex; align-items: center; gap: 12px; }
.user-profile-widget img { width: 40px; height: 40px; border-radius: 50%; object-fit: cover;}
.user-profile-widget .user-info .user-name { font-weight: 600; color: var(--text-color); display: block;}
.user-profile-widget .user-info .user-role { font-size: 0.85em; color: var(--text-light); }

/* --- Styles pour les Contenus de Page Standards --- */
.page-container {
    background-color: var(--card-bg); padding: 24px; border-radius: var(--border-radius-md);
    box-shadow: var(--shadow-sm); border: 1px solid var(--border-color); width: 100%;
    transition: background-color 0.3s, border-color 0.3s;
}
.page-header { margin-bottom: 24px; padding-bottom:16px; border-bottom: 1px solid var(--border-color);}
.page-header h2 {
    color: var(--text-color); /* Plus standard pour les titres de page */
    font-size: 20px; font-weight: 600; margin: 0; display: flex; align-items:center; gap:10px;
}
.page-header p { color: var(--text-light); font-size: 14px; margin-top: 4px; margin-bottom:0; }

/* --- Styles pour Formulaires --- */
.form-section-container {
    margin-bottom: 24px; padding: 20px; border-radius: var(--border-radius-md);
    background-color: var(--card-bg); /* Fond blanc pour les sections de formulaire */
    border: 1px solid var(--border-color);
}
body.dark-theme .form-section-container { background-color: var(--secondary-color); } /* Léger contraste en mode sombre */

.form-section-container h3 {
    color: var(--primary-color); margin-top: 0; margin-bottom: 20px; font-size: 18px;
    font-weight: 600; display: flex; align-items: center; gap: 10px;
    padding-bottom: 10px; border-bottom: 1px solid var(--primary-light);
}
.form-group { margin-bottom: 16px; }
.form-group label {
    display: block; margin-bottom: 6px; font-weight: 500;
    font-size: 14px; color: var(--text-color); /* Labels plus foncés */
}
.form-group label span[style*="color:red"] { color: var(--danger-color) !important; } /* Pour l'astérisque */

.form-group input[type="text"],
.form-group input[type="url"],
.form-group input[type="email"],
.form-group input[type="password"],
.form-group textarea,
.form-group select {
    width: 100%; padding: 10px 12px; border-radius: var(--border-radius-sm);
    border: 1px solid var(--border-color); font-size: 14px;
    background-color: var(--page-bg); /* Fond de page pour inputs, contraste avec section */
    color: var(--text-color); box-sizing: border-box;
    transition: border-color 0.2s, box-shadow 0.2s;
}
body.dark-theme .form-group input[type="text"],
body.dark-theme .form-group input[type="url"],
body.dark-theme .form-group input[type="email"],
body.dark-theme .form-group input[type="password"],
body.dark-theme .form-group textarea,
body.dark-theme .form-group select {
    background-color: var(--secondary-color); /* Un peu plus clair que le fond de page en mode sombre */
}

.form-group input:focus, .form-group textarea:focus, .form-group select:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px var(--primary-light);
    outline: none;
}
.form-group textarea { min-height: 100px; resize: vertical; }
.form-group input[type="file"] {
    padding: 8px; border: 1px solid var(--border-color); border-radius: var(--border-radius-sm);
    background-color: var(--page-bg); color: var(--text-color); display: block; width:100%;
}
.form-group .file-or-link-separator {
    text-align: center; margin: 15px 0; color: var(--text-light); font-weight: 500; font-size: 13px;
}
.btn-submit-form {
    background-color: var(--primary-color); color: white; padding: 10px 20px;
    border: none; border-radius: var(--border-radius-md); font-size: 14px; font-weight: 600;
    cursor: pointer; transition: background-color 0.2s;
    display: inline-flex; align-items: center; gap: 8px;
}
.btn-submit-form:hover { background-color: var(--primary-dark); }

/* --- Styles pour Liste de Ressources --- */
.resource-list-container {
    margin-bottom: 24px; padding: 20px; border-radius: var(--border-radius-md);
    background-color: var(--card-bg); border: 1px solid var(--border-color);
}
.resource-list-container h3 { /* Similaire à .form-section-container h3 */
    color: var(--primary-color); margin-top: 0; margin-bottom: 20px; font-size: 18px;
    font-weight: 600; display: flex; align-items: center; gap: 10px;
    padding-bottom: 10px; border-bottom: 1px solid var(--primary-light);
}
/* Formulaire de filtre dans la liste des ressources */
.filter-form {
    margin-bottom: 20px; display:flex; gap:10px; align-items:center;
    padding: 10px; background-color: var(--secondary-color); border-radius: var(--border-radius-md);
}
.filter-form label { margin-bottom:0; white-space:nowrap; color:var(--text-light); font-weight:500; }
.filter-form select {
    flex-grow:1; padding:8px 10px; border-radius:var(--border-radius-sm);
    border:1px solid var(--border-color); background-color: var(--card-bg); color: var(--text-color);
}

.ressource-item {
    background-color: var(--page-bg); /* Ou var(--secondary-color) pour léger contraste */
    padding: 16px; border-radius: var(--border-radius-sm); margin-bottom: 12px;
    border: 1px solid var(--border-color); display: flex; flex-wrap:wrap;
    justify-content: space-between; align-items: flex-start; /* flex-start pour descriptions longues */
    gap: 10px;
}
body.dark-theme .ressource-item { background-color: var(--secondary-color); }

.ressource-info { flex-grow:1; min-width: 200px; }
.ressource-info .titre { font-weight: 600; color: var(--text-color); display: block; margin-bottom: 4px; font-size: 15px;}
.ressource-info .description { font-size: 13px; color: var(--text-light); margin-bottom: 6px; line-height: 1.5;}
.ressource-info .date-depot { font-size: 12px; color: var(--text-light); }
.ressource-info .lien, .ressource-info .fichier { font-size: 13px; display: block; margin-top: 6px;}
.ressource-info .lien a, .ressource-info .fichier a {
    color: var(--primary-color); text-decoration: none; word-break: break-all; font-weight:500;
}
.ressource-info .lien a:hover, .ressource-info .fichier a:hover { text-decoration: underline; }
.ressource-info .lien i, .ressource-info .fichier i { margin-right: 6px; }

.ressource-actions { display:flex; gap:8px; flex-shrink:0; margin-top: 8px; /* S'assure qu'il y a de l'espace si ça wrap */ }
.btn-action-icon {
    text-decoration: none; font-size: 14px; /* Taille de l'icône */
    padding: 8px; border-radius: 50%; /* Rond */
    display: inline-flex; align-items: center; justify-content:center;
    width: 32px; height:32px; /* Taille fixe pour les boutons ronds */
    border: 1px solid transparent;
    transition: background-color 0.2s, color 0.2s, border-color 0.2s;
}
.btn-action-icon.btn-edit { color: var(--accent-blue); }
.btn-action-icon.btn-edit:hover { background-color: #EFF4FF; border-color: var(--accent-blue); }
.btn-action-icon.btn-delete { color: var(--danger-color); }
.btn-action-icon.btn-delete:hover { background-color: #FEF3F2; border-color: var(--danger-color); }


/* --- Alertes --- */
.alert { padding: 12px 16px; margin-bottom: 20px; border-radius: var(--border-radius-sm); border-width:1px; border-style:solid; font-size:14px;}
.alert-success { background-color: #ECFDF3; color: #027A48; border-color: #ABEFC6; }
.alert-danger { background-color: #FEF3F2; color: #B42318; border-color: #FECDCA; }
.alert-info { background-color: #EFF4FF; color: #2970FF; border-color: #B2CCFF; }

/* Message pour aucune donnée */
.no-data-message { text-align:center; padding:20px; color:var(--text-light); font-style: italic; }


/* Styles du Dashboard index.php enseignant (welcome banner, quick stats, etc.) doivent aussi être ici */
.welcome-banner-teacher {
    background: var(--primary-color); color: white; padding: 24px 30px; border-radius: var(--border-radius-md);
    display: flex; justify-content: space-between; align-items: center;
}
/* ... (tous les autres styles que vous aviez pour index.php enseignant) ... */
    </style>
</head>
<body>
    <div class="dashboard-container">
         <aside class="sidebar">
            <div class="sidebar-logo"><i class="fas fa-chalkboard-teacher logo-icon"></i> E-SCHOOL</div>
            <nav class="sidebar-nav">
                <ul>
                    <li><a href="index.php"><i class="fas fa-th-large"></i> Tableau de Bord</a></li>
                    <li><a href="deposer_cours.php" class="<?php echo ($current_page == 'deposer_cours.php') ? 'active' : ''; ?>"><i class="fas fa-file-medical"></i> Déposer Cours</a></li>
                    <li><a href="enregistrer_cours.php"><i class="fas fa-video"></i> Planifier/Enregistrer Live</a></li>
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
                    <a href="deposer_cours.php" class="btn-add-new"><i class="fas fa-plus"></i> Nouveau Contenu</a>
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
                            <h2><i class="fas fa-cloud-upload-alt"></i> Gérer les Supports de Cours</h2>
                            <p>Ajoutez, visualisez et organisez les ressources pédagogiques pour vos matières.</p>
                        </div>

                        <?php if ($message): ?>
                            <?php echo $message; ?>
                        <?php endif; ?>

                        <div class="form-section-container">
                            <h3><i class="fas fa-plus-circle"></i> Déposer une nouvelle ressource</h3>
                            <form action="deposer_cours.php<?php echo $selected_matiere_id_filter ? '?id_matiere_filter='.$selected_matiere_id_filter : ''; ?>" method="POST" enctype="multipart/form-data">
                                <div class="form-group">
                                    <label for="id_matiere">Matière <span style="color:red;">*</span></label>
                                    <select name="id_matiere" id="id_matiere" required>
                                        <option value="">-- Sélectionner une matière --</option>
                                        <?php foreach ($matieres_enseignant as $matiere): ?>
                                            <option value="<?php echo $matiere['id']; ?>" <?php echo ($selected_matiere_id_filter == $matiere['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($matiere['nom_matiere'] . ' (' . $matiere['nom_classe'] . ')'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="titre_cours">Titre de la ressource <span style="color:red;">*</span></label>
                                    <input type="text" id="titre_cours" name="titre_cours" required value="<?php echo isset($_POST['titre_cours']) ? htmlspecialchars($_POST['titre_cours']) : ''; ?>">
                                </div>
                                <div class="form-group">
                                    <label for="description_cours">Description (optionnel)</label>
                                    <textarea id="description_cours" name="description_cours" rows="3"><?php echo isset($_POST['description_cours']) ? htmlspecialchars($_POST['description_cours']) : ''; ?></textarea>
                                </div>
                                <div class="form-group">
                                    <label for="fichier_cours">Uploader un fichier (PDF, DOC, PPT, Vidéo, etc. - Max 20MB)</label>
                                    <input type="file" id="fichier_cours" name="fichier_cours">
                                </div>
                                <div class="form-group file-or-link-separator">OU</div>
                                <div class="form-group">
                                    <label for="lien_externe">Fournir un lien externe (ex: YouTube, Google Drive, Article)</label>
                                    <input type="url" id="lien_externe" name="lien_externe" placeholder="https://example.com/resource" value="<?php echo isset($_POST['lien_externe']) ? htmlspecialchars($_POST['lien_externe']) : ''; ?>">
                                </div>
                                <button type="submit" name="submit_cours" class="btn-submit-form">
                                    <i class="fas fa-save"></i> Déposer la Ressource
                                </button>
                            </form>
                        </div>

                        <div class="resource-list-container">
                            <h3><i class="fas fa-list-ul"></i> Ressources déjà déposées</h3>
                            <form method="GET" action="deposer_cours.php" class="filter-form" style="margin-bottom:20px;">
                                <div class="form-group" style="display:flex; align-items:center; gap:10px; margin-bottom:0;">
                                    <label for="id_matiere_filter" style="margin-bottom:0; white-space:nowrap; color:var(--text-light); font-weight:500;">Filtrer :</label>
                                    <select name="id_matiere_filter" id="id_matiere_filter" onchange="this.form.submit()" style="flex-grow:1;">
                                        <option value="">-- Toutes mes matières --</option>
                                        <?php foreach ($matieres_enseignant as $matiere): ?>
                                            <option value="<?php echo $matiere['id']; ?>" <?php echo ($selected_matiere_id_filter == $matiere['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($matiere['nom_matiere'] . ' (' . $matiere['nom_classe'] . ')'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </form>

                            <?php if ($selected_matiere_id_filter && empty($ressources_deposees)): ?>
                                <p class="no-data-message">Aucune ressource déposée pour cette matière.</p>
                            <?php elseif (!$selected_matiere_id_filter && empty($matieres_enseignant)): ?>
                                 <p class="no-data-message">Vous n'êtes assigné à aucune matière.</p>
                            <?php elseif (!$selected_matiere_id_filter && !empty($matieres_enseignant)): ?>
                                 <p class="no-data-message">Veuillez sélectionner une matière.</p>
                            <?php elseif (!empty($ressources_deposees)): ?>
                                <?php foreach ($ressources_deposees as $res): ?>
                                <div class="ressource-item">
                                    <div class="ressource-info">
                                        <span class="titre"><?php echo htmlspecialchars($res['titre']); ?></span>
                                        <?php if(!empty($res['description'])): ?>
                                            <span class="description"><?php echo nl2br(htmlspecialchars(substr($res['description'], 0, 150))); ?>...</span>
                                        <?php endif; ?>
                                        <?php if(!empty($res['fichier_ressource'])): ?>
                                            <span class="fichier">
                                                <i class="fas fa-paperclip"></i> Fichier: 
                                                <a href="../../uploads/cours/<?php echo htmlspecialchars($res['fichier_ressource']); ?>" download>
                                                    <?php echo htmlspecialchars(substr($res['fichier_ressource'], strpos($res['fichier_ressource'], "_", strpos($res['fichier_ressource'], "_") + 1) + 1)); ?>
                                                </a>
                                            </span>
                                        <?php endif; ?>
                                        <?php if(!empty($res['lien_visionnage'])): ?>
                                            <span class="lien">
                                                <i class="fas fa-link"></i> Lien: 
                                                <a href="<?php echo htmlspecialchars($res['lien_visionnage']); ?>" target="_blank" rel="noopener noreferrer">
                                                    <?php echo htmlspecialchars(substr($res['lien_visionnage'],0,50)); ?>...
                                                </a>
                                            </span>
                                        <?php endif; ?>
                                        <span class="date-depot">Déposé le: <?php echo date('d/m/Y H:i', strtotime($res['date_cours'])); ?></span>
                                    </div>
                                    <div class="ressource-actions">
                                        <a href="modifier_cours.php?id_ressource=<?php echo $res['id']; ?>" class="btn-action-icon btn-edit" title="Modifier"><i class="fas fa-edit"></i></a>
                                        <a href="supprimer_cours.php?id_ressource=<?php echo $res['id']; ?>&id_matiere_filter=<?php echo $selected_matiere_id_filter; ?>" 
                                           class="btn-action-icon btn-delete" title="Supprimer" 
                                           onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette ressource ? Cette action est irréversible.');">
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