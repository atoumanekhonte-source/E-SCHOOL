<?php
// /dashboard/etudiant/telecharger_td.php

// PAS D'AUTHENTIFICATION POUR CET EXEMPLE
require_once '../../config/db.php';

$database = new Database();
$conn = $database->connect();

// --- Fonctions de récupération de données ---

// Récupérer tous les devoirs (TD/TC)
// Sans étudiant connecté, on ne peut pas filtrer par sa classe facilement.
// On va simuler un ID de classe ou afficher tous les devoirs.
// **POUR L'EXEMPLE, UTILISONS UN ID DE CLASSE STATIQUE (ex: 1)**
// En production, cet ID viendrait de la session de l'étudiant.
define('DEFAULT_CLASSE_ID_FOR_EXAMPLE', 1); 

function get_all_devoirs_for_class($db_conn, $id_classe, $type_devoir = null, $search_term = null, $filter_matiere_id = null) {
    $sql = "SELECT d.id, d.titre, d.type, d.fichier, d.date_depot, d.date_limite, 
                   m.nom_matiere, u.nom as nom_enseignant, u.prenom as prenom_enseignant
            FROM devoirs d
            JOIN matieres m ON d.id_matiere = m.id
            JOIN enseignants ens ON d.id_enseignant = ens.id
            JOIN utilisateurs u ON ens.id_utilisateur = u.id
            WHERE m.id_classe = :id_classe ";
    
    $params = [':id_classe' => $id_classe];

    if ($type_devoir) {
        $sql .= " AND d.type = :type_devoir ";
        $params[':type_devoir'] = $type_devoir;
    }
    if ($filter_matiere_id) {
        $sql .= " AND d.id_matiere = :id_matiere ";
        $params[':id_matiere'] = $filter_matiere_id;
    }
    if ($search_term) {
        $sql .= " AND (d.titre LIKE :search_term OR m.nom_matiere LIKE :search_term) ";
        $params[':search_term'] = '%' . $search_term . '%';
    }

    $sql .= " ORDER BY d.date_depot DESC";
    
    $stmt = $db_conn->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// (Fonction get_all_matieres_for_filter déjà définie dans voir_videos.php,
// si vous centralisez les fonctions, vous n'aurez pas besoin de la redéfinir)
if (!function_exists('get_all_matieres_for_filter')) {
    function get_all_matieres_for_filter($db_conn) {
        $stmt = $db_conn->prepare("SELECT id, nom_matiere FROM matieres WHERE id_classe = :id_classe ORDER BY nom_matiere ASC");
        // Note: On filtre les matières par la classe par défaut pour la pertinence du filtre
        $stmt->bindValue(':id_classe', DEFAULT_CLASSE_ID_FOR_EXAMPLE, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}


// --- Logique de la page ---
$student_avatar = '../../assets/images/default_avatar.png';
$student_name = 'Visiteur';

$filter_type = isset($_GET['type']) ? $_GET['type'] : null; // 'TD' ou 'TC'
$filter_matiere = isset($_GET['matiere']) ? (int)$_GET['matiere'] : null;
$search_query = isset($_GET['recherche']) ? trim($_GET['recherche']) : null;

// Utilisation de l'ID de classe par défaut car pas d'étudiant connecté
$devoirs = get_all_devoirs_for_class($conn, DEFAULT_CLASSE_ID_FOR_EXAMPLE, $filter_type, $search_query, $filter_matiere);
$matieres_for_filter = get_all_matieres_for_filter($conn);


$page_title = "Télécharger TD / TC";
$current_page = basename($_SERVER['PHP_SELF']);
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

/* Barre de filtres */

.filters-bar {
    display: flex;
    gap: 15px;
    margin-bottom: 25px;
    flex-wrap: wrap;
    padding: 15px;
    background: #eef4ff;
    border-radius: 10px;
    border: 1px solid #d6e4ff;
}

.filters-bar select,
.filters-bar input[type="text"] {
    padding: 10px 12px;
    border-radius: 8px;
    border: 1px solid #d6e4ff;
    font-size: 14px;
    background: #ffffff;
    color: #495057;
}

.filters-bar select:focus,
.filters-bar input[type="text"]:focus {
    outline: none;
    border-color: #0D6EFD;
}

/* Bouton rechercher */

.filters-bar button {
    background: #0D6EFD;
    color: white;
    cursor: pointer;
    border: none;
    padding: 10px 18px;
    border-radius: 8px;
    font-weight: 600;
    transition: 0.3s;
    box-shadow: 0 3px 10px rgba(13,110,253,0.25);
}

.filters-bar button:hover {
    background: #0b5ed7;
    transform: translateY(-2px);
}

/* Tableau */

.devoir-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
    background: white;
    border-radius: 10px;
    overflow: hidden;
}

.devoir-table th,
.devoir-table td {
    padding: 12px 15px;
    text-align: left;
    border-bottom: 1px solid #d6e4ff;
}

.devoir-table th {
    background: #eef4ff;
    color: #0D6EFD;
    font-weight: 700;
    font-size: 0.9em;
    text-transform: uppercase;
}

.devoir-table td {
    font-size: 0.95em;
    color: #495057;
}

.devoir-table tr:hover {
    background: #f8fbff;
}

/* Actions */

.devoir-table .actions a {
    margin-right: 10px;
    text-decoration: none;
    color: #0D6EFD;
    padding: 6px 10px;
    border-radius: 6px;
    border: 1px solid transparent;
    transition: 0.3s;
    font-weight: 500;
}

.devoir-table .actions a:hover {
    border-color: #0D6EFD;
    background: #d6e4ff;
}

.devoir-table .actions a i {
    margin-right: 4px;
}

/* Badges */

.devoir-table .type-badge {
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 0.8em;
    font-weight: 600;
    color: white;
}

/* TD */

.devoir-table .type-badge.td {
    background: #0D6EFD;
}

/* TC */

.devoir-table .type-badge.tc {
    background: #3B82F6;
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

    .devoir-table {
        display: block;
        overflow-x: auto;
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
                            <h2><i class="fas fa-download"></i> Télécharger Travaux Dirigés / de Classe</h2>
                            <p>Consultez et téléchargez les sujets des devoirs assignés.</p>
                        </div>

                        <form method="GET" action="telecharger_td.php" class="filters-bar">
                            <select name="type" id="type">
                                <option value="">Tous les types</option>
                                <option value="TD" <?php echo ($filter_type == 'TD') ? 'selected' : ''; ?>>TD</option>
                                <option value="TC" <?php echo ($filter_type == 'TC') ? 'selected' : ''; ?>>TC</option>
                            </select>
                             <select name="matiere" id="matiere">
                                <option value="">Toutes les matières</option>
                                <?php foreach ($matieres_for_filter as $matiere): ?>
                                    <option value="<?php echo $matiere['id']; ?>" <?php echo ($filter_matiere == $matiere['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($matiere['nom_matiere']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="text" name="recherche" placeholder="Rechercher par titre, matière..." value="<?php echo htmlspecialchars($search_query ?? ''); ?>">
                            <button type="submit"><i class="fas fa-filter"></i> Filtrer / Rechercher</button>
                        </form>

                        <?php if (empty($devoirs)): ?>
                            <p>Aucun devoir ne correspond à vos critères ou aucun devoir n'est disponible pour la classe d'exemple (ID: <?php echo DEFAULT_CLASSE_ID_FOR_EXAMPLE; ?>).</p>
                        <?php else: ?>
                            <table class="devoir-table">
                                <thead>
                                    <tr>
                                        <th>Titre du Devoir</th>
                                        <th>Type</th>
                                        <th>Matière</th>
                                        <th>Enseignant</th>
                                        <th>Date Dépôt</th>
                                        <th>Date Limite</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($devoirs as $devoir): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($devoir['titre']); ?></td>
                                            <td>
                                                <span class="type-badge <?php echo strtolower($devoir['type']); ?>">
                                                    <?php echo htmlspecialchars($devoir['type']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($devoir['nom_matiere']); ?></td>
                                            <td><?php echo htmlspecialchars($devoir['prenom_enseignant'] . ' ' . $devoir['nom_enseignant']); ?></td>
                                            <td><?php echo date('d/m/Y', strtotime($devoir['date_depot'])); ?></td>
                                            <td><?php echo date('d/m/Y', strtotime($devoir['date_limite'])); ?></td>
                                            <td class="actions">
                                                <?php if ($devoir['fichier']): 
                                                    $filePath = '../../uploads/devoirs/' . htmlspecialchars($devoir['fichier']);
                                                ?>
                                                    <a href="<?php echo $filePath; ?>" download title="Télécharger le sujet">
                                                        <i class="fas fa-download"></i> Télécharger
                                                    </a>
                                                <?php else: ?>
                                                    <span>Pas de fichier</span>
                                                <?php endif; ?>
                                                <!-- Un lien pour remettre le devoir pourrait être ici aussi -->
                                                <!-- <a href="remettre_td.php?id_devoir=<?php echo $devoir['id']; ?>" title="Soumettre ce devoir"><i class="fas fa-upload"></i> Soumettre</a> -->
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
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