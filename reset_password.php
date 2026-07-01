<?php
session_start();

// Connexion à la base de données
$host = 'localhost';
$dbname = 'gestion_school';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

// Variables d'état
$error = '';
$success = '';
$token = $_GET['token'] ?? '';
$validToken = false;

// Si le formulaire est soumis
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'], $_POST['token'])) {
    $password = trim($_POST['password']);
    $token = trim($_POST['token']);

    if (strlen($password) < 8) {
        $error = "Le mot de passe doit contenir au moins 8 caractères.";
    } else {
        // Vérifier si le token est valide
        $stmt = $pdo->prepare("SELECT email FROM password_resets WHERE token = ? AND expires_at > NOW()");
        $stmt->execute([$token]);
        $resetRequest = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$resetRequest) {
            $error = "Lien de réinitialisation invalide ou expiré.";
        } else {
            // Mettre à jour le mot de passe
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("UPDATE  utilisateurs SET mot_de_passe = ? WHERE email = ?");
            $stmt->execute([$hashedPassword, $resetRequest['email']]);

            // Supprimer le token
            $stmt = $pdo->prepare("DELETE FROM password_resets WHERE token = ?");
            $stmt->execute([$token]);

            $_SESSION['success'] = "Mot de passe mis à jour avec succès. Veuillez vous connecter.";
            header("Location: index.php");
            exit();
        }
    }
}

// Si le token est passé dans l'URL
if (!empty($token) && empty($error)) {
    $stmt = $pdo->prepare("SELECT email FROM password_resets WHERE token = ? AND expires_at > NOW()");
    $stmt->execute([$token]);
    $validToken = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$validToken) {
        $error = "Lien de réinitialisation invalide ou expiré.";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Réinitialisation mot de passe - Gestion Scolaire</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background: linear-gradient(120deg, #007bff, #28a745);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .reset-box {
            background: #fff;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
            width: 100%;
            max-width: 400px;
        }
    </style>
</head>
<body>

<div class="reset-box text-center">
    <h3><i class="fas fa-key"></i> Réinitialiser votre mot de passe</h3>

    <?php if ($error): ?>
        <div class="alert alert-danger mt-3"><?= $error ?></div>
    <?php endif; ?>

    <?php if ($validToken): ?>
        <form method="post" class="mt-4">
            <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

            <div class="form-floating mb-3">
                <input type="password" name="password" class="form-control" id="password" placeholder="Nouveau mot de passe" required>
                <label for="password"><i class="fas fa-lock"></i> Nouveau mot de passe</label>
            </div>

            <button type="submit" class="btn btn-success w-100">
                <i class="fas fa-sync-alt"></i> Réinitialiser
            </button>
        </form>
    <?php elseif (!$error): ?>
        <div class="alert alert-warning mt-3">Lien de réinitialisation invalide ou expiré.</div>
    <?php endif; ?>

    <p class="mt-3">
        <a href="login.php"><i class="fas fa-arrow-left"></i> Retour à la connexion</a>
    </p>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
