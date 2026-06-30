<?php
require_once __DIR__ . '/config/config.php';
require_once 'config/db.php';      // Contient la classe Database



// Crée une instance de la base de données
$database = new Database();
$db = $database->connect();
// --- Configuration de l'affichage public ---
define('ID_CLASSE_EMPLOI_DU_TEMPS_PUBLIC', 1); // IMPORTANT: Remplacez 1 par un ID de classe valide !

// --- Initialisation pour le header (s'adapte si l'utilisateur est connecté) ---
$user_name_display = 'Invité';
$user_profile_pic = SITE_URL . '/assets/images/default_profile.png';
$is_user_connected = isset($_SESSION['user_id']);
$dashboard_link = '#';
$profile_page_link = '#';
$mes_cours_link_visible = false;

if ($is_user_connected) {
    $user_name_display = !empty($_SESSION['user_prenom']) ? $_SESSION['user_prenom'] : (!empty($_SESSION['user_nom']) ? $_SESSION['user_nom'] : 'Utilisateur');
    if (isset($_SESSION['user_photo']) && !empty($_SESSION['user_photo'])) {
        $user_profile_pic = SITE_URL . '/uploads/profiles/' . $_SESSION['user_photo'];
    }
    if (isset($_SESSION['user_role'])) {
        $profile_page_link = SITE_URL . '/dashboard/' . $_SESSION['user_role'] . '/profile.php';
        switch ($_SESSION['user_role']) {
            case ROLE_STUDENT:
                $dashboard_link = SITE_URL . '/dashboard/etudiant/index.php';
                $mes_cours_link_visible = true;
                break;
            case ROLE_TEACHER:
                $dashboard_link = SITE_URL . '/dashboard/enseignant/index.php';
                break;
            case ROLE_ADMIN:
                $dashboard_link = SITE_URL . '/dashboard/admin/index.php';
                break;
        }
    }
}

// --- Emploi du Temps Dynamique pour la Semaine ---
$jours_semaine = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
// Définir les créneaux horaires de début (pour des cours de 2h)
// Par exemple: 8h-10h, 10h-12h, (pause), 14h-16h, 16h-18h
$creneaux_horaires_debut = ['08:00', '10:00', '14:00', '16:00'];
// Pour l'affichage des heures dans la première colonne de la grille
$edt_lignes_heures_labels = [
    '08:00 - 10:00',
    '10:00 - 12:00',
    '14:00 - 16:00',
    '16:00 - 18:00'
];


$emploi_du_temps_semaine = []; // Structure: $emploi_du_temps_semaine[jour][heure_debut] = cours_details

if (defined('ID_CLASSE_EMPLOI_DU_TEMPS_PUBLIC')) {
    try {
        $stmt_edt_semaine = $db->prepare("
            SELECT edt.jour_semaine, 
                   TIME_FORMAT(edt.heure_debut, '%H:%i') AS heure_debut_formatted, 
                   TIME_FORMAT(edt.heure_fin, '%H:%i') AS heure_fin_formatted, 
                   m.nom_matiere
            FROM emplois_temps edt
            JOIN matieres m ON edt.id_matiere = m.id
            WHERE edt.id_classe = :id_classe_public
            ORDER BY FIELD(edt.jour_semaine, 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'), edt.heure_debut ASC
        ");
        $id_classe_val = ID_CLASSE_EMPLOI_DU_TEMPS_PUBLIC;
        $stmt_edt_semaine->bindParam(':id_classe_public', $id_classe_val, PDO::PARAM_INT);
        $stmt_edt_semaine->execute();
        $cours_semaine_brut = $stmt_edt_semaine->fetchAll(PDO::FETCH_ASSOC);

        // Mapper les cours récupérés à notre structure de grille
        foreach ($jours_semaine as $jour) {
            foreach ($creneaux_horaires_debut as $heure_debut_creneau) {
                $emploi_du_temps_semaine[$jour][$heure_debut_creneau] = null; // Initialiser
                foreach ($cours_semaine_brut as $cours) {
                    if ($cours['jour_semaine'] === $jour && $cours['heure_debut_formatted'] === $heure_debut_creneau) {
                        $cours['color'] = $edt_colors_map[$cours['nom_matiere']] ?? $default_course_color; // Assigner une couleur
                        $emploi_du_temps_semaine[$jour][$heure_debut_creneau] = $cours;
                        break; // Un seul cours par créneau dans cette logique simplifiée
                    }
                }
            }
        }

    } catch (PDOException $e) {
        error_log("Erreur EDT semaine public : " . $e->getMessage());
    }
}

$edt_colors_map = [ // Vous pouvez enrichir cette map ou la rendre dynamique
    'JSF' => '#0d6efd', 'Web Design' => '#198754', 'Ionic Platform' => '#6f42c1',
    'Mathématiques' => '#ffc107', 'Physique' => '#dc3545', 'Anglais' => '#fd7e14',
    'Algorithmique' => '#20c997', 'Base de données' => '#6610f2',
];
$default_course_color = '#6c757d';


// --- Annonces et Filières (logique identique) ---
$stmt_annonces = $db->prepare("SELECT id, titre, contenu, date_creation FROM notifications WHERE visible = TRUE ORDER BY date_creation DESC LIMIT 5");
$stmt_annonces->execute();
$annonces = $stmt_annonces->fetchAll(PDO::FETCH_ASSOC);

$stmt_filieres = $db->prepare("SELECT id, nom_filiere FROM filieres ORDER BY nom_filiere ASC");
$stmt_filieres->execute();
$filieres_db = $stmt_filieres->fetchAll(PDO::FETCH_ASSOC);
$filieres_display_data_config = [
    'Computer Science' => ['icon' => 'fas fa-laptop-code', 'color' => '#007bff', 'nom_affiche' => 'Computer Science'],
    'Business Administration' => ['icon' => 'fas fa-briefcase', 'color' => '#ff8c00', 'nom_affiche' => 'Business Administration'],
    'Electrical Engineering' => ['icon' => 'fas fa-bolt', 'color' => '#28a745', 'nom_affiche' => 'Electrical Engineering'],
    'Marketing' => ['icon' => 'fas fa-bullhorn', 'color' => '#dc3545', 'nom_affiche' => 'Marketing'],
    'Graphic Design' => ['icon' => 'fas fa-paint-brush', 'color' => '#6f42c1', 'nom_affiche' => 'Graphic Design'],
    'Automotive Engineering' => ['icon' => 'fas fa-car', 'color' => '#17a2b8', 'nom_affiche' => 'Automotive Engineering'],
    'Civil Engineering' => ['icon' => 'fas fa-hard-hat', 'color' => '#fd7e14', 'nom_affiche' => 'Civil Engineering'],
    'Data Science' => ['icon' => 'fas fa-chart-line', 'color' => '#007bff', 'nom_affiche' => 'Data Science'],
    'Network Security' => ['icon' => 'fas fa-shield-alt', 'color' => '#e83e8c', 'nom_affiche' => 'Network Security'],
    'Economics' => ['icon' => 'fas fa-landmark', 'color' => '#ffc107', 'nom_affiche' => 'Economics'],
    'Biotechnology' => ['icon' => 'fas fa-flask', 'color' => '#20c997', 'nom_affiche' => 'Biotechnology'],
    'Education' => ['icon' => 'fas fa-graduation-cap', 'color' => '#6610f2', 'nom_affiche' => 'Éducation'],
];
$filieres_final_display = [];
foreach ($filieres_db as $f_db) {
    if (isset($filieres_display_data_config[$f_db['nom_filiere']])) {
        $filieres_final_display[$f_db['nom_filiere']] = array_merge($f_db, $filieres_display_data_config[$f_db['nom_filiere']]);
    } else {
         $filieres_final_display[$f_db['nom_filiere']] = array_merge($f_db, ['icon' => 'fas fa-university', 'color' => '#6c757d', 'nom_affiche' => $f_db['nom_filiere']]);
    }
}
$filiere_count = count($filieres_final_display);
if ($filiere_count < 12) {
    foreach ($filieres_display_data_config as $nom_filiere_key => $data) {
        if ($filiere_count >= 12) break;
        if (!isset($filieres_final_display[$nom_filiere_key])) {
            $filieres_final_display[$nom_filiere_key] = [
                'id' => crc32($nom_filiere_key),
                'nom_filiere' => $nom_filiere_key,
                'icon' => $data['icon'],
                'color' => $data['color'],
                'nom_affiche' => $data['nom_affiche']
            ];
            $filiere_count++;
        }
    }
}
$filieres_final_display = array_slice(array_values($filieres_final_display), 0, 12);

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accueil - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap');

        :root {
            --primary-bg: #1a0759;
            --header-bg: #ff8c00;
            --header-text-color: #ffffff;
            --card-bg: #ffffff;
            --text-light: #ffffff;
            --text-dark: #333333;
            --text-muted: #6c757d; /* Gris plus standard pour muted */
            --accent-blue: #007bff;
            --accent-green: #28a745;
            --accent-purple: #6f42c1;
            --filiere-card-hover-brightness: 1.1;
            --timetable-border-color: #e9ecef; /* Bordure plus claire pour la grille */
        }

        html { box-sizing: border-box; font-size: 16px; }
        *, *:before, *:after { box-sizing: inherit; }

        body {
            margin: 0;
            font-family: 'Roboto', sans-serif;
            background-color: var(--primary-bg);
            color: var(--text-light);
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        .page-container { display: flex; flex-direction: column; flex-grow: 1; width: 100%; overflow-x: hidden; }

        .site-header {
            background-color: var(--header-bg); color: var(--header-text-color); padding: 0.625rem 1.5rem;
            display: flex; justify-content: space-between; align-items: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2); position: sticky; top: 0; z-index: 1000; flex-wrap: wrap;
        }
        .site-header .header-left, .site-header .header-right { display: flex; align-items: center; flex-wrap: wrap; }
        .site-header .round-logo-container { margin-right: 1.25rem; }
        .site-header .round-logo { height: 2.8125rem; width: 2.8125rem; border-radius: 50%; object-fit: cover; border: 2px solid var(--header-text-color); }
        .site-header nav ul { list-style: none; padding: 0; margin: 0; display: flex; flex-wrap: wrap; }
        .site-header nav ul li { margin-right: 1.5rem; margin-bottom: 0.3rem; /* Pour mobile si wrap */ }
        .site-header nav ul li:last-child { margin-right: 0; }
        .site-header nav ul li a { text-decoration: none; color: var(--header-text-color); font-weight: 500; font-size: 1.05em; transition: opacity 0.3s; white-space: nowrap; }
        .site-header nav ul li a:hover, .site-header nav ul li a.active { opacity: 0.8; }
        .site-header .header-right { margin-left: auto; }
        .site-header .user-greeting { margin-right: 0.75rem; font-weight: 500; font-size: 1em; color: var(--header-text-color); text-align: right; }
        .site-header .user-greeting .user-role-label { display: block; font-size: 0.8em; opacity: 0.8; margin-top: -0.125rem; }
        .site-header .profile-pic { height: 2.8125rem; width: 2.8125rem; border-radius: 50%; object-fit: cover; border: 2px solid var(--header-text-color); cursor: pointer; margin-left: 0.5rem; }
        .site-header .auth-links-header a { margin-left: 0.9375rem; text-decoration: none; color: var(--header-text-color); font-weight: 500; padding: 0.375rem 0.625rem; border-radius: 5px; font-size: 0.95em; border: 1px solid var(--header-text-color); transition: background-color 0.2s, color 0.2s; white-space: nowrap; }
        .site-header .auth-links-header a:hover { background-color: var(--header-text-color); color: var(--header-bg); }

        .main-content { flex-grow: 1; padding: 1.875rem; max-width: 1300px; width: 100%; margin: 0 auto; box-sizing: border-box; }
        .section-title { font-size: clamp(1.5em, 4vw, 2em); font-weight: 700; margin-bottom: 1.5rem; color: var(--text-light); }
        
        .timetable-section { background-color: var(--card-bg); padding: 1.5rem; border-radius: 0.9375rem; margin-bottom: 1.875rem; box-shadow: 0 5px 15px rgba(0,0,0,0.1); color: var(--text-dark); overflow-x: auto; }
        .timetable-grid-container { min-width: 800px; /* Augmenté pour 6 jours */ }
        .timetable-grid {
            display: grid;
            /* Colonne pour les heures + 6 colonnes pour les jours */
            grid-template-columns: minmax(100px, auto) repeat(<?php echo count($jours_semaine); ?>, 1fr);
            gap: 1px;
            background-color: var(--timetable-border-color);
            border: 1px solid var(--timetable-border-color);
            border-radius: 8px;
            overflow: hidden;
        }
        .timetable-header, .timetable-time-slot, .timetable-cell {
            background-color: var(--card-bg); padding: 0.6rem 0.5rem; /* Padding ajusté */
            text-align: center; font-weight: 500; font-size: 0.85em; /* Police plus petite pour plus de contenu */
            border-bottom: 1px solid var(--timetable-border-color);
            border-right: 1px solid var(--timetable-border-color);
        }
        .timetable-grid > *:nth-child(<?php echo count($jours_semaine) + 1; ?>n) { border-right: none; } /* Pas de bordure à droite pour la dernière colonne */
        .timetable-grid > div:nth-last-child(-n+<?php echo count($jours_semaine) + 1; ?>) { border-bottom: none; } /* Pas de bordure en bas pour la dernière ligne */


        .timetable-header { color: var(--text-muted); text-transform: capitalize; }
        .timetable-time-slot { display: flex; align-items: center; justify-content: center; font-weight: bold; background-color: #f8f9fa; }
        .timetable-cell { min-height: 3.75rem; display: flex; align-items: center; justify-content: center; padding: 0.25rem; }
        .course-block {
            padding: 0.4rem 0.5rem; border-radius: 5px; color: var(--text-light);
            font-weight: 500; font-size: 0.8em; text-align: center; width: 100%; height: 100%;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            line-height: 1.2; overflow: hidden; text-overflow: ellipsis;
        }
        .course-block small { font-size: 0.85em; opacity: 0.8; display: block; margin-top: 0.1rem;}


        .announcements-section { margin-bottom: 1.875rem; }
        /* ... (Styles pour annonces et filières identiques à la réponse précédente) ... */
        .announcement-item { background-color: var(--accent-blue); color: var(--text-light); padding: 0.9375rem 1.25rem; border-radius: 0.625rem; margin-bottom: 0.9375rem; display: flex; align-items: center; justify-content: space-between; transition: background-color 0.3s; text-decoration: none; flex-wrap: wrap; }
        .announcement-item:hover { background-color: #0056b3; }
        .announcement-item .icon-area { background-color: rgba(255,255,255,0.2); border-radius: 50%; min-width: 35px; height: 35px; display: flex; align-items: center; justify-content: center; margin-right: 0.9375rem; font-weight: bold; }
        .announcement-item .icon-area i { font-size: 1.2em; }
        .announcement-item .content-area { flex-grow: 1; min-width: 0; }
        .announcement-item .content-area .title { font-weight: 700; font-size: 1.1em; margin-bottom: 0.1875rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .announcement-item .content-area .excerpt { font-size: 0.9em; opacity: 0.9; display: -webkit-box; -webkit-line-clamp: 1; -webkit-box-orient: vertical; overflow: hidden; text-overflow: ellipsis; }
        .announcement-item .arrow-area { margin-left: 0.625rem; }
        .announcement-item .arrow-area i { font-size: 1.2em; }

        .filieres-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 1.25rem; }
        .filiere-card { padding: 1.5rem 1.25rem; border-radius: 0.9375rem; color: var(--text-light); text-align: center; display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 10rem; cursor: pointer; transition: transform 0.3s ease, filter 0.3s ease; text-decoration: none; }
        .filiere-card:hover { transform: translateY(-5px) scale(1.03); filter: brightness(var(--filiere-card-hover-brightness)); }
        .filiere-card i { font-size: 2.8em; margin-bottom: 0.9375rem; opacity: 0.9; }
        .filiere-card span { font-weight: 700; font-size: 1.05em; line-height: 1.3;}


        .site-footer { background-color: #0f0538; padding: 1.25rem 1.875rem; display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; color: var(--text-muted); font-size: 0.9em; border-top: 1px solid rgba(255,255,255,0.1); }
        .site-footer .contact-info { margin-bottom: 0.625rem; }
        .site-footer .contact-info i { margin-right: 0.5rem; }
        .site-footer .copyright { margin-bottom: 0.625rem; text-align: center; flex-grow: 1; }
        .site-footer .social-links a { color: var(--text-muted); margin-left: 0.9375rem; font-size: 1.3em; transition: color 0.3s; }
        .site-footer .social-links a:hover { color: var(--text-light); }


        @media (max-width: 1200px) { /* Grands écrans / tablettes paysage */
            .main-content { padding: 1.5rem; }
            .filieres-grid { grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); }
            .timetable-grid { grid-template-columns: minmax(90px, auto) repeat(<?php echo count($jours_semaine); ?>, minmax(100px, 1fr)); }
        }

        @media (max-width: 992px) { /* Tablettes */
            .site-header nav ul li { margin-right: 1rem; }
            .site-header nav ul li a { font-size: 1em; }
            .timetable-grid { font-size: 0.8em; } /* Réduire un peu la police pour que ça tienne */
            .timetable-header, .timetable-time-slot, .timetable-cell { padding: 0.5rem 0.3rem; }
            .course-block { font-size: 0.75em; padding: 0.25rem;}
        }

        @media (max-width: 768px) { /* Petites tablettes et grands téléphones */
            .site-header { flex-direction: column; padding: 1rem; }
            .site-header .header-left, .site-header .header-right { width: 100%; justify-content: space-between; }
            .site-header .header-left { margin-bottom: 0.625rem; }
            .site-header nav { width: 100%; overflow-x: auto; }
            .site-header nav ul { justify-content: flex-start; flex-wrap: nowrap; }
            .site-header nav ul li { margin: 0 0.5rem; }
            .site-header .user-greeting { display: none; } 
            .section-title { font-size: 1.6em; }
            .timetable-grid-container { min-width: 100%; /* Permettre à la grille de s'adapter plus */ }
             .timetable-grid {
                grid-template-columns: minmax(70px, auto) repeat(<?php echo count($jours_semaine); ?>, minmax(80px, 1fr));
                font-size: 0.75em;
            }
            .timetable-header, .timetable-time-slot, .timetable-cell { padding: 0.4rem 0.2rem; }
            .course-block { font-size: 0.7em; }


            .announcement-item .content-area .title { font-size: 1em; }
            .announcement-item .content-area .excerpt { font-size: 0.85em; }
            .filieres-grid { grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 1rem; }
            .filiere-card { min-height: 9rem; padding: 1rem; }
            .filiere-card i { font-size: 2.2em; margin-bottom: 0.625rem; }
            .filiere-card span { font-size: 0.95em; }
            .site-footer { flex-direction: column; text-align: center; }
            .site-footer > div { margin-bottom: 0.625rem; }
            .site-footer .social-links a { margin: 0 0.625rem; }
        }

        @media (max-width: 480px) { /* Petits téléphones */
            .site-header { padding: 0.5rem 0.75rem; }
            .site-header .round-logo-container { margin-right: 0.5rem;}
            .site-header .round-logo, .site-header .profile-pic { height: 35px; width: 35px; }
            .site-header nav ul li { margin-right: 0.75rem; }
            .site-header nav ul li a { font-size: 0.85em; }
            .site-header .auth-links-header a { font-size: 0.8em; padding: 0.25rem 0.5rem;}
            .section-title { font-size: 1.4em; }
            .main-content { padding: 1rem; }
            .timetable-section { padding: 0.5rem; }
            .timetable-grid {
                grid-template-columns: minmax(60px, auto) repeat(<?php echo count($jours_semaine); ?>, minmax(70px, 1fr));
                font-size: 0.7em;
            }
            .timetable-header, .timetable-time-slot, .timetable-cell { padding: 0.3rem 0.15rem; }
            .course-block { font-size: 0.65em; padding: 0.2rem; }
            .filieres-grid { grid-template-columns: 1fr 1fr; gap: 0.75rem; }
            .filiere-card { min-height: auto; padding: 0.75rem; }
            .filiere-card i { font-size: 2em; }
            .filiere-card span { font-size: 0.85em; }
            .announcement-item { padding: 0.75rem 1rem; }
            .announcement-item .icon-area { min-width: 30px; height: 30px; margin-right: 0.5rem; }
            .announcement-item .content-area .title { font-size: 0.9em; }
        }
    </style>
</head>
<body>
    <div class="page-container">
        <header class="site-header">
            <div class="header-left">
                <div class="round-logo-container">
                    <img src="<?php echo SITE_URL; ?>/assets/images/logo.png" alt="Logo <?php echo SITE_NAME; ?>" class="round-logo">
                </div>
                <nav>
                    <ul>
                        <li><a href="<?php echo SITE_URL; ?>/Accueil.php" class="active">Accueil</a></li>
                        <?php if ($is_user_connected): ?>
                            <li><a href="<?php echo $dashboard_link; ?>">Tableau de bord</a></li>
                            <?php if ($mes_cours_link_visible): ?>
                                <li><a href="<?php echo SITE_URL; ?>/dashboard/etudiant/mes_cours.php">Mes Cours</a></li>
                            <?php endif; ?>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
            <div class="header-right">
                <?php if ($is_user_connected): ?>
                    <div class="user-greeting">
                        <?php echo htmlspecialchars($user_name_display); ?>
                        <span class="user-role-label">Profile</span>
                    </div>
                    <a href="<?php echo $profile_page_link; ?>">
                        <img src="<?php echo $user_profile_pic; ?>" alt="Photo de profil" class="profile-pic">
                    </a>
                <?php else: ?>
                    <div class="auth-links-header">
                        <a href="<?php echo SITE_URL; ?>/index.php">Connexion</a>
                        <a href="<?php echo SITE_URL; ?>/register.php">S'inscrire</a>
                    </div>
                <?php endif; ?>
            </div>
        </header>

        <main class="main-content">
            <section class="timetable-section">
                <h2 class="section-title" style="color: var(--text-dark);">Emploi du temps 
                    <small>(Classe Publique ID: <?php echo htmlspecialchars(ID_CLASSE_EMPLOI_DU_TEMPS_PUBLIC); ?>)</small>
                </h2>
                <div class="timetable-grid-container">
                    <div class="timetable-grid">
                        <!-- Première cellule vide pour le coin supérieur gauche -->
                        <div class="timetable-header">Heures</div>
                        <?php foreach ($jours_semaine as $jour): ?>
                            <div class="timetable-header"><?php echo $jour; ?></div>
                        <?php endforeach; ?>

                        <?php foreach ($edt_lignes_heures_labels as $index_heure => $heure_label): ?>
                            <div class="timetable-time-slot"><?php echo $heure_label; ?></div>
                            <?php 
                            $heure_debut_creneau_actuel = $creneaux_horaires_debut[$index_heure]; // Heure de début pour ce créneau (ex: 08:00)
                            foreach ($jours_semaine as $jour): ?>
                                <div class="timetable-cell">
                                    <?php
                                    if (isset($emploi_du_temps_semaine[$jour][$heure_debut_creneau_actuel])) {
                                        $cours = $emploi_du_temps_semaine[$jour][$heure_debut_creneau_actuel];
                                        echo '<div class="course-block" style="background-color: ' . ($cours['color'] ?? $default_course_color) . ';">';
                                        echo htmlspecialchars($cours['nom_matiere']);
                                        // Optionnel: afficher l'heure exacte du cours si différente du label du créneau
                                        // echo '<small>' . htmlspecialchars($cours['heure_debut_formatted']) . '-' . htmlspecialchars($cours['heure_fin_formatted']) . '</small>';
                                        echo '</div>';
                                    }
                                    ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
                 <?php if (empty($cours_semaine_brut) && defined('ID_CLASSE_EMPLOI_DU_TEMPS_PUBLIC')): ?>
                     <p style="margin-top: 15px; text-align:center;">Aucun cours programmé pour la classe publique (ID: <?php echo htmlspecialchars(ID_CLASSE_EMPLOI_DU_TEMPS_PUBLIC); ?>) cette semaine.</p>
                <?php endif; ?>
            </section>

            <section class="announcements-section">
                <h2 class="section-title">Annonces</h2>
                <?php if (!empty($annonces)): ?>
                    <?php $annonce_idx = 1; foreach ($annonces as $annonce): ?>
                        <a href="<?php echo SITE_URL; ?>/annonce_detail.php?id=<?php echo $annonce['id']; ?>" class="announcement-item">
                            <div class="icon-area"><?php echo $annonce_idx++; ?></div>
                            <div class="content-area">
                                <span class="title"><?php echo htmlspecialchars($annonce['titre']); ?></span>
                                <span class="excerpt"><?php echo htmlspecialchars(mb_substr($annonce['contenu'], 0, 80)) . (mb_strlen($annonce['contenu']) > 80 ? '...' : ''); ?></span>
                            </div>
                            <div class="arrow-area"><i class="fas fa-chevron-right"></i></div>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="background-color: rgba(0,123,255,0.1); color: #007bff; padding: 15px; border-radius: 8px;">Aucune annonce pour le moment.</div>
                <?php endif; ?>
            </section>

            <section class="filieres-section">
                <h2 class="section-title">Filières de formations</h2>
                <?php if (!empty($filieres_final_display)): ?>
                    <div class="filieres-grid">
                        <?php foreach ($filieres_final_display as $filiere): ?>
                            <a href="<?php echo SITE_URL; ?>/filiere_details.php?id=<?php echo $filiere['id']; ?>" class="filiere-card" style="background-color: <?php echo $filiere['color']; ?>;">
                                <i class="<?php echo $filiere['icon']; ?>"></i>
                                <span><?php echo htmlspecialchars($filiere['nom_affiche']); ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div style="background-color: rgba(0,123,255,0.1); color: #007bff; padding: 15px; border-radius: 8px;">Aucune filière de formation disponible pour le moment.</div>
                <?php endif; ?>
            </section>
        </main>

        <footer class="site-footer">
            <div class="contact-info"><i class="fas fa-phone-alt"></i> 70 336 29 64</div>
            <div class="copyright">© <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. Tous droits réservés.</div>
            <div class="social-links">
                <a href="#" target="_blank" title="Twitter"><i class="fab fa-twitter"></i></a>
                <a href="#" target="_blank" title="Facebook"><i class="fab fa-facebook-f"></i></a>
                <a href="#" target="_blank" title="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
            </div>
        </footer>
    </div>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const currentLocation = window.location.href;
        const navLinks = document.querySelectorAll('.site-header nav ul li a');
        navLinks.forEach(link => {
            if (link.href === currentLocation ||
                (link.pathname.includes('Accueil.php') && (currentLocation.endsWith('/') || currentLocation.pathname.endsWith('/Accueil.php') || currentLocation.pathname.endsWith('/index.php') && link.pathname.includes('Accueil.php')))
            ) {
                link.classList.add('active');
            }
        });
    });
</script>
</body>
</html>