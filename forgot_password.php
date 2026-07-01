<?php
session_start();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Mot de passe oublié - Gestion Scolaire</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- ✅ Bootstrap 5 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- ✅ Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- ✅ Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">

    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #007bff, #00c851);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            animation: fadeIn 1s ease-in-out;
        }

        @keyframes fadeIn {
            from {opacity: 0; transform: translateY(-30px);}
            to {opacity: 1; transform: translateY(0);}
        }

        .forgot-container {
            background-color: white;
            border-radius: 15px;
            padding: 30px;
            max-width: 450px;
            width: 100%;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
        }

        .forgot-container h2 {
            color: #343a40;
        }

        .form-control {
            border-radius: 30px;
        }

        .btn-primary {
            border-radius: 30px;
        }

        .text-muted a {
            color: #007bff;
            text-decoration: none;
        }

        .text-muted a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

<div class="forgot-container text-center">
    <h2><i class="fas fa-unlock-alt"></i> Réinitialisation du mot de passe</h2>
    <p class="text-muted">Entrez votre adresse email pour recevoir un lien de réinitialisation.</p>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <form action="traitement_forgot_password.php" method="post">
        <div class="form-floating mb-3">
            <input type="email" class="form-control" id="email" placeholder="Adresse Email" name="email" required>
            <label for="email"><i class="fas fa-envelope"></i> Adresse Email</label>
        </div>

        <button type="submit" class="btn btn-primary w-100">
            <i class="fas fa-paper-plane"></i> Envoyer le lien
        </button>

        <p class="mt-3 text-muted">
            <a href="login.php"><i class="fas fa-arrow-left"></i> Retour à la connexion</a>
        </p>
    </form>
</div>

<!-- ✅ JS Bootstrap -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>