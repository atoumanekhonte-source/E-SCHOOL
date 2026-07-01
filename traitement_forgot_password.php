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

// Vérifier si le formulaire est soumis
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
    $email = trim($_POST['email']);

    // Vérifier si l'utilisateur existe dans la table users
    try {
        $stmt = $pdo->prepare("SELECT id, email FROM utilisateurs WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Générer un token sécurisé
            $token = bin2hex(random_bytes(50)); // 100 caractères hexadécimaux
            $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // Supprimer les anciens tokens pour cet email
            $stmt = $pdo->prepare("DELETE FROM password_resets WHERE email = ?");
            $stmt->execute([$email]);

            // Insérer le nouveau token
            $stmt = $pdo->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
            $stmt->execute([$email, $token, $expires_at]);

            // Créer le lien de réinitialisation
            $resetLink = "http://localhost/gestion_school/reset_password.php?token=" . urlencode($token);

            // Affichage pour test (remplacer par envoi d'email en production)
            echo "<div style='padding:20px;font-family:Arial;max-width:600px;margin:0 auto;'>";
            echo "<h3 style='color:#007bff;'>Lien de réinitialisation généré</h3>";
            echo "<p>Un lien de réinitialisation a été créé pour l'email <strong>$email</strong>.</p>";
            echo "<p><a href='$resetLink' style='word-break:break-all;'>$resetLink</a></p>";
            echo "<p style='color:#dc3545;'>Ce lien expirera le " . date('d/m/Y à H:i', strtotime($expires_at)) . "</p>";
            echo "<a href='forgot_password.php' style='display:inline-block;margin-top:20px;color:#007bff;'>← Retour</a>";
            echo "</div>";
        } else {
            $_SESSION['error'] = "Aucun compte n'est associé à cette adresse email.";
            header("Location: forgot_password.php");
            exit();
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Une erreur technique est survenue. Veuillez réessayer.";
        header("Location: forgot_password.php");
        exit();
    }
} else {
    // Redirection si accès direct au script
    $_SESSION['error'] = "Accès non autorisé.";
    header("Location: forgot_password.php");
    exit();
}
?>