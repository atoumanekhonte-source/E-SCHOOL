<?php
// /dashboard/etudiant/messagerie.php

// PAS D'AUTHENTIFICATION POUR CET EXEMPLE
require_once '../../config/db.php';

$database = new Database();
$conn = $database->connect();

// --- ID Utilisateur Simulé (pour la messagerie) ---
// En production, cet ID viendrait de $_SESSION['user_info']['utilisateur_id'] ou similaire,
// qui correspond à l'ID dans la table 'utilisateurs'.
define('SIMULATED_UTILISATEUR_ID_MESSAGERIE', 1); // !! IMPORTANT: ID utilisateur existant
                                                // Cet ID doit correspondre à un étudiant pour la simulation.
                                                // Par exemple, si etudiants.id = 1 a utilisateurs.id = 1.

// --- Fonctions de récupération de données ---
function get_messages_for_user($db_conn, $utilisateur_id) {
    $stmt = $db_conn->prepare("
        SELECT 
            msg.id, msg.sujet, msg.contenu, msg.date_envoi,
            msg.expediteur_id, msg.destinataire_id,
            exp.nom as exp_nom, exp.prenom as exp_prenom, exp.role as exp_role,
            dest.nom as dest_nom, dest.prenom as dest_prenom, dest.role as dest_role
            -- Ajouter une colonne 'lu' (BOOLEAN) à la table 'messages' serait utile
        FROM messages msg
        JOIN utilisateurs exp ON msg.expediteur_id = exp.id
        JOIN utilisateurs dest ON msg.destinataire_id = dest.id
        WHERE msg.expediteur_id = :utilisateur_id OR msg.destinataire_id = :utilisateur_id
        ORDER BY msg.date_envoi DESC
    ");
    $stmt->bindParam(':utilisateur_id', $utilisateur_id, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// --- Logique de la page ---
$student_avatar = '../../assets/images/default_avatar.png'; // Simulé
$student_name = 'Visiteur'; // Simulé

$messages = get_messages_for_user($conn, SIMULATED_UTILISATEUR_ID_MESSAGERIE);

$page_title = "Messagerie Interne";
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
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
}

.page-header h2 {
    color: #0D6EFD;
    margin: 0;
    font-weight: 700;
}

/* Bouton nouveau message */
.btn-nouveau-message {
    background: #0D6EFD;
    color: #ffffff;
    padding: 10px 18px;
    text-decoration: none;
    border-radius: 8px;
    font-weight: 600;
    transition: 0.3s;
    box-shadow: 0 3px 10px rgba(13,110,253,0.25);
}

.btn-nouveau-message:hover {
    background: #0b5ed7;
    transform: translateY(-2px);
}

.btn-nouveau-message i {
    margin-right: 8px;
}

/* Liste des messages */
.message-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

/* Carte message */
.message-item {
    border: 1px solid #d6e4ff;
    border-radius: 10px;
    margin-bottom: 15px;
    overflow: hidden;
    cursor: pointer;
    transition: all 0.3s ease;
    background: #ffffff;
}

.message-item:hover {
    border-color: #0D6EFD;
    box-shadow: 0 4px 15px rgba(13,110,253,0.15);
    transform: translateY(-2px);
}

/* En-tête message */
.message-header {
    padding: 12px 18px;
    background: #eef4ff;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #d6e4ff;
}

.message-header.unread {
    background: #d6e4ff;
    font-weight: bold;
}

/* Participants */
.message-participants {
    font-size: 0.9em;
}

.message-participants .sender,
.message-participants .recipient {
    color: #0D6EFD;
    font-weight: 600;
}

.message-participants .arrow {
    color: #6c757d;
    margin: 0 5px;
}

/* Badge rôle */
.message-participants .role-badge {
    font-size: 0.8em;
    padding: 3px 8px;
    border-radius: 20px;
    background: #d6e4ff;
    color: #0D6EFD;
    margin-left: 5px;
    font-weight: 600;
}

/* Date */
.message-date {
    font-size: 0.85em;
    color: #6c757d;
}

/* Sujet */
.message-subject {
    padding: 12px 18px;
    font-weight: 600;
    color: #0D6EFD;
    background: #ffffff;
}

/* Contenu */
.message-preview-content {
    display: none;
    padding: 15px 18px;
    font-size: 0.95em;
    color: #495057;
    border-top: 1px dashed #d6e4ff;
    background: #f8fbff;
    white-space: pre-wrap;
}

.message-item.expanded .message-preview-content {
    display: block;
}

/* Aucun message */
.no-messages {
    text-align: center;
    padding: 30px;
    color: #0D6EFD;
    background: #eef4ff;
    border-radius: 10px;
    border: 1px dashed #0D6EFD;
}

/* Responsive */
@media (max-width: 768px) {
    .page-header {
        flex-direction: column;
        gap: 15px;
        align-items: flex-start;
    }

    .message-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
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
                            <h2><i class="fas fa-envelope"></i> Messagerie Interne</h2>
                            <!-- L'envoi de message nécessite une identification de l'expéditeur (non implémenté ici) -->
                            <!-- <a href="nouveau_message.php" class="btn-nouveau-message"><i class="fas fa-plus"></i> Nouveau Message</a> -->
                        </div>
                        <p><small>Affichage des messages pour l'utilisateur simulé ID: <?php echo SIMULATED_UTILISATEUR_ID_MESSAGERIE; ?></small></p>

                        <?php if (empty($messages)): ?>
                            <div class="no-messages">
                                <i class="fas fa-comments fa-3x" style="margin-bottom:10px;"></i><br>
                                Vous n'avez aucun message pour le moment.
                            </div>
                        <?php else: ?>
                            <ul class="message-list">
                                <?php foreach ($messages as $msg): ?>
                                    <li class="message-item" data-message-id="<?php echo $msg['id']; ?>">
                                        <div class="message-header <?php /* echo (!$msg['lu'] && $msg['destinataire_id'] == SIMULATED_UTILISATEUR_ID_MESSAGERIE) ? 'unread' : ''; */ ?>">
                                            <span class="message-participants">
                                                <?php if ($msg['expediteur_id'] == SIMULATED_UTILISATEUR_ID_MESSAGERIE): ?>
                                                    <span class="sender">Vous</span>
                                                    <span class="arrow"><i class="fas fa-long-arrow-alt-right"></i></span>
                                                    <span class="recipient"><?php echo htmlspecialchars($msg['dest_prenom'] . ' ' . $msg['dest_nom']); ?></span>
                                                    <span class="role-badge"><?php echo htmlspecialchars($msg['dest_role']); ?></span>
                                                <?php else: ?>
                                                    <span class="sender"><?php echo htmlspecialchars($msg['exp_prenom'] . ' ' . $msg['exp_nom']); ?></span>
                                                    <span class="role-badge"><?php echo htmlspecialchars($msg['exp_role']); ?></span>
                                                    <span class="arrow"><i class="fas fa-long-arrow-alt-right"></i></span>
                                                    <span class="recipient">Vous</span>
                                                <?php endif; ?>
                                            </span>
                                            <span class="message-date"><?php echo date('d/m/Y H:i', strtotime($msg['date_envoi'])); ?></span>
                                        </div>
                                        <div class="message-subject">
                                            <?php echo htmlspecialchars($msg['sujet']); ?>
                                        </div>
                                        <div class="message-preview-content">
                                            <?php echo nl2br(htmlspecialchars($msg['contenu'])); ?>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Script pour le thème (identique aux autres pages)
            const themeToggle = document.getElementById('theme-toggle');
            if (themeToggle) { /* ... (code du thème identique) ... */ }

            // Script pour déplier/replier les messages
            const messageItems = document.querySelectorAll('.message-item');
            messageItems.forEach(item => {
                // On écoute le clic sur l'en-tête ou le sujet pour déplier
                const header = item.querySelector('.message-header');
                const subject = item.querySelector('.message-subject');

                const toggleExpand = () => {
                    item.classList.toggle('expanded');
                    // Marquer comme lu (nécessiterait un appel AJAX si on avait une colonne 'lu')
                    // if (item.classList.contains('expanded') && header.classList.contains('unread')) {
                    //     header.classList.remove('unread');
                    //     // Ici, faire un appel AJAX pour marquer le message comme lu dans la BDD
                    // }
                };

                if(header) header.addEventListener('click', toggleExpand);
                if(subject) subject.addEventListener('click', toggleExpand);
            });
        });
    </script>
</body>
</html>