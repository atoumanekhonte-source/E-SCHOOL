<?php
// /dashboard/etudiant/remettre_td.php

// PAS D'AUTHENTIFICATION POUR CET EXEMPLE
// if (session_status() == PHP_SESSION_NONE) {
//     session_start();
// }
// require_once '../../includes/auth.php';
// checkAuth('etudiant');

require_once '../../config/db.php'; // Votre classe Database

$database = new Database();
$conn = $database->connect();

// --- ID Étudiant Simulé (puisque pas d'authentification) ---
// En production, cet ID viendrait de $_SESSION['user_id'] (qui serait etudiants.id)
define('SIMULATED_ETUDIANT_ID', 1); // !! IMPORTANT: Remplacez par un ID étudiant existant dans votre table 'etudiants'

// --- Fonctions de récupération de données ---
function get_devoir_details_for_submission($db_conn, $id_devoir, $id_etudiant) {
    $stmt = $db_conn->prepare("
        SELECT d.id, d.titre, d.type, d.fichier as fichier_sujet, d.date_limite, 
               m.nom_matiere,
               dr.id as id_remis, dr.fichier_remis, dr.date_remise
        FROM devoirs d
        JOIN matieres m ON d.id_matiere = m.id
        LEFT JOIN devoirs_remis dr ON d.id = dr.id_devoir AND dr.id_etudiant = :id_etudiant
        WHERE d.id = :id_devoir
    ");
    $stmt->bindParam(':id_devoir', $id_devoir, PDO::PARAM_INT);
    $stmt->bindParam(':id_etudiant', $id_etudiant, PDO::PARAM_INT); // Pour vérifier si déjà remis
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// --- Logique de la page ---
// Simuler des infos pour les templates
$student_avatar = '../../assets/images/default_avatar.png'; // Simulé
$student_name = 'Étudiant (Simulé)'; // Simulé

$id_devoir_get = isset($_GET['id_devoir']) ? (int)$_GET['id_devoir'] : null;
$devoir_details = null;
$message = ''; // Pour les notifications de succès ou d'erreur

if (!$id_devoir_get) {
    header('Location: telecharger_td.php?erreur=id_devoir_manquant'); // Ou mes_cours.php
    exit();
}

$devoir_details = get_devoir_details_for_submission($conn, $id_devoir_get, SIMULATED_ETUDIANT_ID);

if (!$devoir_details) {
    header('Location: telecharger_td.php?erreur=devoir_invalide'); // Ou mes_cours.php
    exit();
}

// Vérifier si le devoir est déjà remis par cet étudiant (simulé) ou si la date limite est passée
$deadline_passed = strtotime($devoir_details['date_limite']) < time();
$already_submitted = !empty($devoir_details['id_remis']);

if ($already_submitted) {
    $message = "<div class='alert alert-info'>Vous avez déjà remis ce devoir le " . date('d/m/Y H:i', strtotime($devoir_details['date_remise'])) . ".</div>";
} elseif ($deadline_passed) {
    $message = "<div class='alert alert-warning'>La date limite pour remettre ce devoir est passée (" . date('d/m/Y', strtotime($devoir_details['date_limite'])) . ").</div>";
}


// Traitement du formulaire de soumission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_devoir']) && !$already_submitted && !$deadline_passed) {
    if (isset($_FILES['fichier_etudiant']) && $_FILES['fichier_etudiant']['error'] == UPLOAD_ERR_OK) {
        $upload_dir = '../../uploads/devoirs_remis/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true); // Crée le dossier s'il n'existe pas
        }

        $file_tmp_path = $_FILES['fichier_etudiant']['tmp_name'];
        $file_name = basename($_FILES['fichier_etudiant']['name']);
        $file_size = $_FILES['fichier_etudiant']['size'];
        // Nettoyer le nom du fichier (sécurité basique)
        $safe_file_name = preg_replace("/[^a-zA-Z0-9._-]/", "_", $file_name);
        // Ajouter un timestamp ou un identifiant unique pour éviter les écrasements
        $unique_file_name = time() . '_' . SIMULATED_ETUDIANT_ID . '_' . $safe_file_name;
        $dest_path = $upload_dir . $unique_file_name;

        // Vérifications (taille, type - à ajouter pour la production)
        $allowed_extensions = ['pdf', 'doc', 'docx', 'txt', 'zip', 'rar', 'jpg', 'png'];
        $file_extension = strtolower(pathinfo($unique_file_name, PATHINFO_EXTENSION));

        if (!in_array($file_extension, $allowed_extensions)) {
            $message = "<div class='alert alert-danger'>Type de fichier non autorisé. Extensions permises: " . implode(', ', $allowed_extensions) . "</div>";
        } elseif ($file_size > 5000000) { // Limite de 5MB
            $message = "<div class='alert alert-danger'>Le fichier est trop volumineux (max 5MB).</div>";
        } else {
            if (move_uploaded_file($file_tmp_path, $dest_path)) {
                // Insérer dans la base de données
                try {
                    $stmt_insert = $conn->prepare("
                        INSERT INTO devoirs_remis (id_devoir, id_etudiant, fichier_remis, date_remise)
                        VALUES (:id_devoir, :id_etudiant, :fichier_remis, NOW())
                    ");
                    $stmt_insert->bindParam(':id_devoir', $id_devoir_get, PDO::PARAM_INT);
                    $stmt_insert->bindValue(':id_etudiant', SIMULATED_ETUDIANT_ID, PDO::PARAM_INT);
                    $stmt_insert->bindParam(':fichier_remis', $unique_file_name, PDO::PARAM_STR);
                    
                    if ($stmt_insert->execute()) {
                        $message = "<div class='alert alert-success'>Devoir remis avec succès ! Votre fichier : " . htmlspecialchars($unique_file_name) . "</div>";
                        // Recharger les détails pour refléter la soumission
                        $devoir_details = get_devoir_details_for_submission($conn, $id_devoir_get, SIMULATED_ETUDIANT_ID);
                        $already_submitted = true; // Mettre à jour le statut
                    } else {
                        $message = "<div class='alert alert-danger'>Erreur lors de l'enregistrement de la soumission en base de données.</div>";
                    }
                } catch (PDOException $e) {
                    error_log("Erreur d'insertion devoirs_remis: " . $e->getMessage());
                    $message = "<div class='alert alert-danger'>Une erreur technique est survenue. Veuillez réessayer.</div>";
                }
            } else {
                $message = "<div class='alert alert-danger'>Erreur lors du téléchargement du fichier. Vérifiez les permissions du dossier 'uploads/devoirs_remis'.</div>";
            }
        }
    } else {
        $message = "<div class='alert alert-danger'>Veuillez sélectionner un fichier à uploader. Erreur: " . ($_FILES['fichier_etudiant']['error'] ?? ' inconnue') ."</div>";
    }
}


$page_title = "Remettre: " . htmlspecialchars($devoir_details['titre']);
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
    margin-bottom: 20px;
}

.page-header h2 {
    color: #0D6EFD;
    margin-bottom: 5px;
    font-weight: 700;
}

.page-header .back-link {
    color: #0D6EFD;
    text-decoration: none;
    font-weight: 600;
    display: inline-block;
    margin-bottom: 15px;
    transition: 0.3s;
}

.page-header .back-link:hover {
    color: #0b5ed7;
}

.page-header .back-link i {
    margin-right: 5px;
}

/* Informations du devoir */

.devoir-info {
    margin-bottom: 25px;
    padding: 18px;
    background: #eef4ff;
    border-radius: 10px;
    border-left: 5px solid #0D6EFD;
}

.devoir-info p {
    margin: 6px 0;
    color: #495057;
}

.devoir-info strong {
    color: #0D6EFD;
}

.devoir-info .sujet-link {
    display: inline-block;
    margin-top: 10px;
    color: #0D6EFD;
    font-weight: 600;
    text-decoration: none;
}

.devoir-info .sujet-link:hover {
    color: #0b5ed7;
}

/* Formulaire */

.submission-form label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #0D6EFD;
}

.submission-form input[type="file"] {
    display: block;
    width: 100%;
    padding: 12px;
    margin-bottom: 20px;
    border: 2px solid #d6e4ff;
    border-radius: 8px;
    background: #f8fbff;
    color: #495057;
    box-sizing: border-box;
}

.submission-form input[type="file"]:focus {
    outline: none;
    border-color: #0D6EFD;
}

/* Bouton */

.submission-form button[type="submit"] {
    background: #0D6EFD;
    color: #ffffff;
    padding: 12px 25px;
    border: none;
    border-radius: 8px;
    font-size: 1em;
    font-weight: 600;
    cursor: pointer;
    transition: 0.3s;
    box-shadow: 0 3px 10px rgba(13,110,253,0.25);
}

.submission-form button[type="submit"]:hover {
    background: #0b5ed7;
    transform: translateY(-2px);
}

.submission-form button[type="submit"]:disabled {
    background: #9ec5fe;
    cursor: not-allowed;
    box-shadow: none;
}

/* Alertes */

.alert {
    padding: 15px;
    margin-bottom: 20px;
    border-radius: 8px;
    border: 1px solid transparent;
    font-weight: 500;
}

/* Succès */
.alert-success {
    background: #dbeafe;
    color: #084298;
    border-color: #93c5fd;
}

/* Erreur */
.alert-danger {
    background: #e7f1ff;
    color: #084298;
    border-color: #93c5fd;
}

/* Attention */
.alert-warning {
    background: #eef4ff;
    color: #0D6EFD;
    border-color: #bfd7ff;
}

/* Information */
.alert-info {
    background: #eef4ff;
    color: #0D6EFD;
    border-color: #bfd7ff;
}

/* Fichier déjà soumis */

.submitted-file-info {
    margin-top: 15px;
    padding: 15px;
    background: #eef4ff;
    border-left: 5px solid #0D6EFD;
    border-radius: 8px;
}

.submitted-file-info p {
    margin: 0;
    font-size: 0.95em;
    color: #495057;
}

.submitted-file-info a {
    color: #0D6EFD;
    font-weight: 700;
    text-decoration: none;
}

.submitted-file-info a:hover {
    color: #0b5ed7;
}

/* Responsive */

@media (max-width: 768px) {
    .page-container {
        margin-left: 0;
        padding: 20px;
    }

    .submission-form button[type="submit"] {
        width: 100%;
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
                            <a href="voir_cours_details.php?id_matiere=<?php echo $devoir_details['id_matiere'] ?? ''; ?>" class="back-link">
                                <i class="fas fa-arrow-left"></i> Retour au cours
                            </a>
                            <h2><i class="fas fa-upload"></i> Remettre le Devoir</h2>
                        </div>

                        <?php if ($message): ?>
                            <?php echo $message; ?>
                        <?php endif; ?>

                        <div class="devoir-info">
                            <p><strong>Titre du devoir :</strong> <?php echo htmlspecialchars($devoir_details['titre']); ?></p>
                            <p><strong>Type :</strong> <span class="type-badge <?php echo strtolower($devoir_details['type']); ?>"><?php echo htmlspecialchars($devoir_details['type']); ?></span></p>
                            <p><strong>Matière :</strong> <?php echo htmlspecialchars($devoir_details['nom_matiere']); ?></p>
                            <p><strong>Date limite de soumission :</strong> <?php echo date('d/m/Y H:i', strtotime($devoir_details['date_limite'])); ?></p>
                            <?php if ($devoir_details['fichier_sujet']): ?>
                                <p>
                                    <strong>Sujet du devoir :</strong> 
                                    <a href="../../uploads/devoirs/<?php echo htmlspecialchars($devoir_details['fichier_sujet']); ?>" download class="sujet-link">
                                        <i class="fas fa-download"></i> Télécharger le sujet
                                    </a>
                                </p>
                            <?php endif; ?>
                        </div>

                        <?php if ($already_submitted && $devoir_details['fichier_remis']): ?>
                            <div class="submitted-file-info">
                                <p><strong>Votre soumission :</strong> 
                                    <a href="../../uploads/devoirs_remis/<?php echo htmlspecialchars($devoir_details['fichier_remis']); ?>" download>
                                        <?php echo htmlspecialchars($devoir_details['fichier_remis']); ?>
                                    </a> (Remis le <?php echo date('d/m/Y H:i', strtotime($devoir_details['date_remise'])); ?>)
                                </p>
                                <p><small>Si vous souhaitez modifier votre soumission et que la date limite n'est pas passée, vous devrez contacter votre enseignant.</small></p>
                            </div>
                        <?php elseif (!$already_submitted && !$deadline_passed): ?>
                            <form action="remettre_td.php?id_devoir=<?php echo $id_devoir_get; ?>" method="POST" enctype="multipart/form-data" class="submission-form">
                                <label for="fichier_etudiant">Sélectionnez votre fichier (PDF, DOC, DOCX, TXT, ZIP, images) - Max 5MB :</label>
                                <input type="file" name="fichier_etudiant" id="fichier_etudiant" required>
                                <button type="submit" name="submit_devoir"><i class="fas fa-paper-plane"></i> Envoyer ma réponse</button>
                            </form>
                        <?php elseif ($deadline_passed && !$already_submitted): ?>
                             <p>Vous ne pouvez plus soumettre ce devoir car la date limite est passée.</p>
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