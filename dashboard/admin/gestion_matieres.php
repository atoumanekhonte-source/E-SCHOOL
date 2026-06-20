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
function sanitizeInput($data) { if (is_array($data)) return array_map('sanitizeInput', $data); if ($data === null) return null; if (!is_string($data) && !is_numeric($data) && !is_bool($data)) $data = (string) $data; if (is_string($data)) { $data = trim($data); $data = stripslashes($data); $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8'); } return $data; }
function getCurrentPage() { return basename($_SERVER['SCRIPT_FILENAME']); }

// --- Connexion DB ---
$database = new Database();
$db = $database->connect();

// --- Logique CRUD pour les Matières ---
$pageTitle = "Gestion des Matières";
$message = '';
$message_type = '';

// Récupérer les enseignants pour la liste déroulante
$enseignants_disponibles = [];
try {
    $stmt_ens = $database->prepare("SELECT e.id, u.nom, u.prenom FROM enseignants e JOIN utilisateurs u ON e.id_utilisateur = u.id WHERE u.role = 'enseignant' ORDER BY u.nom, u.prenom");
    $database->execute($stmt_ens);
    $enseignants_disponibles = $database->fetchAll($stmt_ens);
} catch (PDOException $e) {
    $message = "Erreur récupération enseignants: " . $e->getMessage(); $message_type = "danger";
}

// Récupérer les classes pour la liste déroulante
$classes_disponibles = [];
try {
    $stmt_cls = $database->prepare("SELECT c.id, c.nom_classe, c.niveau, f.nom_filiere FROM classes c JOIN filieres f ON c.id_filiere = f.id ORDER BY f.nom_filiere, c.niveau, c.nom_classe");
    $database->execute($stmt_cls);
    $classes_disponibles = $database->fetchAll($stmt_cls);
} catch (PDOException $e) {
    $message = "Erreur récupération classes: " . $e->getMessage(); $message_type = "danger";
}


// AJOUTER une matière
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_matiere'])) {
    $nom_matiere = sanitizeInput($_POST['nom_matiere']);
    $code_matiere = sanitizeInput($_POST['code_matiere']);
    $id_enseignant = filter_input(INPUT_POST, 'id_enseignant', FILTER_VALIDATE_INT);
    $id_classe = filter_input(INPUT_POST, 'id_classe', FILTER_VALIDATE_INT);

    if (empty($nom_matiere) || empty($code_matiere) || !$id_enseignant || !$id_classe) {
        $_SESSION['message'] = "Tous les champs sont requis.";
        $_SESSION['message_type'] = "danger";
    } else {
        try {
            // Vérifier si une matière avec le même code existe déjà pour la même classe
            $stmt_check = $database->prepare("SELECT id FROM matieres WHERE code_matiere = :code_matiere AND id_classe = :id_classe");
            $database->execute($stmt_check, [':code_matiere' => $code_matiere, ':id_classe' => $id_classe]);
            if ($database->rowCount($stmt_check) > 0) {
                $_SESSION['message'] = "Une matière avec ce code existe déjà pour cette classe.";
                $_SESSION['message_type'] = "warning";
            } else {
                $stmt = $database->prepare("INSERT INTO matieres (nom_matiere, code_matiere, id_enseignant, id_classe) VALUES (:nom, :code, :id_ens, :id_cls)");
                $database->execute($stmt, [
                    ':nom' => $nom_matiere, ':code' => $code_matiere, ':id_ens' => $id_enseignant, ':id_cls' => $id_classe
                ]);
                $_SESSION['message'] = "Matière ajoutée avec succès!";
                $_SESSION['message_type'] = "success";
            }
        } catch (PDOException $e) {
            $_SESSION['message'] = "Erreur ajout matière: " . $e->getMessage(); $_SESSION['message_type'] = "danger";
        }
    }
    header("Location: gestion_matieres.php"); exit();
}

// PRÉPARER L'ÉDITION
$edit_matiere_data = null;
if (isset($_GET['edit_id'])) {
    $edit_id = filter_input(INPUT_GET, 'edit_id', FILTER_VALIDATE_INT);
    if ($edit_id) {
        try {
            $stmt = $database->prepare("SELECT * FROM matieres WHERE id = :id");
            $database->execute($stmt, [':id' => $edit_id]);
            $edit_matiere_data = $database->fetch($stmt);
            if (!$edit_matiere_data) {
                $_SESSION['message'] = "Matière non trouvée."; $_SESSION['message_type'] = "warning";
                header("Location: gestion_matieres.php"); exit();
            }
        } catch (PDOException $e) {
            $_SESSION['message'] = "Erreur récupération matière: " . $e->getMessage(); $_SESSION['message_type'] = "danger";
        }
    }
}

// METTRE À JOUR
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_matiere'])) {
    $id = filter_input(INPUT_POST, 'id_matiere', FILTER_VALIDATE_INT);
    $nom_matiere = sanitizeInput($_POST['nom_matiere']);
    $code_matiere = sanitizeInput($_POST['code_matiere']);
    $id_enseignant = filter_input(INPUT_POST, 'id_enseignant', FILTER_VALIDATE_INT);
    $id_classe = filter_input(INPUT_POST, 'id_classe', FILTER_VALIDATE_INT);

    if (empty($id) || empty($nom_matiere) || empty($code_matiere) || !$id_enseignant || !$id_classe) {
        $_SESSION['message'] = "Tous les champs sont requis pour la MAJ."; $_SESSION['message_type'] = "danger";
    } else {
        try {
            $stmt_check = $database->prepare("SELECT id FROM matieres WHERE code_matiere = :code_matiere AND id_classe = :id_classe AND id != :id");
            $database->execute($stmt_check, [':code_matiere' => $code_matiere, ':id_classe' => $id_classe, ':id' => $id]);
            if ($database->rowCount($stmt_check) > 0) {
                $_SESSION['message'] = "Une autre matière avec ce code existe déjà pour cette classe.";
                $_SESSION['message_type'] = "warning";
            } else {
                $stmt = $database->prepare("UPDATE matieres SET nom_matiere = :nom, code_matiere = :code, id_enseignant = :id_ens, id_classe = :id_cls WHERE id = :id");
                $database->execute($stmt, [
                    ':nom' => $nom_matiere, ':code' => $code_matiere, ':id_ens' => $id_enseignant, ':id_cls' => $id_classe, ':id' => $id
                ]);
                $_SESSION['message'] = "Matière mise à jour!"; $_SESSION['message_type'] = "success";
            }
        } catch (PDOException $e) {
            $_SESSION['message'] = "Erreur MAJ matière: " . $e->getMessage(); $_SESSION['message_type'] = "danger";
        }
    }
    header("Location: gestion_matieres.php"); exit();
}

// SUPPRIMER
if (isset($_GET['delete_id'])) {
    $delete_id = filter_input(INPUT_GET, 'delete_id', FILTER_VALIDATE_INT);
    if ($delete_id) {
        try {
            // Vérifier les dépendances (simplifié, ajoutez plus de vérifications si nécessaire)
            $dependencies = [
                "emplois_temps" => "SELECT COUNT(*) as count FROM emplois_temps WHERE id_matiere = :id_matiere",
                "cours_en_ligne" => "SELECT COUNT(*) as count FROM cours_en_ligne WHERE id_matiere = :id_matiere",
                "notes" => "SELECT COUNT(*) as count FROM notes WHERE id_matiere = :id_matiere",
                "devoirs" => "SELECT COUNT(*) as count FROM devoirs WHERE id_matiere = :id_matiere",
            ];
            $is_dependent = false;
            foreach ($dependencies as $table => $query) {
                $stmt_check_dep = $database->prepare($query);
                $database->execute($stmt_check_dep, [':id_matiere' => $delete_id]);
                $result = $database->fetch($stmt_check_dep);
                if ($result && $result['count'] > 0) {
                    $is_dependent = true;
                    $_SESSION['message'] = "Suppression impossible: matière utilisée dans la table '$table'.";
                    $_SESSION['message_type'] = "warning";
                    break;
                }
            }

            if (!$is_dependent) {
                $stmt_delete = $database->prepare("DELETE FROM matieres WHERE id = :id");
                $database->execute($stmt_delete, [':id' => $delete_id]);
                $_SESSION['message'] = "Matière supprimée!"; $_SESSION['message_type'] = "success";
            }
        } catch (PDOException $e) {
            $_SESSION['message'] = "Erreur suppression: " . $e->getMessage(); $_SESSION['message_type'] = "danger";
        }
    }
    header("Location: gestion_matieres.php"); exit();
}

// LISTER les matières
$matieres_list = [];
$count_total_matieres = 0;
try {
    $sql_list = "SELECT m.id, m.nom_matiere, m.code_matiere, 
                        CONCAT(u.prenom, ' ', u.nom) as nom_enseignant,
                        CONCAT(c.nom_classe, ' (', c.niveau, ')') as nom_classe_complet
                 FROM matieres m
                 JOIN enseignants e ON m.id_enseignant = e.id
                 JOIN utilisateurs u ON e.id_utilisateur = u.id
                 JOIN classes c ON m.id_classe = c.id
                 ORDER BY m.nom_matiere ASC";
    $stmt_list = $database->prepare($sql_list);
    if ($database->execute($stmt_list)) {
        $matieres_list = $database->fetchAll($stmt_list);
        $count_total_matieres = count($matieres_list);
    } else {
        $message = "Erreur récupération liste matières."; $message_type = "danger";
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
        .stat-card-icon.icon-matieres{background-color:rgba(139,92,246,.1);color:#8b5cf6} /* Violet pour matières */
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
        .form-control,.form-select{font-size:.875rem;border-radius:6px;border-color:#d1d5db}
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
            <a class="nav-link <?php echo in_array($currentPage, ['gestion_filieres.php', 'gestion_classes.php', 'gestion_matieres.php']) ? '' : 'collapsed'; ?>"
               href="#submenuGestionAcademique" data-bs-toggle="collapse" role="button"
               aria-expanded="<?php echo in_array($currentPage, ['gestion_filieres.php', 'gestion_classes.php', 'gestion_matieres.php']) ? 'true' : 'false'; ?>" >
                <i class="fas fa-graduation-cap fa-fw"></i> Gestion Académique
            </a>
            <div class="collapse <?php echo in_array($currentPage, ['gestion_filieres.php', 'gestion_classes.php', 'gestion_matieres.php']) ? 'show' : ''; ?>" id="submenuGestionAcademique">
                <ul class="nav flex-column ps-3">
                    <li class="nav-item"><a class="nav-link <?php echo ($currentPage == 'gestion_filieres.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>dashboard/admin/gestion_filieres.php"><i class="fas fa-sitemap fa-fw"></i> Filières</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo ($currentPage == 'gestion_classes.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>dashboard/admin/gestion_classes.php"><i class="fas fa-chalkboard-teacher fa-fw"></i> Classes</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo ($currentPage == 'gestion_matieres.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>dashboard/admin/gestion_matieres.php"><i class="fas fa-book fa-fw"></i> Matières</a></li>
                </ul>
            </div>
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
                    <li class="nav-item"><a href="<?php echo BASE_URL; ?>dashboard/admin/gestion_matieres.php" class="nav-link active" aria-current="page">Liste</a></li>
                </ul>
                <div class="collapse navbar-collapse" id="topNavContent">
                    <form class="d-flex ms-auto me-3 search-form" role="search">
                        <div class="input-group input-group-sm">
                            <input class="form-control" type="search" placeholder="Rechercher matière..." aria-label="Search">
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
                <div class="col-xl-4 col-md-6 mb-4">
                    <div class="stat-card"><div class="stat-card-icon icon-matieres"><i class="fas fa-book-open"></i></div><div class="stat-card-info"><h6>Total Matières</h6><span class="stat-number"><?php echo $count_total_matieres; ?></span></div></div>
                </div>
                 <div class="col-xl-4 col-md-6 mb-4">
                    <div class="stat-card"><div class="stat-card-icon icon-enseignants" style="background-color: rgba(249,115,22,.1); color: #f97316;"><i class="fas fa-chalkboard-user"></i></div><div class="stat-card-info"><h6>Enseignants Actifs</h6><span class="stat-number"><?php echo count($enseignants_disponibles); ?></span></div></div>
                </div>
                 <div class="col-xl-4 col-md-6 mb-4">
                    <div class="stat-card"><div class="stat-card-icon icon-etudiants" style="background-color: rgba(34,197,94,.1); color: #22c55e;"><i class="fas fa-school"></i></div><div class="stat-card-info"><h6>Classes Disponibles</h6><span class="stat-number"><?php echo count($classes_disponibles); ?></span></div></div>
                </div>
            </div>

            <?php if (!empty($message)): ?>
            <div class="alert <?php echo 'alert-custom-' . htmlspecialchars($message_type); ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($message); ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>

            <div class="card card-custom mb-4">
                <div class="card-header"><i class="fas <?php echo $edit_matiere_data ? 'fa-edit' : 'fa-plus-circle'; ?>"></i> <?php echo $edit_matiere_data ? 'Modifier la Matière' : 'Ajouter une Nouvelle Matière'; ?></div>
                <div class="card-body">
                    <form action="gestion_matieres.php" method="POST">
                        <?php if ($edit_matiere_data): ?><input type="hidden" name="id_matiere" value="<?php echo htmlspecialchars($edit_matiere_data['id']); ?>"><?php endif; ?>
                        <div class="row">
                            <div class="col-md-6 mb-3"><label for="nom_matiere" class="form-label">Nom de la Matière</label><input type="text" class="form-control" id="nom_matiere" name="nom_matiere" value="<?php echo $edit_matiere_data ? htmlspecialchars($edit_matiere_data['nom_matiere']) : ''; ?>" required></div>
                            <div class="col-md-6 mb-3"><label for="code_matiere" class="form-label">Code Matière</label><input type="text" class="form-control" id="code_matiere" name="code_matiere" value="<?php echo $edit_matiere_data ? htmlspecialchars($edit_matiere_data['code_matiere']) : ''; ?>" required></div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3"><label for="id_enseignant" class="form-label">Enseignant</label><select class="form-select" id="id_enseignant" name="id_enseignant" required>
                                <option value="">-- Sélectionner un enseignant --</option>
                                <?php foreach ($enseignants_disponibles as $ens): ?>
                                <option value="<?php echo $ens['id']; ?>" <?php echo ($edit_matiere_data && $edit_matiere_data['id_enseignant'] == $ens['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($ens['prenom'] . ' ' . $ens['nom']); ?></option>
                                <?php endforeach; ?>
                                <?php if(empty($enseignants_disponibles)): ?><option disabled>Aucun enseignant disponible.</option><?php endif; ?>
                            </select></div>
                            <div class="col-md-6 mb-3"><label for="id_classe" class="form-label">Classe</label><select class="form-select" id="id_classe" name="id_classe" required>
                                <option value="">-- Sélectionner une classe --</option>
                                <?php foreach ($classes_disponibles as $cls): ?>
                                <option value="<?php echo $cls['id']; ?>" <?php echo ($edit_matiere_data && $edit_matiere_data['id_classe'] == $cls['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($cls['nom_classe'] . ' (' . $cls['niveau'] . ') - ' . $cls['nom_filiere']); ?></option>
                                <?php endforeach; ?>
                                <?php if(empty($classes_disponibles)): ?><option disabled>Aucune classe disponible.</option><?php endif; ?>
                            </select></div>
                        </div>
                        <div class="d-flex justify-content-end mt-3">
                            <?php if ($edit_matiere_data): ?>
                                <button type="submit" name="update_matiere" class="btn btn-custom-edit me-2"><i class="fas fa-save"></i> Mettre à Jour</button>
                                <a href="gestion_matieres.php" class="btn btn-secondary"><i class="fas fa-times"></i> Annuler</a>
                            <?php else: ?>
                                <button type="submit" name="add_matiere" class="btn btn-custom-primary" <?php if(empty($enseignants_disponibles) || empty($classes_disponibles)) echo 'disabled'; ?>><i class="fas fa-plus"></i> Ajouter Matière</button>
                            <?php endif; ?>
                        </div>
                         <?php if(empty($enseignants_disponibles) || empty($classes_disponibles)): ?>
                            <p class="text-danger mt-2"><small><i class="fas fa-exclamation-triangle"></i> Assurez-vous qu'il y a des enseignants et des classes enregistrés avant d'ajouter une matière.</small></p>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <div class="card card-custom">
                <div class="card-header"><i class="fas fa-list-ul"></i> Liste des Matières</div>
                <div class="card-body">
                    <?php if (empty($matieres_list)): ?>
                        <div class="alert alert-custom-info" role="alert">Aucune matière trouvée.</div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead><tr><th>ID</th><th>Nom Matière</th><th>Code</th><th>Enseignant</th><th>Classe</th><th class="text-center">Actions</th></tr></thead>
                            <tbody>
                                <?php foreach ($matieres_list as $matiere): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($matiere['id']); ?></td>
                                    <td><?php echo htmlspecialchars($matiere['nom_matiere']); ?></td>
                                    <td><span class="badge bg-secondary"><?php echo htmlspecialchars($matiere['code_matiere']); ?></span></td>
                                    <td><?php echo htmlspecialchars($matiere['nom_enseignant']); ?></td>
                                    <td><?php echo htmlspecialchars($matiere['nom_classe_complet']); ?></td>
                                    <td class="text-center">
                                        <a href="gestion_matieres.php?edit_id=<?php echo htmlspecialchars($matiere['id']); ?>" class="btn btn-sm btn-custom-edit me-1" title="Modifier"><i class="fas fa-edit"></i></a>
                                        <a href="gestion_matieres.php?delete_id=<?php echo htmlspecialchars($matiere['id']); ?>" class="btn btn-sm btn-danger" title="Supprimer" onclick="return confirm('Supprimer cette matière ? Vérifiez les dépendances.');"><i class="fas fa-trash-alt"></i></a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="mt-2 text-muted"><small>Total matières : <?php echo $count_total_matieres; ?></small></p>
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