<?php
// Fonctions utilitaires (formatage, dates, etc.)

if (!function_exists('sanitizeInput')) {
    /**
     * Sécurise une chaîne de caractères pour éviter les injections XSS simples.
     * @param string $data La donnée à nettoyer.
     * @return string La donnée nettoyée.
     */
    function sanitizeInput($data) {
        $data = trim($data);
        $data = stripslashes($data); // Attention: stripslashes peut poser problème si les magic_quotes sont désactivées (ce qui est le cas par défaut et recommandé)
                                     // Il vaut mieux ne pas l'utiliser sans vérifier la configuration de magic_quotes_gpc, ou simplement se fier à htmlspecialchars.
        $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
        return $data;
    }
}

if (!function_exists('format_date')) {
    /**
     * Formate une date.
     * @param string $dateString La chaîne de date (ex: YYYY-MM-DD HH:MM:SS).
     * @param string $format Le format de sortie souhaité (par défaut 'd/m/Y H:i').
     * @return string La date formatée ou la chaîne originale si la date est invalide.
     */
    function format_date($dateString, $format = 'd/m/Y H:i') {
        if (empty($dateString) || $dateString === '0000-00-00 00:00:00' || $dateString === '0000-00-00') {
            return 'N/A';
        }
        try {
            $date = new DateTime($dateString);
            return $date->format($format);
        } catch (Exception $e) {
            return $dateString; // Retourne la chaîne originale en cas d'erreur
        }
    }
}

if (!function_exists('get_user_role_name')) {
    /**
     * Retourne le nom lisible du rôle.
     * @param string|null $roleKey La clé du rôle (ex: 'admin', 'etudiant').
     * @return string Le nom lisible du rôle.
     */
    function get_user_role_name($roleKey) {
        $roles = [
            'admin' => 'Administrateur',
            'etudiant' => 'Étudiant',
            'enseignant' => 'Enseignant'
        ];
        return isset($roles[$roleKey]) ? $roles[$roleKey] : ucfirst((string) $roleKey);
    }
}


if (!function_exists('is_active_page')) {
    /**
     * Vérifie si la page actuelle correspond à la page donnée pour marquer un lien comme actif.
     * @param string $page_name Le nom du script PHP de la page (ex: 'index.php').
     * @return string 'active' si la page correspond, sinon une chaîne vide.
     */
    function is_active_page($page_name) {
        return basename($_SERVER['PHP_SELF']) == $page_name ? 'active' : '';
    }
}

if (!function_exists('generate_alert')) {
    /**
     * Génère un message d'alerte stylisé.
     * @param string $message Le message à afficher.
     * @param string $type Le type d'alerte ('success', 'error', 'info', 'warning').
     * @return string Le HTML de l'alerte.
     */
    function generate_alert($message, $type = 'info') {
        $alertClass = '';
        switch ($type) {
            case 'success':
                $alertClass = 'alert-success';
                break;
            case 'error':
                $alertClass = 'alert-error';
                break;
            case 'warning':
                $alertClass = 'alert-warning';
                break;
            default:
                $alertClass = 'alert-info';
                break;
        }
        return "<div class='alert {$alertClass}'>" . sanitizeInput($message) . "</div>";
    }
}

if (!function_exists('get_base_url')) {
    /**
     * Récupère l'URL de base du site.
     * Utile pour les liens absolus.
     * @return string L'URL de base.
     */
    function get_base_url() {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'];
        // Supposons que le projet est à la racine du dossier 'gestion_school'
        // Si votre projet est dans un sous-dossier de 'gestion_school', ajustez SCRIPT_NAME
        // Exemple: /gestion_school/projet_ici/index.php -> dirname(dirname($_SERVER['SCRIPT_NAME']))
        // Pour /gestion_school/index.php -> dirname($_SERVER['SCRIPT_NAME'])
        $script_path = dirname($_SERVER['SCRIPT_NAME']); 
        // Si SCRIPT_NAME est /index.php (à la racine du domaine), dirname retourne '/'
        // Si SCRIPT_NAME est /gestion_school/index.php, dirname retourne '/gestion_school'
        // On veut s'assurer qu'il n'y a pas de double slash et qu'il y a un slash à la fin
        $base_path = rtrim($script_path, '/');
        if ($base_path === '' || $base_path === '\\') { // Peut arriver si à la racine du domaine
            $base_path = '';
        }

        return $protocol . $host . $base_path . '/';
    }
}
?>