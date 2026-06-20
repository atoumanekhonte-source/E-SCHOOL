<?php
session_start();

// Configuration des erreurs (utile pour le développement)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// --- Définition de BASE_URL ---
// Calcule BASE_URL pour pointer vers la racine du projet 'gestion_school'
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
// SCRIPT_NAME sera /gestion_school/dashboard/admin/gestion_filieres.php
// Nous voulons remonter de 3 niveaux pour atteindre /gestion_school/
$base_path_script_dir = dirname(dirname(dirname($_SERVER['SCRIPT_NAME'])));
define('BASE_URL', rtrim($protocol . $host . $base_path_script_dir, '/') . '/');

// --- Classe Database (intégrée) ---
class Database {
    private $host = 'localhost';
    private $db_name = 'gestion_school';
    private $username = 'root';
    private $password = ''; // Remplacez par votre mot de passe MySQL si vous en avez un
    private $conn;

    public function connect() {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                'mysql:host=' . $this->host . ';dbname=' . $this->db_name,
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->exec('SET NAMES utf8');
        } catch(PDOException $e) {
            error_log('Erreur de connexion: ' . $e->getMessage());
            die('Erreur de connexion à la base de données. Vérifiez les identifiants et que le serveur MySQL est actif. Message: ' . $e->getMessage());
        }
        return $this->conn;
    }

    public function prepare($sql) {
        if (!$this->conn) $this->connect();
        return $this->conn->prepare($sql);
    }

    public function execute($stmt, $params = []) {
        try {
            return $stmt->execute($params);
        } catch(PDOException $e) {
            error_log('Erreur d\'exécution: ' . $e->getMessage() . ' | Query: ' . $stmt->queryString);
            return false;
        }
    }

    public function fetchAll($stmt) {
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function fetch($stmt) {
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function lastInsertId() {
        return $this->conn->lastInsertId();
    }

    public function rowCount($stmt) {
        return $stmt->rowCount();
    }
}

// --- Fonction de Sanétisation (intégrée) ---
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    // Modification pour accepter les chaînes vides sans les transformer en ''
    if ($data === null) return null; // Garder les nulls tels quels si besoin explicite

    if (!is_string($data) && !is_numeric($data) && !is_bool($data)) {
        $data = (string) $data;
    }
    if (is_string($data)) {
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    }
    return $data;
}

// --- Fonction pour obtenir la page actuelle (pour les liens actifs du sidebar) ---
function getCurrentPage() {
    return basename($_SERVER['SCRIPT_FILENAME']);
}

// --- Connexion à la base de données ---
$database = new Database();
$db = $database->connect();

// --- Logique CRUD pour les Filières ---
$pageTitle = "Gestion des Filières";
$message = '';
$message_type = ''; // 'success' ou 'danger' ou 'warning'

// AJOUTER une filière
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_filiere'])) {
    $nom_filiere = sanitizeInput($_POST['nom_filiere']);

    if (!empty($nom_filiere)) {
        try {
            // Vérifier si la filière existe déjà
            $stmt_check = $database->prepare("SELECT id FROM filieres WHERE nom_filiere = :nom_filiere");
            $database->execute($stmt_check, [':nom_filiere' => $nom_filiere]);
            if ($database->rowCount($stmt_check) > 0) {
                $_SESSION['message'] = "Cette filière existe déjà.";
                $_SESSION['message_type'] = "warning";
            } else {
                $stmt = $database->prepare("INSERT INTO filieres (nom_filiere) VALUES (:nom_filiere)");
                $database->execute($stmt, [':nom_filiere' => $nom_filiere]);
                $_SESSION['message'] = "Filière ajoutée avec succès!";
                $_SESSION['message_type'] = "success";
            }
        } catch (PDOException $e) {
            $_SESSION['message'] = "Erreur lors de l'ajout de la filière: " . $e->getMessage();
            $_SESSION['message_type'] = "danger";
        }
    } else {
        $_SESSION['message'] = "Le nom de la filière est requis.";
        $_SESSION['message_type'] = "danger";
    }
    header("Location: gestion_filieres.php"); // Rediriger vers la même page
    exit();
}

// PRÉPARER L'ÉDITION d'une filière
$edit_filiere_data = null;
if (isset($_GET['edit_id'])) {
    $edit_id = filter_input(INPUT_GET, 'edit_id', FILTER_VALIDATE_INT);
    if ($edit_id) {
        try {
            $stmt = $database->prepare("SELECT * FROM filieres WHERE id = :id");
            $database->execute($stmt, [':id' => $edit_id]);
            $edit_filiere_data = $database->fetch($stmt);
            if (!$edit_filiere_data) {
                $_SESSION['message'] = "Filière non trouvée pour l'édition.";
                $_SESSION['message_type'] = "warning";
                header("Location: gestion_filieres.php");
                exit();
            }
        } catch (PDOException $e) {
            $_SESSION['message'] = "Erreur lors de la récupération de la filière: " . $e->getMessage();
            $_SESSION['message_type'] = "danger";
            // Ne pas rediriger ici pour potentiellement afficher l'erreur
        }
    }
}

// METTRE À JOUR une filière
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_filiere'])) {
    $id = filter_input(INPUT_POST, 'id_filiere', FILTER_VALIDATE_INT);
    $nom_filiere = sanitizeInput($_POST['nom_filiere']);

    if ($id && !empty($nom_filiere)) {
        try {
            // Vérifier si le nouveau nom existe déjà pour une autre filière
            $stmt_check = $database->prepare("SELECT id FROM filieres WHERE nom_filiere = :nom_filiere AND id != :id");
            $database->execute($stmt_check, [':nom_filiere' => $nom_filiere, ':id' => $id]);
            if ($database->rowCount($stmt_check) > 0) {
                 $_SESSION['message'] = "Une autre filière porte déjà ce nom.";
                 $_SESSION['message_type'] = "warning";
            } else {
                $stmt = $database->prepare("UPDATE filieres SET nom_filiere = :nom_filiere WHERE id = :id");
                $database->execute($stmt, [':nom_filiere' => $nom_filiere, ':id' => $id]);
                $_SESSION['message'] = "Filière mise à jour avec succès!";
                $_SESSION['message_type'] = "success";
            }
        } catch (PDOException $e) {
            $_SESSION['message'] = "Erreur lors de la mise à jour: " . $e->getMessage();
            $_SESSION['message_type'] = "danger";
        }
    } else {
        $_SESSION['message'] = "Le nom de la filière est requis pour la mise à jour.";
        $_SESSION['message_type'] = "danger";
    }
    header("Location: gestion_filieres.php");
    exit();
}

// SUPPRIMER une filière
if (isset($_GET['delete_id'])) {
    $delete_id = filter_input(INPUT_GET, 'delete_id', FILTER_VALIDATE_INT);
    if ($delete_id) {
        try {
            // Vérifier si la filière est utilisée par des classes
            $stmt_check_classes = $database->prepare("SELECT COUNT(*) as count FROM classes WHERE id_filiere = :id_filiere");
            $database->execute($stmt_check_classes, [':id_filiere' => $delete_id]);
            $result = $database->fetch($stmt_check_classes);

            if ($result && $result['count'] > 0) {
                $_SESSION['message'] = "Impossible de supprimer cette filière, elle est utilisée par " . $result['count'] . " classe(s).";
                $_SESSION['message_type'] = "warning";
            } else {
                $stmt = $database->prepare("DELETE FROM filieres WHERE id = :id");
                $database->execute($stmt, [':id' => $delete_id]);
                $_SESSION['message'] = "Filière supprimée avec succès!";
                $_SESSION['message_type'] = "success";
            }
        } catch (PDOException $e) {
            // Gérer d'autres erreurs potentielles (contraintes de clé étrangère si ajoutées plus tard)
            if ($e->getCode() == '23000') { // Code d'erreur pour violation de contrainte FK
                 $_SESSION['message'] = "Suppression impossible: cette filière est référencée ailleurs.";
            } else {
                 $_SESSION['message'] = "Erreur lors de la suppression: " . $e->getMessage();
            }
            $_SESSION['message_type'] = "danger";
        }
    }
    header("Location: gestion_filieres.php");
    exit();
}

// LISTER les filières
$filieres_list = [];
$count_total_filieres = 0;
try {
    $stmt_list = $database->prepare("SELECT * FROM filieres ORDER BY nom_filiere ASC");
    if ($database->execute($stmt_list)) {
        $filieres_list = $database->fetchAll($stmt_list);
        $count_total_filieres = count($filieres_list);
    } else {
        $message = "Erreur lors de la récupération de la liste des filières.";
        $message_type = "danger";
    }
} catch (PDOException $e) {
    $message = "Erreur de base de données: " . $e->getMessage();
    $message_type = "danger";
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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - Gestion École</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* CSS Intégré (copié et adapté de style.css précédent) */
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
        .stat-card-icon.icon-cr-progress{background-color:rgba(239,68,68,.1);color:#ef4444}
        .stat-card-icon.icon-tasks-pending{background-color:rgba(249,115,22,.1);color:#f97316}
        .stat-card-icon.icon-tasks-not-assigned{background-color:rgba(34,197,94,.1);color:#22c55e}
        .stat-card-icon.icon-tasks-completed{background-color:rgba(59,130,246,.1);color:#3b82f6}
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
        .btn-custom-success{background-color:#22c55e;border-color:#22c55e;color:#fff}
        .btn-custom-success:hover{background-color:#16a34a;border-color:#16a34a}
        .alert-custom-success{background-color:#d1fae5;color:#065f46;border-color:#a7f3d0}
        .alert-custom-danger{background-color:#fee2e2;color:#991b1b;border-color:#fecaca}
        .alert-custom-warning{background-color:#ffedd5;color:#9a3412;border-color:#fed7aa}
        .alert-custom-info{background-color:#dbeafe;color:#1e40af;border-color:#bfdbfe}
        .form-label{font-weight:500;color:#374151;margin-bottom:.3rem;font-size:.875rem}
        .form-control,.form-select{font-size:.875rem;border-radius:6px;border-color:#d1d5db}
        .form-control:focus,.form-select:focus{border-color:#ef4444;box-shadow:0 0 0 .2rem rgba(239,68,68,.25)}
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
            <a class="nav-link <?php echo ($currentPage == 'dashboard.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>dashboard.php"> <!-- Adaptez dashboard.php -->
                <i class="fas fa-home fa-fw"></i> Accueil
            </a>
            <a class="nav-link <?php echo ($currentPage == 'gestion_filieres.php' || $currentPage == 'gestion_classes.php') ? '' : 'collapsed'; ?>"
               href="#submenuGestionAcademique" data-bs-toggle="collapse" role="button"
               aria-expanded="<?php echo ($currentPage == 'gestion_filieres.php' || $currentPage == 'gestion_classes.php') ? 'true' : 'false'; ?>" aria-controls="submenuGestionAcademique">
                <i class="fas fa-graduation-cap fa-fw"></i> Gestion Académique
            </a>
            <div class="collapse <?php echo ($currentPage == 'gestion_filieres.php' || $currentPage == 'gestion_classes.php') ? 'show' : ''; ?>" id="submenuGestionAcademique">
                <ul class="nav flex-column ps-3">
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($currentPage == 'gestion_filieres.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>dashboard/admin/gestion_filieres.php">
                            <i class="fas fa-sitemap fa-fw"></i> Filières
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($currentPage == 'gestion_classes.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>dashboard/admin/gestion_classes.php"> <!-- Assurez-vous que ce chemin est correct -->
                            <i class="fas fa-chalkboard-teacher fa-fw"></i> Classes
                        </a>
                    </li>
                    <!-- Ajoutez d'autres liens académiques ici -->
                </ul>
            </div>
            <!-- Autres liens principaux -->
             <a class="nav-link" href="#"><i class="fas fa-users fa-fw"></i> Teams</a>
            <hr class="sidebar-divider">
            <a class="nav-link" href="#"><i class="fas fa-sign-out-alt fa-fw"></i> Déconnexion</a>
        </nav>
    </aside>

    <div class="main-wrapper">
        <nav class="top-navbar navbar navbar-expand-lg">
            <div class="container-fluid">
                <button class="btn d-md-none me-2" type="button" id="sidebarToggle"><i class="fas fa-bars"></i></button>
                <h1 class="page-main-title"><?php echo htmlspecialchars($pageTitle); ?></h1>
                <ul class="nav nav-pills top-nav-tabs me-auto">
                    <li class="nav-item"><a href="<?php echo BASE_URL; ?>dashboard/admin/gestion_filieres.php" class="nav-link active" aria-current="page">Liste</a></li>
                    <!-- Autres onglets si pertinent pour les filières -->
                </ul>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#topNavContent"><span class="navbar-toggler-icon"></span></button>
                <div class="collapse navbar-collapse" id="topNavContent">
                    <form class="d-flex ms-auto me-3 search-form" role="search">
                        <div class="input-group input-group-sm">
                            <input class="form-control" type="search" placeholder="Rechercher filière..." aria-label="Search">
                            <button class="btn btn-outline-secondary" type="submit"><i class="fas fa-search"></i></button>
                        </div>
                    </form>
                    <ul class="navbar-nav align-items-center">
                        <li class="nav-item"><button class="btn btn-sm btn-new"><i class="fas fa-plus"></i> New</button></li>
                        <li class="nav-item ms-2">
                            <a class="nav-link" href="#" role="button" title="Notifications">
                                <i class="fas fa-bell fs-5"></i><span class="badge rounded-pill bg-danger notification-badge">1</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>

        <main class="content-area">
            <!-- Cartes de statistiques -->
            <div class="row stats-card-row">
                <div class="col-xl-4 col-md-6 mb-4">
                    <div class="stat-card">
                        <div class="stat-card-icon icon-tasks-pending">
                            <i class="fas fa-sitemap"></i>
                        </div>
                        <div class="stat-card-info">
                            <h6>Total Filières</h6>
                            <span class="stat-number"><?php echo $count_total_filieres; ?></span>
                        </div>
                    </div>
                </div>
                <!-- Vous pouvez ajouter d'autres cartes de statistiques pertinentes ici -->
            </div>

            <!-- Affichage des messages -->
            <?php if (!empty($message)): ?>
            <div class="alert <?php echo 'alert-custom-' . htmlspecialchars($message_type); ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>

            <!-- Formulaire d'ajout/édition -->
            <div class="card card-custom mb-4">
                <div class="card-header">
                    <i class="fas <?php echo $edit_filiere_data ? 'fa-edit' : 'fa-plus-circle'; ?>"></i>
                    <?php echo $edit_filiere_data ? 'Modifier la Filière' : 'Ajouter une Nouvelle Filière'; ?>
                </div>
                <div class="card-body">
                    <form action="gestion_filieres.php" method="POST">
                        <?php if ($edit_filiere_data): ?>
                            <input type="hidden" name="id_filiere" value="<?php echo htmlspecialchars($edit_filiere_data['id']); ?>">
                        <?php endif; ?>
                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label for="nom_filiere" class="form-label">Nom de la Filière</label>
                                <input type="text" class="form-control" id="nom_filiere" name="nom_filiere"
                                       value="<?php echo $edit_filiere_data ? htmlspecialchars($edit_filiere_data['nom_filiere']) : ''; ?>" required>
                            </div>
                            <div class="col-md-4 mb-3 d-flex align-items-end">
                                <?php if ($edit_filiere_data): ?>
                                    <button type="submit" name="update_filiere" class="btn btn-custom-edit w-100 me-2"><i class="fas fa-save"></i> Mettre à Jour</button>
                                    <a href="gestion_filieres.php" class="btn btn-secondary w-100"><i class="fas fa-times"></i> Annuler</a>
                                <?php else: ?>
                                    <button type="submit" name="add_filiere" class="btn btn-custom-primary w-100"><i class="fas fa-plus"></i> Ajouter Filière</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Liste des filières -->
            <div class="card card-custom">
                <div class="card-header">
                    <i class="fas fa-list-ul"></i> Liste des Filières Enregistrées
                </div>
                <div class="card-body">
                    <?php if (empty($filieres_list)): ?>
                        <div class="alert alert-custom-info" role="alert">Aucune filière n'a été trouvée.</div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nom de la Filière</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($filieres_list as $filiere): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($filiere['id']); ?></td>
                                    <td><?php echo htmlspecialchars($filiere['nom_filiere']); ?></td>
                                    <td class="text-center">
                                        <a href="gestion_filieres.php?edit_id=<?php echo htmlspecialchars($filiere['id']); ?>" class="btn btn-sm btn-custom-edit me-1" title="Modifier">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="gestion_filieres.php?delete_id=<?php echo htmlspecialchars($filiere['id']); ?>"
                                           class="btn btn-sm btn-danger" title="Supprimer"
                                           onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette filière ? Cette action pourrait être irréversible si des classes y sont liées.');">
                                            <i class="fas fa-trash-alt"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="mt-2 text-muted"><small>Total des filières : <?php echo $count_total_filieres; ?></small></p>
                    <?php endif; ?>
                </div>
            </div>
        </main>

        <footer class="page-footer">
            © <?php echo date("Y"); ?> Gestion École. Tous droits réservés.
        </footer>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Optionnel : JavaScript pour basculer la sidebar sur mobile
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebar = document.querySelector('.sidebar');
        if (sidebarToggle && sidebar) {
            sidebarToggle.addEventListener('click', () => {
                sidebar.classList.toggle('active');
                 // Si la sidebar se superpose, ajustez main-wrapper si nécessaire
                 // document.querySelector('.main-wrapper').classList.toggle('shifted');
            });
        }
    </script>
</body>
</html>