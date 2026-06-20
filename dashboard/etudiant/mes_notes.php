<?php
// /dashboard/etudiant/mes_notes.php

// PAS D'AUTHENTIFICATION POUR CET EXEMPLE
require_once '../../config/db.php';

$database = new Database();
$conn = $database->connect();

// --- ID Étudiant Simulé ---
define('SIMULATED_ETUDIANT_ID_NOTES', 1); // !! IMPORTANT: ID existant dans la table 'etudiants'

// --- Fonctions de récupération de données ---
function get_student_notes_grouped_by_matiere($db_conn, $id_etudiant) {
    $stmt = $db_conn->prepare("
        SELECT 
            n.id as note_id, n.type_eval, n.note, n.date_saisie,
            m.id as matiere_id, m.nom_matiere, m.code_matiere,
            u.nom as nom_enseignant, u.prenom as prenom_enseignant
        FROM notes n
        JOIN matieres m ON n.id_matiere = m.id
        JOIN enseignants ens ON m.id_enseignant = ens.id
        JOIN utilisateurs u ON ens.id_utilisateur = u.id
        WHERE n.id_etudiant = :id_etudiant
        ORDER BY m.nom_matiere ASC, n.date_saisie DESC, n.type_eval ASC
    ");
    $stmt->bindParam(':id_etudiant', $id_etudiant, PDO::PARAM_INT);
    $stmt->execute();
    
    $notes_grouped = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $notes_grouped[$row['matiere_id']]['details'] = [
            'nom_matiere' => $row['nom_matiere'],
            'code_matiere' => $row['code_matiere'],
            'nom_enseignant' => $row['prenom_enseignant'] . ' ' . $row['nom_enseignant']
        ];
        $notes_grouped[$row['matiere_id']]['evaluations'][] = [
            'type_eval' => $row['type_eval'],
            'note' => $row['note'],
            'date_saisie' => $row['date_saisie']
        ];
    }
    return $notes_grouped;
}

// --- Logique de la page ---
$student_avatar = '../../assets/images/default_avatar.png'; // Simulé
$student_name = 'Étudiant (Simulé)'; // Simulé

$notes_etudiant = get_student_notes_grouped_by_matiere($conn, SIMULATED_ETUDIANT_ID_NOTES);

$page_title = "Mes Notes";
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
    border-bottom:1px solid #D6E4FF;
    padding-bottom:15px;
}

.page-header h2{
    color:#0D6EFD;
    font-size:28px;
    margin-bottom:8px;
}

.page-header p{
    color:#64748B;
}

.matiere-notes-card{
    margin-bottom:25px;
    border:1px solid #D6E4FF;
    border-radius:12px;
    overflow:hidden;
    background:#FFFFFF;
    box-shadow:0 2px 10px rgba(13,110,253,.05);
    transition:.3s;
}

.matiere-notes-card:hover{
    transform:translateY(-3px);
    box-shadow:0 6px 20px rgba(13,110,253,.12);
}

.matiere-header{
    background:linear-gradient(135deg,#0D6EFD,#3B82F6);
    padding:18px 20px;
    border-bottom:none;
}

.matiere-header h3{
    margin:0;
    color:#FFFFFF;
    font-size:1.2rem;
    font-weight:600;
}

.matiere-header small{
    color:#EAF2FF;
}

.matiere-header .enseignant-info{
    font-size:.9em;
    color:#EAF2FF;
    margin-top:5px;
    display:block;
}

.evaluations-table{
    width:100%;
    border-collapse:collapse;
}

.evaluations-table th,
.evaluations-table td{
    padding:12px 20px;
    text-align:left;
    border-bottom:1px solid #E5E7EB;
}

.evaluations-table th{
    background:#EFF6FF;
    color:#0D6EFD;
    font-weight:600;
    text-transform:uppercase;
    font-size:.8rem;
}

.evaluations-table tr:hover{
    background:#F8FBFF;
}

.evaluations-table .note-value{
    font-weight:700;
    color:#0D6EFD;
    font-size:1rem;
}

.type-eval-badge{
    display:inline-block;
    padding:5px 10px;
    border-radius:20px;
    font-size:.85rem;
    font-weight:600;
    background:#D6E4FF;
    color:#0D6EFD;
}

.no-notes-message{
    padding:40px;
    text-align:center;
    color:#64748B;
}

.no-notes-message i{
    color:#0D6EFD;
    margin-bottom:15px;
}

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

body.dark-theme .page-header p{
    color:#CBD5E1;
}

body.dark-theme .matiere-notes-card{
    background:#1E293B;
    border-color:#334155;
}

body.dark-theme .matiere-header{
    background:linear-gradient(135deg,#2563EB,#1D4ED8);
}

body.dark-theme .evaluations-table th{
    background:#162033;
    color:#60A5FA;
}

body.dark-theme .evaluations-table td{
    border-color:#334155;
    color:#F8FAFC;
}

body.dark-theme .evaluations-table tr:hover{
    background:#243447;
}

body.dark-theme .note-value{
    color:#60A5FA;
}

body.dark-theme .type-eval-badge{
    background:#1E40AF;
    color:#BFDBFE;
}

body.dark-theme .no-notes-message{
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
                            <h2><i class="fas fa-clipboard-list"></i> Mes Notes</h2>
                            <p>Consultez vos notes pour les différentes évaluations par matière.</p>
                            <p><small>Affichage des notes pour l'étudiant simulé ID: <?php echo SIMULATED_ETUDIANT_ID_NOTES; ?></small></p>
                        </div>

                        <?php if (empty($notes_etudiant)): ?>
                            <div class="no-notes-message">
                                <i class="fas fa-folder-open fa-3x" style="margin-bottom:10px;"></i><br>
                                Aucune note n'a été enregistrée pour vous pour le moment.
                            </div>
                        <?php else: ?>
                            <?php foreach ($notes_etudiant as $matiere_id => $data): ?>
                                <div class="matiere-notes-card">
                                    <div class="matiere-header">
                                        <h3><?php echo htmlspecialchars($data['details']['nom_matiere']); ?> 
                                            <small>(<?php echo htmlspecialchars($data['details']['code_matiere']); ?>)</small>
                                        </h3>
                                        <span class="enseignant-info">
                                            Enseignant: <?php echo htmlspecialchars($data['details']['nom_enseignant']); ?>
                                        </span>
                                    </div>
                                    <table class="evaluations-table">
                                        <thead>
                                            <tr>
                                                <th>Type d'Évaluation</th>
                                                <th>Note /20</th>
                                                <th>Date de Saisie</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($data['evaluations'] as $eval): ?>
                                                <tr>
                                                    <td>
                                                        <span class="type-eval-badge">
                                                            <?php echo htmlspecialchars($eval['type_eval']); ?>
                                                        </span>
                                                    </td>
                                                    <td class="note-value"><?php echo htmlspecialchars(number_format($eval['note'], 2)); ?></td>
                                                    <td><?php echo date('d/m/Y', strtotime($eval['date_saisie'])); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endforeach; ?>
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
            if (themeToggle) { }
        });
    </script>
</body>
</html>