<?php
// /dashboard/etudiant/index.php

require_once '../../config/db.php'; // Connexion à la base de données

$database = new Database();
$conn = $database->connect();

// --- Valeur d'étudiant utilisée par défaut (aucune session requise) ---
$student_session_id = 1; // ID d'étudiant à afficher

// --- Fonctions ---
function get_student_info($db_conn, $student_id) {
    try {
        $stmt = $db_conn->prepare("
            SELECT e.id as etudiant_id, e.matricule, e.id_classe, 
                   u.id as utilisateur_id, u.nom, u.prenom, u.email, u.photo
            FROM etudiants e
            JOIN utilisateurs u ON e.id_utilisateur = u.id
            WHERE e.id = :student_id
        ");
        $stmt->bindParam(':student_id', $student_id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur get_student_info: " . $e->getMessage());
        return false;
    }
}

function get_active_courses_count_for_student($db_conn, $id_classe) {
    if (!$id_classe) return 0;
    $stmt = $db_conn->prepare("SELECT COUNT(DISTINCT id_matiere) FROM emplois_temps WHERE id_classe = :id_classe");
    $stmt->bindParam(':id_classe', $id_classe, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchColumn();
}

function get_new_notes_count_for_student($db_conn, $id_etudiant) {
    $stmt = $db_conn->prepare("
        SELECT COUNT(*) FROM notes 
        WHERE id_etudiant = :id_etudiant AND date_saisie >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    ");
    $stmt->bindParam(':id_etudiant', $id_etudiant, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchColumn();
}

function get_pending_assignments_count_for_student($db_conn, $id_etudiant, $id_classe) {
    if (!$id_classe) return 0;
    $stmt = $db_conn->prepare("
        SELECT COUNT(d.id)
        FROM devoirs d
        JOIN matieres m ON d.id_matiere = m.id
        LEFT JOIN devoirs_remis dr ON d.id = dr.id_devoir AND dr.id_etudiant = :id_etudiant
        WHERE m.id_classe = :id_classe AND dr.id IS NULL AND d.date_limite >= CURDATE()
    ");
    $stmt->bindParam(':id_etudiant', $id_etudiant, PDO::PARAM_INT);
    $stmt->bindParam(':id_classe', $id_classe, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchColumn();
}

function get_unread_messages_count_for_student($db_conn, $id_utilisateur) {
    // À adapter si tu as une vraie table de messages
    return 2; // Valeur temporaire
}

function get_videos_available_count_for_student($db_conn, $id_classe) {
    if (!$id_classe) return 0;
    $stmt = $db_conn->prepare("
        SELECT COUNT(cel.id)
        FROM cours_en_ligne cel
        JOIN matieres m ON cel.id_matiere = m.id
        WHERE m.id_classe = :id_classe
    ");
    $stmt->bindParam(':id_classe', $id_classe, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchColumn();
}

// --- Récupération des données ---
$student_info = get_student_info($conn, $student_session_id);

if (!$student_info) {
    die("Erreur : Étudiant non trouvé. <a href='../../index.php'>Retour</a>");
}

$student_name = htmlspecialchars($student_info['prenom'] . ' ' . $student_info['nom']);
$student_avatar = !empty($student_info['photo'])
    ? '../../uploads/profiles/' . htmlspecialchars($student_info['photo'])
    : '../../assets/images/default_avatar.png';

$id_etudiant = $student_info['etudiant_id'];
$id_classe = $student_info['id_classe'];
$id_utilisateur = $student_info['utilisateur_id'];

// --- Statistiques ---
$stat_total_cours = get_active_courses_count_for_student($conn, $id_classe);
$stat_nouvelles_notes = get_new_notes_count_for_student($conn, $id_etudiant);
$stat_td_a_remettre = get_pending_assignments_count_for_student($conn, $id_etudiant, $id_classe);
$stat_messages_non_lus = get_unread_messages_count_for_student($conn, $id_utilisateur);
$stat_videos_disponibles = get_videos_available_count_for_student($conn, $id_classe);

$stat_prochain_cours = "Demain 08:00";
$stat_devoirs_postes = 3;

$page_title = "Tableau de Bord Étudiant";
$current_page = basename($_SERVER['PHP_SELF']);
?>


<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - SkillSet</title>
    <link rel="stylesheet" href="../../assets/css/style.css"> <!-- Assurez-vous que ce chemin est correct -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Variables de Thème (identiques à votre code précédent) */
        :root {
            --primary-color: #0066FF;
            --primary-light: #D6E8FF;
            --secondary-color: #F0F7FF;
            --page-bg: #EAF3FF;
            --sidebar-bg: #FFFFFF;
            --card-bg: #FFFFFF;
            --text-color: #1E3A5F;
            --text-light: #5A7CA8;
            --border-color: #C7DAF5;
            --accent-color: #0066FF;
            --font-family: 'Inter', sans-serif;
            --star-color: #3399FF;
            --btn-order-bg: #D6E8FF;
            --btn-order-text: #0066FF;
            --btn-order-border: #0066FF;
        }
        body.dark-theme {
            --primary-color: #4D94FF;
            --primary-light: #1A3F75;
            --secondary-color: #10243D;
            --page-bg: #0A192F;
            --sidebar-bg: #10243D;
            --card-bg: #163357;
            --text-color: #EAF3FF;
            --text-light: #A8C7F0;
            --border-color: #2A4F7A;
            --accent-color: #4D94FF;
            --star-color: #66B2FF;
        }

        /* Styles Généraux (identiques) */
        body {
            font-family: var(--font-family); margin: 0; background-color: var(--page-bg);
            color: var(--text-color); display: flex; min-height: 100vh;
            transition: background-color 0.3s, color 0.3s;
        }
        .dashboard-container { display: flex; width: 100%; }

        /* Barre Latérale Gauche (identique) */
        .sidebar {
            width: 260px; background-color: var(--sidebar-bg); padding: 20px;
            box-shadow: 2px 0 10px rgba(0,0,0,0.05); display: flex; flex-direction: column;
            height: 100vh; position: fixed; left: 0; top: 0; z-index: 1000; /* S'assurer qu'elle est au-dessus */
            transition: background-color 0.3s;
        }
        .sidebar-logo { display: flex; align-items: center; font-size: 24px; font-weight: 700; color: var(--primary-color); margin-bottom: 30px; }
        .sidebar-logo i { margin-right: 10px; }
        .sidebar-nav ul { list-style: none; padding: 0; margin: 0; flex-grow: 1; }
        .sidebar-nav li a {
            display: flex; align-items: center; padding: 12px 15px; color: var(--text-light);
            text-decoration: none; border-radius: 8px; margin-bottom: 8px; font-weight: 500;
        }
        .sidebar-nav li a i { margin-right: 12px; width: 20px; }
        .sidebar-nav li a:hover, .sidebar-nav li a.active { background-color: var(--primary-light); color: var(--primary-color); }
        .sidebar-upgrade { background-color: var(--secondary-color); padding: 15px; border-radius: 8px; text-align: center; margin-top: auto; }
        .sidebar-upgrade h4 { margin-top: 0; font-size: 14px; color: var(--text-color); }
        .sidebar-upgrade p { font-size: 12px; color: var(--text-light); margin-bottom: 15px;}
        .sidebar-upgrade .btn-upgrade { background-color: var(--primary-color); color: white; padding: 10px 15px; border: none; border-radius: 8px; text-decoration: none; display: block; font-weight: 500; }
        .sidebar-upgrade .btn-upgrade i { margin-left: 5px; }

        /* Conteneur Principal (DROITE DE LA SIDEBAR GAUCHE) */
        .main-wrapper {
            flex-grow: 1;
            margin-left: 300px; /* Important: Doit correspondre à la largeur de .sidebar */
            display: flex;
            flex-direction: column;
            width: calc(100% - 260px); /* S'assurer que la largeur est correcte */
        }

        /* Barre Supérieure (identique) */
        .top-bar {
            background-color: var(--sidebar-bg); padding: 15px 30px; display: flex; justify-content: space-between;
            align-items: center; border-bottom: 1px solid var(--border-color); height: 70px; box-sizing: border-box;
            transition: background-color 0.3s, border-color 0.3s;
        }
        /* ... autres styles de .top-bar ... */
        .search-bar { position: relative; }
        .search-bar input {
            padding: 10px 15px 10px 35px; border-radius: 8px; border: 1px solid var(--border-color);
            width: 300px; background-color: var(--secondary-color); color: var(--text-color);
        }
        .search-bar i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-light); }
        .top-bar-actions { display: flex; align-items: center; }
        .top-bar-actions .btn-live { background-color: var(--accent-color); color: white; padding: 8px 15px; border-radius: 8px; text-decoration: none; margin-right: 15px; font-weight: 500; }
        .top-bar-actions .icon-btn { color: var(--text-light); font-size: 20px; margin-left: 20px; text-decoration: none; cursor: pointer; }
        .user-avatar img { width: 36px; height: 36px; border-radius: 50%; margin-left: 20px; object-fit: cover; }


        /* Zone de Contenu (sous la top-bar, dans main-wrapper) */
        .content-area {
            flex-grow: 1;
            padding: 30px;
            display: flex; /* Pour main-column et right-sidebar côte à côte */
            gap: 30px;
            overflow-y: auto; /* Permet le défilement si le contenu est trop long */
        }

        /* Colonne Principale (Contenu central) */
        .main-column {
            flex-grow: 1; /* Prend l'espace restant */
            display: flex;
            flex-direction: column;
            gap: 30px; /* Espace entre les sections de la colonne principale */
        }

        /* Barre Latérale Droite (Widget Area) */
        .right-sidebar {
            width: 300px; /* Largeur fixe */
            flex-shrink: 0; /* Ne pas réduire */
            display: flex;
            flex-direction: column;
            gap: 20px; /* Espace entre les widgets */
        }
        
        /* Bannière de Bienvenue (identique) */
        .welcome-banner {
            background: linear-gradient(100deg, #0066FF 0%, #3399FF 100%); color: white; padding: 30px;
            border-radius: 12px; display: flex; justify-content: space-between; align-items: center;
            /* margin-bottom: 30px; Supprimé car géré par le gap de .main-column */
        }
        body.dark-theme .welcome-banner { background: linear-gradient(100deg, #004FCC 0%, #0066FF 100%); }
        .welcome-banner-text h2 { margin-top: 0; font-size: 28px; font-weight: 600; }
        .welcome-banner-text p { font-size: 14px; opacity: 0.9; margin-bottom: 20px; max-width: 400px; }
        .welcome-banner-text .btn-learn-more { background-color: rgba(255,255,255,0.2); color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 500; border: 1px solid rgba(255,255,255,0.3); }
        .welcome-banner-image { display: flex; align-items: flex-end; }
        .welcome-banner-image .book-stack { width: 120px; height: 120px; margin-left: -30px; }
        .welcome-banner-image .book-stack img { width: 100%; height: 100%; object-fit: contain; }
        .welcome-banner-image .book-stack:first-child { margin-left: 0; }

        /* Cartes de Résumé (Nouvelle Section) */
        .summary-cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); /* Grille responsive */
            gap: 20px;
            /* margin-bottom: 30px; Supprimé car géré par le gap de .main-column */
        }
        .summary-card {
            background-color: var(--card-bg);
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            text-align: center;
            transition: background-color 0.3s, transform 0.2s;
            text-decoration: none; /* Si la carte entière est un lien */
            color: var(--text-color); /* Assurer l'héritage de la couleur du texte */
        }
        .summary-card:hover {
            transform: translateY(-5px);
        }
        .summary-card i {
            font-size: 28px;
            color: var(--primary-color);
            margin-bottom: 10px;
        }
        .summary-card .card-value {
            font-size: 24px;
            font-weight: 700;
            color: var(--text-color);
            margin-bottom: 5px;
        }
        .summary-card .card-label {
            font-size: 13px;
            color: var(--text-light);
        }

        /* Sections de Cours (Populaires, En cours - identiques) */
        .course-section { /* margin-bottom: 30px; Supprimé */ }
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .section-header h3 { font-size: 20px; font-weight: 600; margin: 0; color: var(--text-color); }
        .section-header .view-all-link { color: var(--primary-color); text-decoration: none; font-weight: 500; font-size: 14px; }
        .course-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 20px; }
        .course-card { background-color: var(--card-bg); border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); overflow: hidden; transition: background-color 0.3s; }
        .course-card-image { height: 140px; background-color: var(--primary-light); display: flex; align-items: center; justify-content: center; }
        .course-card-image i { font-size: 50px; color: var(--primary-color); }
        .course-card-content { padding: 15px; }
        .course-card-content h4 { font-size: 16px; font-weight: 600; margin: 0 0 5px 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: var(--text-color); }
        .course-card-content p { font-size: 12px; color: var(--text-light); margin: 0; height: 3.6em; overflow: hidden; text-overflow: ellipsis; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; }

        /* Widgets de la Barre Latérale Droite (identiques) */
        .widget { background-color: var(--card-bg); padding: 20px; border-radius: 12px; /* margin-bottom: 20px; Supprimé */ box-shadow: 0 4px 15px rgba(0,0,0,0.05); transition: background-color 0.3s; }
        /* ... autres styles de .widget, .achievement-item, .sales-item ... */
        .widget-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        .widget-header h4 { margin: 0; font-size: 16px; font-weight: 600; color: var(--text-color); }
        .widget-header .toggle-switch { width: 40px; height: 20px; background-color: #ccc; border-radius: 10px; position: relative; cursor: pointer; }
        .widget-header .toggle-switch::after { content: ''; position: absolute; width: 16px; height: 16px; background-color: white; border-radius: 50%; top: 2px; left: 2px; transition: transform 0.3s; }
        .widget-header .toggle-switch.active { background-color: var(--primary-color); }
        .widget-header .toggle-switch.active::after { transform: translateX(20px); }

        .achievement-item { display: flex; align-items: center; margin-bottom: 15px; }
        .achievement-item img { width: 32px; height: 32px; border-radius: 50%; margin-right: 10px;}
        .achievement-info { flex-grow: 1; }
        .achievement-info .progress-text { font-size: 12px; color: var(--text-color); font-weight: 500;}
        .achievement-info .progress-bar { height: 6px; background-color: var(--primary-light); border-radius: 3px; margin-top: 4px; overflow: hidden; }
        .achievement-info .progress-bar div { height: 100%; background-color: var(--primary-color); border-radius: 3px;}
        .achievement-days { font-size: 12px; color: var(--text-light); white-space: nowrap; margin-left: 10px; }

        .sales-item { display: flex; align-items: center; margin-bottom: 15px; }
        .sales-item-icon { width: 40px; height: 40px; border-radius: 8px; background-color: var(--primary-light); display: flex; align-items: center; justify-content: center; margin-right: 12px; color: var(--primary-color); }
        .sales-item-info { flex-grow: 1; }
        .sales-item-info h5 { margin: 0 0 2px 0; font-size: 14px; font-weight: 500; color: var(--text-color); }
        .sales-item-info p { margin: 0; font-size: 12px; color: var(--text-light); }
        .sales-item-info p i.fa-star { color: var(--star-color); }
        .sales-item .btn-order {
            background-color: var(--btn-order-bg); color: var(--btn-order-text);
            border: 1px solid var(--btn-order-border); padding: 6px 12px;
            border-radius: 6px; text-decoration: none; font-size: 12px; font-weight: 500;
        }

    </style>
</head>
<body>
    <div class="dashboard-container">
        <aside class="sidebar">
            <div class="sidebar-logo">
                 <img src="../../assets/images/logo.png" style="width: 50px; height: 50px; border-radius: 50%;">
</i>E-<span style="color: #0066FF;">SCHOOL</span>

            </div> 
            <nav class="sidebar-nav">
                <ul>
                    <li><a href="index.php" class="<?php echo ($current_page == 'index.php') ? 'active' : ''; ?>"><i class="fas fa-tachometer-alt"></i> Tableau de Bord</a></li>
                    <li><a href="mes_cours.php" class="<?php echo ($current_page == 'mes_cours.php') ? 'active' : ''; ?>"><i class="fas fa-book"></i> Mes Cours</a></li>
                    <li><a href="voir_videos.php" class="<?php echo ($current_page == 'voir_videos.php') ? 'active' : ''; ?>"><i class="fas fa-video"></i> Vidéos & Replays</a></li>
                    <li><a href="telecharger_td.php" class="<?php echo ($current_page == 'telecharger_td.php') ? 'active' : ''; ?>"><i class="fas fa-download"></i> Télécharger TD/TC</a></li>
                    <li><a href="remettre_td.php" class="<?php echo ($current_page == 'remettre_td.php') ? 'active' : ''; ?>"><i class="fas fa-upload"></i> Remettre TD/TC</a></li>
                    <li><a href="mes_notes.php" class="<?php echo ($current_page == 'mes_notes.php') ? 'active' : ''; ?>"><i class="fas fa-clipboard-list"></i> Mes Notes</a></li>
                    <li><a href="emploi_temps.php" class="<?php echo ($current_page == 'emploi_temps.php') ? 'active' : ''; ?>"><i class="fas fa-calendar-alt"></i> Emploi du Temps</a></li>
                    <li><a href="messagerie.php" class="<?php echo ($current_page == 'messagerie.php') ? 'active' : ''; ?>"><i class="fas fa-envelope"></i> Messagerie</a></li>
                    <!-- Liens additionnels de l'image originale -->
                    <li><a href="#" class="<?php echo ($current_page == 'etudiants_liste.php') ? 'active' : ''; ?>"><i class="fas fa-users"></i> Autres Étudiants</a></li>
                    <li><a href="#" class="<?php echo ($current_page == 'enseignants_liste.php') ? 'active' : ''; ?>"><i class="fas fa-chalkboard-teacher"></i> Enseignants</a></li>
                    <li><a href="mes_absences.php" class="<?php echo ($current_page == 'mes_absences.php') ? 'active' : ''; ?>"><i class="fas fa-calendar-check"></i> Présence</a></li>
                    <li><a href="paiements.php" class="<?php echo ($current_page == 'paiements.php') ? 'active' : ''; ?>"><i class="fas fa-credit-card"></i> Paiements</a></li>
                    <li><a href="rapports.php" class="<?php echo ($current_page == 'rapports.php') ? 'active' : ''; ?>"><i class="fas fa-chart-bar"></i> Rapports</a></li>
                </ul>
            </nav>
            <div class="sidebar-upgrade">
                <h4>Passer à Pro</h4>
                <p>pour plus de fonctionnalités</p>
                <a href="#" class="btn-upgrade">Mettre à niveau <i class="fas fa-arrow-right"></i></a>
            </div>
        </aside>

        <div class="main-wrapper">
            <header class="top-bar">
                <div class="search-bar">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Rechercher...">
                </div>
                <div class="top-bar-actions">
                    <a href="#" class="btn-live"><i class="fas fa-broadcast-tower"></i> En Direct</a>
                    <a href="#" class="icon-btn"><i class="fas fa-bell"></i></a>
                    <a href="#" class="icon-btn" id="theme-toggle" title="Changer de thème">
                        <i class="fas fa-moon"></i>
                    </a>
                    <a href="profil.php" class="user-avatar" title="Mon profil">
                        <img src="../../assets/images/logo.png">
                    </a>
                </div>
            </header>

            <main class="content-area">
                <div class="main-column">
                    <section class="welcome-banner">
                        <div class="welcome-banner-text">
                            <h2>Bonjour, <?php echo $student_name; ?> !</h2>
                            <p>Bienvenue sur votre tableau de bord. Voici un aperçu de votre activité.</p>
                            <a href="mes_cours.php" class="btn-learn-more">Voir mes cours</a>
                        </div>
                        <div class="welcome-banner-image">
                            <div class="book-stack"><img src="../../assets/images/image1.png" alt="Pile de livres" ></div>
                            <div class="book-stack"><img src="../../assets/images/image2.png" alt="Pile de livres"></div>
                        </div>
                    </section>

                    <section class="summary-cards-grid">
                        <a href="mes_cours.php" class="summary-card">
                            <i class="fas fa-book-open"></i>
                            <div class="card-value"><?php echo $stat_total_cours; ?></div>
                            <div class="card-label">Cours Actifs</div>
                        </a>
                        <a href="mes_notes.php" class="summary-card">
                            <i class="fas fa-star"></i>
                            <div class="card-value"><?php echo $stat_nouvelles_notes; ?></div>
                            <div class="card-label">Nouvelles Notes</div>
                        </a>
                        <a href="remettre_td.php" class="summary-card">
                            <i class="fas fa-tasks"></i>
                            <div class="card-value"><?php echo $stat_td_a_remettre; ?></div>
                            <div class="card-label">TD/TC à Rendre</div>
                        </a>
                        <a href="mes_cours.php?filtre=devoirs" class="summary-card"> <!-- Lien à adapter -->
                            <i class="fas fa-file-alt"></i>
                            <div class="card-value"><?php echo $stat_devoirs_postes; ?></div>
                            <div class="card-label">Devoirs Postés</div>
                        </a>
                        <a href="messagerie.php" class="summary-card">
                            <i class="fas fa-envelope-open-text"></i>
                            <div class="card-value"><?php echo $stat_messages_non_lus; ?></div>
                            <div class="card-label">Messages Non Lus</div>
                        </a>
                        <a href="emploi_temps.php" class="summary-card">
                            <i class="fas fa-calendar-day"></i>
                            <div class="card-value"><?php echo $stat_prochain_cours; ?></div>
                            <div class="card-label">Prochain Cours</div>
                        </a>
                        <a href="voir_videos.php" class="summary-card">
                            <i class="fas fa-play-circle"></i>
                            <div class="card-value"><?php echo $stat_videos_disponibles; ?></div>
                            <div class="card-label">Vidéos Disponibles</div>
                        </a>
                    </section>

                    <!-- Vous pouvez remettre les sections "Populaires" et "En cours" ici si vous le souhaitez -->
                    <!--
                    <section class="course-section popular-courses">
                        ...
                    </section>
                    <section class="course-section ongoing-courses">
                        ...
                    </section>
                    -->
                </div>

                <aside class="right-sidebar">
                    <div class="widget achievement-widget">
                        <div class="widget-header">
                            <h4>Succès débloqués</h4>
                            <div class="toggle-switch" id="achievement-toggle"></div>
                        </div>
                        <p style="font-size: 12px; color: var(--text-light); margin-top: -10px; margin-bottom: 15px;">Objectif atteint, succès déverrouillé.</p>
                        <div class="achievement-item">
                            <img src="../../assets/images/avatar1.png" alt="Utilisateur">
                            <div class="achievement-info">
                                <span class="progress-text">66% Atteint</span>
                                <div class="progress-bar"><div style="width: 66%;"></div></div>
                            </div>
                            <span class="achievement-days">7 Jours restants</span>
                        </div>
                        <div class="achievement-item">
                            <img src="../../assets/images/avatar2.png" alt="Utilisateur">
                            <div class="achievement-info">
                                <span class="progress-text">33% Atteint</span>
                                <div class="progress-bar"><div style="width: 33%;"></div></div>
                            </div>
                            <span class="achievement-days">12 Jours restants</span>
                        </div>
                    </div>

                    <div class="widget best-sales-widget">
                        <div class="widget-header">
                            <h4>Recommandations</h4> <!-- Changé de "Meilleures ventes" -->
                            <a href="#" class="view-all-link">VOIR TOUT</a>
                        </div>
                        <div class="sales-item">
                            <div class="sales-item-icon"><i class="fas fa-lightbulb"></i></div>
                            <div class="sales-item-info">
                                <h5>Astuces de productivité</h5>
                                <p><i class="fas fa-star"></i> 4.8</p>
                            </div>
                            <a href="#" class="btn-order">Consulter</a>
                        </div>
                        <div class="sales-item">
                            <div class="sales-item-icon"><i class="fas fa-comments"></i></div>
                            <div class="sales-item-info">
                                <h5>Forum d'entraide</h5>
                                <p><i class="fas fa-star"></i> Actif</p>
                            </div>
                            <a href="#" class="btn-order">Participer</a>
                        </div>
                    </div>
                </aside>
            </main>
        </div>
    </div>

    <script>
        // Script pour le changement de thème (identique à votre code précédent)
        document.addEventListener('DOMContentLoaded', function () {
            const themeToggle = document.getElementById('theme-toggle');
            const body = document.body;
            const themeIcon = themeToggle.querySelector('i');
            const achievementToggle = document.getElementById('achievement-toggle'); // Si vous l'utilisez

            const currentTheme = localStorage.getItem('theme');
            if (currentTheme) {
                body.classList.add(currentTheme);
                if (currentTheme === 'dark-theme') {
                    themeIcon.classList.remove('fa-moon');
                    themeIcon.classList.add('fa-sun');
                } else {
                    themeIcon.classList.remove('fa-sun');
                    themeIcon.classList.add('fa-moon');
                }
            } else {
                themeIcon.classList.add('fa-moon'); // Default icon for light
            }

            themeToggle.addEventListener('click', function (e) {
                e.preventDefault();
                body.classList.toggle('dark-theme');
                let theme = 'light-theme';
                if (body.classList.contains('dark-theme')) {
                    themeIcon.classList.remove('fa-moon');
                    themeIcon.classList.add('fa-sun');
                    theme = 'dark-theme';
                } else {
                    themeIcon.classList.remove('fa-sun');
                    themeIcon.classList.add('fa-moon');
                }
                localStorage.setItem('theme', theme);
            });

            if(achievementToggle) {
                achievementToggle.addEventListener('click', function() {
                    this.classList.toggle('active');
                });
            }
        });
    </script>
</body>
</html>