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

$pageTitle = "Statistiques Générales";
$message = '';
$message_type = '';

// --- Calcul des statistiques ---
$stats = [];

try {
    // Nombre total d'utilisateurs par rôle
    $stmt_users_roles = $database->prepare("SELECT role, COUNT(*) as count FROM utilisateurs GROUP BY role");
    $database->execute($stmt_users_roles);
    $roles_counts = $database->fetchAll($stmt_users_roles);
    $stats['utilisateurs'] = ['total' => 0, 'admin' => 0, 'etudiant' => 0, 'enseignant' => 0];
    foreach ($roles_counts as $rc) {
        $stats['utilisateurs'][$rc['role']] = $rc['count'];
        $stats['utilisateurs']['total'] += $rc['count'];
    }

    // Nombre de filières
    $stmt_filieres = $database->prepare("SELECT COUNT(*) as count FROM filieres");
    $database->execute($stmt_filieres);
    $stats['filieres'] = $database->fetch($stmt_filieres)['count'] ?? 0;

    // Nombre de classes
    $stmt_classes = $database->prepare("SELECT COUNT(*) as count FROM classes");
    $database->execute($stmt_classes);
    $stats['classes'] = $database->fetch($stmt_classes)['count'] ?? 0;

    // Nombre de matières
    $stmt_matieres = $database->prepare("SELECT COUNT(*) as count FROM matieres");
    $database->execute($stmt_matieres);
    $stats['matieres'] = $database->fetch($stmt_matieres)['count'] ?? 0;
    
    // Nombre d'absences (total, justifiées, non justifiées)
    $stmt_absences_total = $database->prepare("SELECT COUNT(*) as count FROM absences");
    $database->execute($stmt_absences_total);
    $stats['absences']['total'] = $database->fetch($stmt_absences_total)['count'] ?? 0;

    $stmt_absences_just = $database->prepare("SELECT COUNT(*) as count FROM absences WHERE est_justifie = 1");
    $database->execute($stmt_absences_just);
    $stats['absences']['justifiees'] = $database->fetch($stmt_absences_just)['count'] ?? 0;
    $stats['absences']['non_justifiees'] = $stats['absences']['total'] - $stats['absences']['justifiees'];

    $stmt_avg_notes = $database->prepare("SELECT AVG(note) as moyenne_generale FROM notes");
$database->execute($stmt_avg_notes);
$avg_note_result = $database->fetch($stmt_avg_notes);

if ($avg_note_result && $avg_note_result['moyenne_generale'] !== null) {
    $stats['notes']['moyenne_generale'] = round($avg_note_result['moyenne_generale'], 2);
} else {
    $stats['notes']['moyenne_generale'] = 'N/A';
}

    // Nombre de notifications
    $stmt_notifs = $database->prepare("SELECT COUNT(*) as count FROM notifications WHERE visible = 1");
    $database->execute($stmt_notifs);
    $stats['notifications_visibles'] = $database->fetch($stmt_notifs)['count'] ?? 0;


} catch (PDOException $e) {
    $message = "Erreur lors du calcul des statistiques: " . $e->getMessage();
    $message_type = "danger";
    error_log("Erreur statistiques: " . $e->getMessage());
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
        .stats-card-row{margin-bottom:25px}
        .stat-card{background-color:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:20px;display:flex;align-items:center;box-shadow:0 1px 3px rgba(0,0,0,.03); height: 100%; /* Pour aligner les cartes de même hauteur */}
        .stat-card-icon{font-size:1.8rem; /* Taille icône légèrement réduite */ padding:12px;border-radius:50%;margin-right:15px;display:flex;align-items:center;justify-content:center;width:50px;height:50px}
        .stat-card-info h6{margin-bottom:2px;font-size:.75rem;color:#6b7280;text-transform:uppercase;font-weight:500}
        .stat-card-info .stat-number{font-size:1.5rem;font-weight:600;color:#111827}
        .alert-custom-danger{background-color:#fee2e2;color:#991b1b;border-color:#fecaca}
        .page-footer{padding:15px 25px;background-color:#fff;border-top:1px solid #e5e7eb;font-size:.8rem;color:#6b7280;text-align:center}
        
        /* Couleurs spécifiques pour les icônes de statistiques */
        .icon-users { background-color: rgba(59, 130, 246, 0.1); color: #3b82f6; } /* Bleu */
        .icon-etudiants { background-color: rgba(34, 197, 94, 0.1); color: #22c55e; } /* Vert */
        .icon-enseignants { background-color: rgba(249, 115, 22, 0.1); color: #f97316; } /* Orange */
        .icon-admins { background-color: rgba(107, 114, 128, 0.1); color: #6b7280; } /* Gris */
        .icon-filieres { background-color: rgba(139, 92, 246, 0.1); color: #8b5cf6; } /* Violet */
        .icon-classes { background-color: rgba(236, 72, 153, 0.1); color: #ec4899; } /* Rose */
        .icon-matieres { background-color: rgba(20, 184, 166, 0.1); color: #14b8a6; } /* Teal */
        .icon-absences { background-color: rgba(239, 68, 68, 0.1); color: #ef4444; } /* Rouge */
        .icon-notes { background-color: rgba(217, 119, 6, 0.1); color: #d97706; } /* Ambre */
        .icon-notifications { background-color: rgba(16,185,129,.1);color:#10b981}
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
                <!-- ... (reste de la top navbar, peut-être vide pour cette page) ... -->
            </div>
        </nav>

        <main class="content-area">
            <?php if (!empty($message)): ?>
            <div class="alert <?php echo 'alert-custom-' . htmlspecialchars($message_type); ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($message); ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>

            <div class="row stats-card-row">
                <!-- Utilisateurs -->
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="stat-card"><div class="stat-card-icon icon-users"><i class="fas fa-users"></i></div><div class="stat-card-info"><h6>Total Utilisateurs</h6><span class="stat-number"><?php echo $stats['utilisateurs']['total'] ?? 0; ?></span></div></div>
                </div>
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="stat-card"><div class="stat-card-icon icon-etudiants"><i class="fas fa-user-graduate"></i></div><div class="stat-card-info"><h6>Étudiants</h6><span class="stat-number"><?php echo $stats['utilisateurs']['etudiant'] ?? 0; ?></span></div></div>
                </div>
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="stat-card"><div class="stat-card-icon icon-enseignants"><i class="fas fa-chalkboard-user"></i></div><div class="stat-card-info"><h6>Enseignants</h6><span class="stat-number"><?php echo $stats['utilisateurs']['enseignant'] ?? 0; ?></span></div></div>
                </div>
                 <div class="col-xl-3 col-md-6 mb-4">
                    <div class="stat-card"><div class="stat-card-icon icon-admins"><i class="fas fa-user-shield"></i></div><div class="stat-card-info"><h6>Administrateurs</h6><span class="stat-number"><?php echo $stats['utilisateurs']['admin'] ?? 0; ?></span></div></div>
                </div>

                <!-- Structure académique -->
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="stat-card"><div class="stat-card-icon icon-filieres"><i class="fas fa-sitemap"></i></div><div class="stat-card-info"><h6>Filières</h6><span class="stat-number"><?php echo $stats['filieres'] ?? 0; ?></span></div></div>
                </div>
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="stat-card"><div class="stat-card-icon icon-classes"><i class="fas fa-school"></i></div><div class="stat-card-info"><h6>Classes</h6><span class="stat-number"><?php echo $stats['classes'] ?? 0; ?></span></div></div>
                </div>
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="stat-card"><div class="stat-card-icon icon-matieres"><i class="fas fa-book"></i></div><div class="stat-card-info"><h6>Matières</h6><span class="stat-number"><?php echo $stats['matieres'] ?? 0; ?></span></div></div>
                </div>
                 <div class="col-xl-3 col-md-6 mb-4">
                    <div class="stat-card"><div class="stat-card-icon icon-notifications"><i class="fas fa-bullhorn"></i></div><div class="stat-card-info"><h6>Notifications Actives</h6><span class="stat-number"><?php echo $stats['notifications_visibles'] ?? 0; ?></span></div></div>
                </div>

                <!-- Suivi étudiants -->
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="stat-card"><div class="stat-card-icon icon-absences"><i class="fas fa-user-clock"></i></div><div class="stat-card-info"><h6>Total Absences</h6><span class="stat-number"><?php echo $stats['absences']['total'] ?? 0; ?></span></div></div>
                </div>
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="stat-card"><div class="stat-card-icon icon-absences" style="background-color: rgba(34,197,94,.1); color: #22c55e;"><i class="fas fa-user-check"></i></div><div class="stat-card-info"><h6>Abs. Justifiées</h6><span class="stat-number"><?php echo $stats['absences']['justifiees'] ?? 0; ?></span></div></div>
                </div>
                 <div class="col-xl-3 col-md-6 mb-4">
                    <div class="stat-card"><div class="stat-card-icon icon-absences" style="background-color: rgba(249,115,22,.1); color: #f97316;"><i class="fas fa-user-times"></i></div><div class="stat-card-info"><h6>Abs. Non Justifiées</h6><span class="stat-number"><?php echo $stats['absences']['non_justifiees'] ?? 0; ?></span></div></div>
                </div>
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="stat-card"><div class="stat-card-icon icon-notes"><i class="fas fa-clipboard-check"></i></div><div class="stat-card-info"><h6>Moyenne Gén. Notes</h6><span class="stat-number"><?php echo $stats['notes']['moyenne_generale'] ?? 'N/A'; ?></span></div></div>
                </div>
                <!-- Ajoutez d'autres cartes de statistiques ici -->
            </div>
            
            <!-- Section pour des graphiques futurs (placeholder) -->
            <div class="row">
                <div class="col-lg-6 mb-4">
                    <div class="card card-custom">
                        <div class="card-header"><i class="fas fa-chart-bar"></i> Évolution des Inscriptions (Exemple)</div>
                        <div class="card-body text-center text-muted">
                            <p>Un graphique pourrait être affiché ici.</p>
                            <i class="fas fa-chart-line fa-3x opacity-50"></i>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6 mb-4">
                     <div class="card card-custom">
                        <div class="card-header"><i class="fas fa-chart-pie"></i> Répartition des Rôles (Exemple)</div>
                        <div class="card-body text-center text-muted">
                             <p>Un autre graphique ici.</p>
                             <i class="fas fa-chart-pie fa-3x opacity-50"></i>
                        </div>
                    </div>
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
        // Pour des graphiques, vous utiliseriez une bibliothèque comme Chart.js ici.
    </script>
</body>
</html>