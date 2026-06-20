<?php
session_start();

// Configuration des erreurs
ini_set('display_errors', 1); ini_set('display_startup_errors', 1); error_reporting(E_ALL);

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

$pageTitle = "Gestion de l'Emploi du Temps";
$message = '';
$message_type = '';

// Jours de la semaine pour le formulaire
$jours_semaine_options = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];

// Récupérer les classes pour la liste déroulante
$classes_disponibles = [];
try {
    $stmt_cls = $database->prepare("SELECT c.id, c.nom_classe, c.niveau, f.nom_filiere FROM classes c JOIN filieres f ON c.id_filiere = f.id ORDER BY f.nom_filiere, c.niveau, c.nom_classe");
    $database->execute($stmt_cls);
    $classes_disponibles = $database->fetchAll($stmt_cls);
} catch (PDOException $e) { $message = "Erreur récupération classes: " . $e->getMessage(); $message_type = "danger"; }

// Récupérer les matières pour la liste déroulante (pourraient être filtrées par classe plus tard via JS si besoin)
$matieres_disponibles = [];
try {
    $stmt_mat = $database->prepare("SELECT m.id, m.nom_matiere, m.code_matiere, cl.nom_classe as classe_matiere 
                                   FROM matieres m 
                                   JOIN classes cl ON m.id_classe = cl.id
                                   ORDER BY m.nom_matiere");
    $database->execute($stmt_mat);
    $matieres_disponibles = $database->fetchAll($stmt_mat);
} catch (PDOException $e) { $message = "Erreur récupération matières: " . $e->getMessage(); $message_type = "danger"; }


// AJOUTER une entrée à l'emploi du temps
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_emploi_temps'])) {
    $jour_semaine = sanitizeInput($_POST['jour_semaine']);
    $heure_debut = sanitizeInput($_POST['heure_debut']);
    $heure_fin = sanitizeInput($_POST['heure_fin']);
    $id_classe = filter_input(INPUT_POST, 'id_classe', FILTER_VALIDATE_INT);
    $id_matiere = filter_input(INPUT_POST, 'id_matiere', FILTER_VALIDATE_INT);
    $salle = sanitizeInput($_POST['salle']);

    if (!in_array($jour_semaine, $jours_semaine_options) || empty($heure_debut) || empty($heure_fin) || !$id_classe || !$id_matiere || empty($salle)) {
        $_SESSION['message'] = "Tous les champs sont requis.";
        $_SESSION['message_type'] = "danger";
    } elseif (strtotime($heure_fin) <= strtotime($heure_debut)) {
        $_SESSION['message'] = "L'heure de fin doit être après l'heure de début.";
        $_SESSION['message_type'] = "danger";
    } else {
        try {
            // Vérification de base de non-chevauchement pour la même classe, même jour, même matière
            $stmt_check = $database->prepare("SELECT id FROM emplois_temps 
                                             WHERE id_classe = :id_classe AND jour_semaine = :jour AND id_matiere = :id_matiere
                                             AND ((heure_debut < :h_fin AND heure_fin > :h_debut))");
            $database->execute($stmt_check, [
                ':id_classe' => $id_classe, ':jour' => $jour_semaine, ':id_matiere' => $id_matiere,
                ':h_debut' => $heure_debut, ':h_fin' => $heure_fin
            ]);
            if ($database->rowCount($stmt_check) > 0) {
                $_SESSION['message'] = "Conflit d'horaire: Cette matière est déjà programmée pour cette classe à ce moment-là.";
                $_SESSION['message_type'] = "warning";
            } else {
                $stmt = $database->prepare("INSERT INTO emplois_temps (jour_semaine, heure_debut, heure_fin, id_classe, id_matiere, salle) 
                                           VALUES (:jour, :h_debut, :h_fin, :id_cls, :id_mat, :salle)");
                $database->execute($stmt, [
                    ':jour' => $jour_semaine, ':h_debut' => $heure_debut, ':h_fin' => $heure_fin,
                    ':id_cls' => $id_classe, ':id_mat' => $id_matiere, ':salle' => $salle
                ]);
                $_SESSION['message'] = "Entrée d'emploi du temps ajoutée!";
                $_SESSION['message_type'] = "success";
            }
        } catch (PDOException $e) {
            $_SESSION['message'] = "Erreur ajout EDT: " . $e->getMessage(); $_SESSION['message_type'] = "danger";
        }
    }
    header("Location: emploi_temps.php"); exit();
}

// PRÉPARER L'ÉDITION
$edit_edt_data = null;
if (isset($_GET['edit_id'])) {
    $edit_id = filter_input(INPUT_GET, 'edit_id', FILTER_VALIDATE_INT);
    if ($edit_id) {
        try {
            $stmt = $database->prepare("SELECT * FROM emplois_temps WHERE id = :id");
            $database->execute($stmt, [':id' => $edit_id]);
            $edit_edt_data = $database->fetch($stmt);
            if (!$edit_edt_data) {
                $_SESSION['message'] = "Entrée EDT non trouvée."; $_SESSION['message_type'] = "warning";
                header("Location: emploi_temps.php"); exit();
            }
        } catch (PDOException $e) { $_SESSION['message'] = "Erreur récupération EDT: " . $e->getMessage(); $_SESSION['message_type'] = "danger"; }
    }
}

// METTRE À JOUR
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_emploi_temps'])) {
    $id = filter_input(INPUT_POST, 'id_edt', FILTER_VALIDATE_INT);
    $jour_semaine = sanitizeInput($_POST['jour_semaine']);
    $heure_debut = sanitizeInput($_POST['heure_debut']);
    $heure_fin = sanitizeInput($_POST['heure_fin']);
    $id_classe = filter_input(INPUT_POST, 'id_classe', FILTER_VALIDATE_INT);
    $id_matiere = filter_input(INPUT_POST, 'id_matiere', FILTER_VALIDATE_INT);
    $salle = sanitizeInput($_POST['salle']);

    if (!$id || !in_array($jour_semaine, $jours_semaine_options) || empty($heure_debut) || empty($heure_fin) || !$id_classe || !$id_matiere || empty($salle)) {
        $_SESSION['message'] = "Tous les champs sont requis pour la MAJ."; $_SESSION['message_type'] = "danger";
    } elseif (strtotime($heure_fin) <= strtotime($heure_debut)) {
        $_SESSION['message'] = "L'heure de fin doit être après l'heure de début."; $_SESSION['message_type'] = "danger";
    } else {
        try {
            $stmt_check = $database->prepare("SELECT id FROM emplois_temps 
                                             WHERE id_classe = :id_classe AND jour_semaine = :jour AND id_matiere = :id_matiere
                                             AND ((heure_debut < :h_fin AND heure_fin > :h_debut)) AND id != :current_id");
            $database->execute($stmt_check, [
                ':id_classe' => $id_classe, ':jour' => $jour_semaine, ':id_matiere' => $id_matiere,
                ':h_debut' => $heure_debut, ':h_fin' => $heure_fin, ':current_id' => $id
            ]);
            if ($database->rowCount($stmt_check) > 0) {
                $_SESSION['message'] = "Conflit d'horaire: Cette matière est déjà programmée pour cette classe à ce moment-là (hors entrée actuelle).";
                $_SESSION['message_type'] = "warning";
            } else {
                $stmt = $database->prepare("UPDATE emplois_temps SET jour_semaine = :jour, heure_debut = :h_debut, heure_fin = :h_fin, 
                                           id_classe = :id_cls, id_matiere = :id_mat, salle = :salle WHERE id = :id");
                $database->execute($stmt, [
                    ':jour' => $jour_semaine, ':h_debut' => $heure_debut, ':h_fin' => $heure_fin,
                    ':id_cls' => $id_classe, ':id_mat' => $id_matiere, ':salle' => $salle, ':id' => $id
                ]);
                $_SESSION['message'] = "Entrée EDT mise à jour!"; $_SESSION['message_type'] = "success";
            }
        } catch (PDOException $e) { $_SESSION['message'] = "Erreur MAJ EDT: " . $e->getMessage(); $_SESSION['message_type'] = "danger"; }
    }
    header("Location: emploi_temps.php"); exit();
}

// SUPPRIMER
if (isset($_GET['delete_id'])) {
    $delete_id = filter_input(INPUT_GET, 'delete_id', FILTER_VALIDATE_INT);
    if ($delete_id) {
        try {
            $stmt_delete = $database->prepare("DELETE FROM emplois_temps WHERE id = :id");
            $database->execute($stmt_delete, [':id' => $delete_id]);
            $_SESSION['message'] = "Entrée EDT supprimée!"; $_SESSION['message_type'] = "success";
        } catch (PDOException $e) { $_SESSION['message'] = "Erreur suppression EDT: " . $e->getMessage(); $_SESSION['message_type'] = "danger"; }
    }
    header("Location: emploi_temps.php"); exit();
}

// LISTER les entrées de l'emploi du temps
$edt_list = [];
$count_total_edt = 0;
// Option de filtrage par classe (exemple simple)
$filter_classe_id = isset($_GET['filter_classe']) ? filter_input(INPUT_GET, 'filter_classe', FILTER_VALIDATE_INT) : null;

try {
    $sql_list = "SELECT edt.id, edt.jour_semaine, edt.heure_debut, edt.heure_fin, edt.salle,
                        c.nom_classe, c.niveau, f.nom_filiere,
                        m.nom_matiere, m.code_matiere,
                        CONCAT(u.prenom, ' ', u.nom) as nom_enseignant
                 FROM emplois_temps edt
                 JOIN classes c ON edt.id_classe = c.id
                 JOIN filieres f ON c.id_filiere = f.id
                 JOIN matieres m ON edt.id_matiere = m.id
                 LEFT JOIN enseignants ens ON m.id_enseignant = ens.id 
                 LEFT JOIN utilisateurs u ON ens.id_utilisateur = u.id "; // LEFT JOIN pour enseignant au cas où il n'est pas défini pour une matière

    $params_sql = [];
    if ($filter_classe_id) {
        $sql_list .= " WHERE edt.id_classe = :filter_classe_id ";
        $params_sql[':filter_classe_id'] = $filter_classe_id;
    }
    // Convertir l'ordre des jours ENUM en un ordre logique
    $sql_list .= " ORDER BY FIELD(edt.jour_semaine, 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'), edt.heure_debut ASC";
                 
    $stmt_list = $database->prepare($sql_list);
    if ($database->execute($stmt_list, $params_sql)) {
        $edt_list_raw = $database->fetchAll($stmt_list);
        // Grouper par jour pour l'affichage
        foreach($edt_list_raw as $item) {
            $edt_list[$item['jour_semaine']][] = $item;
        }
        $count_total_edt = count($edt_list_raw);
    } else { $message = "Erreur récupération EDT."; $message_type = "danger"; }
} catch (PDOException $e) { $message = "Erreur DB (liste EDT): " . $e->getMessage(); $message_type = "danger"; }

// Afficher les messages de session
if (isset($_SESSION['message'])) { $message = $_SESSION['message']; $message_type = $_SESSION['message_type']; unset($_SESSION['message']); unset($_SESSION['message_type']); }

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
        /* ... (Reste du CSS identique) ... */
        .main-wrapper{display:flex;flex-direction:column;flex-grow:1;margin-left:250px;transition:margin-left .3s ease;background-color:#f8f9fa}
        .top-navbar{background-color:#fff;padding:0 20px;border-bottom:1px solid #e5e7eb;box-shadow:0 1px 3px rgba(0,0,0,.03);min-height:60px;display:flex;align-items:center}
        .top-navbar .page-main-title{font-size:1.25rem;color:#111827;font-weight:600;margin:0;margin-right:20px}
        .content-area{padding:25px;background-color:#f0f2f5;flex-grow:1}
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
        .timetable-day-header { background-color: #e9ecef; padding: 0.75rem; font-weight: bold; margin-top: 1.5rem; border-radius: 0.25rem; }
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
            <a class="nav-link <?php echo in_array($currentPage, ['gestion_filieres.php', 'gestion_classes.php', 'gestion_matieres.php', 'gestion_absences.php', 'emploi_temps.php']) ? '' : 'collapsed'; ?>"
               href="#submenuGestionAcademique" data-bs-toggle="collapse" role="button"
               aria-expanded="<?php echo in_array($currentPage, ['gestion_filieres.php', 'gestion_classes.php', 'gestion_matieres.php', 'gestion_absences.php', 'emploi_temps.php']) ? 'true' : 'false'; ?>" >
                <i class="fas fa-graduation-cap fa-fw"></i> Gestion Académique
            </a>
            <div class="collapse <?php echo in_array($currentPage, ['gestion_filieres.php', 'gestion_classes.php', 'gestion_matieres.php', 'gestion_absences.php', 'emploi_temps.php']) ? 'show' : ''; ?>" id="submenuGestionAcademique">
                <ul class="nav flex-column ps-3">
                    <!-- ... autres liens académiques ... -->
                    <li class="nav-item"><a class="nav-link <?php echo ($currentPage == 'gestion_matieres.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>dashboard/admin/gestion_matieres.php"><i class="fas fa-book fa-fw"></i> Matières</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo ($currentPage == 'emploi_temps.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>dashboard/admin/emploi_temps.php"><i class="fas fa-calendar-alt fa-fw"></i> Emploi du Temps</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo ($currentPage == 'gestion_absences.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>dashboard/admin/gestion_absences.php"><i class="fas fa-user-clock fa-fw"></i> Absences</a></li>
                </ul>
            </div>
            <a class="nav-link" href="<?php echo BASE_URL; ?>dashboard/admin/notifications.php"><i class="fas fa-bell fa-fw"></i> Notifications</a>
            <hr class="sidebar-divider">
            <a class="nav-link" href="#"><i class="fas fa-sign-out-alt fa-fw"></i> Déconnexion</a>
        </nav>
    </aside>

    <div class="main-wrapper">
        <nav class="top-navbar navbar navbar-expand-lg">
             <div class="container-fluid">
                <button class="btn d-md-none me-2" id="sidebarToggle"><i class="fas fa-bars"></i></button>
                <h1 class="page-main-title"><?php echo htmlspecialchars($pageTitle); ?></h1>
                <!-- ... reste de la top navbar ... -->
            </div>
        </nav>

        <main class="content-area">
            <?php if (!empty($message)): ?>
            <div class="alert <?php echo 'alert-custom-' . htmlspecialchars($message_type); ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($message); ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>

            <div class="card card-custom mb-4">
                <div class="card-header"><i class="fas <?php echo $edit_edt_data ? 'fa-edit' : 'fa-plus-circle'; ?>"></i> <?php echo $edit_edt_data ? 'Modifier l\'Entrée' : 'Ajouter une Entrée à l\'Emploi du Temps'; ?></div>
                <div class="card-body">
                    <form action="emploi_temps.php" method="POST">
                        <?php if ($edit_edt_data): ?><input type="hidden" name="id_edt" value="<?php echo htmlspecialchars($edit_edt_data['id']); ?>"><?php endif; ?>
                        <div class="row">
                            <div class="col-md-4 mb-3"><label for="jour_semaine" class="form-label">Jour</label><select class="form-select" id="jour_semaine" name="jour_semaine" required>
                                <?php foreach ($jours_semaine_options as $jour): ?>
                                <option value="<?php echo $jour; ?>" <?php echo ($edit_edt_data && $edit_edt_data['jour_semaine'] == $jour) ? 'selected' : ''; ?>><?php echo $jour; ?></option>
                                <?php endforeach; ?>
                            </select></div>
                            <div class="col-md-4 mb-3"><label for="heure_debut" class="form-label">Heure de Début</label><input type="time" class="form-control" id="heure_debut" name="heure_debut" value="<?php echo $edit_edt_data ? htmlspecialchars($edit_edt_data['heure_debut']) : ''; ?>" required></div>
                            <div class="col-md-4 mb-3"><label for="heure_fin" class="form-label">Heure de Fin</label><input type="time" class="form-control" id="heure_fin" name="heure_fin" value="<?php echo $edit_edt_data ? htmlspecialchars($edit_edt_data['heure_fin']) : ''; ?>" required></div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3"><label for="id_classe" class="form-label">Classe</label><select class="form-select" id="id_classe" name="id_classe" required>
                                <option value="">-- Sélectionner classe --</option>
                                <?php foreach ($classes_disponibles as $cls): ?>
                                <option value="<?php echo $cls['id']; ?>" <?php echo ($edit_edt_data && $edit_edt_data['id_classe'] == $cls['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($cls['nom_classe'] . ' (' . $cls['niveau'] . ') - ' . $cls['nom_filiere']); ?></option>
                                <?php endforeach; ?><?php if(empty($classes_disponibles)): ?><option disabled>Aucune classe.</option><?php endif; ?>
                            </select></div>
                            <div class="col-md-4 mb-3"><label for="id_matiere" class="form-label">Matière</label><select class="form-select" id="id_matiere" name="id_matiere" required>
                                <option value="">-- Sélectionner matière --</option>
                                <?php foreach ($matieres_disponibles as $mat): ?>
                                <option value="<?php echo $mat['id']; ?>" 
                                   <?php echo ($edit_edt_data && isset($edit_edt_data['id_matiere']) && $edit_edt_data['id_matiere'] == $mat['id']) ? 'selected' : ''; ?> data-classe-matiere="<?php echo isset($mat['id_classe']) ? $mat['id_classe'] : ''; ?>">
                                   <?php echo htmlspecialchars($mat['nom_matiere'] . ' (' . (isset($mat['code_matiere']) ? $mat['code_matiere'] : '') . ' - ' .  (isset($mat['classe_matiere']) ? $mat['classe_matiere'] : '') . ')' ); ?>
                                </option>
                                <?php endforeach; ?><?php if(empty($matieres_disponibles)): ?><option disabled>Aucune matière.</option><?php endif; ?>
                            </select></div>
                            <div class="col-md-4 mb-3"><label for="salle" class="form-label">Salle</label><input type="text" class="form-control" id="salle" name="salle" value="<?php echo $edit_edt_data ? htmlspecialchars($edit_edt_data['salle']) : ''; ?>" required></div>
                        </div>
                        <div class="d-flex justify-content-end mt-3">
                            <?php if ($edit_edt_data): ?>
                                <button type="submit" name="update_emploi_temps" class="btn btn-custom-edit me-2"><i class="fas fa-save"></i> Mettre à Jour</button>
                                <a href="emploi_temps.php" class="btn btn-secondary"><i class="fas fa-times"></i> Annuler</a>
                            <?php else: ?>
                                <button type="submit" name="add_emploi_temps" class="btn btn-custom-primary" <?php if(empty($classes_disponibles) || empty($matieres_disponibles)) echo 'disabled'; ?>><i class="fas fa-plus"></i> Ajouter au Planning</button>
                            <?php endif; ?>
                        </div>
                         <?php if(empty($classes_disponibles) || empty($matieres_disponibles)): ?>
                            <p class="text-danger mt-2"><small><i class="fas fa-exclamation-triangle"></i> Des classes et matières doivent être créées.</small></p>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <div class="card card-custom">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-calendar-alt"></i> Emploi du Temps</span>
                    <form action="emploi_temps.php" method="GET" class="d-flex align-items-center">
                        <label for="filter_classe" class="form-label me-2 mb-0">Filtrer par classe:</label>
                        <select name="filter_classe" id="filter_classe" class="form-select form-select-sm me-2" style="width: auto;" onchange="this.form.submit()">
                            <option value="">Toutes les classes</option>
                            <?php foreach ($classes_disponibles as $cls): ?>
                            <option value="<?php echo $cls['id']; ?>" <?php if ($filter_classe_id == $cls['id']) echo 'selected'; ?>><?php echo htmlspecialchars($cls['nom_classe'] . ' (' . $cls['niveau'] . ')'); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <noscript><button type="submit" class="btn btn-sm btn-outline-secondary">Filtrer</button></noscript>
                    </form>
                </div>
                <div class="card-body">
                    <?php if (empty($edt_list_raw) && empty($message) && !$filter_classe_id): ?>
                        <div class="alert alert-custom-info" role="alert">Aucune entrée dans l'emploi du temps.</div>
                    <?php elseif (empty($edt_list_raw) && empty($message) && $filter_classe_id): ?>
                        <div class="alert alert-custom-info" role="alert">Aucune entrée pour la classe sélectionnée.</div>
                    <?php elseif (!empty($edt_list)): ?>
                        <?php foreach ($jours_semaine_options as $jour): // Boucle sur les jours pour l'ordre ?>
                            <?php if (isset($edt_list[$jour]) && !empty($edt_list[$jour])): ?>
                                <h5 class="timetable-day-header"><?php echo htmlspecialchars($jour); ?></h5>
                                <div class="table-responsive mb-3">
                                    <table class="table table-sm table-bordered table-hover">
                                        <thead><tr><th>Heure</th><th>Matière (Code)</th><th>Enseignant</th><th>Salle</th><th>Classe</th><th class="text-center">Actions</th></tr></thead>
                                        <tbody>
                                            <?php foreach ($edt_list[$jour] as $entree): ?>
                                            <tr>
                                                <td><?php echo date('H:i', strtotime($entree['heure_debut'])) . ' - ' . date('H:i', strtotime($entree['heure_fin'])); ?></td>
                                                <td><?php echo htmlspecialchars($entree['nom_matiere'] . ' (' . $entree['code_matiere'] . ')'); ?></td>
                                                <td><?php echo htmlspecialchars($entree['nom_enseignant'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($entree['salle']); ?></td>
                                                <td><?php echo htmlspecialchars($entree['nom_classe'] . ' - ' . $entree['niveau'] . ' (' . $entree['nom_filiere'] . ')'); ?></td>
                                                <td class="text-center">
                                                    <a href="emploi_temps.php?edit_id=<?php echo htmlspecialchars($entree['id']); ?>" class="btn btn-sm btn-custom-edit me-1 py-0 px-1" title="Modifier"><i class="fas fa-edit"></i></a>
                                                    <a href="emploi_temps.php?delete_id=<?php echo htmlspecialchars($entree['id']); ?>" class="btn btn-sm btn-danger py-0 px-1" title="Supprimer" onclick="return confirm('Supprimer cette entrée ?');"><i class="fas fa-trash-alt"></i></a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                     <?php if (!empty($message) && $message_type == 'danger' && (strpos($message, 'liste EDT') !== false) ): ?>
                        <div class="alert alert-custom-danger" role="alert"><?php echo htmlspecialchars($message); ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
        <footer class="page-footer">© <?php echo date("Y"); ?> Gestion École.</footer>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebar = document.querySelector('.sidebar');
        if (sidebarToggle && sidebar) { sidebarToggle.addEventListener('click', () => { sidebar.classList.toggle('active'); }); }
    </script>
</body>
</html>