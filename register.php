<?php
require_once 'config/config.php';
require_once 'config/db.php';

$database = new Database();
$db = $database->connect();

$success_message = '';
$error_message = '';

$nom_value = '';
$prenom_value = '';
$email_value = '';
$role_value = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = sanitizeInput($_POST['nom']);
    $prenom = sanitizeInput($_POST['prenom']);
    $email = sanitizeInput($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = isset($_POST['role']) ? sanitizeInput($_POST['role']) : '';

    $nom_value = $nom;
    $prenom_value = $prenom;
    $email_value = $email;
    $role_value = $role;

    // Gestion de la photo
    $photo_path = null;
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $photo_tmp_name = $_FILES['photo']['tmp_name'];
        $photo_name = basename($_FILES['photo']['name']);
        $photo_ext = strtolower(pathinfo($photo_name, PATHINFO_EXTENSION));
        $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        if (in_array($photo_ext, $allowed_exts)) {
            $photo_new_name = uniqid('photo_') . '.' . $photo_ext;
            $upload_dir = __DIR__ . '/uploads/profiles/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            $photo_path = 'uploads/profiles/' . $photo_new_name;
            move_uploaded_file($photo_tmp_name, $upload_dir . $photo_new_name);
        } else {
            $error_message = 'Format de photo non autorisé. Veuillez choisir une image.';
        }
    }

    if (empty($nom) || empty($prenom) || empty($email) || empty($password) || empty($confirm_password) || empty($role)) {
        $error_message = 'Tous les champs sont obligatoires.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Format d\'adresse e-mail invalide.';
    } elseif ($password !== $confirm_password) {
        $error_message = 'Les mots de passe ne correspondent pas.';
    } elseif (strlen($password) < PASSWORD_MIN_LENGTH) {
        $error_message = 'Le mot de passe doit contenir au moins ' . PASSWORD_MIN_LENGTH . ' caractères.';
    } else {
        $password_valid = true;

        if (defined('PASSWORD_REQUIRE_UPPERCASE') && PASSWORD_REQUIRE_UPPERCASE && !preg_match('/[A-Z]/', $password)) {
            $error_message = 'Le mot de passe doit contenir au moins une lettre majuscule.';
            $password_valid = false;
        }
        if (defined('PASSWORD_REQUIRE_NUMBER') && PASSWORD_REQUIRE_NUMBER && !preg_match('/[0-9]/', $password) && $password_valid) {
            $error_message = 'Le mot de passe doit contenir au moins un chiffre.';
            $password_valid = false;
        }
        if (defined('PASSWORD_REQUIRE_SPECIAL_CHAR') && PASSWORD_REQUIRE_SPECIAL_CHAR && !preg_match('/[^A-Za-z0-9]/', $password) && $password_valid) {
            $error_message = 'Le mot de passe doit contenir au moins un caractère spécial.';
            $password_valid = false;
        }

        if ($password_valid && !$error_message) {
            try {
                $stmt_check_email = $db->prepare("SELECT id FROM utilisateurs WHERE email = :email");
                $stmt_check_email->bindParam(':email', $email);
                $stmt_check_email->execute();

                if ($stmt_check_email->fetch()) {
                    $error_message = 'Cette adresse e-mail est déjà utilisée.';
                } else {
                    $hashed_password = password_hash($password, PASSWORD_BCRYPT);

                    $insert_stmt = $db->prepare("INSERT INTO utilisateurs (nom, prenom, email, mot_de_passe, role, photo, date_inscription) 
                        VALUES (:nom, :prenom, :email, :mot_de_passe, :role, :photo, NOW())");

                    $insert_stmt->bindParam(':nom', $nom);
                    $insert_stmt->bindParam(':prenom', $prenom);
                    $insert_stmt->bindParam(':email', $email);
                    $insert_stmt->bindParam(':mot_de_passe', $hashed_password);
                    $insert_stmt->bindParam(':role', $role);
                    $insert_stmt->bindParam(':photo', $photo_path);

                    if ($insert_stmt->execute()) {
                        $user_id = $db->lastInsertId();

                        if ($role === ROLE_STUDENT) {
                            $matricule = 'ETU' . date('Y') . str_pad($user_id, 4, '0', STR_PAD_LEFT);
                            $stmt_etu = $db->prepare("INSERT INTO etudiants (id_utilisateur, matricule) VALUES (:id_utilisateur, :matricule)");
                            $stmt_etu->bindParam(':id_utilisateur', $user_id);
                            $stmt_etu->bindParam(':matricule', $matricule);
                            $stmt_etu->execute();
                        } elseif ($role === ROLE_TEACHER) {
                            $stmt_ens = $db->prepare("INSERT INTO enseignants (id_utilisateur) VALUES (:id_utilisateur)");
                            $stmt_ens->bindParam(':id_utilisateur', $user_id);
                            $stmt_ens->execute();
                        }

                        $success_message = "Compte pour $prenom $nom créé avec succès.";
                        $nom_value = '';
                        $prenom_value = '';
                        $email_value = '';
                        $role_value = '';
                    } else {
                        $error_message = "Erreur lors de la création du compte.";
                    }
                }
            } catch (PDOException $e) {
                error_log('Registration Error: ' . $e->getMessage());
                $error_message = 'Une erreur est survenue. ' . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Créer un compte - <?php echo SITE_NAME; ?></title>
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
            --label-color: #a0aec0; /* Couleur pour les labels */
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
            overflow-x: hidden;
            position: relative;
            padding: 20px;
            box-sizing: border-box;
        }

        .particles-background {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            overflow: hidden;
        }

        .particle {
            position: absolute;
            border-radius: 50%;
            background-color: rgba(255, 215, 0, 0.5);
            opacity: 0;
            animation: sparkle 10s linear infinite;
            box-shadow: 0 0 5px rgba(255, 215, 0, 0.7), 0 0 10px rgba(255, 215, 0, 0.5);
        }

        .particle.blue {
            background-color: rgba(59, 130, 246, 0.4);
            box-shadow: 0 0 5px rgba(59, 130, 246, 0.6), 0 0 10px rgba(59, 130, 246, 0.4);
        }

        @keyframes sparkle {
            0%, 100% { opacity: 0; transform: translateY(0) scale(0.5); }
            20% { opacity: 0.8; }
            50% { opacity: 1; transform: translateY(-30px) scale(1.2); }
            80% { opacity: 0.8; }
        }

        .register-container { /* Changé de login-container */
            background-color: rgba(1, 1, 25, 0.88);
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4);
            width: 100%;
            max-width: 480px; /* Un peu plus large pour plus de champs */
            text-align: center;
            border: 1px solid rgba(var(--brand-blue), 0.3);
            z-index: 1;
            position: relative;
        }

        .logo-area {
            margin-bottom: 15px; /* Réduit pour plus d'espace pour le titre du formulaire */
        }

        .site-logo {
            max-width: 150px; /* Un peu plus petit */
            height: auto;
            margin-bottom: 5px;
        }

        .brand-name {
            font-size: clamp(26px, 6vw, 32px);
            font-weight: 700;
            color: var(--brand-gold);
            letter-spacing: 1px;
            margin-bottom: 20px; /* Espace avant le formulaire */
        }

        .form-title {
            font-size: clamp(20px, 5vw, 24px);
            color: var(--text-light);
            margin-bottom: 25px;
            font-weight: 500;
        }


        .input-group {
            position: relative;
            margin-bottom: 18px;
            text-align: left; /* Pour les labels */
        }

        .input-group label {
            display: block;
            margin-bottom: 6px;
            font-size: 14px;
            color: var(--label-color);
            font-weight: 500;
        }

        .input-group .icon {
            position: absolute;
            left: 15px; /* Maintenir à gauche pour les inputs */
            top: calc(50% + 12px); /* Ajuster si label au-dessus, sinon 50% */
            transform: translateY(-50%);
            color: var(--text-icon);
            font-size: 16px;
        }
        /* Pour select, l'icône peut être différente ou pas nécessaire si stylé comme input */
        .select-group .icon {
            top: calc(50% + 12px); /* S'aligne avec le select quand il y a un label */
        }


        .input-field, .select-field {
            width: 100%;
            padding: 12px 12px 12px 40px; /* Espace pour icône */
            border: 1px solid var(--input-border);
            border-radius: 8px;
            background-color: var(--input-bg);
            color: var(--text-light);
            font-size: 15px;
            box-sizing: border-box;
            transition: border-color 0.3s, box-shadow 0.3s;
        }
         /* Spécifique pour select si pas d'icône à gauche */
        .select-field {
             padding-left: 12px; /* Pas d'icône interne à gauche par défaut */
             padding-right: 30px; /* Espace pour la flèche du select */
             appearance: none; /* Supprime le style natif */
            background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%23%2394a3b8%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22%2F%3E%3C%2Fsvg%3E');
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 10px 10px;
        }


        .input-field::placeholder, .select-field option[value=""] {
            color: var(--text-icon);
            opacity: 0.7;
        }
         .select-field option {
            background-color: var(--input-bg);
            color: var(--text-light);
        }


        .input-field:focus, .select-field:focus {
            outline: none;
            border-color: var(--brand-blue);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.3);
        }

        .register-button { /* Changé de login-button */
            width: 100%;
            padding: 12px;
            background-color: var(--brand-gold);
            border: none;
            border-radius: 8px;
            color: var(--input-bg);
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: background-color 0.3s, transform 0.1s;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 10px; /* Espace au-dessus du bouton */
        }

        .register-button:hover {
            background-color: #facc15;
        }
        .register-button:active {
            transform: translateY(1px);
        }

        .login-link { /* Nouveau style pour le lien vers la connexion */
            display: block;
            margin-top: 20px;
            color: var(--brand-blue);
            text-decoration: none;
            font-size: 14px;
            transition: color 0.3s;
        }

        .login-link:hover {
            color: #93c5fd;
            text-decoration: underline;
        }

        .message {
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
            text-align: left; /* Pour que le texte soit aligné à gauche dans la boîte */
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

        @media (max-width: 400px) {
            .register-container {
                padding: 20px;
            }
            .brand-name {
                font-size: 24px;
            }
            .form-title {
                font-size: 18px;
            }
        }
    </style>
</head>
<body>
    <div class="particles-background" id="particles-js"></div>

    <div class="register-container">
        <div class="logo-area">
            <img src="<?php echo SITE_URL; ?>/assets/images/logo.png" alt="Logo <?php echo SITE_NAME; ?>" class="site-logo">
            <div class="brand-name"><?php echo SITE_NAME; ?></div>
        </div>
        <h2 class="form-title">Créer un nouveau compte</h2>

        <?php if (!empty($success_message)): ?>
            <div class="message success-message"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>
        <?php if (!empty($error_message)): ?>
            <div class="message error-message"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <form method="POST" action="<?php echo SITE_URL; ?>/register.php">
           <div class="input-group">
        <label for="nom">Nom :</label>
        <span class="icon">👤</span>
        <input type="text" id="nom" name="nom" class="input-field" placeholder="Votre nom de famille" value="<?php echo htmlspecialchars($nom_value); ?>" required>
    </div>

    <div class="input-group">
        <label for="prenom">Prénom :</label>
        <span class="icon">👤</span>
        <input type="text" id="prenom" name="prenom" class="input-field" placeholder="Votre prénom" value="<?php echo htmlspecialchars($prenom_value); ?>" required>
    </div>

    <div class="input-group">
        <label for="email">Adresse e-mail :</label>
        <span class="icon">✉️</span>
        <input type="email" id="email" name="email" class="input-field" placeholder="exemple@domaine.com" value="<?php echo htmlspecialchars($email_value); ?>" required>
    </div>

    <div class="input-group">
        <label for="password">Mot de passe :</label>
        <span class="icon">🔒</span>
        <input type="password" id="password" name="password" class="input-field" placeholder="Choisissez un mot de passe" required>
    </div>

    <div class="input-group">
        <label for="confirm_password">Confirmer le mot de passe :</label>
        <span class="icon">🔒</span>
        <input type="password" id="confirm_password" name="confirm_password" class="input-field" placeholder="Retapez votre mot de passe" required>
    </div>

    <div class="input-group select-group">
        <label for="role">Type de compte :</label>
        <select id="role" name="role" class="select-field" required>
            <option value="" <?php if(empty($role_value)) echo 'selected'; ?> disabled>-- Sélectionner un rôle --</option>
            <option value="<?php echo ROLE_STUDENT; ?>" <?php if($role_value === ROLE_STUDENT) echo 'selected'; ?>>Étudiant</option>
            <option value="<?php echo ROLE_TEACHER; ?>" <?php if($role_value === ROLE_TEACHER) echo 'selected'; ?>>Enseignant</option>
            <option value="<?php echo ROLE_ADMIN; ?>" <?php if($role_value === ROLE_ADMIN) echo 'selected'; ?>>Administrateur</option>
        </select>
    </div>

    <div class="input-group">
        <label for="photo">Photo de profil :</label>
        <span class="icon">📷</span>
        <input type="file" id="photo" name="photo" class="input-field" accept="image/*">
    </div>

    <button type="submit" class="register-button">Créer le compte</button>
</form>
        <a href="<?php echo SITE_URL; ?>/index.php" class="login-link">Déjà un compte ? Se connecter</a>
    </div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const particlesContainer = document.getElementById('particles-js');
    if (!particlesContainer) return;
    const numberOfParticles = 50;

    for (let i = 0; i < numberOfParticles; i++) {
        let particle = document.createElement('div');
        particle.classList.add('particle');
        particle.style.top = Math.random() * 100 + '%';
        particle.style.left = Math.random() * 100 + '%';
        const size = Math.random() * 4 + 1;
        particle.style.width = size + 'px';
        particle.style.height = size + 'px';
        particle.style.animationDelay = Math.random() * 10 + 's';
        particle.style.animationDuration = Math.random() * 5 + 5 + 's';
        if (Math.random() < 0.3) {
            particle.classList.add('blue');
        }
        particlesContainer.appendChild(particle);
    }
});
</script>
</body>
</html>