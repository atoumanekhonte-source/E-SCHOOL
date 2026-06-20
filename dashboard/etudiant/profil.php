<?php
// /dashboard/etudiant/profil.php

// PAS D'AUTHENTIFICATION POUR CET EXEMPLE
require_once '../../config/db.php';

$database = new Database();
$conn = $database->connect();

// --- ID Utilisateur Simulé (pour le profil) ---
// Cet ID doit correspondre à un ID dans la table 'utilisateurs' qui est lié à un étudiant.
define('SIMULATED_UTILISATEUR_ID_PROFIL', 1); // !! IMPORTANT: ID utilisateur existant

// --- Fonctions de récupération de données ---
function get_user_profile_data($db_conn, $id_utilisateur) {
    // Récupérer les infos de la table 'utilisateurs' et 'etudiants' si applicable
    $stmt = $db_conn->prepare("
        SELECT u.id as utilisateur_id, u.nom, u.prenom, u.email, u.photo, u.role,
               e.id as etudiant_id, e.matricule, e.id_classe,
               c.nom_classe, c.niveau,
               f.nom_filiere
        FROM utilisateurs u
        LEFT JOIN etudiants e ON u.id = e.id_utilisateur AND u.role = 'etudiant'
        LEFT JOIN classes c ON e.id_classe = c.id
        LEFT JOIN filieres f ON c.id_filiere = f.id
        WHERE u.id = :id_utilisateur
    ");
    $stmt->bindParam(':id_utilisateur', $id_utilisateur, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// --- Logique de la page ---
$student_avatar_session = '../../assets/images/default_avatar.png'; // Simulé pour la topbar
$student_name_session = 'Visiteur'; // Simulé pour la topbar

$message = ''; // Pour les notifications
$profile_data = get_user_profile_data($conn, SIMULATED_UTILISATEUR_ID_PROFIL);

if (!$profile_data) {
    die("Erreur: Profil utilisateur non trouvé pour l'ID simulé.");
}

// Si le formulaire de mise à jour est soumis (logique de traitement non implémentée ici)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    // Récupérer les données du POST
    $nom = $_POST['nom'] ?? $profile_data['nom'];
    $prenom = $_POST['prenom'] ?? $profile_data['prenom'];
    $email = $_POST['email'] ?? $profile_data['email'];
    // Gérer l'upload de la photo de profil...
    // Gérer le changement de mot de passe...

    // **LOGIQUE DE MISE À JOUR DE LA BASE DE DONNÉES ICI**
    // Exemple:
    // $stmt_update = $conn->prepare("UPDATE utilisateurs SET nom = :nom, prenom = :prenom, email = :email WHERE id = :id");
    // $stmt_update->execute([':nom' => $nom, ':prenom' => $prenom, ':email' => $email, ':id' => SIMULATED_UTILISATEUR_ID_PROFIL]);
    // $message = "<div class='alert alert-success'>Profil mis à jour avec succès !</div>";
    // $profile_data = get_user_profile_data($conn, SIMULATED_UTILISATEUR_ID_PROFIL); // Recharger les données

    $message = "<div class='alert alert-info'>Fonctionnalité de mise à jour non implémentée dans cet exemple.</div>";
}


$page_title = "Mon Profil";
$current_page = basename($_SERVER['PHP_SELF']);
// Pour que la topbar affiche le nom correct si disponible
if ($profile_data) {
    $student_avatar_session = !empty($profile_data['photo']) ? '../../uploads/profiles/' . htmlspecialchars($profile_data['photo']) : '../../assets/images/default_avatar.png';
    $student_name_session = htmlspecialchars($profile_data['prenom'] . ' ' . $profile_data['nom']);
}

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
    margin-bottom:25px;
    padding-bottom:15px;
    border-bottom:2px solid #D6E4FF;
}

.page-header h2{
    color:#0D6EFD;
    font-size:1.8rem;
    font-weight:700;
}

/* GRID */
.profile-grid{
    display:grid;
    grid-template-columns:250px 1fr;
    gap:30px;
}

@media (max-width:768px){
    .profile-grid{
        grid-template-columns:1fr;
    }
}

/* PHOTO */
.profile-avatar-section{
    text-align:center;
    background:#EEF4FF;
    padding:25px;
    border-radius:15px;
    border:1px solid #D6E4FF;
}

.profile-avatar-section img{
    width:180px;
    height:180px;
    border-radius:50%;
    object-fit:cover;
    margin-bottom:15px;
    border:5px solid #0D6EFD;
    box-shadow:0 4px 15px rgba(13,110,253,.20);
}

.profile-avatar-section .change-photo-btn{
    display:block;
    width:100%;
    padding:12px;
    background:#0D6EFD;
    border:none;
    color:white;
    border-radius:10px;
    cursor:pointer;
    font-size:.95rem;
    font-weight:600;
    transition:.3s;
}

.profile-avatar-section .change-photo-btn:hover{
    background:#0B5ED7;
}

.profile-avatar-section input[type="file"]{
    display:none;
}

/* FORMULAIRE */
.profile-form{
    background:#FFFFFF;
    padding:25px;
    border-radius:15px;
    border:1px solid #D6E4FF;
}

.profile-form .form-section{
    margin-bottom:30px;
}

.profile-form .form-section h3{
    font-size:1.15rem;
    color:#0D6EFD;
    margin-bottom:20px;
    padding-bottom:10px;
    border-bottom:2px solid #D6E4FF;
}

.profile-form .form-group{
    margin-bottom:18px;
}

.profile-form label{
    display:block;
    margin-bottom:8px;
    font-weight:600;
    font-size:.9rem;
    color:#0D6EFD;
}

.profile-form input[type="text"],
.profile-form input[type="email"],
.profile-form input[type="password"],
.profile-form select{
    width:100%;
    padding:12px 14px;
    border-radius:10px;
    border:1px solid #D6E4FF;
    background:#F8FBFF;
    color:#1E293B;
    font-size:1rem;
    box-sizing:border-box;
    transition:.3s;
}

.profile-form input:focus,
.profile-form select:focus{
    outline:none;
    border-color:#0D6EFD;
    box-shadow:0 0 0 4px rgba(13,110,253,.15);
}

.profile-form input:read-only,
.profile-form select:disabled{
    background:#EEF4FF;
    opacity:.9;
}

/* BOUTON */
.profile-form .btn-update-profile{
    background:#0D6EFD;
    color:white;
    padding:14px 28px;
    border:none;
    border-radius:10px;
    font-size:1rem;
    font-weight:600;
    cursor:pointer;
    transition:.3s;
    box-shadow:0 4px 12px rgba(13,110,253,.25);
}

.profile-form .btn-update-profile:hover{
    background:#0B5ED7;
    transform:translateY(-2px);
}

/* ALERTES */
.alert{
    padding:15px 20px;
    margin-bottom:20px;
    border-radius:10px;
    font-weight:500;
}

.alert-success{
    background:#D6E4FF;
    color:#0D6EFD;
    border:1px solid #0D6EFD;
}

.alert-info{
    background:#EEF4FF;
    color:#0D6EFD;
    border:1px solid #D6E4FF;
}

/* MODE SOMBRE */
body.dark-theme .page-container,
body.dark-theme .profile-form{
    background:#1E293B;
    border-color:#334155;
}

body.dark-theme .profile-avatar-section{
    background:#162033;
    border-color:#334155;
}

body.dark-theme .profile-form input,
body.dark-theme .profile-form select{
    background:#162033;
    border-color:#334155;
    color:#F8FAFC;
}

body.dark-theme .profile-form label,
body.dark-theme .page-header h2,
body.dark-theme .profile-form .form-section h3{
    color:#60A5FA;
}
</style>
</head>
<body>
    <div class="dashboard-container">
        <?php 
            // Redéfinir $student_avatar et $student_name pour la topbar
            $student_avatar = $student_avatar_session; 
            $student_name = $student_name_session;
            include_once '../templates/sidebar_etudiant.php'; 
        ?>
        
        <div class="main-wrapper">
            <?php include_once '../templates/topbar_etudiant.php'; ?>

            <main class="content-area">
                <div class="main-column" style="width: 100%;">
                     <div class="page-container">
                        <div class="page-header">
                            <h2><i class="fas fa-user-edit"></i> Mon Profil</h2>
                        </div>
                        <p><small>Affichage du profil pour l'utilisateur simulé ID: <?php echo SIMULATED_UTILISATEUR_ID_PROFIL; ?></small></p>

                        <?php if ($message): ?>
                            <?php echo $message; ?>
                        <?php endif; ?>

                        <?php if ($profile_data): ?>
                        <form action="profil.php" method="POST" enctype="multipart/form-data" class="profile-form">
                            <div class="profile-grid">
                                <div class="profile-avatar-section">
                                    <img src="<?php echo !empty($profile_data['photo']) ? '../../uploads/profiles/' . htmlspecialchars($profile_data['photo']) : '../../assets/images/default_avatar.png'; ?>" alt="Photo de profil">
                                    <input type="file" name="profile_photo" id="profile_photo_input" accept="image/*">
                                    <label for="profile_photo_input" class="change-photo-btn">
                                        <i class="fas fa-camera"></i> Changer de photo
                                    </label>
                                </div>

                                <div class="profile-details-section">
                                    <div class="form-section">
                                        <h3>Informations Personnelles</h3>
                                        <div class="form-group">
                                            <label for="prenom">Prénom</label>
                                            <input type="text" id="prenom" name="prenom" value="<?php echo htmlspecialchars($profile_data['prenom']); ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="nom">Nom</label>
                                            <input type="text" id="nom" name="nom" value="<?php echo htmlspecialchars($profile_data['nom']); ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="email">Adresse Email</label>
                                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($profile_data['email']); ?>" required>
                                        </div>
                                    </div>

                                    <?php if ($profile_data['role'] == 'etudiant' && isset($profile_data['matricule'])): ?>
                                    <div class="form-section">
                                        <h3>Informations Étudiant</h3>
                                        <div class="form-group">
                                            <label for="matricule">Matricule</label>
                                            <input type="text" id="matricule" name="matricule" value="<?php echo htmlspecialchars($profile_data['matricule']); ?>" readonly>
                                        </div>
                                        <div class="form-group">
                                            <label for="classe">Classe</label>
                                            <input type="text" id="classe" name="classe" value="<?php echo htmlspecialchars($profile_data['nom_classe'] . ' - ' . $profile_data['niveau']); ?>" readonly>
                                        </div>
                                        <div class="form-group">
                                            <label for="filiere">Filière</label>
                                            <input type="text" id="filiere" name="filiere" value="<?php echo htmlspecialchars($profile_data['nom_filiere']); ?>" readonly>
                                        </div>
                                    </div>
                                    <?php endif; ?>

                                    <div class="form-section">
                                        <h3>Changer de Mot de Passe</h3>
                                        <div class="form-group">
                                            <label for="current_password">Mot de passe actuel</label>
                                            <input type="password" id="current_password" name="current_password" placeholder="Laisser vide pour ne pas changer">
                                        </div>
                                        <div class="form-group">
                                            <label for="new_password">Nouveau mot de passe</label>
                                            <input type="password" id="new_password" name="new_password" placeholder="Minimum 6 caractères">
                                        </div>
                                        <div class="form-group">
                                            <label for="confirm_password">Confirmer le nouveau mot de passe</label>
                                            <input type="password" id="confirm_password" name="confirm_password">
                                        </div>
                                    </div>
                                    
                                    <button type="submit" name="update_profile" class="btn-update-profile"><i class="fas fa-save"></i> Enregistrer les modifications</button>
                                </div>
                            </div>
                        </form>
                        <?php else: ?>
                            <p>Impossible de charger les données du profil.</p>
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