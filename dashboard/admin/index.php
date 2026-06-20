<?php
// Dans gestion_school/dashboard/admin/index.php

// Gestion de session et rôles
require_once __DIR__ . '/../../includes/auth.php'; // Remonter de deux niveaux (admin -> dashboard -> gestion_school) puis includes

// Connexion à la base de données
require_once __DIR__ . '/../../config/db.php'; // Remonter de deux niveaux puis config

// Fonctions utilitaires
require_once __DIR__ . '/../../includes/helpers.php'; // Remonter de deux niveaux puis includes

$auth_path = __DIR__ . '/../../includes/auth.php';
if (!file_exists($auth_path)) {
    die("Fichier auth.php introuvable à : $auth_path");
}
require_once $auth_path;

define('BASE_PATH', realpath(__DIR__ . '/../')); // /gestion_school

$database = new Database();
$db = $database->connect();



// --- Récupération des données pour le tableau de bord ---
// Exemple : Nombre d'étudiants
$stmt_etudiants = $db->prepare("SELECT COUNT(*) as total_etudiants FROM etudiants");
$database->execute($stmt_etudiants);
$count_etudiants = $database->fetch($stmt_etudiants)['total_etudiants'] ?? 0;

// Exemple : Nombre d'enseignants
$stmt_enseignants = $db->prepare("SELECT COUNT(*) as total_enseignants FROM enseignants");
$database->execute($stmt_enseignants);
$count_enseignants = $database->fetch($stmt_enseignants)['total_enseignants'] ?? 0;

// Exemple : Nombre de classes
$stmt_classes = $db->prepare("SELECT COUNT(*) as total_classes FROM classes");
$database->execute($stmt_classes);
$count_classes = $database->fetch($stmt_classes)['total_classes'] ?? 0;

// Exemple : Nombre de matières
$stmt_matieres = $db->prepare("SELECT COUNT(*) as total_matieres FROM matieres");
$database->execute($stmt_matieres);
$count_matieres = $database->fetch($stmt_matieres)['total_matieres'] ?? 0;


// Inclure l'en-tête HTML
// Le header.php devra être adapté pour ne pas entrer en conflit avec cette mise en page spécifique
// ou ce fichier devra gérer l'intégralité de sa structure HTML.
// Pour cet exemple, je vais supposer que header.php fournit la base <head> et le début de <body>
// et que nous construisons la structure principale ici.

// Note: Si header.php a déjà une barre de navigation complète, il faudra la modifier
// pour qu'elle s'intègre ou soit remplacée par celle de ce tableau de bord.
// Idéalement, header.php devient plus modulaire.

// Pour cet exemple, on va redéfinir le header pour qu'il corresponde au design.
// Ceci est une simplification. Normalement, vous auriez un header.php pour les pages publiques
// et un admin_header.php (ou des sections conditionnelles dans header.php) pour le backend.

$user_name = get_user_name();
$user_role = get_user_role_name(get_user_role());
$user_photo = get_user_photo();
$base_url = get_base_url();

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo sanitizeInput($page_title); ?> - Gestion Scolaire</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
       :root {
    /* Couleurs principales BLEUES */
    --primary-color: #2563EB;       /* Bleu principal */
    --primary-light: #DBEAFE;       /* Bleu clair */
    --primary-dark: #1D4ED8;        /* Bleu foncé */

    --secondary-color: #0EA5E9;     /* Bleu secondaire */
    --accent-blue: #3B82F6;
    --accent-red: #EF4444;
    --accent-orange: #F97316;
    --accent-green: #10B981;

    /* Couleurs générales */
    --bg-color: #F8FAFC;
    --sidebar-bg: #FFFFFF;
    --card-bg: #FFFFFF;
    --text-color: #1E293B;
    --text-light: #64748B;
    --border-color: #E2E8F0;

    /* Ombres */
    --shadow-color: rgba(37, 99, 235, 0.15);

    /* Dimensions */
    --sidebar-width: 260px;
    --topbar-height: 70px;
    --border-radius-main: 12px;
    --border-radius-card: 10px;
}

             body.dark-mode {
               --bg-color: #1F2937;
               --sidebar-bg: #111827;
               --card-bg: #1F2937;
               --text-color: #F9FAFB;
               --text-light: #9CA3AF;
               --border-color: #374151;
               --shadow-color: rgba(0, 0, 0, 0.5);
             }


        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--bg-color);
            color: var(--text-color);
            display: flex;
            height: 100vh;
            overflow: hidden; /* Empêche le scroll sur le body, le contenu scollera */
        }
        

        .admin-dashboard-layout {
            display: flex;
            width: 100%;
            height: 100%;
        }

        /* Sidebar */
        .sidebar {
            width: var(--sidebar-width);
            background-color: var(--sidebar-bg);
            padding: 25px 20px;
            display: flex;
            flex-direction: column;
            border-right: 1px solid var(--border-color);
            transition: width 0.3s ease;
        }

        .sidebar-logo {
            display: flex;
            align-items: center;
            margin-bottom: 30px;
            padding-left: 5px; /* slight indent */
        }
        .sidebar-logo i {
            font-size: 28px;
            color: var(--primary-color);
            margin-right: 10px;
        }
        .sidebar-logo span {
            font-size: 22px;
            font-weight: 700;
            color: var(--primary-color);
        }

        .sidebar-menu {
            list-style: none;
            flex-grow: 1;
        }
        .sidebar-menu li a {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            margin-bottom: 8px;
            border-radius: var(--border-radius-card);
            color: var(--text-light);
            text-decoration: none;
            font-weight: 500;
            transition: background-color 0.2s ease, color 0.2s ease;
        }
        .sidebar-menu li a i {
            margin-right: 15px;
            font-size: 18px;
            width: 20px; /* Ensure icons align */
            text-align: center;
        }
        .sidebar-menu li a:hover,
        .sidebar-menu li a.active {
            background-color: var(--primary-light);
            color: var(--primary-color);
            font-weight: 600;
        }
        .sidebar-menu li a.active {
             box-shadow: 0 4px 8px rgba(123, 102, 255, 0.1);
        }
        
        .sidebar-upgrade {
            background-color: var(--primary-light);
            padding: 20px;
            border-radius: var(--border-radius-main);
            text-align: center;
            margin-top: auto; /* Pushes to bottom */
        }
        .sidebar-upgrade h4 {
            font-size: 1em;
            font-weight: 600;
            color: var(--primary-dark);
            margin-bottom: 5px;
        }
        .sidebar-upgrade p {
            font-size: 0.85em;
            color: var(--text-light);
            margin-bottom: 15px;
        }
        .sidebar-upgrade .btn-upgrade {
            display: block;
            background-color: var(--primary-color);
            color: white;
            padding: 10px;
            border-radius: var(--border-radius-card);
            text-decoration: none;
            font-weight: 500;
            transition: background-color 0.2s ease;
        }
        .sidebar-upgrade .btn-upgrade:hover {
            background-color: var(--primary-dark);
        }


        /* Main Panel */
        .main-panel {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            height: 100vh; /* Full height */
        }

        /* Top Navbar */
        .top-navbar {
            height: var(--topbar-height);
            background-color: var(--sidebar-bg); /* White, like sidebar */
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 30px;
            border-bottom: 1px solid var(--border-color);
        }
        .top-navbar .search-bar {
            display: flex;
            align-items: center;
            background-color: var(--bg-color);
            padding: 8px 15px;
            border-radius: var(--border-radius-card);
            width: 300px;
        }
        .top-navbar .search-bar i {
            color: var(--text-light);
            margin-right: 10px;
        }
        .top-navbar .search-bar input {
            border: none;
            outline: none;
            background: transparent;
            color: var(--text-color);
            font-size: 0.9em;
            width: 100%;
        }
        .top-navbar .user-actions {
            display: flex;
            align-items: center;
        }
        .top-navbar .action-icon {
            font-size: 20px;
            color: var(--text-light);
            margin-left: 20px;
            cursor: pointer;
            transition: color 0.2s ease;
        }
        .top-navbar .action-icon:hover {
            color: var(--primary-color);
        }
        .top-navbar .live-btn {
            background-color: var(--accent-red);
            color: white;
            padding: 8px 15px;
            border-radius: var(--border-radius-card);
            font-weight: 500;
            font-size: 0.9em;
            text-decoration: none;
            margin-left: 20px;
            transition: background-color 0.2s ease;
        }
        .top-navbar .live-btn i { margin-right: 5px; }
        .top-navbar .live-btn:hover { background-color: #D32F2F; }

        .profile-section { /* From old header.php, adapt for top-navbar */
            display: flex;
            align-items: center;
            margin-left: 20px;
        }
        .profile-section .user-info {
            text-align: right;
            margin-right: 10px;
        }
        .profile-section .user-name {
            font-weight: 600;
            font-size: 0.9em;
            color: var(--text-color);
        }
        .profile-section .user-role {
            font-size: 0.75em;
            color: var(--text-light);
        }
        .profile-section .profile-pic {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--primary-light);
        }

        /* Content Area */
        .content-area {
            flex-grow: 1;
            padding: 30px;
            overflow-y: auto; /* Enable scroll for content */
        }
        .content-header h1 {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 10px;
            color: var(--text-color);
        }
        .content-header p {
            font-size: 1em;
            color: var(--text-light);
            margin-bottom: 30px;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }
        .stat-card {
            background-color: var(--card-bg);
            padding: 25px;
            border-radius: var(--border-radius-main);
            box-shadow: 0 5px 20px var(--shadow-color);
            display: flex;
            align-items: center;
            transition: transform 0.2s ease-out, box-shadow 0.2s ease-out;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px var(--shadow-color);
        }
        .stat-card .card-icon {
            font-size: 28px;
            padding: 15px;
            border-radius: 50%;
            margin-right: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 60px;
            height: 60px;
        }
        .stat-card .card-icon.icon-students { background-color: #E0F2FE; color: #0EA5E9; } /* Light Blue */
        .stat-card .card-icon.icon-teachers { background-color: #FEF3C7; color: #F59E0B; } /* Light Yellow */
        .stat-card .card-icon.icon-classes { background-color: #D1FAE5; color: #10B981; } /* Light Green */
        .stat-card .card-icon.icon-subjects { background-color: #FEE2E2; color: #EF4444; } /* Light Red */
        
        .stat-card .card-info h3 {
            font-size: 26px;
            font-weight: 700;
            color: var(--text-color);
        }
        .stat-card .card-info p {
            font-size: 0.9em;
            color: var(--text-light);
        }

        /* Quick Actions */
        .quick-actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }
        .action-card {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            padding: 25px;
            border-radius: var(--border-radius-main);
            color: white;
            text-decoration: none;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: transform 0.2s ease-out, box-shadow 0.2s ease-out;
            box-shadow: 0 5px 15px rgba(123, 102, 255, 0.3);
        }
         .action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(123, 102, 255, 0.4);
        }
        .action-card-header {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        .action-card-header i {
            font-size: 24px;
            margin-right: 15px;
            opacity: 0.8;
        }
        .action-card-header h4 {
            font-size: 1.1em;
            font-weight: 600;
        }
        .action-card p {
            font-size: 0.85em;
            opacity: 0.8;
            margin-bottom: 20px;
            flex-grow: 1;
        }
        .action-card .action-link {
            font-weight: 500;
            font-size: 0.9em;
            display: inline-flex; /* To align icon with text */
            align-items: center; /* To align icon with text */
        }
        .action-card .action-link i {
            margin-left: 8px;
            transition: transform 0.2s ease;
        }
        .action-card:hover .action-link i {
            transform: translateX(3px);
        }

        /* Main Banner - like "Hi, Irham..." */
        .welcome-banner {
            background: linear-gradient(105deg, var(--primary-color) 0%, #A594FF 100%); /* Slightly lighter purple blend */
            padding: 35px 40px;
            border-radius: var(--border-radius-main);
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: white;
            box-shadow: 0 10px 30px rgba(123, 102, 255, 0.25);
        }
        .welcome-banner-text h2 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        .welcome-banner-text p {
            font-size: 1em;
            opacity: 0.9;
            margin-bottom: 20px;
        }
        .welcome-banner-text .btn-banner {
            background-color: rgba(255,255,255,0.2);
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: var(--border-radius-card);
            font-weight: 500;
            transition: background-color 0.2s ease;
            border: 1px solid rgba(255,255,255,0.3);
        }
        .welcome-banner-text .btn-banner:hover {
            background-color: rgba(255,255,255,0.3);
        }
        .welcome-banner-image img {
            max-width: 220px; /* Adjust as needed */
            animation: floatAnimation 3s ease-in-out infinite;
        }

        @keyframes floatAnimation {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        /* Alerts Section - Example */
        .alerts-section {
            background-color: var(--card-bg);
            padding: 25px;
            border-radius: var(--border-radius-main);
            box-shadow: 0 5px 20px var(--shadow-color);
        }
        .alerts-section h3 {
            font-size: 1.2em;
            font-weight: 600;
            margin-bottom: 15px;
        }
        .alert-item {
            display: flex;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid var(--border-color);
        }
        .alert-item:last-child { border-bottom: none; }
        .alert-item .alert-icon {
            font-size: 18px;
            margin-right: 15px;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .alert-item .alert-icon.warning { background-color: #FFFBEB; color: #F59E0B; }
        .alert-item .alert-icon.info { background-color: #EFF6FF; color: #3B82F6; }
        .alert-item .alert-text p { font-size: 0.9em; margin-bottom: 2px; }
        .alert-item .alert-text span { font-size: 0.8em; color: var(--text-light); }
        
        /* Logout link styling in sidebar (if you add it) */
        .sidebar-menu li a.logout-link {
            margin-top: 20px; /* Space it out */
            color: var(--accent-red);
        }
        .sidebar-menu li a.logout-link:hover {
            background-color: #FEE2E2; /* Light red background */
            color: var(--accent-red); /* Keep text red */
        }

    </style>
</head>
<body>
    <div class="admin-dashboard-layout">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-logo">
                <i class="fas fa-school"></i> <!-- Icône école -->
                <span>MonÉcole</span>
            </div>

            <ul class="sidebar-menu">
                <li><a href="<?php echo $base_url; ?>index.php" class="active"><i class="fas fa-tachometer-alt"></i> Tableau de bord</a></li>
                <li><a href="<?php echo $base_url; ?>gestion_utilisateurs.php"><i class="fas fa-users-cog"></i> Utilisateurs</a></li>
                <li><a href="<?php echo $base_url; ?>inscrire_etudiant.php"><i class="fas fa-user-plus"></i> Inscrire Étudiant</a></li>
                <li><a href="<?php echo $base_url; ?>inscrire_enseignant.php"><i class="fas fa-chalkboard-teacher"></i> Inscrire Enseignant</a></li>
                <li><a href="<?php echo $base_url; ?>gestion_classes.php"><i class="fas fa-university"></i> Classes & Filières</a></li>
                <li><a href="<?php echo $base_url; ?>gestion_matieres.php"><i class="fas fa-book"></i> Matières</a></li>
                <li><a href="<?php echo $base_url; ?>emploi_temps.php"><i class="fas fa-calendar-alt"></i> Emplois du Temps</a></li>
                <li><a href="<?php echo $base_url; ?>gestion_absences.php"><i class="fas fa-user-check"></i> Gestion Absences</a></li>
                <li><a href="<?php echo $base_url; ?>notifications.php"><i class="fas fa-bell"></i> Notifications</a></li>
                <li><a href="<?php echo $base_url; ?>statistiques.php"><i class="fas fa-chart-line"></i> Statistiques</a></li>
                <li><a href="<?php echo $base_url; ?>parametres.php"><i class="fas fa-cogs"></i> Paramètres</a></li>
                <li><a href="<?php echo $base_url; ?>logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> Déconnexion</a></li>
            </ul>

            <div class="sidebar-upgrade">
                <h4>MonÉcole Pro</h4>
                <p>Passez à la version Pro pour plus de fonctionnalités.</p>
                <a href="#" class="btn-upgrade">Mettre à niveau <i class="fas fa-arrow-right"></i></a>
            </div>
        </aside>

        <!-- Main Panel -->
        <div class="main-panel">
            <!-- Top Navbar -->
            <header class="top-navbar">
                <div class="search-bar">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Rechercher...">
                </div>
                <div class="user-actions">
                    <a href="#" class="live-btn"><i class="fas fa-broadcast-tower"></i> Live</a>
                    <i class="fas fa-bell action-icon"></i> <!-- Notifications Icon -->
                    <i class="fas fa-moon action-icon"></i> <!-- Theme Toggle Icon (placeholder) -->
                    
                    <div class="profile-section">
                        <div class="user-info">
                            <span class="user-name"><?php echo sanitizeInput($user_name); ?></span>
                            <span class="user-role"><?php echo sanitizeInput($user_role); ?></span>
                        </div>
                        <img src="<?php echo sanitizeInput($user_photo); ?>" alt="Photo de profil" class="profile-pic">
                    </div>
                </div>
            </header>

            <!-- Content Area -->
            <main class="content-area">
                <div class="content-header">
                    <h1>Tableau de Bord Administrateur</h1>
                    <p>Bienvenue, <?php echo sanitizeInput($user_name); ?>! Gérez votre établissement efficacement.</p>
                </div>

                 <!-- Welcome Banner -->
                <div class="welcome-banner">
                    <div class="welcome-banner-text">
                        <h2>Bonjour, <?php echo strtok(sanitizeInput($user_name), " "); ?>!</h2>
                        <p>Le système de gestion scolaire est à votre service pour simplifier l'administration.</p>
                        <a href="<?php echo $base_url; ?>parametres.php" class="btn-banner">Explorer les paramètres <i class="fas fa-rocket"></i></a>
                    </div>
                    <div class="welcome-banner-image">
                        <!-- Vous pouvez mettre une image illustrative ici -->
                        <img src="<?php echo $base_url; ?>assets/images/admin_dashboard_hero.png" alt="Illustration dashboard" style="opacity:0.8;"> 
                        <!-- Assurez-vous que cette image existe, ou utilisez un SVG -->
                    </div>
                </div>


                <!-- Stats Grid -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="card-icon icon-students"><i class="fas fa-user-graduate"></i></div>
                        <div class="card-info">
                            <h3><?php echo $count_etudiants; ?></h3>
                            <p>Étudiants Inscrits</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="card-icon icon-teachers"><i class="fas fa-chalkboard-teacher"></i></div>
                        <div class="card-info">
                            <h3><?php echo $count_enseignants; ?></h3>
                            <p>Enseignants Actifs</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="card-icon icon-classes"><i class="fas fa-school"></i></div>
                        <div class="card-info">
                            <h3><?php echo $count_classes; ?></h3>
                            <p>Classes Ouvertes</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="card-icon icon-subjects"><i class="fas fa-book-open"></i></div>
                        <div class="card-info">
                             <h3><?php echo $count_matieres; ?></h3>
                            <p>Matières Enseignées</p>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions Grid -->
                 <div class="quick-actions-grid">
                    <a href="<?php echo $base_url; ?>gestion_utilisateurs.php" class="action-card">
                        <div class="action-card-header">
                            <i class="fas fa-users"></i>
                            <h4>Gestion des Utilisateurs</h4>
                        </div>
                        <p>Gérer les comptes étudiants, enseignants et administrateurs.</p>
                        <span class="action-link">Accéder <i class="fas fa-arrow-right"></i></span>
                    </a>
                     <a href="<?php echo $base_url; ?>gestion_classes.php" class="action-card">
                        <div class="action-card-header">
                            <i class="fas fa-layer-group"></i>
                            <h4>Gestion Classes & Filières</h4>
                        </div>
                        <p>Configurer les niveaux, classes et filières de l'établissement.</p>
                        <span class="action-link">Configurer <i class="fas fa-arrow-right"></i></span>
                    </a>
                    <a href="<?php echo $base_url; ?>notifications.php" class="action-card">
                        <div class="action-card-header">
                            <i class="fas fa-bullhorn"></i>
                            <h4>Envoyer une Notification</h4>
                        </div>
                        <p>Communiquer des informations importantes à tous les utilisateurs.</p>
                        <span class="action-link">Envoyer <i class="fas fa-arrow-right"></i></span>
                    </a>
                </div>
                
                <!-- Alerts Section (Exemple) -->
                <div class="alerts-section">
                    <h3>Alertes & Tâches Récentes</h3>
                    <div class="alert-item">
                        <div class="alert-icon warning"><i class="fas fa-exclamation-triangle"></i></div>
                        <div class="alert-text">
                            <p><strong>Devoirs non corrigés:</strong> 5 devoirs en attente de correction.</p>
                            <span>Matière: Mathématiques, Classe: Terminale A</span>
                        </div>
                    </div>
                    <div class="alert-item">
                        <div class="alert-icon info"><i class="fas fa-info-circle"></i></div>
                        <div class="alert-text">
                            <p><strong>Nouvelle inscription:</strong> Un nouvel étudiant s'est inscrit.</p>
                            <span>Nom: Alioune Fall, Classe demandée: Seconde S</span>
                        </div>
                    </div>
                    <!-- Ajouter plus d'alertes dynamiquement -->
                </div>

            </main>
        </div>
    </div>
    
    <!-- Scripts JS (si vous en avez) -->
    <!-- <script src="<?php echo $base_url; ?>assets/js/admin_script.js"></script> -->
    <script>
        // Petit script pour la navigation active (peut être plus complexe)
        document.addEventListener('DOMContentLoaded', function() {
            const currentPath = window.location.pathname;
            const sidebarLinks = document.querySelectorAll('.sidebar-menu li a');
            sidebarLinks.forEach(link => {
                if (link.getAttribute('href').includes(currentPath)) {
                    // Pour une correspondance exacte, vous devrez peut-être ajuster la logique
                    // surtout si index.php est implicite
                    // Exemple simple :
                    // if (link.href === window.location.href) {
                    //    link.classList.add('active');
                    // }
                }
            });

            // Placeholder pour le theme toggle
            const themeToggle = document.querySelector('.top-navbar .fa-moon');
            if(themeToggle) {
                themeToggle.addEventListener('click', () => {
    document.body.classList.toggle('dark-mode');

    // Changer l’icône entre lune et soleil si tu veux
    themeToggle.classList.toggle('fa-moon');
    themeToggle.classList.toggle('fa-sun');

    // Enregistre le thème choisi
    const mode = document.body.classList.contains('dark-mode') ? 'dark' : 'light';
    localStorage.setItem('theme', mode);
});

            }
        });

        document.addEventListener('DOMContentLoaded', () => {
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme === 'dark') {
        document.body.classList.add('dark-mode');
        themeToggle.classList.remove('fa-moon');
        themeToggle.classList.add('fa-sun');
    }
});

    </script>
</body>
</html>