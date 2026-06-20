<?php
session_start();

// Configuration des erreurs
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// --- Définition de BASE_URL ---
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
$base_path_script_dir = dirname(dirname(dirname($_SERVER['SCRIPT_NAME'])));
define('BASE_URL', rtrim($protocol . $host . $base_path_script_dir, '/') . '/');

// --- Classe Database (intégrée) ---
class Database {
    private $host = 'localhost'; private $db_name = 'gestion_school'; private $username = 'root'; private $password = ''; private $conn;
    public function connect() { $this->conn = null; try { $this->conn = new PDO('mysql:host=' . $this->host . ';dbname=' . $this->db_name, $this->username, $this->password); $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); $this->conn->exec('SET NAMES utf8'); } catch(PDOException $e) { error_log('Erreur connexion: ' . $e->getMessage()); die('Erreur connexion DB. Message: ' . $e->getMessage()); } return $this->conn; }
    public function prepare($sql) { if (!$this->conn) $this->connect(); return $this->conn->prepare($sql); }
    public function execute($stmt, $params = []) { try { return $stmt->execute($params); } catch(PDOException $e) { error_log('Erreur SQL: ' . $e->getMessage() . ' | Query: ' . $stmt->queryString); return false; } }
    public function fetchAll($stmt) { return $stmt->fetchAll(PDO::FETCH_ASSOC); }
    public function fetch($stmt) { return $stmt->fetch(PDO::FETCH_ASSOC); }
    public function lastInsertId() { return $this->conn->lastInsertId(); }
    public function rowCount($stmt) { return $stmt->rowCount(); }
}

// --- Fonctions Utilitaires (intégrées) ---
function sanitizeInput($data, $allow_html = false) {
    if (is_array($data)) {
        $sanitized_array = [];
        foreach ($data as $key => $value) {
            $sanitized_array[$key] = sanitizeInput($value, $allow_html);
        }
        return $sanitized_array;
    }
    if ($data === null) return null;
    if (!is_string($data) && !is_numeric($data) && !is_bool($data)) $data = (string) $data;
    
    if (is_string($data)) {
        $data = trim($data);
        if (!$allow_html) {
            $data = stripslashes($data); // Important si magic_quotes_gpc est activé (bien que déprécié)
            $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
        } else {
            // Pour du HTML autorisé, vous utiliseriez une bibliothèque de purification HTML comme HTMLPurifier
            // Pour cet exemple, nous allons faire une sanétisation basique, mais ce n'est PAS SÉCURISÉ pour du HTML arbitraire.
            // $data = filter_var($data, FILTER_SANITIZE_SPECIAL_CHARS); // Moins strict, mais NE PAS UTILISER pour HTML venant de l'utilisateur sans purifier
        }
    }
    return $data;
}
function getCurrentPage() { return basename($_SERVER['SCRIPT_FILENAME']); }

// --- Connexion DB ---
$database = new Database();
$db = $database->connect();

// --- Logique CRUD pour les Notifications ---
$pageTitle = "Gestion des Notifications";
$message = '';
$message_type = '';
// ID de l'utilisateur admin connecté (à remplacer par votre logique d'authentification)
// Pour l'exemple, on suppose un id_auteur fixe ou null si non géré.
$id_auteur_admin = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null; 


// AJOUTER une notification
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_notification'])) {
    $titre = sanitizeInput($_POST['titre']);
    // Pour le contenu, si vous voulez autoriser un peu de HTML, soyez prudent.
    // Une vraie application utiliserait un purificateur HTML.
    // Ici, nous allons le traiter comme du texte simple pour la sécurité par défaut.
    $contenu = sanitizeInput($_POST['contenu']); 
    $visible = isset($_POST['visible']) ? 1 : 0;

    if (empty($titre) || empty($contenu)) {
        $_SESSION['message'] = "Le titre et le contenu sont requis.";
        $_SESSION['message_type'] = "danger";
    } else {
        try {
            $stmt = $database->prepare("INSERT INTO notifications (titre, contenu, visible, id_auteur) 
                                       VALUES (:titre, :contenu, :visible, :id_auteur)");
            $params = [
                ':titre' => $titre, 
                ':contenu' => $contenu, // Contenu sanétisé comme texte simple
                ':visible' => $visible,
                ':id_auteur' => $id_auteur_admin // Peut être NULL si non défini
            ];
            
            if ($database->execute($stmt, $params)) {
                $_SESSION['message'] = "Notification ajoutée avec succès!";
                $_SESSION['message_type'] = "success";
            } else {
                $_SESSION['message'] = "Erreur lors de l'ajout de la notification.";
                $_SESSION['message_type'] = "danger";
                error_log("Erreur SQL (add notification): " . ($stmt->errorInfo()[2] ?? "Erreur inconnue"));
            }
        } catch (PDOException $e) {
            $_SESSION['message'] = "Erreur PDO ajout notification: " . $e->getMessage(); 
            $_SESSION['message_type'] = "danger";
            error_log("Erreur PDO (add notification): " . $e->getMessage());
        }
    }
    header("Location: notifications.php"); 
    exit();
}

// PRÉPARER L'ÉDITION
$edit_notification_data = null;
if (isset($_GET['edit_id'])) {
    $edit_id = filter_input(INPUT_GET, 'edit_id', FILTER_VALIDATE_INT);
    if ($edit_id) {
        try {
            $stmt = $database->prepare("SELECT * FROM notifications WHERE id = :id");
            $database->execute($stmt, [':id' => $edit_id]);
            $edit_notification_data = $database->fetch($stmt);
            
            if (!$edit_notification_data) {
                $_SESSION['message'] = "Notification non trouvée."; 
                $_SESSION['message_type'] = "warning";
                header("Location: notifications.php"); 
                exit();
            }
        } catch (PDOException $e) {
            $_SESSION['message'] = "Erreur récupération notification: " . $e->getMessage(); 
            $_SESSION['message_type'] = "danger";
        }
    }
}

// METTRE À JOUR
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_notification'])) {
    $id = filter_input(INPUT_POST, 'id_notification', FILTER_VALIDATE_INT);
    $titre = sanitizeInput($_POST['titre']);
    $contenu = sanitizeInput($_POST['contenu']); // Traité comme texte simple
    $visible = isset($_POST['visible']) ? 1 : 0;

    if (!$id || empty($titre) || empty($contenu)) {
        $_SESSION['message'] = "Titre et contenu sont requis pour la MAJ."; 
        $_SESSION['message_type'] = "danger";
    } else {
        try {
            $stmt = $database->prepare("UPDATE notifications SET 
                                      titre = :titre, 
                                      contenu = :contenu, 
                                      visible = :visible
                                      WHERE id = :id");
                                      
            $params = [
                ':titre' => $titre, 
                ':contenu' => $contenu,
                ':visible' => $visible,
                ':id' => $id
            ];
            // Note: on ne met pas à jour id_auteur ou date_creation ici
            
            if ($database->execute($stmt, $params)) {
                $_SESSION['message'] = "Notification mise à jour!"; 
                $_SESSION['message_type'] = "success";
            } else {
                $_SESSION['message'] = "Erreur lors de la MAJ de la notification."; 
                $_SESSION['message_type'] = "danger";
                 error_log("Erreur SQL (update notification): " . ($stmt->errorInfo()[2] ?? "Erreur inconnue"));
            }
        } catch (PDOException $e) {
            $_SESSION['message'] = "Erreur PDO MAJ notification: " . $e->getMessage(); 
            $_SESSION['message_type'] = "danger";
             error_log("Erreur PDO (update notification): " . $e->getMessage());
        }
    }
    header("Location: notifications.php"); 
    exit();
}

// SUPPRIMER
if (isset($_GET['delete_id'])) {
    $delete_id = filter_input(INPUT_GET, 'delete_id', FILTER_VALIDATE_INT);
    if ($delete_id) {
        try {
            $stmt_delete = $database->prepare("DELETE FROM notifications WHERE id = :id");
            if ($database->execute($stmt_delete, [':id' => $delete_id])) {
                $_SESSION['message'] = "Notification supprimée!"; 
                $_SESSION['message_type'] = "success";
            } else {
                $_SESSION['message'] = "Erreur lors de la suppression de la notification."; 
                $_SESSION['message_type'] = "danger";
                error_log("Erreur SQL (delete notification): " . ($stmt_delete->errorInfo()[2] ?? "Erreur inconnue"));
            }
        } catch (PDOException $e) {
            $_SESSION['message'] = "Erreur PDO suppression: " . $e->getMessage(); 
            $_SESSION['message_type'] = "danger";
             error_log("Erreur PDO (delete notification): " . $e->getMessage());
        }
    }
    header("Location: notifications.php"); 
    exit();
}

// LISTER les notifications
$notifications_list = [];
$count_total_notifications = 0;
try {
    $sql_list = "SELECT n.id, n.titre, LEFT(n.contenu, 100) as contenu_extrait, n.date_creation, n.visible, u.nom as auteur_nom, u.prenom as auteur_prenom
                 FROM notifications n
                 LEFT JOIN utilisateurs u ON n.id_auteur = u.id
                 ORDER BY n.date_creation DESC";
                 
    $stmt_list = $database->prepare($sql_list);
    if ($database->execute($stmt_list)) {
        $notifications_list = $database->fetchAll($stmt_list);
        $count_total_notifications = count($notifications_list);
    } else {
        $message = "Erreur récupération liste notifications."; 
        $message_type = "danger";
        error_log("Erreur SQL (liste notifications): " . ($stmt_list->errorInfo()[2] ?? "Erreur inconnue"));
    }
} catch (PDOException $e) {
    $message = "Erreur DB (liste notifications): " . $e->getMessage(); 
    $message_type = "danger";
    error_log("Erreur PDO (liste notifications): " . $e->getMessage());
}

// Afficher les messages de session
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message']; 
    $message_type = $_SESSION['message_type'];
    unset($_SESSION['message']); 
    unset($_SESSION['message_type']);
}

$currentPage = getCurrentPage();
$userImageUrl = BASE_URL . 'assets/images/default_avatar.png';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - Gestion École</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* CSS Intégré (identique pour la cohérence) */
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
        .stat-card-icon.icon-notifications{background-color:rgba(16,185,129,.1);color:#10b981} /* Vert émeraude pour notifications */
        .stat-card-info h6{margin-bottom:2px;font-size:.8rem;color:#6b7280;text-transform:uppercase;font-weight:500}
        .stat-card-info .stat-number{font-size:1.75rem;font-weight:600;color:#111827}
        .card-custom{border:1px solid #e5e7eb;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.03);margin-bottom:25px;background-color:#fff}
        .card-custom .card-header{background-color:#f9fafb;color:#1f2937;border-bottom:1px solid #e5e7eb;border-top-left-radius:8px;border-top-right-radius:8px;font-weight:600;padding:.9rem 1.25rem;font-size:1rem}
        .card-custom .card-header i{margin-right:8px;color:#6b7280}
        .card-custom .card-body{padding:1.5rem}
        .table th{background-color:#f3f4f6;color:#374151;font-weight:600;font-size:.85rem;border-bottom-width:1px}
        .table td{vertical-align:middle;font-size:.875rem;color:#4b5563}
        .table-hover tbody tr:hover{background-color:#f9fafb}
        .btn-custom-primary{background-color:#ef4444;border-color:#ef4444;color:#fff}
        .btn-custom-primary:hover{background-color:#dc2626;border-color:#dc2626}
        .btn-custom-edit{background-color:#f97316;border-color:#f97316;color:#fff}
        .btn-custom-edit:hover{background-color:#ea580c;border-color:#ea580c}
        .alert-custom-success{background-color:#d1fae5;color:#065f46;border-color:#a7f3d0}
        .alert-custom-danger{background-color:#fee2e2;color:#991b1b;border-color:#fecaca}
        .alert-custom-warning{background-color:#ffedd5;color:#9a3412;border-color:#fed7aa}
        .alert-custom-info{background-color:#dbeafe;color:#1e40af;border-color:#bfdbfe}
        .form-label{font-weight:500;color:#374151;margin-bottom:.3rem;font-size:.875rem}
        .form-control,.form-select,.form-check-input{font-size:.875rem;border-radius:6px;}
        .form-control, .form-select { border-color:#d1d5db; }
        .form-control:focus,.form-select:focus{border-color:#ef4444;box-shadow:0 0 0 .2rem rgba(239,68,68,.25)}
        .page-footer{padding:15px 25px;background-color:#fff;border-top:1px solid #e5e7eb;font-size:.8rem;color:#6b7280;text-align:center}
        @media (max-width:767.98px){.sidebar{width:0;overflow:hidden}.main-wrapper{margin-left:0}.sidebar.active{width:250px}.top-navbar .page-main-title{font-size:1.1rem}.top-navbar .top-nav-tabs{display:none}.top-navbar .search-form{display:none}}
    </style>
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-header">
            <img src="<?php echo htmlspecialchars($userImageUrl); ?>" alt="Avatar" class="user-avatar">
            <h5><?php echo isset($_SESSION['user_nom_complet']) ? htmlspecialchars($_SESSION['user_nom_complet']) : 'Yacoub Saad'; ?></h5>
        </div>
        <nav class="nav sidebar-nav flex-column">
            <a class="nav-link" href="<?php echo BASE_URL; ?>dashboard.php"><i class="fas fa-home fa-fw"></i> Accueil</a>
            <a class="nav-link" href="<?php echo BASE_URL; ?>dashboard/admin/gestion_utilisateurs.php"><i class="fas fa-users-cog fa-fw"></i> Utilisateurs</a>
            <a class="nav-link <?php echo in_array($currentPage, ['gestion_filieres.php', 'gestion_classes.php', 'gestion_matieres.php', 'gestion_absences.php', 'notifications.php']) ? '' : 'collapsed'; ?>"
               href="#submenuGestionAcademique" data-bs-toggle="collapse" role="button"
               aria-expanded="<?php echo in_array($currentPage, ['gestion_filieres.php', 'gestion_classes.php', 'gestion_matieres.php', 'gestion_absences.php', 'notifications.php']) ? 'true' : 'false'; ?>" >
                <i class="fas fa-graduation-cap fa-fw"></i> Gestion Académique
            </a>
            <div class="collapse <?php echo in_array($currentPage, ['gestion_filieres.php', 'gestion_classes.php', 'gestion_matieres.php', 'gestion_absences.php', 'notifications.php']) ? 'show' : ''; ?>" id="submenuGestionAcademique">
                <ul class="nav flex-column ps-3">
                    <li class="nav-item"><a class="nav-link <?php echo ($currentPage == 'gestion_filieres.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>dashboard/admin/gestion_filieres.php"><i class="fas fa-sitemap fa-fw"></i> Filières</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo ($currentPage == 'gestion_classes.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>dashboard/admin/gestion_classes.php"><i class="fas fa-chalkboard-teacher fa-fw"></i> Classes</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo ($currentPage == 'gestion_matieres.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>dashboard/admin/gestion_matieres.php"><i class="fas fa-book fa-fw"></i> Matières</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo ($currentPage == 'gestion_absences.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>dashboard/admin/gestion_absences.php"><i class="fas fa-user-clock fa-fw"></i> Absences</a></li>
                </ul>
            </div>
             <a class="nav-link <?php echo ($currentPage == 'notifications.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>dashboard/admin/notifications.php">
                <i class="fas fa-bell fa-fw"></i> Notifications
            </a>
            <hr class="sidebar-divider">
            <a class="nav-link" href="#"><i class="fas fa-sign-out-alt fa-fw"></i> Déconnexion</a>
        </nav>
    </aside>

    <div class="main-wrapper">
        <nav class="top-navbar navbar navbar-expand-lg">
            <div class="container-fluid">
                <button class="btn d-md-none me-2" id="sidebarToggle"><i class="fas fa-bars"></i></button>
                <h1 class="page-main-title"><?php echo htmlspecialchars($pageTitle); ?></h1>
                <ul class="nav nav-pills top-nav-tabs me-auto">
                    <li class="nav-item"><a href="<?php echo BASE_URL; ?>dashboard/admin/notifications.php" class="nav-link active" aria-current="page">Liste</a></li>
                </ul>
                <div class="collapse navbar-collapse" id="topNavContent">
                    <form class="d-flex ms-auto me-3 search-form" role="search">
                        <div class="input-group input-group-sm">
                            <input class="form-control" type="search" placeholder="Rechercher notification..." aria-label="Search">
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
                <div class="col-xl-6 col-md-6 mb-4">
                    <div class="stat-card"><div class="stat-card-icon icon-notifications"><i class="fas fa-bullhorn"></i></div><div class="stat-card-info"><h6>Total Notifications</h6><span class="stat-number"><?php echo $count_total_notifications; ?></span></div></div>
                </div>
                <?php
                    $count_visibles = 0;
                    if(is_array($notifications_list) || is_object($notifications_list)){
                        foreach ($notifications_list as $notif) { if ($notif['visible']) $count_visibles++; }
                    }
                ?>
                <div class="col-xl-6 col-md-6 mb-4"><div class="stat-card"><div class="stat-card-icon" style="background-color: rgba(34,197,94,.1); color: #22c55e;"><i class="fas fa-eye"></i></div><div class="stat-card-info"><h6>Notifications Visibles</h6><span class="stat-number"><?php echo $count_visibles; ?></span></div></div></div>
            </div>

            <?php if (!empty($message)): ?>
            <div class="alert <?php echo 'alert-custom-' . htmlspecialchars($message_type); ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($message); ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>

            <div class="card card-custom mb-4">
                <div class="card-header"><i class="fas <?php echo $edit_notification_data ? 'fa-edit' : 'fa-plus-circle'; ?>"></i> <?php echo $edit_notification_data ? 'Modifier la Notification' : 'Ajouter une Nouvelle Notification'; ?></div>
                <div class="card-body">
                    <form action="notifications.php" method="POST">
                        <?php if ($edit_notification_data): ?><input type="hidden" name="id_notification" value="<?php echo htmlspecialchars($edit_notification_data['id']); ?>"><?php endif; ?>
                        <div class="mb-3">
                            <label for="titre" class="form-label">Titre de la Notification</label>
                            <input type="text" class="form-control" id="titre" name="titre" value="<?php echo $edit_notification_data ? htmlspecialchars($edit_notification_data['titre']) : ''; ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="contenu" class="form-label">Contenu</label>
                            <textarea class="form-control" id="contenu" name="contenu" rows="5" required><?php echo $edit_notification_data ? htmlspecialchars($edit_notification_data['contenu']) : ''; ?></textarea>
                            <div class="form-text">Le formatage HTML simple (comme <b>, <i>, <br>) peut être utilisé, mais sera affiché tel quel dans la liste. Pour un affichage HTML riche, un traitement supplémentaire est nécessaire.</div>
                        </div>
                        <div class="mb-3 form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="visible" name="visible" value="1" <?php echo ($edit_notification_data && $edit_notification_data['visible']) ? 'checked' : (!$edit_notification_data ? 'checked' : ''); // Visible par défaut à la création ?>>
                            <label class="form-check-label" for="visible">Visible pour les utilisateurs</label>
                        </div>
                        <div class="d-flex justify-content-end mt-3">
                            <?php if ($edit_notification_data): ?>
                                <button type="submit" name="update_notification" class="btn btn-custom-edit me-2"><i class="fas fa-save"></i> Mettre à Jour</button>
                                <a href="notifications.php" class="btn btn-secondary"><i class="fas fa-times"></i> Annuler</a>
                            <?php else: ?>
                                <button type="submit" name="add_notification" class="btn btn-custom-primary"><i class="fas fa-plus"></i> Ajouter Notification</button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card card-custom">
                <div class="card-header"><i class="fas fa-list-ul"></i> Liste des Notifications</div>
                <div class="card-body">
                    <?php if (empty($notifications_list) && empty($message) ): ?>
                        <div class="alert alert-custom-info" role="alert">Aucune notification trouvée.</div>
                    <?php elseif (!empty($notifications_list)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead><tr><th>ID</th><th>Titre</th><th>Extrait Contenu</th><th>Créée le</th><th>Auteur</th><th>Visible</th><th class="text-center">Actions</th></tr></thead>
                            <tbody>
                                <?php foreach ($notifications_list as $notification): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($notification['id']); ?></td>
                                    <td><?php echo htmlspecialchars($notification['titre']); ?></td>
                                    <td><?php echo htmlspecialchars($notification['contenu_extrait']); ?>...</td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($notification['date_creation'])); ?></td>
                                    <td><?php echo ($notification['auteur_prenom'] || $notification['auteur_nom']) ? htmlspecialchars($notification['auteur_prenom'] . ' ' . $notification['auteur_nom']) : 'Système'; ?></td>
                                    <td><span class="badge bg-<?php echo $notification['visible'] ? 'success' : 'secondary'; ?>"><?php echo $notification['visible'] ? 'Oui' : 'Non'; ?></span></td>
                                    <td class="text-center">
                                        <a href="notifications.php?edit_id=<?php echo htmlspecialchars($notification['id']); ?>" class="btn btn-sm btn-custom-edit me-1" title="Modifier"><i class="fas fa-edit"></i></a>
                                        <a href="notifications.php?delete_id=<?php echo htmlspecialchars($notification['id']); ?>" class="btn btn-sm btn-danger" title="Supprimer" onclick="return confirm('Supprimer cette notification ?');"><i class="fas fa-trash-alt"></i></a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="mt-2 text-muted"><small>Total notifications : <?php echo $count_total_notifications; ?></small></p>
                    <?php endif; ?>
                    <?php if (!empty($message) && $message_type == 'danger' && strpos($message, 'liste notifications') !== false): ?>
                        <div class="alert alert-custom-danger" role="alert">
                            <?php echo htmlspecialchars($message); ?> Veuillez vérifier les logs du serveur pour plus de détails.
                        </div>
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
        if (sidebarToggle && sidebar) { sidebarToggle.addEventListener('click', () => { sidebar.classList.toggle('active'); }); }
    </script>
</body>
</html>