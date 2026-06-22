<?php
// FICHIER: includes/auth.php
// RÔLE: Gestion centralisée des sessions utilisateur, des rôles et des contrôles d'accès.

// -----------------------------------------------------------------------------
// INITIALISATION DE LA SESSION
// -----------------------------------------------------------------------------
if (session_status() == PHP_SESSION_NONE) {
    $cookieParams = [
        'lifetime' => 0,
        'path' => '/',
        'domain' => $_SERVER['HTTP_HOST'],
        'secure' => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax'
    ];

    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params($cookieParams);
    } else {
        session_set_cookie_params(
            $cookieParams['lifetime'],
            $cookieParams['path'],
            $cookieParams['domain'],
            $cookieParams['secure'],
            $cookieParams['httponly']
        );
    }

    session_start();
}

// -----------------------------------------------------------------------------
// DÉPENDANCES
// -----------------------------------------------------------------------------
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/helpers.php';

// -----------------------------------------------------------------------------
// CONSTANTES DE CONFIGURATION
// -----------------------------------------------------------------------------
define('SESSION_TIMEOUT_DURATION', 1800); // 30 minutes

// -----------------------------------------------------------------------------
// FONCTIONS DE SESSION
// -----------------------------------------------------------------------------
function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function get_user_id() {
    return $_SESSION['user_id'] ?? null;
}

function get_user_role() {
    return $_SESSION['user_role'] ?? null;
}

function get_user_name() {
    if (isset($_SESSION['user_prenom']) && isset($_SESSION['user_nom'])) {
        return sanitizeInput($_SESSION['user_prenom'] . ' ' . $_SESSION['user_nom']);
    }
    return null;
}

function get_user_photo() {
    $base_url = get_base_url();
    $defaultPhoto = $base_url . 'assets/images/default-profile.png';

    if (!empty($_SESSION['user_photo'])) {
        return $base_url . 'uploads/profiles/' . sanitizeInput($_SESSION['user_photo']);
    }

    return $defaultPhoto;
}

// -----------------------------------------------------------------------------
// CONTRÔLE D'ACCÈS
// -----------------------------------------------------------------------------
function require_login($allowed_roles = []) {
    $base_url = get_base_url();

    if (!is_logged_in()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        $_SESSION['error_message'] = "Vous devez être connecté pour accéder à cette page.";
        header('Location: ' . $base_url . 'index.php');
        exit();
    }

    check_session_timeout();

    if (!empty($allowed_roles)) {
        $user_role = get_user_role();
        if (!$user_role || !in_array($user_role, $allowed_roles)) {
            $_SESSION['error_message'] = "Accès refusé. Vous n'avez pas les permissions nécessaires.";

            $role_dashboard_map = [
                'admin' => 'dashboard/admin/index.php',
                'etudiant' => 'dashboard/etudiant/index.php',
                'enseignant' => 'dashboard/enseignant/index.php'
            ];

            $dashboard_path = $base_url . ($role_dashboard_map[$user_role] ?? 'error_access_denied.php');
            header('Location: ' . $dashboard_path);
            exit();
        }
    }
}

// -----------------------------------------------------------------------------
// CONNEXION / DÉCONNEXION
// -----------------------------------------------------------------------------
function establish_session($user_data) {
    session_regenerate_id(true);

    $_SESSION['user_id'] = $user_data['id'];
    $_SESSION['user_nom'] = $user_data['nom'];
    $_SESSION['user_prenom'] = $user_data['prenom'];
    $_SESSION['user_email'] = $user_data['email'];
    $_SESSION['user_role'] = $user_data['role'];
    $_SESSION['user_photo'] = $user_data['photo'];
    $_SESSION['last_activity'] = time();

    unset($_SESSION['redirect_after_login']);
}

function logout_user($redirect_url = null) {
    $_SESSION = [];

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    session_destroy();

    $base_url = get_base_url();
    $final_redirect_url = $redirect_url ?? $base_url . 'index.php';
    header('Location: ' . $final_redirect_url);
    exit();
}

// -----------------------------------------------------------------------------
// TIMEOUT DE SESSION
// -----------------------------------------------------------------------------
function check_session_timeout() {
    if (defined('SESSION_TIMEOUT_DURATION') && SESSION_TIMEOUT_DURATION > 0) {
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT_DURATION) {
            $_SESSION['error_message'] = "Votre session a expiré pour cause d'inactivité.";
            logout_user(get_base_url() . 'index.php?timeout=1');
        } else {
            $_SESSION['last_activity'] = time();
        }
    }
}



function get_student_info($conn, $student_id) {
    $stmt = $conn->prepare("SELECT * FROM etudiants WHERE id = ?");
    $stmt->execute([$student_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
?>



