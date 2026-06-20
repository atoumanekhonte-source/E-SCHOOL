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

$pageTitle = "Paramètres de l'Application";
$message = '';
$message_type = '';

// Exemple de gestion de paramètres (très basique)
// Dans une vraie application, ces paramètres seraient lus/écrits depuis une table de configuration ou un fichier.
$param_nom_ecole = "Mon École Virtuelle";
$param_annee_scolaire = "2023-2024";
$param_email_contact = "contact@monecole.com";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_parametres'])) {
    // Ici, vous enregistreriez les paramètres (par exemple, dans une table 'parametres_app' ou un fichier config)
    // Pour cet exemple, nous allons juste simuler et afficher un message.
    $param_nom_ecole_new = sanitizeInput($_POST['nom_ecole']);
    $param_annee_scolaire_new = sanitizeInput($_POST['annee_scolaire']);
    $param_email_contact_new = sanitizeInput($_POST['email_contact']);

    // Simuler la sauvegarde (remplacer par une vraie logique de sauvegarde)
    if (!empty($param_nom_ecole_new) && !empty($param_annee_scolaire_new) && filter_var($param_email_contact_new, FILTER_VALIDATE_EMAIL)) {
        // $param_nom_ecole = $param_nom_ecole_new; // Mettre à jour les variables si la sauvegarde était réelle
        // $param_annee_scolaire = $param_annee_scolaire_new;
        // $param_email_contact = $param_email_contact_new;
        $_SESSION['message'] = "Paramètres (simulés) enregistrés avec succès!";
        $_SESSION['message_type'] = "success";
    } else {
        $_SESSION['message'] = "Erreur: Tous les champs sont requis et l'email doit être valide.";
        $_SESSION['message_type'] = "danger";
    }
    header("Location: parametres.php");
    exit();
}


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
        .btn-custom-primary{background-color:#ef4444;border-color:#ef4444;color:#fff}
        .btn-custom-primary:hover{background-color:#dc2626;border-color:#dc2626}
        .alert-custom-success{background-color:#d1fae5;color:#065f46;border-color:#a7f3d0}
        .alert-custom-danger{background-color:#fee2e2;color:#991b1b;border-color:#fecaca}
        .form-label{font-weight:500;color:#374151;margin-bottom:.3rem;font-size:.875rem}
        .form-control,.form-select{font-size:.875rem;border-radius:6px;border-color:#d1d5db}
        .form-control:focus,.form-select:focus{border-color:#ef4444;box-shadow:0 0 0 .2rem rgba(239,68,68,.25)}
        .page-footer{padding:15px 25px;background-color:#fff;border-top:1px solid #e5e7eb;font-size:.8rem;color:#6b7280;text-align:center}
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
            <!-- ... (liens de gestion) ... -->
            <a class="nav-link <?php echo ($currentPage == 'statistiques.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>dashboard/admin/statistiques.php">
                <i class="fas fa-chart-pie fa-fw"></i> Statistiques
            </a>
            <a class="nav-link <?php echo ($currentPage == 'parametres.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>dashboard/admin/parametres.php">
                <i class="fas fa-cog fa-fw"></i> Paramètres
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
                <!-- ... (reste de la top navbar) ... -->
            </div>
        </nav>

        <main class="content-area">
            <?php if (!empty($message)): ?>
            <div class="alert <?php echo 'alert-custom-' . htmlspecialchars($message_type); ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($message); ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>

            <div class="card card-custom">
                <div class="card-header"><i class="fas fa-cogs"></i> Paramètres Généraux</div>
                <div class="card-body">
                    <form action="parametres.php" method="POST">
                        <div class="mb-3">
                            <label for="nom_ecole" class="form-label">Nom de l'École</label>
                            <input type="text" class="form-control" id="nom_ecole" name="nom_ecole" value="<?php echo htmlspecialchars($param_nom_ecole); ?>">
                        </div>
                        <div class="mb-3">
                            <label for="annee_scolaire" class="form-label">Année Scolaire en Cours</label>
                            <input type="text" class="form-control" id="annee_scolaire" name="annee_scolaire" value="<?php echo htmlspecialchars($param_annee_scolaire); ?>" placeholder="Ex: 2023-2024">
                        </div>
                        <div class="mb-3">
                            <label for="email_contact" class="form-label">Email de Contact Principal</label>
                            <input type="email" class="form-control" id="email_contact" name="email_contact" value="<?php echo htmlspecialchars($param_email_contact); ?>">
                        </div>
                        
                        <hr>
                        <h5 class="mt-4">Autres Paramètres (Exemples)</h5>
                        <div class="mb-3 form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="maintenance_mode" name="maintenance_mode" disabled>
                            <label class="form-check-label" for="maintenance_mode">Mode Maintenance (Fonctionnalité future)</label>
                        </div>
                         <div class="mb-3">
                            <label for="logo_ecole" class="form-label">Logo de l'École (Fonctionnalité future)</label>
                            <input type="file" class="form-control" id="logo_ecole" name="logo_ecole" disabled>
                        </div>
                        
                        <div class="d-flex justify-content-end mt-4">
                            <button type="submit" name="save_parametres" class="btn btn-custom-primary"><i class="fas fa-save"></i> Enregistrer les Paramètres</button>
                        </div>
                    </form>
                </div>
            </div>
             <div class="card card-custom mt-4">
                <div class="card-header"><i class="fas fa-info-circle"></i> Informations Système</div>
                <div class="card-body">
                    <p><strong>Version PHP:</strong> <?php echo phpversion(); ?></p>
                    <p><strong>Serveur Web:</strong> <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'N/A'; ?></p>
                    <p><strong>Base de Données (Client):</strong> <?php echo defined('PDO::ATTR_CLIENT_VERSION') ? $db->getAttribute(PDO::ATTR_CLIENT_VERSION) : 'N/A'; ?></p>
                    <p><strong>Base de Données (Serveur):</strong> <?php echo defined('PDO::ATTR_SERVER_VERSION') ? $db->getAttribute(PDO::ATTR_SERVER_VERSION) : 'N/A'; ?></p>
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