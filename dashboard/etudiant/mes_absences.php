<?php
// /dashboard/etudiant/mes_absences.php

// PAS D'AUTHENTIFICATION POUR CET EXEMPLE
require_once '../../config/db.php';

$database = new Database();
$conn = $database->connect();

// --- ID Étudiant Simulé ---
define('SIMULATED_ETUDIANT_ID_ABSENCES', 1); // !! IMPORTANT: ID existant dans la table 'etudiants'

// --- Fonctions de récupération de données ---
function get_student_absences($db_conn, $id_etudiant) {
    $stmt = $db_conn->prepare("
        SELECT 
            a.id, a.date_absence, a.heure_debut, a.heure_fin, a.raison, a.est_justifie,
            m.nom_matiere
        FROM absences a
        JOIN matieres m ON a.id_matiere = m.id
        WHERE a.id_etudiant = :id_etudiant
        ORDER BY a.date_absence DESC, a.heure_debut DESC
    ");
    $stmt->bindParam(':id_etudiant', $id_etudiant, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// --- Logique de la page ---
$student_avatar = '../../assets/images/default_avatar.png'; // Simulé
$student_name = 'Visiteur'; // Simulé

$absences_etudiant = get_student_absences($conn, SIMULATED_ETUDIANT_ID_ABSENCES);

$page_title = "Mes Absences";
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
        background-color: #ffffff;
        padding: 25px;
        border-radius: 12px;
        box-shadow: 0 4px 15px rgba(13,110,253,0.10);
        margin-left: 30px;
        border: 1px solid #d6e4ff;
    }

    .page-header {
        margin-bottom: 25px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .page-header h2 {
        color: #0D6EFD;
        margin: 0;
        font-weight: 700;
    }

    .btn-justifier-absence {
        background: linear-gradient(135deg, #0D6EFD, #3B82F6);
        color: white;
        padding: 10px 18px;
        text-decoration: none;
        border-radius: 8px;
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .btn-justifier-absence:hover {
        background: #084298;
        transform: translateY(-2px);
    }

    .btn-justifier-absence i {
        margin-right: 8px;
    }

    .absences-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
        background: #ffffff;
        border-radius: 10px;
        overflow: hidden;
    }

    .absences-table th,
    .absences-table td {
        padding: 12px 15px;
        text-align: left;
        border-bottom: 1px solid #d6e4ff;
    }

    .absences-table th {
        background: #0D6EFD;
        color: white;
        font-weight: 600;
        font-size: 0.9em;
        text-transform: uppercase;
    }

    .absences-table td {
        font-size: 0.95em;
        color: #1E293B;
    }

    .absences-table tr:hover {
        background-color: #EEF4FF;
    }

    /* BADGES BLEUS */

    .status-badge {
        padding: 5px 10px;
        border-radius: 20px;
        font-size: 0.8em;
        font-weight: 600;
        color: white;
        display: inline-block;
    }

    .status-badge.justifiee {
        background: #0D6EFD;
    }

    .status-badge.non-justifiee {
        background: #2563EB;
    }

    .status-badge.en-attente {
        background: #60A5FA;
        color: white;
    }

    .no-data-message {
        padding: 25px;
        text-align: center;
        color: #64748B;
        font-style: italic;
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
                            <h2><i class="fas fa-calendar-times"></i> Mes Absences</h2>
                            <!-- Le bouton pour justifier une absence ouvrirait un modal ou une autre page -->
                            <!-- <a href="justifier_absence.php" class="btn-justifier-absence"><i class="fas fa-file-medical-alt"></i> Justifier une absence</a> -->
                        </div>
                        <p><small>Affichage des absences pour l'étudiant simulé ID: <?php echo SIMULATED_ETUDIANT_ID_ABSENCES; ?></small></p>

                        <?php if (empty($absences_etudiant)): ?>
                            <div class="no-data-message">
                                <i class="fas fa-check-circle fa-3x" style="margin-bottom:10px; color: #28a745;"></i><br>
                                Félicitations ! Vous n'avez aucune absence enregistrée.
                            </div>
                        <?php else: ?>
                            <table class="absences-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Matière</th>
                                        <th>Heure Début</th>
                                        <th>Heure Fin</th>
                                        <th>Raison (si fournie)</th>
                                        <th>Statut</th>
                                        <!-- <th>Actions</th> -->
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($absences_etudiant as $absence): ?>
                                        <tr>
                                            <td><?php echo date('d/m/Y', strtotime($absence['date_absence'])); ?></td>
                                            <td><?php echo htmlspecialchars($absence['nom_matiere']); ?></td>
                                            <td><?php echo date('H:i', strtotime($absence['heure_debut'])); ?></td>
                                            <td><?php echo date('H:i', strtotime($absence['heure_fin'])); ?></td>
                                            <td><?php echo !empty($absence['raison']) ? htmlspecialchars($absence['raison']) : '<em>Non spécifiée</em>'; ?></td>
                                            <td>
                                                <?php if ($absence['est_justifie']): ?>
                                                    <span class="status-badge justifiee">Justifiée</span>
                                                <?php else: ?>
                                                    <span class="status-badge non-justifiee">Non Justifiée</span>
                                                <?php endif; ?>
                                            </td>
                                            <!-- <td>
                                                <?php // if (!$absence['est_justifie']): ?>
                                                    <a href="justifier_absence.php?id_absence=<?php // echo $absence['id']; ?>" title="Justifier cette absence">Justifier</a>
                                                <?php // endif; ?>
                                            </td> -->
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
            if (themeToggle) { /* ... (code du thème identique) ... */ }
        });
    </script>
</body>
</html>