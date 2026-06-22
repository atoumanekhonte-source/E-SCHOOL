<?php
// Configuration globale de l'application

// Constantes de base
define('SITE_NAME', 'Gestion Scolaire');
define('SITE_URL', 'http://localhost/gestion_school/');
define('SITE_EMAIL', 'contact@gestion-scolaire.com');
define('ADMIN_EMAIL', 'admin@gestion-scolaire.com');

// Paramètres de session
define('SESSION_TIMEOUT', 1800); // 30 minutes d'inactivité avant déconnexion
define('SESSION_REGENERATE', 300); // Régénération de l'ID de session toutes les 5 minutes

// Chemins des dossiers
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('COURS_PATH', UPLOAD_PATH . 'cours/');
define('VIDEOS_PATH', UPLOAD_PATH . 'videos/');
define('DEVOIRS_PATH', UPLOAD_PATH . 'devoirs/');

// Tailles maximales des fichiers (en octets)
define('MAX_FILE_SIZE', 10485760); // 10MB
define('MAX_VIDEO_SIZE', 524288000); // 500MB

// Paramètres de sécurité
define('PASSWORD_MIN_LENGTH', 8);
define('PASSWORD_REQUIRE_UPPERCASE', true);
define('PASSWORD_REQUIRE_NUMBER', true);
define('PASSWORD_REQUIRE_SPECIAL_CHAR', true);

// Types de fichiers autorisés
$ALLOWED_FILE_TYPES = [
    'pdf' => 'application/pdf',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xls' => 'application/vnd.ms-excel',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'ppt' => 'application/vnd.ms-powerpoint',
    'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'mp4' => 'video/mp4',
    'webm' => 'video/webm',
    'ogg' => 'video/ogg',
    'mp3' => 'audio/mpeg',
    'wav' => 'audio/wav',
    'zip' => 'application/zip',
    'rar' => 'application/x-rar-compressed'
];

// Paramètres des rôles
define('ROLE_ADMIN', 'admin');
define('ROLE_TEACHER', 'enseignant');
define('ROLE_STUDENT', 'etudiant');

// Inclusion du fichier de connexion à la base de données
require_once 'db.php';



// Vérifier et gérer le timeout de session
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > SESSION_TIMEOUT)) {
    // Dernière activité il y a plus de 30 minutes
    session_unset();
    session_destroy();
    header('Location: ../index.php?timeout=1');
    exit();
}
$_SESSION['LAST_ACTIVITY'] = time(); // Mise à jour du timestamp

// Régénération périodique de l'ID de session
if (!isset($_SESSION['CREATED'])) {
    $_SESSION['CREATED'] = time();
} elseif (time() - $_SESSION['CREATED'] > SESSION_REGENERATE) {
    session_regenerate_id(true);
    $_SESSION['CREATED'] = time();
}

// Fonction pour vérifier les permissions
function checkPermission($requiredRole) {
    if (!isset($_SESSION['user_role'])) {
        header('Location: ../index.php?error=unauthorized');
        exit();
    }
    
    $hierarchy = [ROLE_STUDENT => 1, ROLE_TEACHER => 2, ROLE_ADMIN => 3];
    
    if ($hierarchy[$_SESSION['user_role']] < $hierarchy[$requiredRole]) {
        header('Location: ../dashboard/' . $_SESSION['user_role'] . '/index.php?error=permission_denied');
        exit();
    }
}

// Fonction pour générer un mot de passe aléatoire
function generateRandomPassword($length = 10) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_-=+;:,.?';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $password;
}

// Fonction pour formater la date
function formatDate($date, $format = 'd/m/Y H:i') {
    $dateTime = new DateTime($date);
    return $dateTime->format($format);
}
?>