<?php
$page_title = "Modifier un Utilisateur";

require_once __DIR__ . '/../../includes/auth.php';
require_login(['admin']);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';

$database = new Database();
$db = $database->connect();

$user_id_to_edit = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$user_id_to_edit || $user_id_to_edit <= 0) {
    $_SESSION['error_message'] = "ID utilisateur non valide ou manquant.";
    header('Location: gestion_utilisateurs.php');
    exit;
}

// Récupérer les données de l'utilisateur
$stmt_user = $db->prepare("SELECT * FROM utilisateurs WHERE id = :id");
$stmt_user->bindParam(':id', $user_id_to_edit, PDO::PARAM_INT);
$stmt_user->execute();
$user_data = $stmt_user->fetch(PDO::FETCH_ASSOC);

if (!$user_data) {
    $_SESSION['error_message'] = "Utilisateur non trouvé.";
    header('Location: gestion_utilisateurs.php');
    exit;
}

$etudiant_data = null;
$enseignant_data = null;

if ($user_data['role'] === 'etudiant') {
    $stmt_etud = $db->prepare("SELECT * FROM etudiants WHERE id_utilisateur = :id_user");
    $stmt_etud->bindParam(':id_user', $user_id_to_edit, PDO::PARAM_INT);
    $stmt_etud->execute();
    $etudiant_data = $stmt_etud->fetch(PDO::FETCH_ASSOC);
} elseif ($user_data['role'] === 'enseignant') {
    $stmt_ens = $db->prepare("SELECT * FROM enseignants WHERE id_utilisateur = :id_user");
    $stmt_ens->bindParam(':id_user', $user_id_to_edit, PDO::PARAM_INT);
    $stmt_ens->execute();
    $enseignant_data = $stmt_ens->fetch(PDO::FETCH_ASSOC);
}

$errors = [];
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Nettoyage et récupération sécurisée des inputs
    $nom = sanitizeInput($_POST['nom'] ?? $user_data['nom']);
    $prenom = sanitizeInput($_POST['prenom'] ?? $user_data['prenom']);
    $email = sanitizeInput($_POST['email'] ?? $user_data['email']);
    $role = sanitizeInput($_POST['role'] ?? $user_data['role']);
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    $matricule = sanitizeInput($_POST['matricule'] ?? ($etudiant_data['matricule'] ?? ''));
    $id_classe = filter_input(INPUT_POST, 'id_classe', FILTER_VALIDATE_INT) ?? ($etudiant_data['id_classe'] ?? null);
    $specialite = sanitizeInput($_POST['specialite'] ?? ($enseignant_data['specialite'] ?? ''));

    // Validation des champs
    if (empty($nom)) {
        $errors[] = "Le nom est requis.";
    }
    if (empty($prenom)) {
        $errors[] = "Le prénom est requis.";
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Adresse e-mail invalide.";
    }

    if (!empty($new_password)) {
        if ($new_password !== $confirm_password) {
            $errors[] = "Les mots de passe ne correspondent pas.";
        } elseif (strlen($new_password) < 6) {
            $errors[] = "Le mot de passe doit contenir au moins 6 caractères.";
        }
    }

    if (empty($errors)) {
        try {
            $db->beginTransaction();

            if (!empty($new_password)) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt_update = $db->prepare("
                    UPDATE utilisateurs 
                    SET nom = :nom, prenom = :prenom, email = :email, role = :role, mot_de_passe = :password 
                    WHERE id = :id
                ");
                $stmt_update->bindParam(':password', $hashed_password);
            } else {
                $stmt_update = $db->prepare("
                    UPDATE utilisateurs 
                    SET nom = :nom, prenom = :prenom, email = :email, role = :role 
                    WHERE id = :id
                ");
            }

            $stmt_update->bindParam(':nom', $nom);
            $stmt_update->bindParam(':prenom', $prenom);
            $stmt_update->bindParam(':email', $email);
            $stmt_update->bindParam(':role', $role);
            $stmt_update->bindParam(':id', $user_id_to_edit, PDO::PARAM_INT);
            $stmt_update->execute();

            // Mise à jour des données spécifiques selon rôle
            if ($role === 'etudiant') {
                if ($etudiant_data) {
                    $stmt_update_etud = $db->prepare("
                        UPDATE etudiants 
                        SET matricule = :matricule, id_classe = :id_classe 
                        WHERE id_utilisateur = :id_user
                    ");
                } else {
                    $stmt_update_etud = $db->prepare("
                        INSERT INTO etudiants (matricule, id_classe, id_utilisateur) 
                        VALUES (:matricule, :id_classe, :id_user)
                    ");
                }
                $stmt_update_etud->bindParam(':matricule', $matricule);
                $stmt_update_etud->bindParam(':id_classe', $id_classe);
                $stmt_update_etud->bindParam(':id_user', $user_id_to_edit, PDO::PARAM_INT);
                $stmt_update_etud->execute();

            } elseif ($role === 'enseignant') {
                if ($enseignant_data) {
                    $stmt_update_ens = $db->prepare("
                        UPDATE enseignants 
                        SET specialite = :specialite 
                        WHERE id_utilisateur = :id_user
                    ");
                } else {
                    $stmt_update_ens = $db->prepare("
                        INSERT INTO enseignants (specialite, id_utilisateur) 
                        VALUES (:specialite, :id_user)
                    ");
                }
                $stmt_update_ens->bindParam(':specialite', $specialite);
                $stmt_update_ens->bindParam(':id_user', $user_id_to_edit, PDO::PARAM_INT);
                $stmt_update_ens->execute();
            }

            $db->commit();
            $success_message = "L'utilisateur a été mis à jour avec succès.";

            // Optionnel : recharger les données pour afficher les nouvelles valeurs
            $stmt_user->execute();
            $user_data = $stmt_user->fetch(PDO::FETCH_ASSOC);

            if ($role === 'etudiant') {
                $stmt_etud->execute();
                $etudiant_data = $stmt_etud->fetch(PDO::FETCH_ASSOC);
            } elseif ($role === 'enseignant') {
                $stmt_ens->execute();
                $enseignant_data = $stmt_ens->fetch(PDO::FETCH_ASSOC);
            }

        } catch (PDOException $e) {
            $db->rollBack();
            $errors[] = "Erreur lors de la mise à jour : " . $e->getMessage();
        }
    }
}
?>



<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo sanitizeInput($page_title); ?> - Gestion Scolaire</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Copier les styles communs depuis admin/index.php ou une feuille CSS globale */
        /* :root, body, layout, sidebar, topbar, content-area, form-container, form-grid, form-group, btn-submit, alert... */
        :root { /* ... variables ... */ 
            --primary-color: #7B66FF; --primary-light: #E9E6FF; --primary-dark: #5A48C0;
            --secondary-color: #34D399; --bg-color: #F7F7FC; --sidebar-bg: #FFFFFF;
            --card-bg: #FFFFFF; --text-color: #374151; --text-light: #6B7280;
            --border-color: #E5E7EB; --shadow-color: rgba(123, 102, 255, 0.1);
            --sidebar-width: 260px; --topbar-height: 70px;
            --border-radius-main: 12px; --border-radius-card: 10px;
            --transition-speed: 0.3s;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background-color: var(--bg-color); color: var(--text-color); display: flex; height: 100vh; overflow: hidden; }
        .admin-dashboard-layout { display: flex; width: 100%; height: 100%; }
        /* Collez ici les styles de sidebar.php et topbar.php si non externalisés */
        /* ... (Styles Sidebar et Topbar) ... */
         .sidebar { width: var(--sidebar-width); background-color: var(--sidebar-bg); padding:0; display: flex; flex-direction: column; border-right: 1px solid var(--border-color); transition: width var(--transition-speed) ease; box-shadow: 2px 0 10px var(--shadow-light); z-index: 1000;}
        .sidebar-header{display:flex;align-items:center;justify-content:space-between;padding:20px;height:var(--topbar-height);border-bottom:1px solid var(--border-color)}
        .sidebar-logo-link{display:flex;align-items:center;text-decoration:none}
        .logo-icon{font-size:28px;color:var(--primary-color);margin-right:12px;transition:transform var(--transition-speed) ease}
        .sidebar-logo-link:hover .logo-icon{transform:rotate(-15deg) scale(1.1)}
        .logo-text{font-size:22px;font-weight:700;color:var(--primary-color);white-space:nowrap;overflow:hidden;opacity:1;transition:opacity var(--transition-speed) ease,max-width var(--transition-speed) ease}
        .sidebar-nav{flex-grow:1;overflow-y:auto;padding:15px 0}
        .sidebar-menu{list-style:none;padding:0;margin:0}
        .sidebar-menu li{padding:0 15px}
        .nav-link{display:flex;align-items:center;padding:13px 15px;margin-bottom:6px;border-radius:var(--border-radius-card);color:var(--text-light);text-decoration:none;font-weight:500;transition:background-color var(--transition-speed) ease,color var(--transition-speed) ease,transform .1s ease;position:relative;overflow:hidden}
        .nav-link:before{content:"";position:absolute;left:0;top:0;height:100%;width:4px;background-color:var(--primary-color);transform:scaleY(0);transition:transform var(--transition-speed) ease;border-top-right-radius:4px;border-bottom-right-radius:4px;opacity:0}
        .nav-icon{margin-right:15px;font-size:18px;width:24px;text-align:center;color:var(--text-light);transition:color var(--transition-speed) ease}
        .nav-text{white-space:nowrap;overflow:hidden;opacity:1;transition:opacity var(--transition-speed) ease}
        .nav-link:hover,.nav-link.active{background-color:var(--primary-light);color:var(--primary-color);font-weight:600}
        .nav-link:hover .nav-icon,.nav-link.active .nav-icon{color:var(--primary-color)}
        .nav-link.active:before{transform:scaleY(1);opacity:1}
        .nav-link:active{transform:translateX(2px)}
        .nav-item-separator{padding:15px 30px 5px;font-size:.75em;text-transform:uppercase;font-weight:600;color:var(--text-light);opacity:.7;letter-spacing:.5px;white-space:nowrap}
        .sidebar-footer{padding:15px 20px 20px;border-top:1px solid var(--border-color);margin-top:auto}
        .logout-link{margin-bottom:15px!important;color:var(--accent-red)!important}
        .logout-link:hover{background-color:rgba(239,68,68,.1)!important;color:var(--accent-red)!important}
        .logout-link:hover .nav-icon{color:var(--accent-red)!important}
        .sidebar-pro-card{background:linear-gradient(135deg,var(--primary-color),var(--primary-dark));padding:20px;border-radius:var(--border-radius-main);text-align:center;color:var(--text-inverted);position:relative;overflow:hidden}
        .pro-icon-bg{width:40px;height:40px;background-color:rgba(255,255,255,.2);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 10px}
        .pro-icon{font-size:20px;color:var(--text-inverted)}
        .pro-title{display:block;font-weight:600;font-size:1.1em;margin-bottom:5px}
        .pro-description{font-size:.85em;opacity:.8;margin-bottom:15px}
        .pro-button{display:block;background-color:var(--text-inverted);color:var(--primary-dark);padding:10px;border-radius:var(--border-radius-card);text-decoration:none;font-weight:600;transition:background-color var(--transition-speed) ease,color var(--transition-speed) ease}
        .pro-button:hover{background-color:var(--primary-light);color:var(--primary-dark)}
        .top-navbar{height:var(--topbar-height);background-color:var(--sidebar-bg);display:flex;align-items:center;justify-content:space-between;padding:0 25px;border-bottom:1px solid var(--border-color);box-shadow:0 2px 5px var(--shadow-light);position:sticky;top:0;z-index:900}
        .topbar-left{display:flex;align-items:center}
        .hamburger-btn{background:none;border:none;font-size:20px;color:var(--text-light);cursor:pointer;padding:10px;margin-right:15px;transition:color var(--transition-speed) ease}
        .hamburger-btn:hover{color:var(--primary-color)}
        .search-bar-container{display:flex;align-items:center;background-color:var(--bg-color);padding:0 15px;border-radius:var(--border-radius-main);height:40px;min-width:280px;transition:box-shadow var(--transition-speed) ease}
        .search-bar-container:focus-within{box-shadow:0 0 0 2px var(--primary-color)}
        .search-icon{color:var(--text-light);margin-right:10px;font-size:16px}
        .search-input{border:none;outline:none;background:transparent;color:var(--text-color);font-size:.9em;width:100%;height:100%}
        .search-input::placeholder{color:var(--text-light)}
        .topbar-right{display:flex;align-items:center;gap:10px}
        .topbar-action-btn{background-color:transparent;color:var(--text-light);padding:8px 12px;border:1px solid var(--border-color);border-radius:var(--border-radius-card);font-weight:500;font-size:.9em;text-decoration:none;display:inline-flex;align-items:center;gap:6px;transition:background-color var(--transition-speed) ease,color var(--transition-speed) ease,border-color var(--transition-speed) ease}
        .topbar-action-btn:hover{background-color:var(--primary-light);color:var(--primary-color);border-color:var(--primary-light)}
        .live-btn-header{background-color:var(--accent-red);color:var(--text-inverted);border-color:var(--accent-red)}
        .live-btn-header:hover{background-color:#d32f2f;border-color:#d32f2f;color:var(--text-inverted)}
        .topbar-icon-group{display:flex;align-items:center;gap:5px}
        .topbar-icon-btn{background:none;border:none;color:var(--text-light);font-size:20px;cursor:pointer;width:40px;height:40px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;position:relative;transition:background-color var(--transition-speed) ease,color var(--transition-speed) ease}
        .topbar-icon-btn:hover{background-color:var(--primary-light);color:var(--primary-color)}
        .notification-badge{position:absolute;top:6px;right:6px;background-color:var(--accent-red);color:#fff;font-size:.7em;font-weight:700;width:16px;height:16px;border-radius:50%;display:flex;align-items:center;justify-content:center;line-height:1}
        .profile-dropdown{position:relative}
        .profile-trigger{background:none;border:none;display:flex;align-items:center;cursor:pointer;padding:5px;border-radius:var(--border-radius-card);transition:background-color var(--transition-speed) ease}
        .profile-trigger:hover{background-color:var(--bg-color)}
        .profile-pic-topbar{width:38px;height:38px;border-radius:50%;object-fit:cover;margin-right:10px;border:2px solid var(--primary-light)}
        .profile-info-topbar{text-align:left}
        .profile-name-topbar{font-weight:600;font-size:.9em;color:var(--text-color);display:block}
        .profile-role-topbar{font-size:.75em;color:var(--text-light);display:block}
        .dropdown-arrow{font-size:.8em;color:var(--text-light);margin-left:8px;transition:transform var(--transition-speed) ease}
        .profile-trigger[aria-expanded=true] .dropdown-arrow{transform:rotate(180deg)}
        .dropdown-menu{position:absolute;top:calc(100% + 10px);right:0;background-color:var(--card-bg);border-radius:var(--border-radius-main);box-shadow:0 8px 25px var(--shadow-medium);width:220px;z-index:1010;opacity:0;visibility:hidden;transform:translateY(10px);transition:opacity var(--transition-speed) ease,transform var(--transition-speed) ease,visibility 0s var(--transition-speed) linear;border:1px solid var(--border-color);padding:8px 0}
        .profile-trigger[aria-expanded=true]+.dropdown-menu{opacity:1;visibility:visible;transform:translateY(0);transition-delay:0s,0s,0s}
        .dropdown-item{display:flex;align-items:center;padding:10px 15px;color:var(--text-color);text-decoration:none;font-size:.9em;transition:background-color var(--transition-speed) ease,color var(--transition-speed) ease}
        .dropdown-item i{margin-right:12px;width:18px;text-align:center;color:var(--text-light);transition:color var(--transition-speed) ease}
        .dropdown-item:hover{background-color:var(--primary-light);color:var(--primary-color)}
        .dropdown-item:hover i{color:var(--primary-color)}
        .dropdown-divider{height:1px;background-color:var(--border-color);margin:8px 0}
        .dropdown-item-logout{color:var(--accent-red)}
        .dropdown-item-logout:hover{background-color:rgba(239,68,68,.1);color:var(--accent-red)}
        .dropdown-item-logout:hover i{color:var(--accent-red)}

        .main-panel { flex-grow: 1; display: flex; flex-direction: column; height: 100vh; }
        .content-area { flex-grow: 1; padding: 30px; overflow-y: auto; }
        .content-header { display:flex; justify-content: space-between; align-items:center; margin-bottom: 25px; }
        .content-header h1 { font-size: 26px; font-weight: 700; color: var(--text-color); }
        .content-header .back-link {
            color: var(--primary-color); text-decoration: none; font-weight: 500;
            display:inline-flex; align-items:center;
        }
        .content-header .back-link i { margin-right: 8px; }
        .content-header .back-link:hover { text-decoration: underline; }

        .form-container {
            background-color: var(--card-bg); padding: 35px; border-radius: var(--border-radius-main);
            box-shadow: 0 8px 25px var(--shadow-medium); border: 1px solid var(--border-color);
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); /* Responsive grid */
            gap: 25px 30px; /* row-gap column-gap */
        }
        .form-group { display: flex; flex-direction: column; }
        .form-group label {
            font-weight: 600; /* Plus gras */
            margin-bottom: 10px; /* Plus d'espace */
            color: var(--text-color);
            font-size: 0.9em;
        }
        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group input[type="password"],
        .form-group input[type="file"],
        .form-group select {
            padding: 14px 18px; /* Plus grand */
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-card);
            background-color: #F9FAFB; /* Fond très légèrement différent */
            color: var(--text-color);
            font-size: 1em;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }
        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px var(--primary-light); /* Ombre au focus */
        }
        .form-group input[type="file"] { padding: 10px 15px; }
        .current-photo-preview {
            display: block; max-width: 120px; max-height: 120px;
            border-radius: var(--border-radius-card); margin-top: 10px; border: 1px solid var(--border-color);
            object-fit: cover;
        }
        
        .password-help-text { font-size: 0.8em; color: var(--text-light); margin-top: 5px; }

        .form-actions { margin-top: 30px; text-align: right; }
        .btn-submit {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white; padding: 14px 30px; border: none;
            border-radius: var(--border-radius-card); font-weight: 600; font-size: 1em;
            cursor: pointer; transition: all 0.2s ease;
            box-shadow: 0 4px 15px rgba(123,102,255,0.3);
        }
        .btn-submit i { margin-right: 10px; }
        .btn-submit:hover { opacity:0.9; box-shadow: 0 6px 20px rgba(123,102,255,0.4); }
        .btn-submit:active { transform: scale(0.97); }

        .alert { padding: 15px 20px; margin-bottom: 25px; border-radius: var(--border-radius-card); font-size: 0.95em; display: flex; align-items: center; gap: 10px; border-width: 1px; border-style: solid; }
        .alert i { font-size: 1.2em; }
        .alert-danger { background-color: #FFF1F2; color: #C53030; border-color: #FC8181; }
        .alert-success { background-color: #F0FFF4; color: #2F855A; border-color: #68D391; }
        .alert ul { list-style-position: inside; padding-left: 5px; margin-top: 5px; }
    </style>
</head>
<body>
    <div class="admin-dashboard-layout">
        <?php include __DIR__ . '/../parts/sidebar.php'; ?>
        <div class="main-panel">
            <?php include __DIR__ . '/../parts/topbar.php'; ?>

            <main class="content-area">
                <div class="content-header">
                    <h1><i class="fas fa-user-edit" style="color:var(--primary-color); margin-right:10px;"></i><?php echo sanitizeInput($page_title); ?></h1>
                    <a href="gestion_utilisateurs.php" class="back-link"><i class="fas fa-arrow-left"></i> Retour à la liste</a>
                </div>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-times-circle"></i>
                        <div>
                            <strong>Erreur(s) lors de la modification :</strong>
                            <ul><?php foreach ($errors as $error): ?><li><?php echo sanitizeInput($error); ?></li><?php endforeach; ?></ul>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($success_message): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?php echo sanitizeInput($success_message); ?>
                    </div>
                <?php endif; ?>

                <?php if ($user_data): ?>
                <div class="form-container">
                    <form action="modifier_utilisateur.php?id=<?php echo $user_id_to_edit; ?>" method="POST" enctype="multipart/form-data">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="prenom">Prénom</label>
                                <input type="text" id="prenom" name="prenom" value="<?php echo sanitizeInput($_POST['prenom'] ?? $user_data['prenom']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="nom">Nom</label>
                                <input type="text" id="nom" name="nom" value="<?php echo sanitizeInput($_POST['nom'] ?? $user_data['nom']); ?>" required>
                            </div>
                             <div class="form-group">
                                <label for="email">Email</label>
                                <input type="email" id="email" name="email" value="<?php echo sanitizeInput($_POST['email'] ?? $user_data['email']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="role">Rôle</label>
                                <select id="role" name="role" required onchange="toggleRoleSpecificFields(this.value)">
                                    <option value="admin" <?php echo (($POST['role'] ?? $user_data['role']) == 'admin') ? 'selected' : ''; ?>>Administrateur</option>
                                    <option value="etudiant" <?php echo (($POST['role'] ?? $user_data['role']) == 'etudiant') ? 'selected' : ''; ?>>Étudiant</option>
                                    <option value="enseignant" <?php echo (($POST['role'] ?? $user_data['role']) == 'enseignant') ? 'selected' : ''; ?>>Enseignant</option>
                                </select>
                            </div>

                            <fieldset id="etudiant_fields" style="display:<?php echo (($POST['role'] ?? $user_data['role']) == 'etudiant') ? 'contents' : 'none'; ?>; grid-column: 1 / -1; border:none; padding:0; margin:0;">
                                <!-- 'contents' pour que les enfants se placent dans la grille parente -->
                                <legend style="font-weight: 600; margin-bottom: 10px; font-size: 1em; color: var(--primary-dark); padding-top:15px; border-top:1px dashed var(--border-color); width:100%;">Détails Étudiant</legend>
                                <div class="form-group">
                                    <label for="matricule">Matricule</label>
                                    <input type="text" id="matricule" name="matricule" value="<?php echo sanitizeInput($_POST['matricule'] ?? ($etudiant_data['matricule'] ?? '')); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="id_classe">Classe</label>
                                    <select id="id_classe" name="id_classe">
                                        <option value="">-- Sélectionner une classe --</option>
                                        <?php foreach ($all_classes as $classe_item): ?>
                                            <option value="<?php echo $classe_item['id']; ?>" 
                                                <?php echo (($POST['id_classe'] ?? ($etudiant_data['id_classe'] ?? '')) == $classe_item['id']) ? 'selected' : ''; ?>>
                                                <?php echo sanitizeInput($classe_item['niveau'] . ' - ' . $classe_item['nom_classe']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </fieldset>

                            <fieldset id="enseignant_fields" style="display:<?php echo (($POST['role'] ?? $user_data['role']) == 'enseignant') ? 'contents' : 'none'; ?>; grid-column: 1 / -1; border:none; padding:0; margin:0;">
                                <legend style="font-weight: 600; margin-bottom: 10px; font-size: 1em; color: var(--primary-dark); padding-top:15px; border-top:1px dashed var(--border-color); width:100%;">Détails Enseignant</legend>
                                <div class="form-group">
                                    <label for="specialite">Spécialité</label>
                                    <input type="text" id="specialite" name="specialite" value="<?php echo sanitizeInput($_POST['specialite'] ?? ($enseignant_data['specialite'] ?? '')); ?>">
                                </div>
                            </fieldset>
                            
                            <div class="form-group" style="grid-column: 1 / -1; padding-top:15px; border-top:1px dashed var(--border-color); margin-top:10px;">
                                <label for="photo">Changer la Photo de profil (Optionnel)</label>
                                <?php if (!empty($user_data['photo'])): ?>
                                    <img src="<?php echo $base_url . 'uploads/profiles/' . sanitizeInput($user_data['photo']); ?>" alt="Photo actuelle" class="current-photo-preview">
                                <?php else: ?>
                                    <p>Aucune photo actuelle.</p>
                                <?php endif; ?>
                                <input type="file" id="photo" name="photo" accept="image/png, image/jpeg, image/gif" style="margin-top:10px;">
                            </div>

                            <div class="form-group" style="grid-column: 1 / -1; padding-top:15px; border-top:1px dashed var(--border-color); margin-top:10px;">
                                <label for="new_password">Nouveau Mot de passe (Laisser vide pour ne pas changer)</label>
                                <input type="password" id="new_password" name="new_password">
                                <small class="password-help-text">Minimum 6 caractères.</small>
                            </div>
                            <div class="form-group" style="grid-column: 1 / -1;">
                                <label for="confirm_password">Confirmer le Nouveau Mot de passe</label>
                                <input type="password" id="confirm_password" name="confirm_password">
                            </div>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn-submit"><i class="fas fa-save"></i> Enregistrer les Modifications</button>
                        </div>
                    </form>
                </div>
                <?php else: ?>
                    <p>Utilisateur non trouvé ou erreur lors du chargement des données.</p>
                <?php endif; ?>
            </main>
        </div>
    </div>
<script>
    function toggleRoleSpecificFields(selectedRole) {
        const etudiantFields = document.getElementById('etudiant_fields');
        const enseignantFields = document.getElementById('enseignant_fields');

        etudiantFields.style.display = (selectedRole === 'etudiant') ? 'contents' : 'none';
        enseignantFields.style.display = (selectedRole === 'enseignant') ? 'contents' : 'none';
    }
    // Appel initial pour s'assurer que les bons champs sont visibles au chargement
    document.addEventListener('DOMContentLoaded', function() {
        const roleSelect = document.getElementById('role');
        if (roleSelect) {
            toggleRoleSpecificFields(roleSelect.value);
        }
        // Scripts de la sidebar/topbar (si non chargés globalement)
        // ...
    });
</script>
</body>
</html>