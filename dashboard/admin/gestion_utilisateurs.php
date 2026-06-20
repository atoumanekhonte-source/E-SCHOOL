<?php
session_start();

// Configuration des erreurs (utile pour le développement)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// --- Définition de BASE_URL ---
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
$base_path_script_dir = dirname(dirname(dirname($_SERVER['SCRIPT_NAME']))); // Remonte de 3 niveaux
define('BASE_URL', rtrim($protocol . $host . $base_path_script_dir, '/') . '/');

// --- Classe Database (intégrée) ---
class Database {
    private $host = 'localhost';
    private $db_name = 'gestion_school';
    private $username = 'root';
    private $password = ''; // Votre mot de passe MySQL
    private $conn;

    public function connect() {
        $this->conn = null;
        try {
            $this->conn = new PDO('mysql:host=' . $this->host . ';dbname=' . $this->db_name, $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->exec('SET NAMES utf8');
        } catch(PDOException $e) {
            error_log('Erreur de connexion: ' . $e->getMessage());
            die('Erreur de connexion DB. Message: ' . $e->getMessage());
        }
        return $this->conn;
    }
    public function prepare($sql) { if (!$this->conn) $this->connect(); return $this->conn->prepare($sql); }
    public function execute($stmt, $params = []) { try { return $stmt->execute($params); } catch(PDOException $e) { error_log('Erreur SQL: ' . $e->getMessage() . ' | Query: ' . $stmt->queryString); return false; } }
    public function fetchAll($stmt) { return $stmt->fetchAll(PDO::FETCH_ASSOC); }
    public function fetch($stmt) { return $stmt->fetch(PDO::FETCH_ASSOC); }
    public function lastInsertId() { return $this->conn->lastInsertId(); }
    public function rowCount($stmt) { return $stmt->rowCount(); }
}

// --- Fonction de Sanétisation (intégrée) ---
function sanitizeInput($data) {
    if (is_array($data)) return array_map('sanitizeInput', $data);
    if ($data === null) return null;
    if (!is_string($data) && !is_numeric($data) && !is_bool($data)) $data = (string) $data;
    if (is_string($data)) { $data = trim($data); $data = stripslashes($data); $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8'); }
    return $data;
}

// --- Fonction pour obtenir la page actuelle ---
function getCurrentPage() { return basename($_SERVER['SCRIPT_FILENAME']); }

// --- Connexion à la base de données ---
$database = new Database();
$db = $database->connect();

// --- Logique CRUD pour les Utilisateurs ---
$pageTitle = "Gestion des Utilisateurs";
$message = '';
$message_type = '';

// Chemin pour les uploads de photos
define('UPLOAD_DIR', dirname(dirname(dirname(__DIR__))) . '/assets/uploads/avatars/'); // gestion_school/assets/uploads/avatars/
define('UPLOAD_URL_DIR', BASE_URL . 'assets/uploads/avatars/');

if (!is_dir(UPLOAD_DIR)) {
    if (!mkdir(UPLOAD_DIR, 0775, true)) {
        $message = "Erreur: Impossible de créer le dossier d'upload: " . UPLOAD_DIR;
        $message_type = "danger";
        // Ne pas arrêter le script, mais l'upload ne fonctionnera pas
    }
}


// AJOUTER un utilisateur
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_utilisateur'])) {
    $nom = sanitizeInput($_POST['nom']);
    $prenom = sanitizeInput($_POST['prenom']);
    $email = filter_var(sanitizeInput($_POST['email']), FILTER_VALIDATE_EMAIL);
    $mot_de_passe = $_POST['mot_de_passe']; // Ne pas sanitiser avant hachage
    $role = sanitizeInput($_POST['role']);
    $photo_path = null;

    if (empty($nom) || empty($prenom) || !$email || empty($mot_de_passe) || empty($role)) {
        $_SESSION['message'] = "Tous les champs (sauf photo) sont requis et l'email doit être valide.";
        $_SESSION['message_type'] = "danger";
    } elseif (strlen($mot_de_passe) < 6) {
        $_SESSION['message'] = "Le mot de passe doit comporter au moins 6 caractères.";
        $_SESSION['message_type'] = "danger";
    } else {
        try {
            $stmt_check_email = $database->prepare("SELECT id FROM utilisateurs WHERE email = :email");
            $database->execute($stmt_check_email, [':email' => $email]);
            if ($database->rowCount($stmt_check_email) > 0) {
                $_SESSION['message'] = "Cet email est déjà utilisé par un autre compte.";
                $_SESSION['message_type'] = "warning";
            } else {
                // Gestion de l'upload de la photo
                if (isset($_FILES['photo']) && $_FILES['photo']['error'] == UPLOAD_ERR_OK) {
                    $tmp_name = $_FILES['photo']['tmp_name'];
                    $file_name = time() . '_' . basename($_FILES['photo']['name']);
                    $destination = UPLOAD_DIR . $file_name;
                    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                    if (in_array($_FILES['photo']['type'], $allowed_types) && $_FILES['photo']['size'] < 2000000) { // Max 2MB
                        if (move_uploaded_file($tmp_name, $destination)) {
                            $photo_path = $file_name; // On stocke juste le nom du fichier
                        } else {
                            $_SESSION['message'] = "Erreur lors du déplacement du fichier uploadé.";
                            $_SESSION['message_type'] = "danger";
                        }
                    } else {
                        $_SESSION['message'] = "Type de fichier non autorisé ou fichier trop volumineux (max 2MB, JPEG, PNG, GIF).";
                        $_SESSION['message_type'] = "danger";
                    }
                }

                if (!isset($_SESSION['message']) || $_SESSION['message_type'] != "danger") { // Si pas d'erreur d'upload majeure
                    $hashed_password = password_hash($mot_de_passe, PASSWORD_DEFAULT);
                    $stmt = $database->prepare("INSERT INTO utilisateurs (nom, prenom, email, mot_de_passe, role, photo) VALUES (:nom, :prenom, :email, :mot_de_passe, :role, :photo)");
                    $database->execute($stmt, [
                        ':nom' => $nom,
                        ':prenom' => $prenom,
                        ':email' => $email,
                        ':mot_de_passe' => $hashed_password,
                        ':role' => $role,
                        ':photo' => $photo_path
                    ]);
                    $_SESSION['message'] = "Utilisateur ajouté avec succès!";
                    $_SESSION['message_type'] = "success";
                }
            }
        } catch (PDOException $e) {
            $_SESSION['message'] = "Erreur lors de l'ajout de l'utilisateur: " . $e->getMessage();
            $_SESSION['message_type'] = "danger";
        }
    }
    header("Location: gestion_utilisateurs.php");
    exit();
}

// PRÉPARER L'ÉDITION
$edit_user_data = null;
if (isset($_GET['edit_id'])) {
    $edit_id = filter_input(INPUT_GET, 'edit_id', FILTER_VALIDATE_INT);
    if ($edit_id) {
        try {
            $stmt = $database->prepare("SELECT * FROM utilisateurs WHERE id = :id");
            $database->execute($stmt, [':id' => $edit_id]);
            $edit_user_data = $database->fetch($stmt);
            if (!$edit_user_data) {
                $_SESSION['message'] = "Utilisateur non trouvé."; $_SESSION['message_type'] = "warning";
                header("Location: gestion_utilisateurs.php"); exit();
            }
        } catch (PDOException $e) {
            $_SESSION['message'] = "Erreur récupération utilisateur: " . $e->getMessage(); $_SESSION['message_type'] = "danger";
        }
    }
}

// METTRE À JOUR
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_utilisateur'])) {
    $id = filter_input(INPUT_POST, 'id_utilisateur', FILTER_VALIDATE_INT);
    $nom = sanitizeInput($_POST['nom']);
    $prenom = sanitizeInput($_POST['prenom']);
    $email = filter_var(sanitizeInput($_POST['email']), FILTER_VALIDATE_EMAIL);
    $role = sanitizeInput($_POST['role']);
    $mot_de_passe_nouveau = $_POST['mot_de_passe_nouveau']; // Non sanitizé avant vérification
    $current_photo = sanitizeInput($_POST['current_photo']); // Nom de la photo actuelle
    $photo_path_update = $current_photo; // Par défaut, garder l'ancienne photo

    if (empty($id) || empty($nom) || empty($prenom) || !$email || empty($role)) {
        $_SESSION['message'] = "Nom, prénom, email valide et rôle sont requis."; $_SESSION['message_type'] = "danger";
    } else {
        try {
            $stmt_check_email = $database->prepare("SELECT id FROM utilisateurs WHERE email = :email AND id != :id");
            $database->execute($stmt_check_email, [':email' => $email, ':id' => $id]);
            if ($database->rowCount($stmt_check_email) > 0) {
                $_SESSION['message'] = "Cet email est déjà utilisé par un autre compte."; $_SESSION['message_type'] = "warning";
            } else {
                // Gestion de l'upload de la nouvelle photo
                if (isset($_FILES['photo_update']) && $_FILES['photo_update']['error'] == UPLOAD_ERR_OK) {
                    $tmp_name = $_FILES['photo_update']['tmp_name'];
                    $file_name_new = time() . '_' . basename($_FILES['photo_update']['name']);
                    $destination_new = UPLOAD_DIR . $file_name_new;
                    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];

                    if (in_array($_FILES['photo_update']['type'], $allowed_types) && $_FILES['photo_update']['size'] < 2000000) {
                        if (move_uploaded_file($tmp_name, $destination_new)) {
                            // Supprimer l'ancienne photo si elle existe et est différente
                            if (!empty($current_photo) && file_exists(UPLOAD_DIR . $current_photo) && $current_photo != $file_name_new) {
                                unlink(UPLOAD_DIR . $current_photo);
                            }
                            $photo_path_update = $file_name_new;
                        } else {
                             $_SESSION['message'] = "Erreur déplacement nouvelle photo."; $_SESSION['message_type'] = "danger";
                        }
                    } else {
                        $_SESSION['message'] = "Type/taille nouvelle photo invalide."; $_SESSION['message_type'] = "danger";
                    }
                }

                if (!isset($_SESSION['message']) || $_SESSION['message_type'] != "danger") {
                    $sql_update = "UPDATE utilisateurs SET nom = :nom, prenom = :prenom, email = :email, role = :role, photo = :photo";
                    $params_update = [
                        ':nom' => $nom, ':prenom' => $prenom, ':email' => $email, ':role' => $role, ':photo' => $photo_path_update, ':id' => $id
                    ];

                    if (!empty($mot_de_passe_nouveau)) {
                        if (strlen($mot_de_passe_nouveau) < 6) {
                             $_SESSION['message'] = "Nouveau mot de passe trop court (min 6 car.)."; $_SESSION['message_type'] = "danger";
                        } else {
                            $hashed_password_new = password_hash($mot_de_passe_nouveau, PASSWORD_DEFAULT);
                            $sql_update .= ", mot_de_passe = :mot_de_passe_new";
                            $params_update[':mot_de_passe_new'] = $hashed_password_new;
                        }
                    }
                    $sql_update .= " WHERE id = :id";

                    if (!isset($_SESSION['message']) || $_SESSION['message_type'] != "danger") {
                        $stmt_update = $database->prepare($sql_update);
                        $database->execute($stmt_update, $params_update);
                        $_SESSION['message'] = "Utilisateur mis à jour!"; $_SESSION['message_type'] = "success";
                    }
                }
            }
        } catch (PDOException $e) {
            $_SESSION['message'] = "Erreur MAJ utilisateur: " . $e->getMessage(); $_SESSION['message_type'] = "danger";
        }
    }
    header("Location: gestion_utilisateurs.php");
    exit();
}


// SUPPRIMER
if (isset($_GET['delete_id'])) {
    $delete_id = filter_input(INPUT_GET, 'delete_id', FILTER_VALIDATE_INT);
    if ($delete_id) {
        try {
            // Vérifier si l'utilisateur est référencé dans etudiants ou enseignants
            $stmt_check_etu = $database->prepare("SELECT id FROM etudiants WHERE id_utilisateur = :id_user");
            $database->execute($stmt_check_etu, [':id_user' => $delete_id]);
            $is_etudiant = $database->rowCount($stmt_check_etu) > 0;

            $stmt_check_ens = $database->prepare("SELECT id FROM enseignants WHERE id_utilisateur = :id_user");
            $database->execute($stmt_check_ens, [':id_user' => $delete_id]);
            $is_enseignant = $database->rowCount($stmt_check_ens) > 0;

            if ($is_etudiant || $is_enseignant) {
                $_SESSION['message'] = "Suppression impossible: utilisateur lié à un rôle étudiant/enseignant.";
                $_SESSION['message_type'] = "warning";
            } else {
                // Récupérer le nom de la photo pour la supprimer du serveur
                $stmt_get_photo = $database->prepare("SELECT photo FROM utilisateurs WHERE id = :id");
                $database->execute($stmt_get_photo, [':id' => $delete_id]);
                $user_to_delete = $database->fetch($stmt_get_photo);

                $stmt_delete = $database->prepare("DELETE FROM utilisateurs WHERE id = :id");
                $database->execute($stmt_delete, [':id' => $delete_id]);

                if ($user_to_delete && !empty($user_to_delete['photo']) && file_exists(UPLOAD_DIR . $user_to_delete['photo'])) {
                    unlink(UPLOAD_DIR . $user_to_delete['photo']);
                }
                $_SESSION['message'] = "Utilisateur supprimé!"; $_SESSION['message_type'] = "success";
            }
        } catch (PDOException $e) {
            $_SESSION['message'] = "Erreur suppression: " . $e->getMessage(); $_SESSION['message_type'] = "danger";
        }
    }
    header("Location: gestion_utilisateurs.php");
    exit();
}

// LISTER les utilisateurs
$users_list = [];
$count_total_users = 0;
try {
    $stmt_list = $database->prepare("SELECT id, nom, prenom, email, role, photo, date_inscription FROM utilisateurs ORDER BY nom ASC, prenom ASC");
    if ($database->execute($stmt_list)) {
        $users_list = $database->fetchAll($stmt_list);
        $count_total_users = count($users_list);
    } else {
        $message = "Erreur récupération liste utilisateurs."; $message_type = "danger";
    }
} catch (PDOException $e) {
    $message = "Erreur DB: " . $e->getMessage(); $message_type = "danger";
}

// Afficher les messages de session
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message']; $message_type = $_SESSION['message_type'];
    unset($_SESSION['message']); unset($_SESSION['message_type']);
}

$currentPage = getCurrentPage();
$userImageUrl = BASE_URL . 'assets/images/default_avatar.png'; // Image par défaut pour le header
$roles_disponibles = ['admin', 'etudiant', 'enseignant'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - Gestion École</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* CSS Intégré (identique à gestion_filieres.php pour la cohérence) */
        body{font-family:'Segoe UI',system-ui,-apple-system,BlinkMacSystemFont,Roboto,Oxygen,Ubuntu,Cantarell,'Open Sans','Helvetica Neue',sans-serif;background-color:#f0f2f5;margin:0;display:flex;min-height:100vh;font-size:.9rem}
        .sidebar{width:250px;background-color:#1e293b;color:#cbd5e1;padding:0;display:flex;flex-direction:column;position:fixed;top:0;left:0;height:100vh;transition:width .3s ease;z-index:1030;border-right:1px solid #334155}
        .sidebar-header{padding:20px 15px;text-align:center;border-bottom:1px solid #334155}
        .sidebar-header .user-avatar{width:70px;height:70px;border-radius:50%;border:3px solid #475569;margin-bottom:10px;object-fit:cover}
        .sidebar-header h5{margin:0;color:#f8fafc;font-weight:500;font-size:1rem}
        .sidebar-nav{padding:15px;overflow-y:auto;flex-grow:1}
        .sidebar-nav .nav-link{color:#94a3b8;padding:10px 15px;display:flex;align-items:center;border-radius:6px;margin-bottom:4px;transition:background-color .2s,color .2s;font-weight:500}
        .sidebar-nav .nav-link i.fa-fw{margin-right:12px;width:20px;text-align:center;font-size:.95em}
        .sidebar-nav .nav-link:hover{background-color:#334155;color:#f8fafc}
        .sidebar-nav .nav-link.active{background-color:#ef4444;color:#fff}
        .sidebar-nav .collapse .nav-link,.sidebar-nav .collapsing .nav-link{padding-left:25px;font-size:.85em;color:#94a3b8}
        .sidebar-nav .collapse .nav-link.active,.sidebar-nav .collapsing .nav-link.active{background-color:#ef4444;color:#fff;font-weight:700}
        .sidebar-nav .collapse .nav-link:hover{background-color:#334155;color:#f8fafc}
        .sidebar-nav .nav-link[data-bs-toggle=collapse]::after{content:'\f078';font-family:'Font Awesome 6 Free';font-weight:900;display:inline-block;margin-left:auto;transition:transform .2s ease-in-out;font-size:.7em}
        .sidebar-nav .nav-link[data-bs-toggle=collapse][aria-expanded=true]::after{transform:rotate(-180deg)}
        hr.sidebar-divider{margin:1rem 15px;border-top:1px solid #334155}
        .main-wrapper{display:flex;flex-direction:column;flex-grow:1;margin-left:250px;transition:margin-left .3s ease;background-color:#f8f9fa}
        .top-navbar{background-color:#fff;padding:0 20px;border-bottom:1px solid #e5e7eb;box-shadow:0 1px 3px rgba(0,0,0,.03);min-height:60px;display:flex;align-items:center}
        .top-navbar .page-main-title{font-size:1.25rem;color:#111827;font-weight:600;margin:0;margin-right:20px}
        .top-navbar .top-nav-tabs .nav-link{color:#6b7280;font-weight:500;padding:.6rem .9rem;font-size:.9rem;border-bottom:3px solid transparent;border-radius:0}
        .top-navbar .top-nav-tabs .nav-link.active{color:#ef4444;border-bottom-color:#ef4444;background-color:transparent}
        .top-navbar .top-nav-tabs .nav-link:hover{color:#111827}
        .top-navbar .search-form .form-control{border-radius:6px 0 0 6px;font-size:.85rem;border-color:#d1d5db}
        .top-navbar .search-form .btn{border-radius:0 6px 6px 0;border-color:#d1d5db;color:#6b7280}
        .top-navbar .search-form .form-control:focus{border-color:#ef4444;box-shadow:0 0 0 .2rem rgba(239,68,68,.25)}
        .top-navbar .btn-new{background-color:#ef4444;border-color:#ef4444;color:#fff;font-weight:500;font-size:.85rem;padding:.3rem .8rem}
        .top-navbar .btn-new:hover{background-color:#dc2626;border-color:#dc2626}
        .top-navbar .notification-badge{position:absolute;top:-1px;right:-5px;font-size:.6em}
        .top-navbar .nav-link i.fa-bell{color:#6b7280}
        .top-navbar .nav-link:hover i.fa-bell{color:#111827}
        .content-area{padding:25px;background-color:#f0f2f5;flex-grow:1}
        .stats-card-row{margin-bottom:25px}
        .stat-card{background-color:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:20px;display:flex;align-items:center;box-shadow:0 1px 3px rgba(0,0,0,.03)}
        .stat-card-icon{font-size:2rem;padding:15px;border-radius:50%;margin-right:20px;display:flex;align-items:center;justify-content:center;width:60px;height:60px}
        .stat-card-icon.icon-users{background-color:rgba(59,130,246,.1);color:#3b82f6} /* Bleu pour utilisateurs */
        .stat-card-icon.icon-admins{background-color:rgba(239,68,68,.1);color:#ef4444} /* Rouge pour admins */
        .stat-card-icon.icon-etudiants{background-color:rgba(34,197,94,.1);color:#22c55e} /* Vert pour étudiants */
        .stat-card-icon.icon-enseignants{background-color:rgba(249,115,22,.1);color:#f97316} /* Orange pour enseignants */
        .stat-card-info h6{margin-bottom:2px;font-size:.8rem;color:#6b7280;text-transform:uppercase;font-weight:500}
        .stat-card-info .stat-number{font-size:1.75rem;font-weight:600;color:#111827}
        .card-custom{border:1px solid #e5e7eb;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.03);margin-bottom:25px;background-color:#fff}
        .card-custom .card-header{background-color:#f9fafb;color:#1f2937;border-bottom:1px solid #e5e7eb;border-top-left-radius:8px;border-top-right-radius:8px;font-weight:600;padding:.9rem 1.25rem;font-size:1rem}
        .card-custom .card-header i{margin-right:8px;color:#6b7280}
        .card-custom .card-body{padding:1.5rem}
        .table th{background-color:#f3f4f6;color:#374151;font-weight:600;font-size:.85rem;border-bottom-width:1px}
        .table td{vertical-align:middle;font-size:.875rem;color:#4b5563}
        .table-hover tbody tr:hover{background-color:#f9fafb}
        .table .user-table-photo { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; margin-right: 10px; }
        .btn-custom-primary{background-color:#ef4444;border-color:#ef4444;color:#fff}
        .btn-custom-primary:hover{background-color:#dc2626;border-color:#dc2626}
        .btn-custom-edit{background-color:#f97316;border-color:#f97316;color:#fff}
        .btn-custom-edit:hover{background-color:#ea580c;border-color:#ea580c}
        .alert-custom-success{background-color:#d1fae5;color:#065f46;border-color:#a7f3d0}
        .alert-custom-danger{background-color:#fee2e2;color:#991b1b;border-color:#fecaca}
        .alert-custom-warning{background-color:#ffedd5;color:#9a3412;border-color:#fed7aa}
        .alert-custom-info{background-color:#dbeafe;color:#1e40af;border-color:#bfdbfe}
        .form-label{font-weight:500;color:#374151;margin-bottom:.3rem;font-size:.875rem}
        .form-control,.form-select{font-size:.875rem;border-radius:6px;border-color:#d1d5db}
        .form-control:focus,.form-select:focus{border-color:#ef4444;box-shadow:0 0 0 .2rem rgba(239,68,68,.25)}
        .form-text { font-size: 0.75rem; }
        .page-footer{padding:15px 25px;background-color:#fff;border-top:1px solid #e5e7eb;font-size:.8rem;color:#6b7280;text-align:center}
        @media (max-width:767.98px){.sidebar{width:0;overflow:hidden}.main-wrapper{margin-left:0}.sidebar.active{width:250px}.top-navbar .page-main-title{font-size:1.1rem}.top-navbar .top-nav-tabs{display:none}.top-navbar .search-form{display:none}}
    </style>
</head>
<body>

    <aside class="sidebar">
        <div class="sidebar-header">
            <img src="<?php echo htmlspecialchars($userImageUrl); ?>" alt="Avatar Utilisateur" class="user-avatar">
            <h5><?php echo isset($_SESSION['user_nom_complet']) ? htmlspecialchars($_SESSION['user_nom_complet']) : 'Yacoub Saad'; ?></h5>
        </div>
        <nav class="nav sidebar-nav flex-column">
            <a class="nav-link" href="<?php echo BASE_URL; ?>dashboard.php"> <i class="fas fa-home fa-fw"></i> Accueil </a>
            <a class="nav-link active" href="<?php echo BASE_URL; ?>dashboard/admin/gestion_utilisateurs.php"> <i class="fas fa-users-cog fa-fw"></i> Utilisateurs </a>
            <a class="nav-link <?php echo ($currentPage == 'gestion_filieres.php' || $currentPage == 'gestion_classes.php') ? '' : 'collapsed'; ?>"
               href="#submenuGestionAcademique" data-bs-toggle="collapse" role="button"
               aria-expanded="<?php echo ($currentPage == 'gestion_filieres.php' || $currentPage == 'gestion_classes.php') ? 'true' : 'false'; ?>" >
                <i class="fas fa-graduation-cap fa-fw"></i> Gestion Académique
            </a>
            <div class="collapse <?php echo ($currentPage == 'gestion_filieres.php' || $currentPage == 'gestion_classes.php') ? 'show' : ''; ?>" id="submenuGestionAcademique">
                <ul class="nav flex-column ps-3">
                    <li class="nav-item"><a class="nav-link <?php echo ($currentPage == 'gestion_filieres.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>dashboard/admin/gestion_filieres.php"> <i class="fas fa-sitemap fa-fw"></i> Filières </a></li>
                    <li class="nav-item"><a class="nav-link <?php echo ($currentPage == 'gestion_classes.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>dashboard/admin/gestion_classes.php"> <i class="fas fa-chalkboard-teacher fa-fw"></i> Classes </a></li>
                </ul>
            </div>
            <hr class="sidebar-divider">
            <a class="nav-link" href="#"> <i class="fas fa-sign-out-alt fa-fw"></i> Déconnexion </a>
        </nav>
    </aside>

    <div class="main-wrapper">
        <nav class="top-navbar navbar navbar-expand-lg">
            <div class="container-fluid">
                <button class="btn d-md-none me-2" type="button" id="sidebarToggle"><i class="fas fa-bars"></i></button>
                <h1 class="page-main-title"><?php echo htmlspecialchars($pageTitle); ?></h1>
                <ul class="nav nav-pills top-nav-tabs me-auto">
                    <li class="nav-item"><a href="<?php echo BASE_URL; ?>dashboard/admin/gestion_utilisateurs.php" class="nav-link active" aria-current="page">Liste</a></li>
                </ul>
                <div class="collapse navbar-collapse" id="topNavContent">
                    <form class="d-flex ms-auto me-3 search-form" role="search">
                        <div class="input-group input-group-sm">
                            <input class="form-control" type="search" placeholder="Rechercher utilisateur..." aria-label="Search">
                            <button class="btn btn-outline-secondary" type="submit"><i class="fas fa-search"></i></button>
                        </div>
                    </form>
                    <ul class="navbar-nav align-items-center">
                        <li class="nav-item"><button class="btn btn-sm btn-new"><i class="fas fa-plus"></i> New</button></li>
                        <li class="nav-item ms-2"><a class="nav-link" href="#" title="Notifications"><i class="fas fa-bell fs-5"></i><span class="badge rounded-pill bg-danger notification-badge">!</span></a></li>
                    </ul>
                </div>
            </div>
        </nav>

        <main class="content-area">
            <div class="row stats-card-row">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="stat-card"><div class="stat-card-icon icon-users"><i class="fas fa-users"></i></div><div class="stat-card-info"><h6>Total Utilisateurs</h6><span class="stat-number"><?php echo $count_total_users; ?></span></div></div>
                </div>
                <?php
                    $count_admins = 0; $count_etudiants = 0; $count_enseignants = 0;
                    foreach ($users_list as $user) {
                        if ($user['role'] == 'admin') $count_admins++;
                        if ($user['role'] == 'etudiant') $count_etudiants++;
                        if ($user['role'] == 'enseignant') $count_enseignants++;
                    }
                ?>
                <div class="col-xl-3 col-md-6 mb-4"><div class="stat-card"><div class="stat-card-icon icon-admins"><i class="fas fa-user-shield"></i></div><div class="stat-card-info"><h6>Admins</h6><span class="stat-number"><?php echo $count_admins; ?></span></div></div></div>
                <div class="col-xl-3 col-md-6 mb-4"><div class="stat-card"><div class="stat-card-icon icon-etudiants"><i class="fas fa-user-graduate"></i></div><div class="stat-card-info"><h6>Étudiants</h6><span class="stat-number"><?php echo $count_etudiants; ?></span></div></div></div>
                <div class="col-xl-3 col-md-6 mb-4"><div class="stat-card"><div class="stat-card-icon icon-enseignants"><i class="fas fa-chalkboard-user"></i></div><div class="stat-card-info"><h6>Enseignants</h6><span class="stat-number"><?php echo $count_enseignants; ?></span></div></div></div>
            </div>

            <?php if (!empty($message)): ?>
            <div class="alert <?php echo 'alert-custom-' . htmlspecialchars($message_type); ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($message); ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>

            <div class="card card-custom mb-4">
                <div class="card-header"><i class="fas <?php echo $edit_user_data ? 'fa-user-edit' : 'fa-user-plus'; ?>"></i> <?php echo $edit_user_data ? 'Modifier l\'Utilisateur' : 'Ajouter un Nouvel Utilisateur'; ?></div>
                <div class="card-body">
                    <form action="gestion_utilisateurs.php" method="POST" enctype="multipart/form-data">
                        <?php if ($edit_user_data): ?><input type="hidden" name="id_utilisateur" value="<?php echo htmlspecialchars($edit_user_data['id']); ?>"><input type="hidden" name="current_photo" value="<?php echo htmlspecialchars($edit_user_data['photo'] ?? ''); ?>"><?php endif; ?>
                        <div class="row">
                            <div class="col-md-4 mb-3"><label for="nom" class="form-label">Nom</label><input type="text" class="form-control" id="nom" name="nom" value="<?php echo $edit_user_data ? htmlspecialchars($edit_user_data['nom']) : ''; ?>" required></div>
                            <div class="col-md-4 mb-3"><label for="prenom" class="form-label">Prénom</label><input type="text" class="form-control" id="prenom" name="prenom" value="<?php echo $edit_user_data ? htmlspecialchars($edit_user_data['prenom']) : ''; ?>" required></div>
                            <div class="col-md-4 mb-3"><label for="email" class="form-label">Email</label><input type="email" class="form-control" id="email" name="email" value="<?php echo $edit_user_data ? htmlspecialchars($edit_user_data['email']) : ''; ?>" required></div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="<?php echo $edit_user_data ? 'mot_de_passe_nouveau' : 'mot_de_passe'; ?>" class="form-label">
                                    <?php echo $edit_user_data ? 'Nouveau Mot de Passe' : 'Mot de Passe'; ?>
                                </label>
                                <input type="password" class="form-control" id="<?php echo $edit_user_data ? 'mot_de_passe_nouveau' : 'mot_de_passe'; ?>" name="<?php echo $edit_user_data ? 'mot_de_passe_nouveau' : 'mot_de_passe'; ?>" <?php if (!$edit_user_data) echo 'required'; ?>>
                                <?php if ($edit_user_data): ?><div class="form-text">Laissez vide pour ne pas changer. Min 6 caractères si modifié.</div><?php else: ?><div class="form-text">Min 6 caractères.</div><?php endif; ?>
                            </div>
                            <div class="col-md-4 mb-3"><label for="role" class="form-label">Rôle</label><select class="form-select" id="role" name="role" required>
                                <option value="">-- Choisir un rôle --</option>
                                <?php foreach ($roles_disponibles as $r): ?>
                                <option value="<?php echo $r; ?>" <?php echo ($edit_user_data && $edit_user_data['role'] == $r) ? 'selected' : ''; ?>><?php echo ucfirst($r); ?></option>
                                <?php endforeach; ?>
                            </select></div>
                            <div class="col-md-4 mb-3">
                                <label for="<?php echo $edit_user_data ? 'photo_update' : 'photo'; ?>" class="form-label">Photo de Profil <?php if ($edit_user_data) echo '(Optionnel)'; ?></label>
                                <input type="file" class="form-control" id="<?php echo $edit_user_data ? 'photo_update' : 'photo'; ?>" name="<?php echo $edit_user_data ? 'photo_update' : 'photo'; ?>" accept="image/jpeg,image/png,image/gif">
                                <?php if ($edit_user_data && !empty($edit_user_data['photo'])): ?>
                                    <div class="mt-2">Photo actuelle: <img src="<?php echo UPLOAD_URL_DIR . htmlspecialchars($edit_user_data['photo']); ?>" alt="Photo actuelle" style="width: 50px; height: 50px; border-radius: 50%; object-fit: cover;"></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="d-flex justify-content-end mt-3">
                            <?php if ($edit_user_data): ?>
                                <button type="submit" name="update_utilisateur" class="btn btn-custom-edit me-2"><i class="fas fa-save"></i> Mettre à Jour</button>
                                <a href="gestion_utilisateurs.php" class="btn btn-secondary"><i class="fas fa-times"></i> Annuler</a>
                            <?php else: ?>
                                <button type="submit" name="add_utilisateur" class="btn btn-custom-primary"><i class="fas fa-plus"></i> Ajouter Utilisateur</button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card card-custom">
                <div class="card-header"><i class="fas fa-list-ul"></i> Liste des Utilisateurs</div>
                <div class="card-body">
                    <?php if (empty($users_list)): ?>
                        <div class="alert alert-custom-info" role="alert">Aucun utilisateur trouvé.</div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead><tr><th>Photo</th><th>Nom Complet</th><th>Email</th><th>Rôle</th><th>Inscrit le</th><th class="text-center">Actions</th></tr></thead>
                            <tbody>
                                <?php foreach ($users_list as $user): ?>
                                <tr>
                                    <td>
                                        <?php if (!empty($user['photo'])): ?>
                                            <img src="<?php echo UPLOAD_URL_DIR . htmlspecialchars($user['photo']); ?>" alt="Photo de <?php echo htmlspecialchars($user['prenom']); ?>" class="user-table-photo">
                                        <?php else: ?>
                                            <i class="fas fa-user-circle fa-2x text-secondary" title="Pas de photo"></i>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['prenom'] . ' ' . $user['nom']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><span class="badge bg-<?php echo $user['role'] == 'admin' ? 'danger' : ($user['role'] == 'etudiant' ? 'success' : 'info'); ?>"><?php echo ucfirst(htmlspecialchars($user['role'])); ?></span></td>
                                    <td><?php echo date('d/m/Y', strtotime($user['date_inscription'])); ?></td>
                                    <td class="text-center">
                                        <a href="gestion_utilisateurs.php?edit_id=<?php echo htmlspecialchars($user['id']); ?>" class="btn btn-sm btn-custom-edit me-1" title="Modifier"><i class="fas fa-edit"></i></a>
                                        <a href="gestion_utilisateurs.php?delete_id=<?php echo htmlspecialchars($user['id']); ?>" class="btn btn-sm btn-danger" title="Supprimer" onclick="return confirm('Supprimer cet utilisateur ? Cette action est irréversible.');"><i class="fas fa-trash-alt"></i></a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="mt-2 text-muted"><small>Total utilisateurs : <?php echo $count_total_users; ?></small></p>
                    <?php endif; ?>
                </div>
            </div>
        </main>
        <footer class="page-footer">© <?php echo date("Y"); ?> Gestion École. Tous droits réservés.</footer>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebar = document.querySelector('.sidebar');
        if (sidebarToggle && sidebar) {
            sidebarToggle.addEventListener('click', () => { sidebar.classList.toggle('active'); });
        }
    </script>
</body>
</html>