<?php
session_start(); // Optionnel, mais peut être utile si vous voulez afficher des infos utilisateur

// Configuration des erreurs
ini_set('display_errors', 1); ini_set('display_startup_errors', 1); error_reporting(E_ALL);

// --- Définition de BASE_URL ---
// Si ce fichier est à la racine de gestion_school/
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
// SCRIPT_NAME sera /gestion_school/annonce_detail.php
// dirname($_SERVER['SCRIPT_NAME']) sera /gestion_school
define('BASE_URL', rtrim($protocol . $host . dirname($_SERVER['SCRIPT_NAME']), '/') . '/');


// --- Classe Database (intégrée) ---
class Database {
    private $host = 'localhost'; private $db_name = 'gestion_school'; private $username = 'root'; private $password = ''; private $conn;
    public function connect() { $this->conn = null; try { $this->conn = new PDO('mysql:host=' . $this->host . ';dbname=' . $this->db_name, $this->username, $this->password); $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); $this->conn->exec('SET NAMES utf8'); } catch(PDOException $e) { error_log('Erreur connexion: ' . $e->getMessage()); die('Erreur connexion DB. Message: ' . $e->getMessage()); } return $this->conn; }
    public function prepare($sql) { if (!$this->conn) $this->connect(); return $this->conn->prepare($sql); }
    public function execute($stmt, $params = []) { try { return $stmt->execute($params); } catch(PDOException $e) { error_log('Erreur SQL: ' . $e->getMessage() . ' | Query: ' . $stmt->queryString); return false; } }
    public function fetchAll($stmt) { return $stmt->fetchAll(PDO::FETCH_ASSOC); }
    public function fetch($stmt) { return $stmt->fetch(PDO::FETCH_ASSOC); }
    // lastInsertId et rowCount ne sont pas nécessaires pour cette page simple
}

// --- Fonctions Utilitaires (intégrées) ---
function sanitizeInput($data, $allow_html = false) {
    if (is_array($data)) return array_map(function($item) use ($allow_html) { return sanitizeInput($item, $allow_html); }, $data);
    if ($data === null) return null;
    if (!is_string($data) && !is_numeric($data) && !is_bool($data)) $data = (string) $data;
    if (is_string($data)) {
        $data = trim($data);
        if (!$allow_html) {
            $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
        } else {
            // Pour du HTML, une purification plus robuste est nécessaire en production.
            // Ici, nous permettons certaines balises de base pour l'affichage.
            // Ceci est une simplification. HTMLPurifier est recommandé pour la sécurité.
            // $data = strip_tags($data, '<p><br><b><i><u><strong><em><ul><ol><li><a><img><h1><h2><h3><h4><h5><h6><blockquote><code><pre>');
            // Pour un affichage simple, nl2br peut être utile si vous avez traité le contenu comme du texte simple
        }
    }
    return $data;
}
function getCurrentPage() { return basename($_SERVER['SCRIPT_FILENAME']); }

// --- Connexion DB ---
$database = new Database();
$db = $database->connect();

$pageTitle = "Détail de l'Annonce";
$annonce = null;
$message_erreur = '';

// Récupérer l'ID de l'annonce
$annonce_id = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : null;

if (!$annonce_id) {
    $message_erreur = "ID d'annonce invalide ou manquant.";
} else {
    try {
        $sql = "SELECT n.titre, n.contenu, n.date_creation, 
                       u.nom as auteur_nom, u.prenom as auteur_prenom, u.photo as auteur_photo
                FROM notifications n
                LEFT JOIN utilisateurs u ON n.id_auteur = u.id
                WHERE n.id = :id_annonce AND n.visible = 1"; // On ne montre que les annonces visibles
        
        $stmt = $database->prepare($sql);
        $database->execute($stmt, [':id_annonce' => $annonce_id]);
        $annonce = $database->fetch($stmt);

        if (!$annonce) {
            $message_erreur = "Annonce non trouvée ou non visible.";
        }
    } catch (PDOException $e) {
        $message_erreur = "Erreur de base de données lors de la récupération de l'annonce.";
        error_log("Erreur DB (annonce_detail): " . $e->getMessage());
    }
}

// Utiliser le même chemin pour l'avatar par défaut que dans les pages admin
$userImageUrl = BASE_URL . 'assets/images/default_avatar.png';
$auteurAvatarUrl = $userImageUrl; // Avatar par défaut pour l'auteur
if ($annonce && !empty($annonce['auteur_photo'])) {
    // Assurez-vous que le chemin vers les avatars des utilisateurs est correct
    // Si UPLOAD_URL_DIR_ENSEIGNANT (ou similaire) est défini dans un config global, utilisez-le.
    // Sinon, construisez le chemin.
    $auteurAvatarUrl = BASE_URL . 'assets/uploads/avatars/' . htmlspecialchars($annonce['auteur_photo']);
} elseif ($annonce && ($annonce['auteur_nom'] || $annonce['auteur_prenom'])) {
    // Pas de photo, mais un nom -> on garde l'avatar par défaut
} else {
    // Pas d'auteur identifiable, ou auteur "Système"
}


?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $annonce ? htmlspecialchars($annonce['titre']) : htmlspecialchars($pageTitle); ?> - Gestion École</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Style simplifié pour une page publique/détail */
        body { font-family: 'Segoe UI', sans-serif; background-color: #f0f2f5; color: #333; line-height: 1.6; }
        .navbar-custom { background-color: #1e293b; } /* Bleu nuit comme la sidebar admin */
        .navbar-custom .navbar-brand, .navbar-custom .nav-link { color: #f8fafc; }
        .navbar-custom .nav-link:hover { color: #cbd5e1; }
        .page-container { margin-top: 20px; margin-bottom: 20px; }
        .annonce-card { background-color: #fff; border: 1px solid #e5e7eb; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.07); }
        .annonce-header { padding: 1.5rem; border-bottom: 1px solid #e5e7eb; }
        .annonce-title { font-size: 2rem; font-weight: 600; color: #111827; margin-bottom: 0.5rem; }
        .annonce-meta { font-size: 0.85rem; color: #6b7280; }
        .annonce-meta .auteur-avatar { width: 30px; height: 30px; border-radius: 50%; margin-right: 8px; object-fit: cover; vertical-align: middle; }
        .annonce-contenu { padding: 1.5rem; font-size: 1.05rem; }
        .annonce-contenu img { max-width: 100%; height: auto; border-radius: 4px; margin-top: 10px; margin-bottom: 10px; }
        .btn-retour { background-color: #6b7280; border-color: #6b7280; color: white; }
        .btn-retour:hover { background-color: #4b5563; border-color: #4b5563; }
        .footer-custom { background-color: #f8f9fa; padding: 1.5rem 0; text-align: center; border-top: 1px solid #e5e7eb; font-size: 0.9rem; color: #6b7280;}
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-custom">
        <div class="container">
            <a class="navbar-brand" href="<?php echo BASE_URL; ?>"><i class="fas fa-school me-2"></i>Gestion École</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNavPublic" aria-controls="navbarNavPublic" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon" style="filter: invert(1) grayscale(100%) brightness(200%);"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNavPublic">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo BASE_URL; ?>#annonces">Retour aux Annonces</a> <!-- Exemple de lien, à adapter -->
                    </li>
                    <?php if(isset($_SESSION['user_id'])): // Si l'utilisateur est connecté ?>
                         <li class="nav-item"><a class="nav-link" href="<?php echo BASE_URL; ?>dashboard.php">Tableau de Bord</a></li>
                    <?php else: ?>
                         <li class="nav-item"><a class="nav-link" href="<?php echo BASE_URL; ?>login.php">Connexion</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container page-container">
        <?php if (!empty($message_erreur)): ?>
            <div class="alert alert-danger text-center" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($message_erreur); ?>
            </div>
        <?php elseif ($annonce): ?>
            <div class="annonce-card">
                <div class="annonce-header">
                    <h1 class="annonce-title"><?php echo htmlspecialchars($annonce['titre']); ?></h1>
                    <p class="annonce-meta">
                        <i class="fas fa-calendar-alt me-1"></i> Publié le: <?php echo date('d F Y à H:i', strtotime($annonce['date_creation'])); ?>
                        <?php if ($annonce['auteur_nom'] || $annonce['auteur_prenom']): ?>
                            <span class="mx-2">|</span>
                            <img src="<?php echo $auteurAvatarUrl; ?>" alt="Auteur" class="auteur-avatar">
                            Par: <?php echo htmlspecialchars(trim($annonce['auteur_prenom'] . ' ' . $annonce['auteur_nom'])); ?>
                        <?php else: ?>
                             <span class="mx-2">|</span> <i class="fas fa-user-cog me-1"></i> Par: Système
                        <?php endif; ?>
                    </p>
                </div>
                <div class="annonce-contenu">
                    <?php
                        // Afficher le contenu. Si vous avez décidé de stocker du HTML simple et sûr :
                        // echo $annonce['contenu']; // Attention : Risque XSS si le HTML n'est pas purifié à l'insertion !
                        // Pour du texte simple avec des sauts de ligne :
                        echo nl2br(htmlspecialchars($annonce['contenu'])); 
                    ?>
                </div>
                <div class="card-footer bg-light text-center">
                     <a href="<?php echo BASE_URL; ?>" class="btn btn-retour"><i class="fas fa-arrow-left me-2"></i>Retour à l'accueil</a>
                </div>
            </div>
        <?php else: // Cas où $annonce est null mais pas d'erreur explicite (devrait être couvert par $message_erreur) ?>
             <div class="alert alert-warning text-center" role="alert">
                L'annonce demandée n'a pu être chargée.
            </div>
        <?php endif; ?>
    </div>

    <footer class="footer-custom">
        <p class="mb-0">© <?php echo date("Y"); ?> Gestion École. Tous droits réservés.</p>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>