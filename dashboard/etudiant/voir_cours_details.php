<?php
// /dashboard/etudiant/voir_cours_details.php

require_once '../../config/db.php'; // Connexion à la base de données

$database = new Database();
$conn = $database->connect();

// --- Fonctions de récupération de données ---
function get_student_basic_info_for_details($db_conn, $student_id_in_etudiants_table) {
    try {
        $stmt = $db_conn->prepare("
            SELECT e.id as etudiant_id, e.id_classe, u.nom, u.prenom, u.photo
            FROM etudiants e
            JOIN utilisateurs u ON e.id_utilisateur = u.id
            WHERE e.id = :student_id
        ");
        $stmt->bindParam(':student_id', $student_id_in_etudiants_table, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur get_student_basic_info_for_details: " . $e->getMessage());
        return false;
    }
}

function get_matiere_details_for_page($db_conn, $id_matiere) {
    $stmt = $db_conn->prepare("
        SELECT m.id, m.nom_matiere, m.code_matiere, m.id_classe, u.nom as nom_enseignant, u.prenom as prenom_enseignant
        FROM matieres m
        JOIN enseignants ens ON m.id_enseignant = ens.id
        JOIN utilisateurs u ON ens.id_utilisateur = u.id
        WHERE m.id = :id_matiere
    ");
    $stmt->bindParam(':id_matiere', $id_matiere, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function get_fichiers_cours_for_matiere_for_page($db_conn, $id_matiere) {
    $stmt = $db_conn->prepare("
        SELECT id, titre, description, fichier_video as fichier_ressource, lien_visionnage, date_cours 
        FROM cours_en_ligne
        WHERE id_matiere = :id_matiere
        ORDER BY date_cours DESC
    ");
    $stmt->bindParam(':id_matiere', $id_matiere, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_td_for_matiere_for_page($db_conn, $id_matiere, $id_etudiant) {
    $stmt = $db_conn->prepare("
        SELECT d.id, d.titre, d.fichier, d.date_limite, dr.id as id_remis, dr.date_remise, dr.fichier_remis
        FROM devoirs d
        LEFT JOIN devoirs_remis dr ON d.id = dr.id_devoir AND dr.id_etudiant = :id_etudiant
        WHERE d.id_matiere = :id_matiere AND d.type = 'TD'
        ORDER BY d.date_depot DESC
    ");
    $stmt->bindParam(':id_matiere', $id_matiere, PDO::PARAM_INT);
    $stmt->bindParam(':id_etudiant', $id_etudiant, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_tc_for_matiere_for_page($db_conn, $id_matiere, $id_etudiant) {
    $stmt = $db_conn->prepare("
        SELECT d.id, d.titre, d.fichier, d.date_limite, dr.id as id_remis, dr.date_remise, dr.fichier_remis
        FROM devoirs d
        LEFT JOIN devoirs_remis dr ON d.id = dr.id_devoir AND dr.id_etudiant = :id_etudiant
        WHERE d.id_matiere = :id_matiere AND d.type = 'TC'
        ORDER BY d.date_depot DESC
    ");
    $stmt->bindParam(':id_matiere', $id_matiere, PDO::PARAM_INT);
    $stmt->bindParam(':id_etudiant', $id_etudiant, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_live_sessions_for_matiere_for_page($db_conn, $id_matiere) {
    $stmt = $db_conn->prepare("
        SELECT id, titre, description, lien_visionnage, date_cours
        FROM cours_en_ligne
        WHERE id_matiere = :id_matiere AND lien_visionnage IS NOT NULL AND date_cours >= CURDATE()
        ORDER BY date_cours ASC
    ");
    $stmt->bindParam(':id_matiere', $id_matiere, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// --- Logique de la page ---

// Permet d'accéder à la page sans authentification
$student_session_id = 1; // ID fictif
$student_global_info = get_student_basic_info_for_details($conn, $student_session_id);

if (!$student_global_info) {
    die("Erreur : Informations de l'étudiant non trouvées.");
}

$id_classe_etudiant = $student_global_info['id_classe'];
$student_avatar = !empty($student_global_info['photo']) ? '../../uploads/profiles/' . htmlspecialchars($student_global_info['photo']) : '../../assets/images/default_avatar.png';
$student_name = htmlspecialchars($student_global_info['prenom'] . ' ' . $student_global_info['nom']);

$matiere_id_from_get = isset($_GET['id_matiere']) ? (int)$_GET['id_matiere'] : null;

if (!$matiere_id_from_get) {
    die("Erreur : ID de matière manquant.");
}

$matiere_details = get_matiere_details_for_page($conn, $matiere_id_from_get);

if (!$matiere_details) {
    die("Erreur : Matière introuvable.");
}

// Plus de vérification de classe ici : accès libre

// Récupération des données
$fichiers_cours = get_fichiers_cours_for_matiere_for_page($conn, $matiere_id_from_get);
$td_list = get_td_for_matiere_for_page($conn, $matiere_id_from_get, $student_session_id);
$tc_list = get_tc_for_matiere_for_page($conn, $matiere_id_from_get, $student_session_id);
$live_sessions = get_live_sessions_for_matiere_for_page($conn, $matiere_id_from_get);

$page_title = "Détails: " . htmlspecialchars($matiere_details['nom_matiere']);
$current_page = 'mes_cours.php';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - SkillSet</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
    .course-detail-container {
        background: #ffffff;
        padding: 25px;
        border-radius: 12px;
        box-shadow: 0 4px 15px rgba(0,102,255,0.15);
        width: 100%;
        margin-left: 30px;
        border: 1px solid #d6e6ff;
    }

    .course-detail-header h2 {
        color: #0066ff;
        margin-bottom: 5px;
    }

    .course-detail-header p {
        color: #5b7db1;
        margin-top: 0;
        margin-bottom: 20px;
    }

    .back-to-courses-link {
        display: inline-block;
        margin-bottom: 20px;
        color: #0066ff;
        text-decoration: none;
        font-weight: 600;
    }

    .back-to-courses-link:hover {
        color: #0047b3;
    }

    .back-to-courses-link i {
        margin-right: 5px;
    }

    .tabs-container {
        display: flex;
        border-bottom: 2px solid #d6e6ff;
        margin-bottom: 20px;
        flex-wrap: wrap;
    }

    .tab-link {
        padding: 10px 15px;
        cursor: pointer;
       
</head>
<body>
    <div class="dashboard-container">
        <?php include_once '../templates/sidebar_etudiant.php'; ?>
        
        <div class="main-wrapper">
            <?php include_once '../templates/topbar_etudiant.php'; ?>

            <main class="content-area">
                <div class="main-column" style="width: 100%;">
                    <div class="course-detail-container">
                        <a href="mes_cours.php" class="back-to-courses-link"><i class="fas fa-arrow-left"></i> Retour à Mes Cours</a>
                        <div class="course-detail-header">
                            <h2><?php echo htmlspecialchars($matiere_details['nom_matiere']); ?> <small>(<?php echo htmlspecialchars($matiere_details['code_matiere']); ?>)</small></h2>
                            <p>Enseignant: <?php echo htmlspecialchars($matiere_details['prenom_enseignant'] . ' ' . $matiere_details['nom_enseignant']); ?></p>
                        </div>

                        <div class="tabs-container">
                            <button class="tab-link active" data-tab="tab-cours"><i class="fas fa-file-alt"></i> Cours</button>
                            <button class="tab-link" data-tab="tab-td"><i class="fas fa-pencil-ruler"></i> TD</button>
                            <button class="tab-link" data-tab="tab-tc"><i class="fas fa-chalkboard"></i> TC</button>
                            <button class="tab-link" data-tab="tab-chapitres"><i class="fas fa-stream"></i> Chapitres</button>
                            <button class="tab-link" data-tab="tab-live"><i class="fas fa-video"></i> Direct</button>
                        </div>

                        <div id="tab-cours" class="tab-content active resource-list">
                            <h4>Fichiers et Ressources du Cours</h4>
                            <?php if (!empty($fichiers_cours)): ?>
                                <?php foreach ($fichiers_cours as $fichier): ?>
                                    <div class="resource-item">
                                        <div class="item-info">
                                            <span class="item-title"><?php echo htmlspecialchars($fichier['titre']); ?></span>
                                            <?php if($fichier['description']): ?><span class="item-description"><?php echo htmlspecialchars(substr($fichier['description'],0,100)).'...'; ?></span><?php endif; ?>
                                            <span class="item-date">Posté le: <?php echo date('d/m/Y', strtotime($fichier['date_cours'])); ?></span>
                                        </div>
                                        <div class="item-actions">
                                            <?php if ($fichier['fichier_ressource']): 
                                                $filePath = '../../uploads/cours/' . htmlspecialchars($fichier['fichier_ressource']);
                                                // Assurez-vous que le dossier uploads/cours existe et que les fichiers y sont
                                            ?>
                                                <a href="<?php echo $filePath; ?>" download><i class="fas fa-download"></i> Télécharger</a>
                                            <?php endif; ?>
                                            <?php if ($fichier['lien_visionnage']): ?>
                                                <a href="<?php echo htmlspecialchars($fichier['lien_visionnage']); ?>" target="_blank"><i class="fas fa-external-link-alt"></i> Voir lien</a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p>Aucun fichier de cours disponible pour cette matière.</p>
                            <?php endif; ?>
                        </div>

                        <div id="tab-td" class="tab-content resource-list">
                            <h4>Travaux Dirigés (TD)</h4>
                            <?php if (!empty($td_list)): ?>
                                <?php foreach ($td_list as $td): ?>
                                    <div class="resource-item">
                                        <div class="item-info">
                                            <span class="item-title"><?php echo htmlspecialchars($td['titre']); ?></span>
                                            <span class="item-date">Date limite: <?php echo date('d/m/Y', strtotime($td['date_limite'])); ?></span>
                                            <?php if ($td['id_remis']): ?>
                                                <span class="item-status remis">Remis le <?php echo date('d/m/Y H:i', strtotime($td['date_remise'])); ?></span>
                                                <?php if ($td['fichier_remis']): ?>
                                                     <a href="../../uploads/devoirs_remis/<?php echo htmlspecialchars($td['fichier_remis']); ?>" download style="font-size:0.8em; padding:3px 6px;"><i class="fas fa-download"></i> Mon envoi</a>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                 <span class="item-status non-remis">Non remis</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="item-actions">
                                            <?php if ($td['fichier']): 
                                                 $tdFilePath = '../../uploads/devoirs/' . htmlspecialchars($td['fichier']);
                                            ?>
                                                <a href="<?php echo $tdFilePath; ?>" download><i class="fas fa-download"></i> Sujet</a>
                                            <?php endif; ?>
                                            <!-- <a href="visualiser_devoir.php?id_devoir=<?php echo $td['id']; ?>" target="_blank"><i class="fas fa-eye"></i> Visualiser</a> -->
                                            <?php if (!$td['id_remis'] && strtotime($td['date_limite']) >= time()): // Peut remettre si non remis ET date limite non passée ?>
                                                <a href="remettre_td.php?id_devoir=<?php echo $td['id']; ?>" class="btn-submit"><i class="fas fa-upload"></i> Répondre</a>
                                            <?php elseif (!$td['id_remis'] && strtotime($td['date_limite']) < time()): ?>
                                                 <span style="color:grey; font-size:0.9em;">Deadline passée</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p>Aucun TD disponible pour cette matière.</p>
                            <?php endif; ?>
                        </div>
                        
                        <div id="tab-tc" class="tab-content resource-list">
                            <h4>Travaux de Classe (TC)</h4>
                             <?php if (!empty($tc_list)): ?>
                                <?php foreach ($tc_list as $tc): ?>
                                    <div class="resource-item">
                                        <div class="item-info">
                                            <span class="item-title"><?php echo htmlspecialchars($tc['titre']); ?></span>
                                            <span class="item-date">Date limite: <?php echo date('d/m/Y', strtotime($tc['date_limite'])); ?></span>
                                             <?php if ($tc['id_remis']): ?>
                                                <span class="item-status remis">Remis le <?php echo date('d/m/Y H:i', strtotime($tc['date_remise'])); ?></span>
                                                 <?php if ($tc['fichier_remis']): ?>
                                                     <a href="../../uploads/devoirs_remis/<?php echo htmlspecialchars($tc['fichier_remis']); ?>" download style="font-size:0.8em; padding:3px 6px;"><i class="fas fa-download"></i> Mon envoi</a>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                 <span class="item-status non-remis">Non remis</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="item-actions">
                                            <?php if ($tc['fichier']): 
                                                $tcFilePath = '../../uploads/devoirs/' . htmlspecialchars($tc['fichier']);
                                            ?>
                                                <a href="<?php echo $tcFilePath; ?>" download><i class="fas fa-download"></i> Sujet</a>
                                            <?php endif; ?>
                                            <!-- <a href="visualiser_devoir.php?id_devoir=<?php echo $tc['id']; ?>" target="_blank"><i class="fas fa-eye"></i> Visualiser</a> -->
                                             <?php if (!$tc['id_remis'] && strtotime($tc['date_limite']) >= time()): ?>
                                                <a href="remettre_td.php?id_devoir=<?php echo $tc['id']; ?>" class="btn-submit"><i class="fas fa-upload"></i> Répondre</a>
                                            <?php elseif (!$tc['id_remis'] && strtotime($tc['date_limite']) < time()): ?>
                                                 <span style="color:grey; font-size:0.9em;">Deadline passée</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p>Aucun TC disponible pour cette matière.</p>
                            <?php endif; ?>
                        </div>

                        <div id="tab-chapitres" class="tab-content">
                            <h4>Chapitres et Notes Associées</h4>
                            <p><em>Pour une gestion détaillée par chapitres, une table `chapitres` (id, id_matiere, titre, ordre, description) serait nécessaire. Ensuite, les devoirs et ressources pourraient être liés à ces `id_chapitre`.</em></p>
                            <p>Actuellement, les ressources sont listées globalement pour la matière dans l'onglet "Cours".</p>
                        </div>

                        <div id="tab-live" class="tab-content">
                            <h4>Sessions de Cours en Direct à Venir</h4>
                            <?php if (!empty($live_sessions)): ?>
                                <?php foreach ($live_sessions as $session): ?>
                                    <a href="<?php echo htmlspecialchars($session['lien_visionnage']); ?>" target="_blank" class="live-session-link">
                                        <i class="fas fa-video"></i> <?php echo htmlspecialchars($session['titre']); ?> 
                                        <br><small>Le <?php echo date('d/m/Y \à H:i', strtotime($session['date_cours'])); ?>
                                        (<?php echo htmlspecialchars(substr($session['description'] ?? 'Lien externe', 0, 30)); ?>...)
                                        </small>
                                    </a>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p>Aucune session de cours en direct programmée pour cette matière.</p>
                            <?php endif; ?>
                        </div>
                    </div> <!-- Fin de .course-detail-container -->
                </div> <!-- Fin de .main-column -->
            </main>
        </div> <!-- Fin de .main-wrapper -->
    </div> <!-- Fin de .dashboard-container -->

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Script pour le changement de thème (identique à celui de mes_cours.php / index.php)
            const themeToggle = document.getElementById('theme-toggle');
            if (themeToggle) {
                const body = document.body;
                const themeIcon = themeToggle.querySelector('i');
                const applyTheme = (theme) => {
                    if (theme === 'dark-theme') {
                        body.classList.add('dark-theme');
                        themeIcon.classList.remove('fa-moon'); themeIcon.classList.add('fa-sun');
                    } else {
                        body.classList.remove('dark-theme');
                        themeIcon.classList.remove('fa-sun'); themeIcon.classList.add('fa-moon');
                    }
                };
                const currentTheme = localStorage.getItem('theme') || 'light-theme';
                applyTheme(currentTheme);
                themeToggle.addEventListener('click', function (e) {
                    e.preventDefault();
                    let newTheme = body.classList.contains('dark-theme') ? 'light-theme' : 'dark-theme';
                    applyTheme(newTheme);
                    localStorage.setItem('theme', newTheme);
                });
            }

            // Script pour les onglets
            const tabLinks = document.querySelectorAll('.tab-link');
            const tabContents = document.querySelectorAll('.tab-content');
            const activeTabFromStorage = localStorage.getItem('activeCourseTab_<?php echo $matiere_id_from_get; ?>');

            function activateTab(tabId) {
                tabLinks.forEach(l => l.classList.remove('active'));
                tabContents.forEach(c => c.classList.remove('active'));

                const linkToActivate = document.querySelector(`.tab-link[data-tab="${tabId}"]`);
                const contentToActivate = document.getElementById(tabId);

                if (linkToActivate && contentToActivate) {
                    linkToActivate.classList.add('active');
                    contentToActivate.classList.add('active');
                    localStorage.setItem('activeCourseTab_<?php echo $matiere_id_from_get; ?>', tabId);
                }
            }
            
            tabLinks.forEach(link => {
                link.addEventListener('click', () => {
                    const tabId = link.getAttribute('data-tab');
                    activateTab(tabId);
                });
            });

            // Activer le premier onglet par défaut ou celui stocké
            if (activeTabFromStorage && document.getElementById(activeTabFromStorage)) {
                 activateTab(activeTabFromStorage);
            } else if (tabLinks.length > 0) {
                activateTab(tabLinks[0].getAttribute('data-tab'));
            }
        });
    </script>
</body>
</html>