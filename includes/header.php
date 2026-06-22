<?php
// En-tête HTML
// Assurez-vous que auth.php est inclus AVANT header.php sur les pages protégées,
// ou incluez-le ici si toutes les pages utilisant ce header nécessitent une session.
if (session_status() == PHP_SESSION_NONE) {
    session_start(); // Nécessaire pour accéder aux variables de session (nom, rôle, etc.)
}
require_once __DIR__ . '/helpers.php'; // Pour get_base_url() et autres
require_once __DIR__ . '/auth.php'; // Pour is_logged_in(), get_user_name(), etc.

$page_title = isset($page_title) ? sanitizeInput($page_title) : "Gestion Scolaire"; // Titre par défaut
$user_name = get_user_name();
$user_role = get_user_role();
$user_photo = get_user_photo(); // Chemin vers la photo de profil
$base_url = get_base_url();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Mon École</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Vous pouvez ajouter un lien vers une librairie d'icônes ici, ex: Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css"> -->

    <style>
        /* Réinitialisation de base et styles globaux */
        :root {
            --primary-color: #3498db; /* Bleu clair de "JSF" */
            --secondary-color: #2ecc71; /* Vert de "Web Design" */
            --accent-color-purple: #8e44ad; /* Violet de "Ionic Platform" */
            --accent-color-orange: #f39c12; /* Orange de "Business" */
            --accent-color-red: #e74c3c; /* Rouge de "Marketing" */
            
            --dark-bg: #0F0426; /* Fond très sombre, un peu plus clair que noir pur */
            --main-bg-start: #1A0A3F; /* Dégradé fond - début (plus sombre) */
            --main-bg-end: #2E105B; /* Dégradé fond - fin (plus clair) */
            
            --top-bar-bg: #F39C12; /* Orange de "Khalil Profile" - légèrement adapté pour la barre */
            --text-light: #f8f9fa;
            --text-dark: #333;
            --text-muted: #aaa;
            --card-bg: rgba(255, 255, 255, 0.05); /* Fond de carte semi-transparent */
            --border-color: rgba(255, 255, 255, 0.1);
            --border-radius: 12px;
            --box-shadow: 0 8px 16px rgba(0,0,0,0.2);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--dark-bg); /* Fallback */
            background-image: linear-gradient(135deg, var(--main-bg-start), var(--main-bg-end));
            color: var(--text-light);
            line-height: 1.6;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        .container {
            width: 90%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px 0;
        }
        
        /* Styles pour les alertes (de helpers.php) */
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: var(--border-radius);
            color: white;
            text-align: center;
        }
        .alert-success { background-color: #28a745; border-color: #28a745; }
        .alert-error { background-color: #dc3545; border-color: #dc3545; }
        .alert-info { background-color: #17a2b8; border-color: #17a2b8; }
        .alert-warning { background-color: #ffc107; border-color: #ffc107; color: #333; }


        /* Top Bar - comme la barre orange avec le logo et le profil */
        .top-bar {
            background-color: var(--top-bar-bg); /* Orange */
            padding: 10px 0;
            color: var(--text-dark); /* Texte sombre sur fond orange */
        }
        .top-bar .container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 0;
            padding-bottom: 0;
        }
        .top-bar .logo a {
            font-size: 1.5em;
            font-weight: 700;
            color: var(--text-dark);
            text-decoration: none;
        }
        .top-bar .logo img { /* Si vous utilisez une image pour le logo */
            height: 30px; /* Ajustez selon votre logo */
            vertical-align: middle;
        }

        .profile-section {
            display: flex;
            align-items: center;
        }
        .profile-section .user-info {
            margin-right: 15px;
            text-align: right;
        }
        .profile-section .user-name {
            font-weight: 600;
            display: block;
        }
        .profile-section .user-role {
            font-size: 0.8em;
            color: #555; /* Un peu plus sombre que le texte principal */
        }
        .profile-section .profile-pic {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--text-dark); /* Bordure assortie au texte */
        }
        .profile-section .profile-link {
            color: var(--text-dark);
            text-decoration: none;
            font-size: 0.9em;
            margin-left: 5px; /* Petit espace si le nom est cliquable */
        }
        .profile-section .profile-link:hover {
            text-decoration: underline;
        }


        /* Navigation principale (sous la top-bar) */
        .main-nav {
            background-color: var(--main-bg-end); /* Utilise la couleur de fond principale foncée */
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            padding: 10px 0; /* Un peu moins de padding que la top-bar */
        }
        .main-nav .container {
            display: flex;
            justify-content: space-between; /* Éloigne le menu de la recherche/logout */
            align-items: center;
             padding-top: 0;
            padding-bottom: 0;
        }
        .main-nav ul {
            list-style: none;
            display: flex;
        }
        .main-nav ul li a {
            color: var(--text-light);
            text-decoration: none;
            padding: 10px 15px;
            display: block;
            font-weight: 500;
            border-radius: var(--border-radius);
            transition: background-color 0.3s ease, color 0.3s ease;
        }
        .main-nav ul li a:hover,
        .main-nav ul li a.active {
            background-color: var(--primary-color); /* Bleu pour l'élément actif/survolé */
            color: white;
        }

        .nav-actions a {
            color: var(--text-light);
            text-decoration: none;
            padding: 8px 12px;
            margin-left: 10px;
            border-radius: var(--border-radius);
            transition: background-color 0.3s ease;
        }
        .nav-actions a.logout-btn {
            background-color: var(--accent-color-red);
            color: white;
        }
        .nav-actions a.logout-btn:hover {
            background-color: #c0392b; /* Rouge plus sombre */
        }
        
        /* Contenu principal */
        .main-content {
            flex-grow: 1; /* Permet au contenu de prendre l'espace restant pour que le footer soit en bas */
        }

        /* Styles pour les formulaires (simple base) */
        form {
            background: var(--card-bg);
            padding: 25px;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            border: 1px solid var(--border-color);
        }
        form label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }
        form input[type="text"],
        form input[type="email"],
        form input[type="password"],
        form input[type="date"],
        form input[type="time"],
        form input[type="number"],
        form select,
        form textarea {
            width: 100%;
            padding: 12px;
            margin-bottom: 15px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            background-color: rgba(255,255,255,0.1);
            color: var(--text-light);
            font-size: 1em;
        }
        form input[type="text"]:focus,
        form input[type="email"]:focus,
        form input[type="password"]:focus,
        form select:focus,
        form textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.5); /* Lueur bleue */
        }
        form button[type="submit"], .btn {
            background-color: var(--primary-color);
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1em;
            font-weight: 500;
            transition: background-color 0.3s ease;
        }
        form button[type="submit"]:hover, .btn:hover {
            background-color: #2980b9; /* Bleu plus sombre */
        }
        .btn-secondary { background-color: var(--secondary-color); }
        .btn-secondary:hover { background-color: #27ae60; }
        .btn-danger { background-color: var(--accent-color-red); }
        .btn-danger:hover { background-color: #c0392b; }


        /* Styles pour les tableaux (simple base) */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            background-color: var(--card-bg);
            border-radius: var(--border-radius);
            overflow: hidden; /* Pour que le border-radius s'applique aux coins du tableau */
            box-shadow: var(--box-shadow);
        }
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        th {
            background-color: rgba(255,255,255,0.1); /* En-tête de tableau légèrement différent */
            font-weight: 600;
        }
        tbody tr:hover {
            background-color: rgba(255,255,255,0.08);
        }
        tbody tr:last-child td {
            border-bottom: none;
        }

        /* Style pour la section "Emploi du temps" de l'image */
        .schedule-block {
            background-color: #fff; /* Fond blanc comme sur l'image */
            color: var(--text-dark); /* Texte sombre */
            padding: 20px;
            border-radius: var(--border-radius);
            margin-bottom: 30px;
            box-shadow: var(--box-shadow);
        }
        .schedule-block h2 {
            color: var(--main-bg-end); /* Titre avec une couleur foncée du thème */
            margin-bottom: 20px;
        }
        .schedule-grid {
            display: grid;
            grid-template-columns: 100px repeat(5, 1fr); /* Heures + 5 colonnes pour les jours/horaires */
            gap: 1px; /* Crée des lignes fines entre les cellules */
            background-color: #e0e0e0; /* Couleur des lignes de la grille */
            border: 1px solid #e0e0e0;
        }
        .schedule-grid > div {
            background-color: #f8f9fa; /* Fond clair pour les cellules */
            padding: 10px;
            text-align: center;
            font-size: 0.9em;
        }
        .schedule-grid .time-slot {
            font-weight: 600;
            background-color: #e9ecef; /* Fond un peu plus foncé pour les heures */
        }
        .schedule-grid .header-slot {
            font-weight: 600;
            background-color: #e9ecef;
        }
        .schedule-item {
            background-color: var(--primary-color) !important; /* Important pour override */
            color: white !important;
            border-radius: 8px;
            padding: 15px 10px !important;
            font-weight: 500;
            cursor: pointer;
            transition: transform 0.2s ease;
        }
        .schedule-item:hover {
            transform: scale(1.05);
        }
        .schedule-item.web-design { background-color: var(--secondary-color) !important; }
        .schedule-item.ionic { background-color: var(--accent-color-purple) !important; }
        
        /* Style pour les "Annonces" */
        .announcements-block {
            background-color: var(--primary-color); /* Fond bleu comme sur l'image */
            padding: 15px 20px;
            border-radius: var(--border-radius);
            margin-bottom: 30px;
            color: white;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: var(--box-shadow);
        }
        .announcements-block .icon {
            background-color: white;
            color: var(--primary-color);
            border-radius: 50%;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 15px;
        }
        .announcements-block .text strong { display: block; font-size: 1.1em; }
        .announcements-block .text span { font-size: 0.9em; opacity: 0.9; }
        .announcements-block .arrow { font-size: 1.5em; }
        .announcements-block a { color: white; text-decoration: none; }


        /* Style pour "Filières de formations" */
        .courses-section h2 {
            margin-bottom: 20px;
            color: var(--text-light);
        }
        .courses-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); /* Responsive grid */
            gap: 20px;
        }
        .course-card {
            padding: 20px;
            border-radius: var(--border-radius);
            text-align: center;
            color: white;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            box-shadow: var(--box-shadow);
            cursor: pointer;
        }
        .course-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 24px rgba(0,0,0,0.3);
        }
        .course-card .icon-placeholder { /* Remplacez par de vraies icônes SVG */
            font-size: 2.5em;
            margin-bottom: 10px;
            height: 40px; /* Hauteur fixe pour l'icône */
        }
        .course-card h3 {
            font-size: 1.1em;
            font-weight: 600;
        }
        /* Couleurs spécifiques des cartes de filières (basées sur l'image) */
        .course-card.computer-science { background-color: #007bff; } /* Bleu */
        .course-card.business-admin { background-color: #f39c12; } /* Orange */
        .course-card.electrical-eng { background-color: #1abc9c; } /* Vert/Teal */
        .course-card.marketing { background-color: #e74c3c; } /* Rouge */
        .course-card.graphic-design { background-color: #9b59b6; } /* Violet */
        .course-card.automotive-eng { background-color: #2ecc71; } /* Vert plus clair */
        .course-card.civil-eng { background-color: #e67e22; } /* Orange plus foncé */
        .course-card.data-science { background-color: #3498db; } /* Bleu plus clair */
        .course-card.network-security { background-color: #c0392b; } /* Rouge plus foncé */
        .course-card.economics { background-color: #d35400; } /* Autre orange */
        .course-card.biotechnology { background-color: #2980b9; } /* Autre bleu */
        .course-card.education { background-color: #8e44ad; } /* Autre violet */

    </style>
</head>
<body>

    <header>
        <div class="top-bar">
            <div class="container">
                <div class="logo">
                    <!-- Remplacez par votre vrai logo si vous en avez un -->
                    <!-- <img src="<?php echo $base_url; ?>assets/images/logo.png" alt="Logo Mon École"> -->
                    <a href="<?php echo $base_url; ?>index.php">MonÉcole</a>
                </div>
                <?php if (is_logged_in()): ?>
                <div class="profile-section">
                    <div class="user-info">
                        <span class="user-name"><?php echo $user_name; ?></span>
                        <span class="user-role"><?php echo get_user_role_name($user_role); ?></span>
                    </div>
                    <a href="<?php echo $base_url; ?>dashboard/<?php echo $user_role; ?>/profile.php" class="profile-link">
                        <img src="<?php echo $user_photo; ?>" alt="Photo de profil" class="profile-pic">
                    </a>
                    <!-- Vous pouvez ajouter un menu déroulant ici pour "Profile", "Paramètres", "Déconnexion" -->
                </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if (is_logged_in()): // Afficher la navigation principale seulement si connecté ?>
        <nav class="main-nav">
            <div class="container">
                <ul>
                    <li><a href="<?php echo $base_url; ?>dashboard/<?php echo $user_role; ?>/index.php" class="<?php echo is_active_page('index.php'); ?>">Accueil</a></li>
                    
                    <?php if ($user_role === 'etudiant'): ?>
                        <li><a href="<?php echo $base_url; ?>dashboard/etudiant/mes_cours.php" class="<?php echo is_active_page('mes_cours.php'); ?>">Mes Cours</a></li>
                        <li><a href="<?php echo $base_url; ?>dashboard/etudiant/mes_notes.php" class="<?php echo is_active_page('mes_notes.php'); ?>">Mes Notes</a></li>
                        <li><a href="<?php echo $base_url; ?>dashboard/etudiant/emploi_temps.php" class="<?php echo is_active_page('emploi_temps.php'); ?>">Emploi du temps</a></li>
                    <?php elseif ($user_role === 'enseignant'): ?>
                        <li><a href="<?php echo $base_url; ?>dashboard/enseignant/deposer_cours.php" class="<?php echo is_active_page('deposer_cours.php'); ?>">Déposer Cours</a></li>
                        <li><a href="<?php echo $base_url; ?>dashboard/enseignant/noter_etudiants.php" class="<?php echo is_active_page('noter_etudiants.php'); ?>">Noter Étudiants</a></li>
                    <?php elseif ($user_role === 'admin'): ?>
                        <li><a href="<?php echo $base_url; ?>admin/gestion_utilisateurs.php" class="<?php echo is_active_page('gestion_utilisateurs.php'); ?>">Utilisateurs</a></li>
                        <li><a href="<?php echo $base_url; ?>admin/gestion_classes.php" class="<?php echo is_active_page('gestion_classes.php'); ?>">Classes</a></li>
                        <li><a href="<?php echo $base_url; ?>admin/gestion_matieres.php" class="<?php echo is_active_page('gestion_matieres.php'); ?>">Matières</a></li>
                    <?php endif; ?>
                </ul>
                <div class="nav-actions">
                    <a href="<?php echo $base_url; ?>logout.php" class="logout-btn">Déconnexion</a>
                </div>
            </div>
        </nav>
        <?php endif; ?>
    </header>

    <main class="main-content">
        <div class="container">
        <?php
        // Afficher les messages d'erreur ou de succès de la session
        if (isset($_SESSION['success_message'])) {
            echo generate_alert(sanitizeInput($_SESSION['success_message']), 'success');
            unset($_SESSION['success_message']);
        }
        if (isset($_SESSION['error_message'])) {
            echo generate_alert(sanitizeInput($_SESSION['error_message']), 'error');
            unset($_SESSION['error_message']);
        }
        ?>
        <!-- Le contenu spécifique de la page sera inséré ici -->