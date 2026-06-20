<?php
// /dashboard/etudiant/paiements.php

// PAS D'AUTHENTIFICATION POUR CET EXEMPLE
require_once '../../config/db.php';

$database = new Database();
$conn = $database->connect();

// --- ID Étudiant Simulé ---
define('SIMULATED_ETUDIANT_ID_PAIEMENTS', 1); // !! IMPORTANT: ID existant dans la table 'etudiants'

// --- Fonctions de récupération de données (simulées pour cet exemple) ---
// En réalité, vous auriez une table 'paiements' ou 'frais_scolarite' liée aux étudiants.
function get_student_payments_history($db_conn, $id_etudiant) {
    // Simulation de données
    $frais_annuels = 500000; // Exemple de frais de scolarité annuels
    $montant_paye_total = 0;

    // Vous feriez une requête ici pour récupérer les vrais paiements
    // SELECT SUM(montant) FROM paiements WHERE id_etudiant = :id_etudiant AND annee_scolaire = '2023-2024'
    // Pour la simulation :
    $history = [
        ['id' => 1, 'date_paiement' => '2023-09-15', 'montant' => 200000, 'description' => 'Première tranche frais de scolarité 2023-2024', 'methode' => 'Orange Money', 'reference' => 'OM20230915XYZ'],
        ['id' => 2, 'date_paiement' => '2024-01-10', 'montant' => 150000, 'description' => 'Deuxième tranche frais de scolarité 2023-2024', 'methode' => 'Wave', 'reference' => 'WV20240110ABC'],
    ];
    foreach($history as $item) {
        $montant_paye_total += $item['montant'];
    }
    $solde_restant = $frais_annuels - $montant_paye_total;

    return [
        'history' => $history,
        'frais_annuels' => $frais_annuels,
        'montant_paye' => $montant_paye_total,
        'solde_restant' => $solde_restant,
        'annee_scolaire' => '2023-2024' // Exemple
    ];
}

// --- Logique de la page ---
$student_avatar = '../../assets/images/default_avatar.png'; // Simulé
$student_name = 'Visiteur'; // Simulé

$paiements_data = get_student_payments_history($conn, SIMULATED_ETUDIANT_ID_PAIEMENTS);

$page_title = "Mes Paiements";
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
    border-radius:16px;
    border:1px solid #D6E4FF;
    box-shadow:0 4px 20px rgba(13,110,253,.08);
    margin-left:30px;
}

.page-header{
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:25px;
}

.page-header h2{
    color:#0D6EFD;
    margin:0;
    font-weight:700;
}

.btn-effectuer-paiement{
    background:#0D6EFD;
    color:#fff;
    padding:12px 20px;
    text-decoration:none;
    border-radius:10px;
    font-weight:600;
}

.btn-effectuer-paiement:hover{
    background:#0B5ED7;
}

.summary-paiement{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
    gap:20px;
    margin-bottom:30px;
}

.summary-card-paiement{
    background:#EEF4FF;
    border:1px solid #D6E4FF;
    border-left:5px solid #0D6EFD;
    padding:20px;
    border-radius:12px;
    text-align:center;
}

.summary-card-paiement .label{
    color:#64748B;
    font-size:0.9rem;
}

.summary-card-paiement .value{
    color:#0D6EFD;
    font-size:1.8rem;
    font-weight:700;
}

.summary-card-paiement .value.solde-ok{
    color:#0D6EFD;
}

.summary-card-paiement .value.solde-nok{
    color:#0D6EFD;
}

.paiements-table{
    width:100%;
    border-collapse:collapse;
    margin-top:20px;
}

.paiements-table th{
    background:#0D6EFD;
    color:white;
    padding:14px;
    text-transform:uppercase;
}

.paiements-table td{
    padding:14px;
    border-bottom:1px solid #D6E4FF;
}

.paiements-table tr:hover{
    background:#EEF4FF;
}

.paiements-table .montant{
    color:#0D6EFD;
    font-weight:700;
}

.no-data-message{
    text-align:center;
    padding:30px;
    color:#0D6EFD;
    font-weight:500;
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
                            <h2><i class="fas fa-credit-card"></i> Mes Paiements</h2>
                            <!-- Le bouton ouvrirait une page ou un modal d'intégration de paiement -->
                            <!-- <a href="effectuer_paiement.php" class="btn-effectuer-paiement"><i class="fas fa-dollar-sign"></i> Effectuer un paiement</a> -->
                        </div>
                        <p><small>Affichage des informations de paiement pour l'étudiant simulé ID: <?php echo SIMULATED_ETUDIANT_ID_PAIEMENTS; ?></small></p>
                        
                        <div class="summary-paiement">
                            <div class="summary-card-paiement">
                                <span class="label">Année Scolaire</span>
                                <span class="value"><?php echo htmlspecialchars($paiements_data['annee_scolaire']); ?></span>
                            </div>
                            <div class="summary-card-paiement">
                                <span class="label">Frais Annuels</span>
                                <span class="value"><?php echo number_format($paiements_data['frais_annuels'], 0, ',', ' '); ?> FCFA</span>
                            </div>
                            <div class="summary-card-paiement">
                                <span class="label">Total Payé</span>
                                <span class="value solde-ok"><?php echo number_format($paiements_data['montant_paye'], 0, ',', ' '); ?> FCFA</span>
                            </div>
                            <div class="summary-card-paiement">
                                <span class="label">Solde Restant</span>
                                <span class="value <?php echo ($paiements_data['solde_restant'] <= 0) ? 'solde-ok' : 'solde-nok'; ?>">
                                    <?php echo number_format($paiements_data['solde_restant'], 0, ',', ' '); ?> FCFA
                                </span>
                            </div>
                        </div>

                        <h3>Historique des Paiements</h3>
                        <?php if (empty($paiements_data['history'])): ?>
                            <div class="no-data-message">
                                <i class="fas fa-receipt fa-3x" style="margin-bottom:10px;"></i><br>
                                Aucun paiement n'a été enregistré pour vous pour le moment.
                            </div>
                        <?php else: ?>
                            <table class="paiements-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Description</th>
                                        <th>Montant</th>
                                        <th>Méthode</th>
                                        <th>Référence</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($paiements_data['history'] as $paiement): ?>
                                        <tr>
                                            <td><?php echo date('d/m/Y', strtotime($paiement['date_paiement'])); ?></td>
                                            <td><?php echo htmlspecialchars($paiement['description']); ?></td>
                                            <td class="montant"><?php echo number_format($paiement['montant'], 0, ',', ' '); ?> FCFA</td>
                                            <td><?php echo htmlspecialchars($paiement['methode']); ?></td>
                                            <td><?php echo htmlspecialchars($paiement['reference']); ?></td>
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