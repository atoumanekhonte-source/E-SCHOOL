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

$pageTitle = "Inscrire un Nouvel Étudiant";
$message = '';
$message_type = '';

// Chemin pour les uploads de photos
define('UPLOAD_DIR_ETUDIANT', dirname(dirname(dirname(__DIR__))) . '/assets/uploads/avatars/');
define('UPLOAD_URL_DIR_ETUDIANT', BASE_URL . 'assets/uploads/avatars/');

if (!is_dir(UPLOAD_DIR_ETUDIANT)) {
    if (!mkdir(UPLOAD_DIR_ETUDIANT, 0775, true)) { // Tenter de créer avec permissions
        // Ne pas bloquer, mais l'upload échouera
        error_log("Erreur: Impossible de créer le dossier d'upload: " . UPLOAD_DIR_ETUDIANT);
    }
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


if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['inscrire_etudiant'])) {
    $nom = sanitizeInput($_POST['nom']);
    $prenom = sanitizeInput($_POST['prenom']);
    $email = filter_var(sanitizeInput($_POST['email']), FILTER_VALIDATE_EMAIL);
    $mot_de_passe = $_POST['mot_de_passe'];
    $matricule = sanitizeInput($_POST['matricule']);
    $id_classe = filter_input(INPUT_POST, 'id_classe', FILTER_VALIDATE_INT);
    $photo_path = null;

    // Validations
    if (empty($nom) || empty($prenom) || !$email || empty($mot_de_passe) || empty($matricule) || !$id_classe) {
        $_SESSION['message'] = "Tous les champs (sauf photo) sont requis et l'email doit être valide.";
        $_SESSION['message_type'] = "danger";
    } elseif (strlen($mot_de_passe) < 6) {
        $_SESSION['message'] = "Le mot de passe doit comporter au moins 6 caractères.";
        $_SESSION['message_type'] = "danger";
    } else {
        $db->beginTransaction(); // Commencer une transaction
        try {
            // 1. Vérifier si l'email ou le matricule existe déjà
            $stmt_check_email = $database->prepare("SELECT id FROM utilisateurs WHERE email = :email");
            $database->execute($stmt_check_email, [':email' => $email]);
            if ($database->rowCount($stmt_check_email) > 0) {
                throw new Exception("Cet email est déjà utilisé par un autre compte.");
            }

            $stmt_check_matricule = $database->prepare("SELECT id FROM etudiants WHERE matricule = :matricule");
            $database->execute($stmt_check_matricule, [':matricule' => $matricule]);
            if ($database->rowCount($stmt_check_matricule) > 0) {
                throw new Exception("Ce matricule est déjà utilisé.");
            }

            // 2. Gérer l'upload de la photo
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] == UPLOAD_ERR_OK) {
                if (!is_writable(UPLOAD_DIR_ETUDIANT)) {
                     throw new Exception("Le dossier d'upload n'est pas accessible en écriture : " . UPLOAD_DIR_ETUDIANT);
                }
                $tmp_name = $_FILES['photo']['tmp_name'];
                $file_name = time() . '_' . basename($_FILES['photo']['name']);
                $destination = UPLOAD_DIR_ETUDIANT . $file_name;
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                if (in_array($_FILES['photo']['type'], $allowed_types) && $_FILES['photo']['size'] < 2000000) { // Max 2MB
                    if (move_uploaded_file($tmp_name, $destination)) {
                        $photo_path = $file_name;
                    } else {
                        throw new Exception("Erreur lors du déplacement du fichier uploadé.");
                    }
                } else {
                    throw new Exception("Type de fichier non autorisé ou fichier trop volumineux.");
                }
            }

            // 3. Créer l'utilisateur
            $hashed_password = password_hash($mot_de_passe, PASSWORD_DEFAULT);
            $stmt_user = $database->prepare("INSERT INTO utilisateurs (nom, prenom, email, mot_de_passe, role, photo) 
                                           VALUES (:nom, :prenom, :email, :mdp, 'etudiant', :photo)");
            $database->execute($stmt_user, [
                ':nom' => $nom, ':prenom' => $prenom, ':email' => $email, 
                ':mdp' => $hashed_password, ':photo' => $photo_path
            ]);
            $id_utilisateur_cree = $database->lastInsertId();

            // 4. Créer l'entrée étudiant
            $stmt_etudiant = $database->prepare("INSERT INTO etudiants (id_utilisateur, matricule, id_classe) 
                                               VALUES (:id_user, :matricule, :id_classe)");
            $database->execute($stmt_etudiant, [
                ':id_user' => $id_utilisateur_cree, 
                ':matricule' => $matricule, 
                ':id_classe' => $id_classe
            ]);

            $db->commit(); // Valider la transaction
            $_SESSION['message'] = "Étudiant inscrit avec succès! (Utilisateur ID: $id_utilisateur_cree)";
            $_SESSION['message_type'] = "success";

        } catch (Exception $e) {
            $db->rollBack(); // Annuler la transaction en cas d'erreur
            $_SESSION['message'] = "Erreur lors de l'inscription de l'étudiant: " . $e->getMessage();
            $_SESSION['message_type'] = "danger";
            // Supprimer la photo si elle a été uploadée et que la transaction a échoué
            if ($photo_path && file_exists(UPLOAD_DIR_ETUDIANT . $photo_path)) {
                unlink(UPLOAD_DIR_ETUDIANT . $photo_path);
            }
        }
    }
    header("Location: inscrire_etudiant.php"); // Rediriger pour éviter la resoumission
    exit();
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
        /* ... (reste du CSS identique aux fichiers précédents) ... */
        .card-custom{border:1px solid #e5e7eb;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.03);margin-bottom:25px;background-color:#fff}
        .card-custom .card-header{background-color:#f9fafb;color:#1f2937;border-bottom:1px solid #e5e7eb;border-top-left-radius:8px;border-top-right-radius:8px;font-weight:600;padding:.9rem 1.25rem;font-size:1rem}
        .card-custom .card-header i{margin-right:8px;color:#6b7280}
        .card-custom .card-body{padding:1.5rem}
        .btn-custom-primary{background-color:#ef4444;border-color:#ef4444;color:#fff}
        .btn-custom-primary:hover{background-color:#dc2626;border-color:#dc2626}
        .alert-custom-success{background-color:#d1fae5;color:#065f46;border-color:#a7f3d0}
        .alert-custom-danger{background-color:#fee2e2;color:#991b1b;border-color:#fecaca}
        .alert-custom-warning{background-color:#ffedd5;color:#9a3412;border-color:#fed7aa}
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
             <a class="nav-link <?php echo in_array($currentPage, ['gestion_utilisateurs.php', 'inscrire_etudiant.php', 'inscrire_enseignant.php']) ? '' : 'collapsed'; ?>"
               href="#submenuUtilisateurs" data-bs-toggle="collapse" role="button"
               aria-expanded="<?php echo in_array($currentPage, ['gestion_utilisateurs.php', 'inscrire_etudiant.php', 'inscrire_enseignant.php']) ? 'true' : 'false'; ?>" >
                <i class="fas fa-users-cog fa-fw"></i> Gestion Utilisateurs
            </a>
            <div class="collapse <?php echo in_array($currentPage, ['gestion_utilisateurs.php', 'inscrire_etudiant.php', 'inscrire_enseignant.php']) ? 'show' : ''; ?>" id="submenuUtilisateurs">
                <ul class="nav flex-column ps-3">
                    <li class="nav-item"><a class="nav-link <?php echo ($currentPage == 'gestion_utilisateurs.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>dashboard/admin/gestion_utilisateurs.php"><i class="fas fa-list fa-fw"></i> Liste Utilisateurs</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo ($currentPage == 'inscrire_etudiant.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>dashboard/admin/inscrire_etudiant.php"><i class="fas fa-user-graduate fa-fw"></i> Inscrire Étudiant</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo ($currentPage == 'inscrire_enseignant.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>dashboard/admin/inscrire_enseignant.php"><i class="fas fa-chalkboard-user fa-fw"></i> Inscrire Enseignant</a></li>
                </ul>
            </div>
            <!-- ... autres liens de la sidebar ... -->
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
                <div class="card-header"><i class="fas fa-user-plus"></i> Formulaire d'Inscription Étudiant</div>
                <div class="card-body">
                    <form action="inscrire_etudiant.php" method="POST" enctype="multipart/form-data">
                        <h5 class="mb-3">Informations Personnelles</h5>
                        <div class="row">
                            <div class="col-md-6 mb-3"><label for="nom" class="form-label">Nom</label><input type="text" class="form-control" id="nom" name="nom" required></div>
                            <div class="col-md-6 mb-3"><label for="prenom" class="form-label">Prénom</label><input type="text" class="form-control" id="prenom" name="prenom" required></div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3"><label for="email" class="form-label">Email</label><input type="email" class="form-control" id="email" name="email" required></div>
                            <div class="col-md-6 mb-3"><label for="mot_de_passe" class="form-label">Mot de Passe</label><input type="password" class="form-control" id="mot_de_passe" name="mot_de_passe" required><div class="form-text">Minimum 6 caractères.</div></div>
                        </div>
                         <div class="mb-3">
                            <label for="photo" class="form-label">Photo de Profil (Optionnel)</label>
                            <input type="file" class="form-control" id="photo" name="photo" accept="image/jpeg,image/png,image/gif">
                        </div>

                        <hr>
                        <h5 class="mb-3 mt-4">Informations Étudiant</h5>
                         <div class="row">
                            <div class="col-md-6 mb-3"><label for="matricule" class="form-label">Matricule</label><input type="text" class="form-control" id="matricule" name="matricule" required></div>
                            <div class="col-md-6 mb-3"><label for="id_classe" class="form-label">Classe</label><select class="form-select" id="id_classe" name="id_classe" required>
                                <option value="">-- Sélectionner une classe --</option>
                                <?php foreach ($classes_disponibles as $cls): ?>
                                <option value="<?php echo $cls['id']; ?>"><?php echo htmlspecialchars($cls['nom_classe'] . ' (' . $cls['niveau'] . ') - ' . $cls['nom_filiere']); ?></option>
                                <?php endforeach; ?>
                                <?php if(empty($classes_disponibles)): ?><option disabled>Aucune classe disponible. Veuillez en créer une.</option><?php endif; ?>
                            </select></div>
                        </div>
                        
                        <div class="d-flex justify-content-end mt-3">
                            <button type="submit" name="inscrire_etudiant" class="btn btn-custom-primary" <?php if(empty($classes_disponibles)) echo 'disabled'; ?>><i class="fas fa-user-check"></i> Inscrire l'Étudiant</button>
                        </div>
                         <?php if(empty($classes_disponibles)): ?>
                            <p class="text-danger mt-2"><small><i class="fas fa-exclamation-triangle"></i> Aucune classe n'est disponible. Veuillez d'abord <a href="<?php echo BASE_URL; ?>dashboard/admin/gestion_classes.php">créer des classes</a>.</small></p>
                        <?php endif; ?>
                    </form>
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