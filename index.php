<?php
// Démarrer la session si ce n'est pas déjà fait
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/config.php'; // Contient les constantes, les chemins, et la gestion de session
require_once 'config/db.php';     // Connexion à la base de données

// Fonction de nettoyage simple si sanitizeInput n'est pas définie
if (!function_exists('sanitizeInput')) {
    function sanitizeInput($data) {
        return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }
}

// Connexion à la base de données
$database = new Database();
$db = $database->connect();

// Initialisation des variables
$error_message = '';
$success_message = '';
$email_value = '';

// Traitement du formulaire de connexion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $email_value = $email;

    if (empty($email) || empty($password)) {
        $error_message = 'Veuillez remplir tous les champs.';
    } else {
        try {
            $stmt = $db->prepare("SELECT id, nom, prenom, email, mot_de_passe, role FROM utilisateurs WHERE email = :email");
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['mot_de_passe'])) {
                // Authentification réussie
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_nom'] = $user['nom'];
                $_SESSION['user_prenom'] = $user['prenom'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['LAST_ACTIVITY'] = time();
                $_SESSION['CREATED'] = time();

                // Redirection vers le tableau de bord selon le rôle
                header('Location: dashboard/' . $user['role'] . '/index.php');
                exit();
            } else {
                $error_message = 'Adresse e-mail ou mot de passe incorrect.';
            }
        } catch (PDOException $e) {
            error_log('Erreur de connexion : ' . $e->getMessage());
            $error_message = 'Une erreur est survenue lors de la connexion. Veuillez réessayer.';
        }
    }
}

// Gestion des messages GET
if (isset($_GET['logout']) && $_GET['logout'] == '1') {
    $error_message = 'Vous avez été déconnecté avec succès.';
}
if (isset($_GET['timeout']) && $_GET['timeout'] == '1') {
    $error_message = 'Votre session a expiré pour cause d\'inactivité.';
}
if (isset($_GET['error']) && $_GET['error'] == 'unauthorized') {
    $error_message = 'Vous devez être connecté pour accéder à cette page.';
}
if (isset($_GET['reset']) && $_GET['reset'] == 'success') {
    $success_message = 'Votre mot de passe a été réinitialisé. Vous pouvez maintenant vous connecter.';
}
if (isset($_GET['reset']) && $_GET['reset'] == 'link_sent') {
    $success_message = 'Un lien de réinitialisation de mot de passe a été envoyé à votre adresse e-mail.';
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - JÀNGLITECH</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap');

        :root {
            --brand-gold: #f59e0b;
            --brand-blue: #3b82f6;
            --dark-bg-start: #0f2027;
            --dark-bg-mid: #203a43;
            --dark-bg-end: #2c5364;
            --input-bg: #1e293b;
            --input-border: #334155;
            --text-light: #e2e8f0;
            --text-muted: #cbd5e1;
            --text-icon: #94a3b8;
        }

        body {
            margin: 0;
            font-family: 'Roboto', sans-serif;
            background: linear-gradient(135deg, var(--dark-bg-start), var(--dark-bg-mid), var(--dark-bg-end));
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            color: #fff;
            overflow-x: hidden; /* Empêche le défilement horizontal causé par les particules */
            position: relative;
            padding: 20px; /* Ajoute un peu d'espace pour les petits écrans */
            box-sizing: border-box;
        }

        /* Particules brillantes améliorées */
        .particles-background {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            overflow: hidden; /* Assure que les particules ne débordent pas et ne causent pas de scrollbars */
        }

        .particle {
            position: absolute;
            border-radius: 50%;
            background-color: rgba(255, 215, 0, 0.5); /* Or brillant */
            opacity: 0;
            animation: sparkle 10s linear infinite;
            box-shadow: 0 0 5px rgba(255, 215, 0, 0.7), 0 0 10px rgba(255, 215, 0, 0.5);
        }

        .particle.blue {
            background-color: rgba(59, 130, 246, 0.4); /* Bleu brillant */
            box-shadow: 0 0 5px rgba(59, 130, 246, 0.6), 0 0 10px rgba(59, 130, 246, 0.4);
        }


        @keyframes sparkle {
            0%, 100% { opacity: 0; transform: translateY(0) scale(0.5); }
            20% { opacity: 0.8; }
            50% { opacity: 1; transform: translateY(-30px) scale(1.2); }
            80% { opacity: 0.8; }
        }

        /* Génération dynamique des particules via JS ci-dessous */


        .login-container {
            background-color: rgba(1, 1, 25, 0.88);
            padding: 30px; /* Un peu moins de padding pour les petits écrans */
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4);
            width: 100%;
            max-width: 400px; /* Légèrement réduit pour mieux s'adapter */
            text-align: center;
            border: 1px solid rgba(var(--brand-blue), 0.3);
            z-index: 1;
            position: relative; /* Pour être au-dessus des particules */
        }

        .logo-area {
            margin-bottom: 20px;
        }

        .site-logo {
            max-width: 180px; /* Ajustez selon la taille de votre logo */
            height: auto;
            margin-bottom: 10px;
        }

        .brand-name {
            font-size: clamp(28px, 7vw, 36px); /* Responsive font size */
            font-weight: 700;
            color: var(--brand-gold);
            letter-spacing: 1px;
            margin-bottom: 5px;
        }

        .slogan {
            font-size: clamp(12px, 3.5vw, 14px); /* Responsive font size */
            color: var(--text-muted);
            margin-bottom: 25px;
        }

        .input-group {
            position: relative;
            margin-bottom: 18px;
        }

        .input-group .icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-icon);
            font-size: 18px; /* Maintenir la taille de l'icône */
        }

        .input-field {
            width: 100%;
            padding: 12px 12px 12px 45px; /* Ajustement du padding */
            border: 1px solid var(--input-border);
            border-radius: 8px;
            background-color: var(--input-bg);
            color: var(--text-light);
            font-size: 16px; /* Maintenir la taille de la police */
            box-sizing: border-box;
            transition: border-color 0.3s, box-shadow 0.3s;
        }

        .input-field::placeholder {
            color: var(--text-icon);
            opacity: 0.8;
        }

        .input-field:focus {
            outline: none;
            border-color: var(--brand-blue);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.3);
        }

        .login-button {
            width: 100%;
            padding: 12px;
            background-color: var(--brand-gold);
            border: none;
            border-radius: 8px;
            color: var(--input-bg);
            font-size: 16px; /* Maintenir la taille de la police */
            font-weight: 700;
            cursor: pointer;
            transition: background-color 0.3s, transform 0.1s;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .login-button:hover {
            background-color: #facc15; /* Or plus clair */
        }
        .login-button:active {
            transform: translateY(1px);
        }

        .forgot-password {
            display: block;
            margin-top: 18px;
            color: var(--brand-blue);
            text-decoration: none;
            font-size: 14px; /* Maintenir la taille de la police */
            transition: color 0.3s;
        }

        .forgot-password:hover {
            color: #93c5fd; /* Bleu plus clair au survol */
            text-decoration: underline;
        }

        .message {
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px; /* Maintenir la taille de la police */
            text-align: left;
        }
        .error-message {
            background-color: rgba(239, 68, 68, 0.1);
            color: #f87171;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }
        .success-message {
            background-color: rgba(16, 185, 129, 0.1);
            color: #34d399;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        /* Media query pour ajustements très fins si nécessaire sur petits écrans */
        @media (max-width: 360px) {
            .login-container {
                padding: 20px;
            }
            .brand-name {
                font-size: 24px;
            }
            .slogan {
                font-size: 12px;
            }
        }

    </style>
</head>
<body>
    <div class="particles-background" id="particles-js">
        <!-- Les particules seront générées ici par JavaScript -->
    </div>

    <div class="login-container">
        <div class="logo-area">
            <!-- Assurez-vous que le chemin vers votre logo est correct -->
            <!-- Exemple: <img src="assets/images/logo.png" alt="Logo JÀNGLITECH" class="site-logo"> -->
            <!-- J'utilise un placeholder pour l'instant, REMPLACEZ-LE par votre balise img -->
            <img src="<?php echo SITE_URL; ?>assets/images/logo.png" alt="Logo JÀNGLITECH" class="site-logo">
            <div class="brand-name">JÀNGLITECH</div>
        </div>
        <p class="slogan">Façonne ton avenir grâce à l'éducation en ligne.</p>
        <!-- Le slogan est gardé en anglais comme sur votre image originale. Changez-le si besoin. -->
        <!-- Ex: <p class="slogan">Façonnez votre avenir avec l'éducation en ligne</p> -->


        <?php if (!empty($error_message)): ?>
            <div class="message error-message"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
        <?php if (!empty($success_message)): ?>
            <div class="message success-message"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>

        <form method="POST" action="<?php echo SITE_URL; ?>/index.php">
            <div class="input-group">
                <span class="icon">✉️</span>
                <input type="email" name="email" class="input-field" placeholder="Adresse e-mail" value="<?php echo htmlspecialchars($email_value); ?>" required>
            </div>
            <div class="input-group">
                <span class="icon">🔒</span>
                <input type="password" name="password" class="input-field" placeholder="Mot de passe" required>
            </div>
            <button type="submit" class="login-button">Se connecter</button>
        </form>
        <a href="<?php echo SITE_URL; ?>/forgot_password.php" class="forgot-password">Mot de passe oublié ?</a>
    </div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const particlesContainer = document.getElementById('particles-js');
    if (!particlesContainer) return;
    const numberOfParticles = 50; // Augmentez pour plus de densité

    for (let i = 0; i < numberOfParticles; i++) {
        let particle = document.createElement('div');
        particle.classList.add('particle');

        // Position aléatoire
        particle.style.top = Math.random() * 100 + '%';
        particle.style.left = Math.random() * 100 + '%';

        // Taille aléatoire
        const size = Math.random() * 4 + 1; // Particules entre 1px et 5px
        particle.style.width = size + 'px';
        particle.style.height = size + 'px';

        // Délai d'animation aléatoire pour un effet plus naturel
        particle.style.animationDelay = Math.random() * 10 + 's';
        particle.style.animationDuration = Math.random() * 5 + 5 + 's'; // Durée entre 5s et 10s

        // Alterner les couleurs (environ 30% bleues)
        if (Math.random() < 0.3) {
            particle.classList.add('blue');
        }
        
        particlesContainer.appendChild(particle);
    }
});
</script>
</body>
</html>