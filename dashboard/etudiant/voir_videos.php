<?php
// /dashboard/etudiant/voir_videos.php

// PAS D'AUTHENTIFICATION POUR CET EXEMPLE (NON RECOMMANDÉ EN PRODUCTION)
// if (session_status() == PHP_SESSION_NONE) {
//     session_start();
// }
// require_once '../../includes/auth.php';
// checkAuth('etudiant');

require_once '../../config/db.php';

$database = new Database();
$conn = $database->connect();

// --- Fonctions de récupération de données ---

// Récupérer les vidéos de cours (live enregistrés ou vidéos uploadées)
// Sans étudiant connecté, on ne peut pas filtrer par sa classe facilement.
// On va donc afficher toutes les vidéos ou celles d'une classe par défaut.
// Pour l'exemple, on prendra toutes les vidéos.
function get_all_recorded_videos($db_conn, $filter_matiere_id = null, $filter_enseignant_id = null, $search_term = null) {
    $sql = "SELECT cel.id, cel.titre, cel.description, cel.fichier_video, cel.lien_visionnage, cel.date_cours, 
                   m.nom_matiere, u.nom as nom_enseignant, u.prenom as prenom_enseignant
            FROM cours_en_ligne cel
            JOIN matieres m ON cel.id_matiere = m.id
            JOIN enseignants ens ON cel.id_enseignant = ens.id
            JOIN utilisateurs u ON ens.id_utilisateur = u.id
            WHERE (cel.fichier_video IS NOT NULL OR cel.lien_visionnage LIKE '%youtube.com%' OR cel.lien_visionnage LIKE '%vimeo.com%') "; // Filtre pour les vidéos

    $params = [];
    if ($filter_matiere_id) {
        $sql .= " AND cel.id_matiere = :id_matiere ";
        $params[':id_matiere'] = $filter_matiere_id;
    }
    if ($filter_enseignant_id) {
        $sql .= " AND cel.id_enseignant = :id_enseignant ";
        $params[':id_enseignant'] = $filter_enseignant_id;
    }
    if ($search_term) {
        $sql .= " AND (cel.titre LIKE :search_term OR cel.description LIKE :search_term OR m.nom_matiere LIKE :search_term) ";
        $params[':search_term'] = '%' . $search_term . '%';
    }

    $sql .= " ORDER BY cel.date_cours DESC";
    
    $stmt = $db_conn->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Récupérer toutes les matières pour le filtre
function get_all_matieres_for_filter($db_conn) {
    $stmt = $db_conn->prepare("SELECT id, nom_matiere FROM matieres ORDER BY nom_matiere ASC");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Récupérer tous les enseignants pour le filtre
function get_all_enseignants_for_filter($db_conn) {
    $stmt = $db_conn->prepare("SELECT ens.id, u.nom, u.prenom 
                             FROM enseignants ens 
                             JOIN utilisateurs u ON ens.id_utilisateur = u.id 
                             ORDER BY u.nom ASC, u.prenom ASC");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// --- Logique de la page ---
// Simuler des infos pour les templates, car pas d'utilisateur connecté
$student_avatar = '../../assets/images/default_avatar.png';
$student_name = 'Visiteur'; // Ou laisser vide

$filter_matiere = isset($_GET['matiere']) ? (int)$_GET['matiere'] : null;
$filter_enseignant = isset($_GET['enseignant']) ? (int)$_GET['enseignant'] : null;
$search_query = isset($_GET['recherche']) ? trim($_GET['recherche']) : null;

$videos = get_all_recorded_videos($conn, $filter_matiere, $filter_enseignant, $search_query);
$matieres_for_filter = get_all_matieres_for_filter($conn);
$enseignants_for_filter = get_all_enseignants_for_filter($conn);

$page_title = "Vidéos de Cours & Replays";
$current_page = basename($_SERVER['PHP_SELF']); // Pour la sidebar
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


.page-container {
    background: #ffffff;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(13,110,253,0.10);
    margin-left: 30px;
    border: 1px solid #d6e4ff;
}

.page-header {
    margin-bottom: 25px;
}

.page-header h2 {
    color: #0D6EFD;
    font-weight: 700;
}

/* Barre des filtres */

.filters-bar {
    display: flex;
    gap: 15px;
    margin-bottom: 25px;
    flex-wrap: wrap;
    padding: 15px;
    background: #EEF4FF;
    border: 1px solid #D6E4FF;
    border-radius: 10px;
}

.filters-bar select,
.filters-bar input[type="text"] {
    padding: 10px 12px;
    border-radius: 8px;
    border: 1px solid #D6E4FF;
    background: white;
    color: #495057;
    font-size: 14px;
}

.filters-bar select:focus,
.filters-bar input[type="text"]:focus {
    outline: none;
    border-color: #0D6EFD;
}

.filters-bar button {
    padding: 10px 18px;
    border-radius: 8px;
    border: none;
    background: #0D6EFD;
    color: white;
    font-weight: 600;
    cursor: pointer;
    transition: 0.3s;
}

.filters-bar button:hover {
    background: #0B5ED7;
    transform: translateY(-2px);
}

/* Grille vidéos */

.video-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
}

/* Carte vidéo */

.video-card {
    background: #ffffff;
    border: 1px solid #D6E4FF;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 12px rgba(13,110,253,0.08);
    display: flex;
    flex-direction: column;
    transition: 0.3s;
}

.video-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 6px 18px rgba(13,110,253,0.15);
}

/* Miniature */

.video-thumbnail {
    width: 100%;
    height: 180px;
    background: #EEF4FF;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #0D6EFD;
    font-size: 2em;
}

.video-thumbnail img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

/* Infos */

.video-info {
    padding: 15px;
    flex-grow: 1;
    display: flex;
    flex-direction: column;
}

.video-info h4 {
    margin-top: 0;
    margin-bottom: 8px;
    color: #0D6EFD;
    font-size: 1.1em;
    font-weight: 600;
}

.video-info .video-meta {
    font-size: 0.85em;
    color: #6C757D;
    margin-bottom: 5px;
}

.video-info .video-description {
    font-size: 0.9em;
    color: #495057;
    margin-bottom: 15px;
    flex-grow: 1;
}

/* Actions */

.video-actions {
    display: flex;
    gap: 10px;
    margin-top: auto;
}

.video-actions a {
    flex-grow: 1;
    text-align: center;
    padding: 10px;
    text-decoration: none;
    border-radius: 8px;
    font-size: 0.9em;
    font-weight: 600;
    transition: 0.3s;
}

/* Bouton regarder */

.video-actions .btn-watch {
    background: #0D6EFD;
    color: white;
}

.video-actions .btn-watch:hover {
    background: #0B5ED7;
}

/* Bouton télécharger */

.video-actions .btn-download {
    background: #EEF4FF;
    color: #0D6EFD;
    border: 1px solid #0D6EFD;
}

.video-actions .btn-download:hover {
    background: #D6E4FF;
}

/* Statut vidéo */

.video-status {
    font-size: 0.85em;
    color: #0D6EFD;
    font-weight: 600;
    margin-top: 8px;
}

/* Responsive */

@media (max-width: 768px) {
    .page-container {
        margin-left: 0;
        padding: 20px;
    }

    .filters-bar {
        flex-direction: column;
    }

    .filters-bar select,
    .filters-bar input,
    .filters-bar button {
        width: 100%;
    }

    .video-grid {
        grid-template-columns: 1fr;
    }
}
</style>
</head>
<body>
    <div class="dashboard-container">
        <?php include_once '../templates/sidebar_etudiant.php'; ?>
        
        <div class="main-wrapper">
            <?php include_once '../templates/topbar_etudiant.php'; ?>

            <main class="content-area">
                <div class="main-column" style="width: 100%;">
                    <div class="page-container">
                        <div class="page-header">
                            <h2><i class="fas fa-video"></i> Vidéos de Cours & Replays</h2>
                            <p>Retrouvez ici les enregistrements des cours passés et d'autres ressources vidéo.</p>
                        </div>

                        <form method="GET" action="voir_videos.php" class="filters-bar">
                            <select name="matiere" id="matiere">
                                <option value="">Toutes les matières</option>
                                <?php foreach ($matieres_for_filter as $matiere): ?>
                                    <option value="<?php echo $matiere['id']; ?>" <?php echo ($filter_matiere == $matiere['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($matiere['nom_matiere']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <select name="enseignant" id="enseignant">
                                <option value="">Tous les enseignants</option>
                                <?php foreach ($enseignants_for_filter as $ens): ?>
                                    <option value="<?php echo $ens['id']; ?>" <?php echo ($filter_enseignant == $ens['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($ens['prenom'] . ' ' . $ens['nom']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="text" name="recherche" placeholder="Rechercher par titre, mot-clé..." value="<?php echo htmlspecialchars($search_query ?? ''); ?>">
                            <button type="submit"><i class="fas fa-filter"></i> Filtrer / Rechercher</button>
                        </form>

                        <?php if (empty($videos)): ?>
                            <p>Aucune vidéo ne correspond à vos critères de recherche ou aucune vidéo n'est disponible pour le moment.</p>
                        <?php else: ?>
                            <div class="video-grid">
                                <?php foreach ($videos as $video): ?>
                                    <div class="video-card">
                                        <div class="video-thumbnail">
                                            <!-- Idéalement, vous auriez une miniature pour chaque vidéo -->
                                            <?php if (strpos($video['lien_visionnage'] ?? '', 'youtube.com/embed/') !== false): 
                                                // Essayer d'extraire l'ID de la vidéo YouTube pour une miniature
                                                // Exemple : https://img.youtube.com/vi/VIDEO_ID/mqdefault.jpg
                                                // $youtube_video_id = ... ; // Logique d'extraction
                                            ?>
                                                <!-- <img src="https://img.youtube.com/vi/<?php echo $youtube_video_id; ?>/mqdefault.jpg" alt="Miniature <?php echo htmlspecialchars($video['titre']); ?>"> -->
                                                <i class="fab fa-youtube"></i>
                                            <?php elseif (strpos($video['lien_visionnage'] ?? '', 'vimeo.com/') !== false): ?>
                                                 <i class="fab fa-vimeo-v"></i>
                                            <?php elseif ($video['fichier_video']): ?>
                                                <i class="fas fa-film"></i> <!-- Pour fichier vidéo local -->
                                            <?php else: ?>
                                                <i class="fas fa-photo-video"></i> <!-- Générique -->
                                            <?php endif; ?>
                                        </div>
                                        <div class="video-info">
                                            <h4><?php echo htmlspecialchars($video['titre']); ?></h4>
                                            <p class="video-meta">
                                                Matière: <?php echo htmlspecialchars($video['nom_matiere']); ?><br>
                                                Par: <?php echo htmlspecialchars($video['prenom_enseignant'] . ' ' . $video['nom_enseignant']); ?><br>
                                                Date: <?php echo date('d/m/Y', strtotime($video['date_cours'])); ?>
                                            </p>
                                            <p class="video-description">
                                                <?php echo htmlspecialchars(substr($video['description'] ?? 'Pas de description.', 0, 120)); ?>...
                                            </p>
                                            <!-- <p class="video-status">Non vu</p> --> <!-- Indicateur "Déjà vu" nécessiterait une table de suivi -->
                                            <div class="video-actions">
                                                <?php
                                                $watch_link = '#';
                                                if ($video['lien_visionnage']) {
                                                    $watch_link = htmlspecialchars($video['lien_visionnage']);
                                                } elseif ($video['fichier_video']) {
                                                    // Pour le visionnage direct, il faudrait un lecteur vidéo HTML5 ou une page dédiée
                                                    // Pour simplifier, on peut lier au téléchargement si pas de lien de visionnage externe
                                                    $watch_link = '../../uploads/cours/' . htmlspecialchars($video['fichier_video']);
                                                }
                                                ?>
                                                <a href="<?php echo $watch_link; ?>" <?php if (strpos($watch_link, 'uploads/') === false) echo 'target="_blank"'; ?> class="btn-watch">
                                                    <i class="fas fa-play"></i> Visionner
                                                </a>
                                                <?php if ($video['fichier_video']): ?>
                                                    <a href="../../uploads/cours/<?php echo htmlspecialchars($video['fichier_video']); ?>" download class="btn-download">
                                                        <i class="fas fa-download"></i> Télécharger
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
    <script>
        // Script pour le thème (identique aux autres pages)
        document.addEventListener('DOMContentLoaded', function () {
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
        });
    </script>
</body>
</html>