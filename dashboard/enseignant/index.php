<?php
// /dashboard/enseignant/index.php

// PAS D'AUTHENTIFICATION COMPLEXE POUR CET EXEMPLE
// if (session_status() == PHP_SESSION_NONE) {
//     session_start();
// }
// require_once '../../includes/auth.php';
// checkAuth('enseignant');

require_once '../../config/db.php'; // Votre classe Database

$database = new Database();
$conn = $database->connect();

// --- ID Enseignant Simulé ---
define('SIMULATED_ENSEIGNANT_ID', 1); // !! IMPORTANT: ID enseignant existant dans la table 'enseignants'
                                     // Cet enseignant doit avoir des matières associées via la table 'matieres'.

// --- Fonctions de récupération de données (spécifiques à l'enseignant) ---
function get_enseignant_info($db_conn, $id_enseignant_table) {
    $stmt = $db_conn->prepare("
        SELECT ens.id as enseignant_id, ens.specialite, 
               u.id as utilisateur_id, u.nom, u.prenom, u.email, u.photo
        FROM enseignants ens
        JOIN utilisateurs u ON ens.id_utilisateur = u.id
        WHERE ens.id = :id_enseignant
    ");
    $stmt->bindParam(':id_enseignant', $id_enseignant_table, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function get_enseignant_matieres($db_conn, $id_enseignant_table) {
    $stmt = $db_conn->prepare("
        SELECT m.id, m.nom_matiere, m.code_matiere, c.nom_classe, c.niveau
        FROM matieres m
        JOIN classes c ON m.id_classe = c.id
        WHERE m.id_enseignant = :id_enseignant
        ORDER BY m.nom_matiere ASC
    ");
    $stmt->bindParam(':id_enseignant', $id_enseignant_table, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_enseignant_cours_du_jour($db_conn, $id_enseignant_table) {
    $jour_actuel_fr = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'][date('w')];
    $stmt = $db_conn->prepare("
        SELECT et.heure_debut, et.heure_fin, m.nom_matiere, c.nom_classe, et.salle
        FROM emplois_temps et
        JOIN matieres m ON et.id_matiere = m.id
        JOIN classes c ON et.id_classe = c.id
        WHERE m.id_enseignant = :id_enseignant AND et.jour_semaine = :jour
        ORDER BY et.heure_debut ASC
    ");
    $stmt->bindParam(':id_enseignant', $id_enseignant_table, PDO::PARAM_INT);
    $stmt->bindParam(':jour', $jour_actuel_fr, PDO::PARAM_STR);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_devoirs_a_corriger_count($db_conn, $id_enseignant_table) {
    // Compte tous les devoirs remis pour les matières de l'enseignant.
    // Une logique plus fine pourrait vérifier si une note a déjà été attribuée.
    $stmt_simple = $db_conn->prepare("
        SELECT COUNT(dr.id) 
        FROM devoirs_remis dr
        JOIN devoirs d ON dr.id_devoir = d.id
        WHERE d.id_enseignant = :id_enseignant_table 
        -- AND dr.id NOT IN (SELECT id_devoir_remis FROM notes WHERE id_devoir_remis IS NOT NULL) -- Si vous aviez une FK directe
    ");
    $stmt_simple->bindParam(':id_enseignant_table', $id_enseignant_table, PDO::PARAM_INT);
    $stmt_simple->execute();
    return $stmt_simple->fetchColumn();
}

function get_cours_en_ligne_a_lancer($db_conn, $id_enseignant_table) {
    $stmt = $db_conn->prepare("
        SELECT titre, date_cours, lien_visionnage 
        FROM cours_en_ligne 
        WHERE id_enseignant = :id_enseignant AND date_cours >= NOW() AND date_cours < DATE_ADD(NOW(), INTERVAL 2 DAY)
        ORDER BY date_cours ASC
        LIMIT 3
    ");
    $stmt->bindParam(':id_enseignant', $id_enseignant_table, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_devoirs_remis_recemment_pour_enseignant($db_conn, $id_enseignant_table, $limit = 3) {
    $stmt = $db_conn->prepare("
        SELECT 
            dr.id as devoir_remis_id, 
            dr.id_devoir,
            dr.id_etudiant, /* C'est etudiants.id */
            dr.date_remise,
            d.titre as titre_devoir,
            m.nom_matiere,
            etu_user.prenom as etudiant_prenom,
            etu_user.nom as etudiant_nom
        FROM devoirs_remis dr
        JOIN devoirs d ON dr.id_devoir = d.id
        JOIN matieres m ON d.id_matiere = m.id
        JOIN etudiants etu ON dr.id_etudiant = etu.id  /* Jointure sur etudiants.id */
        JOIN utilisateurs etu_user ON etu.id_utilisateur = etu_user.id /* Puis sur utilisateurs.id pour le nom */
        WHERE d.id_enseignant = :id_enseignant
        ORDER BY dr.date_remise DESC
        LIMIT :limit_val
    ");
    $stmt->bindParam(':id_enseignant', $id_enseignant_table, PDO::PARAM_INT);
    $stmt->bindParam(':limit_val', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


// --- Logique de la page ---
$enseignant_info = get_enseignant_info($conn, SIMULATED_ENSEIGNANT_ID);

if (!$enseignant_info) {
    die("Erreur: Enseignant simulé non trouvé. Vérifiez SIMULATED_ENSEIGNANT_ID et la base de données.");
}

$enseignant_nom_complet = htmlspecialchars($enseignant_info['prenom'] . ' ' . $enseignant_info['nom']);
$enseignant_avatar = !empty($enseignant_info['photo']) ? '../../uploads/profiles/' . htmlspecialchars($enseignant_info['photo']) : '../../assets/images/default_avatar_teacher.png'; 

$matieres_enseignees = get_enseignant_matieres($conn, SIMULATED_ENSEIGNANT_ID);
$cours_du_jour = get_enseignant_cours_du_jour($conn, SIMULATED_ENSEIGNANT_ID);
$devoirs_a_corriger_count = get_devoirs_a_corriger_count($conn, SIMULATED_ENSEIGNANT_ID);
$notes_a_saisir_count = 5; // Placeholder
$cours_a_lancer = get_cours_en_ligne_a_lancer($conn, SIMULATED_ENSEIGNANT_ID);
$devoirs_remis_recents_dynamiques = get_devoirs_remis_recemment_pour_enseignant($conn, SIMULATED_ENSEIGNANT_ID, 3);

$page_title = "Tableau de Bord Enseignant";
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> E-SCHOOL</title>
    <link rel="stylesheet" href="../../assets/css/style.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css"> 
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Styles identiques à la version précédente */
        :root {
    --primary-color: #0066FF;
    --primary-light: #E6F0FF;
    --primary-dark: #004FCC;

    --secondary-color: #F8FAFC;
    --page-bg: #F4F8FF;
    --sidebar-bg: #FFFFFF;
    --card-bg: #FFFFFF;

    --text-color: #0F172A;
    --text-light: #64748B;
    --border-color: #DCE6F5;

    --accent-blue: #0066FF;
    --status-dot-tech: #0066FF;
    --status-dot-task: #F59E0B;
    --status-dot-review: #10B981;

    --font-family: 'Inter', sans-serif;
}

body.dark-theme {
    --primary-color: #3B82F6;
    --primary-light: #1E3A8A;
    --primary-dark: #2563EB;

    --page-bg: #0F172A;
    --sidebar-bg: #111827;
    --card-bg: #1E293B;
    --secondary-color: #334155;

    --text-color: #F8FAFC;
    --text-light: #CBD5E1;
    --border-color: #475569;
}
        body {
            font-family: var(--font-family); margin: 0; background-color: var(--page-bg);
            color: var(--text-color); display: flex; min-height: 100vh;
            transition: background-color 0.3s, color 0.3s; font-size: 14px;
        }
        .dashboard-container { display: flex; width: 100%; }
        .main-wrapper {
            flex-grow: 1; margin-left: 260px; display: flex; flex-direction: column;
            width: calc(100% - 260px); background-color: var(--page-bg);
        }
        .content-area {
            flex-grow: 1; padding: 24px 30px; display: flex; gap: 24px; overflow-y: auto;
             margin-left: 30px;
        }
        .main-column { flex-grow: 1; display: flex; flex-direction: column; gap: 24px;}
        .right-column-dashboard { width: 320px; flex-shrink: 0; display: flex; flex-direction: column; gap: 24px; }
        .sidebar {
            width: 260px; background-color: var(--sidebar-bg); padding: 24px 16px;
            border-right: 1px solid var(--border-color); display: flex; flex-direction: column;
            height: 100vh; position: fixed; left: 0; top: 0; z-index: 1000;
            transition: background-color 0.3s; overflow-y: auto;
        }
        .sidebar-logo {
            display: flex; align-items: center; padding: 0 8px 24px 8px;
            font-size: 22px; font-weight: 700; color: var(--primary-color);
        }
        .sidebar-logo .logo-icon { margin-right: 10px; }
        .sidebar-nav ul { list-style: none; padding: 0; margin: 0; flex-grow: 1; }
        .sidebar-nav li a {
            display: flex; align-items: center; padding: 10px 12px; color: var(--text-light);
            text-decoration: none; border-radius: 6px; margin-bottom: 4px; font-weight: 500;
            transition: background-color 0.2s, color 0.2s; font-size: 14px;
        }
        .sidebar-nav li a i { margin-right: 12px; width: 20px; text-align: center; font-size: 16px; }
        .sidebar-nav li a:hover { background-color: var(--primary-light); color: var(--primary-color); }
        .sidebar-nav li a.active { background-color: var(--primary-light); color: var(--primary-color); font-weight: 600; }
        .sidebar .logout-link { margin-top: auto; }
        .top-bar {
            background-color: var(--page-bg); padding: 0 30px; display: flex;
            justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color);
            height: 70px; box-sizing: border-box; transition: background-color 0.3s, border-color 0.3s;
            position: sticky; top: 0; z-index: 999;
        }
        .search-bar { position: relative; flex-grow:1; max-width: 400px;}
        .search-bar input {
            padding: 10px 15px 10px 40px; border-radius: 8px; border: 1px solid var(--border-color);
            width: 100%; background-color: var(--secondary-color); color: var(--text-color); font-size: 14px;
        }
        .search-bar i.fa-search { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: var(--text-light); }
        .top-bar-actions { display: flex; align-items: center; gap: 16px; }
        .btn-add-new {
            background-color: var(--primary-color); color: white; padding: 10px 16px; text-decoration: none;
            border-radius: 8px; font-weight: 600; font-size: 14px; display: flex; align-items: center; gap: 8px;
        }
        .btn-add-new:hover { background-color: var(--primary-dark); }
        .btn-add-new i.fa-plus { font-size: 12px; }
        .top-bar-actions .icon-btn {
            color: var(--text-light); font-size: 20px; cursor: pointer; padding: 8px; border-radius: 50%;
            transition: color 0.2s, background-color 0.2s;
        }
        .top-bar-actions .icon-btn:hover { color: var(--primary-color); background-color: var(--primary-light); }
        .user-profile-widget { display: flex; align-items: center; gap: 12px; }
        .user-profile-widget img { width: 40px; height: 40px; border-radius: 50%; object-fit: cover;}
        .user-profile-widget .user-info .user-name { font-weight: 600; color: var(--text-color); display: block;}
        .user-profile-widget .user-info .user-role { font-size: 0.85em; color: var(--text-light); }
        .welcome-banner-teacher {
            background: var(--primary-color); color: white; padding: 24px 30px; border-radius: 12px;
            display: flex; justify-content: space-between; align-items: center;
        }
        .welcome-banner-teacher .text-content h2 { margin-top: 0; font-size: 24px; font-weight: 600; margin-bottom: 4px;}
        .welcome-banner-teacher .text-content p { font-size: 14px; opacity: 0.9; margin-bottom: 16px; max-width: 450px;}
        .welcome-banner-teacher .btn-review {
            background-color: rgba(255,255,255,0.2); color: white; padding: 10px 20px;
            border-radius: 8px; text-decoration: none; font-weight: 500;
            border: 1px solid rgba(255,255,255,0.3);
        }
        .welcome-banner-teacher .banner-illustration img { max-height: 130px; }
        .section-title { font-size: 18px; font-weight: 600; color: var(--text-color); margin-bottom: 16px; }
        .quick-stats-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 20px; }
        .stat-card {
            background-color: var(--card-bg); padding: 20px; border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.03), 0 1px 2px rgba(0,0,0,0.03);
            border: 1px solid var(--border-color);
        }
        .stat-card .card-icon {
            width: 40px; height: 40px; border-radius: 8px; display: flex; align-items: center;
            justify-content: center; margin-bottom: 12px; font-size: 18px;
        }
        .stat-card .card-icon.icon-cours { background-color: #EFEBFA; color: #5534A5; }
        .stat-card .card-icon.icon-notes { background-color: #FEF3E7; color: #B93815; }
        .stat-card .card-icon.icon-devoirs { background-color: #EBF5FF; color: #175CD3; }
        .stat-card .card-icon.icon-live { background-color: #E6FAF0; color: #039855; }
        .stat-card .card-value { font-size: 22px; font-weight: 700; color: var(--text-color); margin-bottom: 4px;}
        .stat-card .card-label { font-size: 13px; color: var(--text-light); }
        .stat-card .card-link { display: block; margin-top:12px; font-size:13px; color:var(--primary-color); text-decoration:none; font-weight:500;}
        .stat-card .card-link i { margin-left: 4px; }
        .list-table-container {
            background-color: var(--card-bg); padding: 0; border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.03), 0 1px 2px rgba(0,0,0,0.03);
            border: 1px solid var(--border-color);
        }
        .list-table-header {
            padding: 16px 20px; border-bottom: 1px solid var(--border-color);
            display: flex; justify-content: space-between; align-items: center;
        }
        .list-table-header h3 { margin: 0; font-size: 16px; font-weight: 600; }
        .list-table-header .view-all-link { color: var(--primary-color); text-decoration: none; font-weight: 500; font-size: 13px;}
        .custom-table { width: 100%; border-collapse: collapse; }
        .custom-table th, .custom-table td { padding: 12px 20px; text-align: left; font-size: 14px;}
        .custom-table thead th {
            color: var(--text-light); font-weight: 500; font-size: 12px; text-transform: uppercase;
            border-bottom: 1px solid var(--border-color); background-color: var(--secondary-color);
        }
        .custom-table tbody tr:not(:last-child) td { border-bottom: 1px solid var(--border-color); }
        .custom-table tbody tr:hover { background-color: var(--primary-light); }
        .custom-table .actions-cell .btn-actions {
            color: var(--text-light); background:none; border:none; cursor:pointer; font-size:18px; padding: 5px;
        }
        .widget-card {
            background-color: var(--card-bg); padding: 20px; border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.03), 0 1px 2px rgba(0,0,0,0.03);
            border: 1px solid var(--border-color);
        }
        .widget-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
        .widget-header h4 { margin: 0; font-size: 16px; font-weight: 600; }
        .widget-header .action-link { color: var(--primary-color); text-decoration: none; font-size: 13px; font-weight: 500;}
        .widget-header .calendar-nav span { cursor: pointer; padding: 0 5px; color: var(--text-light); }
        .calendar-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 8px; text-align: center;}
        .calendar-day { padding: 8px 0; }
        .calendar-day .day-name { font-size: 12px; color: var(--text-light); display: block; margin-bottom: 4px;}
        .calendar-day .day-number {
            font-weight: 600; color: var(--text-color); display: inline-block;
            width: 32px; height: 32px; line-height: 32px; border-radius: 50%;
        }
        .calendar-day .day-number.active { background-color: var(--primary-color); color: white; }
        .devoir-list .devoir-item {
            display: flex; align-items: center; padding: 10px 0;
            border-bottom: 1px solid var(--border-color);
        }
        .devoir-list .devoir-item:last-child { border-bottom: none; }
        .avatar-initial {
            width: 36px; height: 36px; border-radius: 50%; background-color:var(--primary-light);
            color:var(--primary-color); display:flex; align-items:center; justify-content:center;
            font-weight:600; font-size: 14px; margin-right: 12px; flex-shrink:0;
        }
        .devoir-info .titre { font-weight: 500; color: var(--text-color); display: block; font-size:14px; margin-bottom: 2px;}
        .devoir-info .details { font-size: 12px; color: var(--text-light); line-height: 1.4;}
        .devoir-actions { margin-left: auto; display: flex; gap: 8px; align-items:center;}
        .devoir-actions .icon-action { color: var(--text-light); font-size: 18px; padding: 5px; border-radius:50%;}
        .devoir-actions .icon-action:hover { color: var(--primary-color); background-color: var(--primary-light);}
        .training-list .training-item {
            display: flex; align-items: center; gap:12px; padding: 12px 0; text-decoration:none; color:inherit;
        }
        .training-list .training-item:not(:last-child) { border-bottom: 1px solid var(--border-color); }
        .training-list .training-item .avatar-initial { font-size: 16px; } /* Pour les icônes des liens rapides */
        .training-info .name { font-weight: 500; font-size: 14px; }
        .stat-card:hover, .widget-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.05), 0 2px 6px rgba(0,0,0,0.05);
            transition: transform 0.2s ease-out, box-shadow 0.2s ease-out;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <aside class="sidebar">
            <div class="sidebar-logo">
                <i class="fas fa-chalkboard-teacher logo-icon"></i> E-SCHOOL
            </div>
            <nav class="sidebar-nav">
                <ul>
                    <li><a href="index.php" class="active"><i class="fas fa-th-large"></i> Tableau de Bord</a></li>
                    <li><a href="deposer_cours.php"><i class="fas fa-file-medical"></i> Déposer Cours</a></li>
                    <li><a href="enregistrer_cours.php"><i class="fas fa-video"></i> Planifier/Enregistrer Live</a></li>
                    <li><a href="deposer_td.php"><i class="fas fa-tasks"></i> Déposer TD/TC</a></li>
                    <li><a href="noter_etudiants.php"><i class="fas fa-edit"></i> Noter Étudiants</a></li>
                    <li><a href="voir_devoirs.php"><i class="fas fa-eye"></i> Voir Devoirs Remis</a></li>
                    <li><a href="messagerie.php"><i class="fas fa-envelope"></i> Messagerie</a></li>
                    <li><a href="saisie_absences.php"><i class="fas fa-user-check"></i> Saisir Absences</a></li>
                </ul>
            </nav>
            <div class="sidebar-nav logout-link">
                 <ul>
                    <li><a href="../../logout.php"><i class="fas fa-sign-out-alt"></i> Déconnexion</a></li>
                </ul>
            </div>
        </aside>
        
        <div class="main-wrapper">
            <header class="top-bar">
                <div class="search-bar">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Rechercher quelque chose...">
                </div>
                <div class="top-bar-actions">
                    <a href="deposer_cours.php" class="btn-add-new">
                        <i class="fas fa-plus"></i> Nouveau Contenu
                    </a>
                    <a href="#" class="icon-btn" id="theme-toggle" title="Changer de thème">
                        <i class="fas fa-moon"></i>
                    </a>
                     <a href="#" class="icon-btn" title="Notifications">
                        <i class="fas fa-bell"></i>
                    </a>
                    <div class="user-profile-widget">
                        <img src="<?php echo $enseignant_avatar; ?>" alt="Avatar de <?php echo $enseignant_nom_complet; ?>">
                        <div class="user-info">
                            <span class="user-name"><?php echo $enseignant_nom_complet; ?></span>
                            <span class="user-role">Enseignant</span>
                        </div>
                    </div>
                </div>
            </header>

            <main class="content-area">
                <div class="main-column">
                    <section class="welcome-banner-teacher">
                        <div class="text-content">
                            <h2>Bonjour, <?php echo htmlspecialchars($enseignant_info['prenom']); ?> !</h2>
                            <p>Vous avez plusieurs tâches importantes aujourd'hui. Commençons !</p>
                            <a href="voir_devoirs.php" class="btn-review">Voir les devoirs à corriger</a>
                        </div>
                        <div class="banner-illustration">
                            <img src="../../assets/images/teacher_illustration.png" alt="Illustration enseignant">
                        </div>
                    </section>

                    <section>
                        <h3 class="section-title">Aperçu du Jour</h3>
                        <div class="quick-stats-grid">
                            <div class="stat-card">
                                <div class="card-icon icon-cours"><i class="fas fa-calendar-day"></i></div>
                                <div class="card-value"><?php echo count($cours_du_jour); ?></div>
                                <div class="card-label">Cours Aujourd'hui</div>
                                <?php if(!empty($cours_du_jour)): ?>
                                <a href="#cours-aujourdhui" class="card-link">Voir détails <i class="fas fa-arrow-right"></i></a>
                                <?php endif; ?>
                            </div>
                            <div class="stat-card">
                                <div class="card-icon icon-notes"><i class="fas fa-marker"></i></div>
                                <div class="card-value"><?php echo $notes_a_saisir_count; ?></div>
                                <div class="card-label">Notes à Saisir</div>
                                <a href="noter_etudiants.php" class="card-link">Aller à la saisie <i class="fas fa-arrow-right"></i></a>
                            </div>
                            <div class="stat-card">
                                <div class="card-icon icon-devoirs"><i class="fas fa-file-signature"></i></div>
                                <div class="card-value"><?php echo $devoirs_a_corriger_count; ?></div>
                                <div class="card-label">Devoirs à Corriger</div>
                                <a href="voir_devoirs.php" class="card-link">Voir les soumissions <i class="fas fa-arrow-right"></i></a>
                            </div>
                            <div class="stat-card">
                                <div class="card-icon icon-live"><i class="fas fa-headset"></i></div>
                                <div class="card-value"><?php echo count($cours_a_lancer); ?></div>
                                <div class="card-label">Cours en Ligne Prochains</div>
                                <?php if(!empty($cours_a_lancer)): ?>
                                <a href="enregistrer_cours.php" class="card-link">Gérer les sessions <i class="fas fa-arrow-right"></i></a>
                                 <?php endif; ?>
                            </div>
                        </div>
                    </section>

                    <section class="list-table-container" id="cours-aujourdhui">
                        <div class="list-table-header">
                            <h3><i class="fas fa-chalkboard"></i> Vos Cours d'Aujourd'hui</h3>
                            <a href="emploi_temps_enseignant.php" class="view-all-link">Voir l'emploi du temps complet</a>
                        </div>
                        <?php if (empty($cours_du_jour)): ?>
                            <p style="padding: 20px; text-align:center; color: var(--text-light);">Aucun cours prévu pour vous aujourd'hui.</p>
                        <?php else: ?>
                        <table class="custom-table">
                            <thead>
                                <tr><th>Heure</th><th>Matière</th><th>Classe</th><th>Salle</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cours_du_jour as $cours): ?>
                                <tr>
                                    <td><?php echo date('H:i', strtotime($cours['heure_debut'])) . ' - ' . date('H:i', strtotime($cours['heure_fin'])); ?></td>
                                    <td><?php echo htmlspecialchars($cours['nom_matiere']); ?></td>
                                    <td><?php echo htmlspecialchars($cours['nom_classe']); ?></td>
                                    <td><?php echo htmlspecialchars($cours['salle'] ?? 'N/A'); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php endif; ?>
                    </section>

                     <section class="list-table-container">
                        <div class="list-table-header">
                            <h3><i class="fas fa-graduation-cap"></i> Vos Matières Enseignées</h3>
                             <a href="gestion_matieres_enseignant.php" class="view-all-link">Gérer les matières</a>
                        </div>
                        <?php if (empty($matieres_enseignees)): ?>
                             <p style="padding: 20px; text-align:center; color: var(--text-light);">Aucune matière ne vous est assignée pour le moment.</p>
                        <?php else: ?>
                        <table class="custom-table">
                             <thead><tr><th>Matière</th><th>Code</th><th>Classe</th><th>Actions</th></tr></thead>
                             <tbody>
                                <?php foreach ($matieres_enseignees as $matiere): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($matiere['nom_matiere']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($matiere['code_matiere']); ?></td>
                                    <td><?php echo htmlspecialchars($matiere['nom_classe'] . ' (' . $matiere['niveau'] . ')'); ?></td>
                                    <td class="actions-cell">
                                        <a href="voir_cours_details_enseignant.php?id_matiere=<?php echo $matiere['id']; ?>" class="btn-actions" title="Voir détails et ressources">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="deposer_cours.php?id_matiere=<?php echo $matiere['id']; ?>" class="btn-actions" title="Ajouter ressource">
                                            <i class="fas fa-plus-circle"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                             </tbody>
                        </table>
                        <?php endif; ?>
                    </section>
                </div>

                <aside class="right-column-dashboard">
                    <div class="widget-card">
                        <div class="widget-header">
                            <h4><i class="far fa-calendar-alt"></i> Calendrier</h4>
                            <div class="calendar-nav">
                                <span><i class="fas fa-chevron-left"></i></span>
                                <?php
$formatter = new IntlDateFormatter(
    'fr_FR',
    IntlDateFormatter::NONE,
    IntlDateFormatter::NONE,
    'Europe/Paris',
    IntlDateFormatter::GREGORIAN,
    'MMMM yyyy'
);
echo '<strong>' . $formatter->format(new DateTime()) . '</strong>';
?>
</strong>
                                <span><i class="fas fa-chevron-right"></i></span>
                            </div>
                        </div>
                        <div class="calendar-grid">
                            <?php 
                            $days = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven'];
                            // Exemple simple pour afficher les 5 prochains jours ouvrables
                            // Pour un vrai calendrier, utilisez une bibliothèque JS ou une logique PHP plus complexe
                            $current_day_index = date('N') -1; // 0 pour Lundi, 6 pour Dimanche
                            for ($i=0; $i<5; $i++):
                                $timestamp = strtotime("+$i day", strtotime("Monday this week")); // Commence à Lundi de cette semaine
                                if (date('N', $timestamp) > 5) continue; // Saute Samedi/Dimanche si besoin dans la logique
                            ?>
                            <div class="calendar-day">
                                <span class="day-name"><?php echo $days[date('N', $timestamp)-1]; ?></span>
                                <span class="day-number <?php echo (date('Y-m-d') == date('Y-m-d', $timestamp)) ? 'active' : ''; ?>">
                                    <?php echo date('d', $timestamp); ?>
                                </span>
                            </div>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <div class="widget-card">
                        <div class="widget-header">
                            <h4><i class="fas fa-file-import"></i> Devoirs Récemment Remis</h4>
                            <a href="voir_devoirs.php" class="action-link">Voir tout (<?php echo $devoirs_a_corriger_count; ?>)</a>
                        </div>
                        <div class="devoir-list">
                            <?php if (empty($devoirs_remis_recents_dynamiques)): ?>
                                <p style='text-align:center; color:var(--text-light); font-size:13px; padding: 10px 0;'>
                                    <?php if ($devoirs_a_corriger_count > 0): ?>
                                        Vous avez <?php echo $devoirs_a_corriger_count; ?> devoir(s) à corriger. <a href="voir_devoirs.php">Voir la liste complète</a>.
                                    <?php else: ?>
                                        Aucun devoir n'a été remis récemment par vos étudiants.
                                    <?php endif; ?>
                                </p>
                            <?php else: ?>
                                <?php foreach ($devoirs_remis_recents_dynamiques as $devoir_remis): 
                                    $etudiant_nom_complet = htmlspecialchars($devoir_remis['etudiant_prenom'] . ' ' . $devoir_remis['etudiant_nom']);
                                    // Créer des initiales plus robustes
                                    $prenom_initial = !empty($devoir_remis['etudiant_prenom']) ? strtoupper(mb_substr($devoir_remis['etudiant_prenom'], 0, 1)) : '';
                                    $nom_initial = !empty($devoir_remis['etudiant_nom']) ? strtoupper(mb_substr($devoir_remis['etudiant_nom'], 0, 1)) : '';
                                    $initiales_etudiant = $prenom_initial . $nom_initial;
                                    if(empty($initiales_etudiant)) $initiales_etudiant = '?';


                                ?>
                                <div class="devoir-item">
                                    <div class="avatar-initial" title="<?php echo $etudiant_nom_complet; ?>"><?php echo $initiales_etudiant; ?></div>
                                    <div class="devoir-info">
                                        <span class="titre"><?php echo htmlspecialchars($devoir_remis['titre_devoir']); ?></span>
                                        <span class="details">
                                            Par: <?php echo $etudiant_nom_complet; ?><br>
                                            Matière: <?php echo htmlspecialchars($devoir_remis['nom_matiere']); ?> - 
                                            Remis le: <?php echo date('d/m/y H:i', strtotime($devoir_remis['date_remise'])); ?>
                                        </span>
                                    </div>
                                    <div class="devoir-actions">
                                        <a href="voir_devoirs.php?action=visualiser&id_remis=<?php echo $devoir_remis['devoir_remis_id']; ?>" class="icon-action" title="Visualiser la soumission">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="noter_etudiants.php?id_devoir=<?php echo $devoir_remis['id_devoir']; ?>&id_etudiant=<?php echo $devoir_remis['id_etudiant']; ?>" class="icon-action" title="Noter ce devoir">
                                            <i class="fas fa-check-circle"></i>
                                        </a>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                     <div class="widget-card">
                        <div class="widget-header">
                            <h4><i class="fas fa-link"></i> Liens Rapides</h4>
                        </div>
                        <div class="training-list">
                            <a href="deposer_cours.php" class="training-item">
                                <div class="avatar-initial" style="background-color: var(--status-dot-tech); color:white;"><i class="fas fa-plus"></i></div>
                                <div class="training-info">
                                    <span class="name">Déposer un support de cours</span>
                                </div>
                            </a>
                             <a href="deposer_td.php" class="training-item">
                                <div class="avatar-initial" style="background-color: var(--status-dot-task); color:white;"><i class="fas fa-clipboard-list"></i></div>
                                <div class="training-info">
                                    <span class="name">Créer un nouveau devoir</span>
                                </div>
                            </a>
                             <a href="enregistrer_cours.php" class="training-item">
                                <div class="avatar-initial" style="background-color: var(--status-dot-review); color:white;"><i class="fas fa-video"></i></div>
                                <div class="training-info">
                                    <span class="name">Planifier un cours en direct</span>
                                </div>
                            </a>
                        </div>
                    </div>
                </aside>
            </main>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const themeToggle = document.getElementById('theme-toggle');
            if (themeToggle) {
                const body = document.body;
                const themeIcon = themeToggle.querySelector('i');
                const applyTheme = (theme) => {
                    body.classList.remove('light-theme', 'dark-theme');
                    body.classList.add(theme);
                    if (theme === 'dark-theme') {
                        themeIcon.classList.remove('fa-moon'); themeIcon.classList.add('fa-sun');
                    } else {
                        themeIcon.classList.remove('fa-sun'); themeIcon.classList.add('fa-moon');
                    }
                };
                const storedTheme = localStorage.getItem('janglitech-theme') || 'light-theme';
                applyTheme(storedTheme);

                themeToggle.addEventListener('click', function (e) {
                    e.preventDefault();
                    let newTheme = body.classList.contains('dark-theme') ? 'light-theme' : 'dark-theme';
                    applyTheme(newTheme);
                    localStorage.setItem('janglitech-theme', newTheme);
                });
            }

            // Configuration locale pour strftime (si nécessaire pour le mois en français)
            <?php
            // Tenter de configurer la locale en français. Varie selon le système.
            // setlocale(LC_TIME, 'fr_FR.UTF-8', 'fra'); // ou 'fr_FR', 'french'
            ?>
        });
    </script>
</body>
</html>