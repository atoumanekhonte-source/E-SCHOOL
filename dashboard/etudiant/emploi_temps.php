<?php
// /dashboard/etudiant/emploi_temps.php

// PAS D'AUTHENTIFICATION POUR CET EXEMPLE
require_once '../../config/db.php';

$database = new Database();
$conn = $database->connect();

// --- ID de Classe Simulé ---
// En production, cet ID viendrait de la session de l'étudiant (étudiant -> classe)
define('SIMULATED_CLASSE_ID_EMPLOI_TEMPS', 1); // !! IMPORTANT: Remplacez par un ID de classe existant

// --- Fonctions de récupération de données ---
function get_emploi_du_temps_for_classe($db_conn, $id_classe) {
    $stmt = $db_conn->prepare("
        SELECT 
            et.id, et.jour_semaine, et.heure_debut, et.heure_fin, et.salle,
            m.nom_matiere, m.code_matiere,
            u.nom as nom_enseignant, u.prenom as prenom_enseignant,
            c.nom_classe, c.niveau
        FROM emplois_temps et
        JOIN matieres m ON et.id_matiere = m.id
        JOIN classes c ON et.id_classe = c.id
        JOIN enseignants ens ON m.id_enseignant = ens.id
        JOIN utilisateurs u ON ens.id_utilisateur = u.id
        WHERE et.id_classe = :id_classe
        ORDER BY FIELD(et.jour_semaine, 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'), et.heure_debut ASC
    ");
    $stmt->bindParam(':id_classe', $id_classe, PDO::PARAM_INT);
    $stmt->execute();
    
    $emploi_temps_grouped = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $emploi_temps_grouped[$row['jour_semaine']][] = $row;
        // Pour récupérer le nom de la classe une seule fois
        if (!isset($emploi_temps_grouped['classe_info'])) {
            $emploi_temps_grouped['classe_info'] = [
                'nom_classe' => $row['nom_classe'],
                'niveau' => $row['niveau']
            ];
        }
    }
    return $emploi_temps_grouped;
}

// --- Logique de la page ---
$student_avatar = '../../assets/images/default_avatar.png'; // Simulé
$student_name = 'Visiteur'; // Simulé

$emploi_temps = get_emploi_du_temps_for_classe($conn, SIMULATED_CLASSE_ID_EMPLOI_TEMPS);
$classe_info = $emploi_temps['classe_info'] ?? ['nom_classe' => 'Inconnue', 'niveau' => 'Inconnu'];
unset($emploi_temps['classe_info']); // Retirer l'info de classe du tableau principal pour la boucle

$jours_semaine_ordre = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];

$page_title = "Emploi du Temps - " . htmlspecialchars($classe_info['nom_classe']);
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
.page-container{
    background:#FFFFFF;
    padding:25px;
    border-radius:12px;
    box-shadow:0 4px 15px rgba(13,110,253,.08);
    border:1px solid #D6E4FF;
    margin-left:30px;
}

.page-header{
    margin-bottom:25px;
    padding-bottom:15px;
    border-bottom:1px solid #D6E4FF;
}

.page-header h2{
    color:#0D6EFD;
    font-size:28px;
    margin-bottom:8px;
}

.page-header .classe-info-display{
    font-size:1.1em;
    color:#64748B;
}

.emploi-temps-grid{
    display:grid;
    gap:20px;
}

.jour-colonne{
    margin-bottom:20px;
    border:1px solid #D6E4FF;
    border-radius:12px;
    overflow:hidden;
    background:#FFFFFF;
    box-shadow:0 2px 10px rgba(13,110,253,.05);
    transition:.3s;
}

.jour-colonne:hover{
    transform:translateY(-3px);
    box-shadow:0 6px 20px rgba(13,110,253,.12);
}

.jour-header{
    background:linear-gradient(135deg,#0D6EFD,#3B82F6);
    color:#FFFFFF;
    padding:14px 18px;
    font-size:1.1rem;
    font-weight:600;
}

.creneau-item{
    padding:15px 20px;
    border-bottom:1px dashed #D6E4FF;
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:15px;
}

.creneau-item:last-child{
    border-bottom:none;
}

.creneau-item:hover{
    background:#F8FBFF;
}

.creneau-horaire{
    font-weight:700;
    color:#0D6EFD;
    flex-shrink:0;
    width:130px;
    font-size:.95rem;
}

.creneau-details{
    flex-grow:1;
}

.creneau-details .matiere-nom{
    font-size:1.05rem;
    font-weight:600;
    color:#1E293B;
    margin-bottom:5px;
}

.creneau-details .enseignant-nom,
.creneau-details .salle-info{
    font-size:.9rem;
    color:#64748B;
    display:block;
    margin-top:3px;
}

.creneau-details .enseignant-nom i,
.creneau-details .salle-info i{
    color:#0D6EFD;
    margin-right:6px;
}

.no-cours-message{
    padding:25px;
    text-align:center;
    color:#64748B;
    font-style:italic;
}

/* MODE SOMBRE */

body.dark-theme .page-container{
    background:#1E293B;
    border-color:#334155;
}

body.dark-theme .page-header{
    border-color:#334155;
}

body.dark-theme .page-header h2{
    color:#60A5FA;
}

body.dark-theme .classe-info-display{
    color:#CBD5E1;
}

body.dark-theme .jour-colonne{
    background:#1E293B;
    border-color:#334155;
}

body.dark-theme .jour-header{
    background:linear-gradient(135deg,#2563EB,#1D4ED8);
}

body.dark-theme .creneau-item{
    border-color:#334155;
}

body.dark-theme .creneau-item:hover{
    background:#243447;
}

body.dark-theme .creneau-horaire{
    color:#60A5FA;
}

body.dark-theme .creneau-details .matiere-nom{
    color:#F8FAFC;
}

body.dark-theme .creneau-details .enseignant-nom,
body.dark-theme .creneau-details .salle-info{
    color:#CBD5E1;
}

body.dark-theme .creneau-details i{
    color:#60A5FA;
}

body.dark-theme .no-cours-message{
    color:#CBD5E1;
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
                            <h2><i class="fas fa-calendar-alt"></i> Emploi du Temps</h2>
                            <p class="classe-info-display">
                                Classe : <?php echo htmlspecialchars($classe_info['nom_classe']); ?> 
                                (Niveau: <?php echo htmlspecialchars($classe_info['niveau']); ?>)
                            </p>
                            <p><small>Affichage pour la classe simulée ID: <?php echo SIMULATED_CLASSE_ID_EMPLOI_TEMPS; ?></small></p>
                        </div>

                        <div class="emploi-temps-grid">
                            <?php if (empty($emploi_temps)): ?>
                                <p>Aucun emploi du temps n'est disponible pour cette classe pour le moment.</p>
                            <?php else: ?>
                                <?php foreach ($jours_semaine_ordre as $jour): ?>
                                    <?php if (isset($emploi_temps[$jour]) && !empty($emploi_temps[$jour])): ?>
                                        <div class="jour-colonne">
                                            <div class="jour-header"><?php echo htmlspecialchars($jour); ?></div>
                                            <?php foreach ($emploi_temps[$jour] as $creneau): ?>
                                                <div class="creneau-item">
                                                    <div class="creneau-horaire">
                                                        <i class="far fa-clock"></i> 
                                                        <?php echo date('H:i', strtotime($creneau['heure_debut'])); ?> - 
                                                        <?php echo date('H:i', strtotime($creneau['heure_fin'])); ?>
                                                    </div>
                                                    <div class="creneau-details">
                                                        <div class="matiere-nom">
                                                            <?php echo htmlspecialchars($creneau['nom_matiere']); ?> 
                                                            (<?php echo htmlspecialchars($creneau['code_matiere']); ?>)
                                                        </div>
                                                        <span class="enseignant-nom">
                                                           <i class="fas fa-chalkboard-teacher"></i> <?php echo htmlspecialchars($creneau['prenom_enseignant'] . ' ' . $creneau['nom_enseignant']); ?>
                                                        </span>
                                                        <?php if (!empty($creneau['salle'])): ?>
                                                            <span class="salle-info">
                                                                <i class="fas fa-map-marker-alt"></i> Salle: <?php echo htmlspecialchars($creneau['salle']); ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <!-- Optionnel: Afficher les jours sans cours -->
                                        <!--
                                        <div class="jour-colonne">
                                            <div class="jour-header"><?php echo htmlspecialchars($jour); ?></div>
                                            <div class="no-cours-message">Aucun cours prévu ce jour.</div>
                                        </div>
                                        -->
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    <script>
        // Script pour le thème (identique aux autres pages)
        document.addEventListener('DOMContentLoaded', function () {
            const themeToggle = document.getElementById('theme-toggle');
            if (themeToggle) { /* ... (code du thème identique) ... */ }
        });
    </script>
</body>
</html>