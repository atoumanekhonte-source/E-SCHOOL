<?php
// Chemin vers config/db.php basé sur votre structure
// Si gestion_classes.php est dans modules/classes/
// et config/db.php est dans config/ à la racine du projet.
require_once __DIR__ . '/../../config/db.php'; // Ajustez si votre structure est différente
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../config/db.php';


define('BASE_URL', 'http://localhost/gestion_school/');

function getCurrentPage() {
    return basename($_SERVER['PHP_SELF']);
}

$pageTitle = "Gestion des Classes"; // Titre pour la barre du haut et l'onglet du navigateur

// --- Initialisation Database (comme dans votre code) ---
$database = new Database();
$db = $database->connect(); // $db est utilisé par les requêtes ci-dessous

// --- CRUD Operations for Classes (VOTRE LOGIQUE PHP EXISTANTE) ---
$message = '';
$message_type = ''; // 'success' or 'danger'

// Fetch Filières for dropdown
$filieres = [];
$count_filieres = 0;
try {
    $stmt_filieres = $database->prepare("SELECT id, nom_filiere FROM filieres ORDER BY nom_filiere ASC");
    if ($database->execute($stmt_filieres)) {
        $filieres = $database->fetchAll($stmt_filieres);
        $count_filieres = count($filieres);
    } else {
        throw new PDOException("Erreur lors de l'exécution de la requête pour les filières.");
    }
} catch (PDOException $e) {
    // Ne pas définir $message ici, cela pourrait masquer des messages de session
    error_log("Filiere fetch error: " . $e->getMessage());
}

// ADD Classe
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_classe'])) {
    $nom_classe = sanitizeInput($_POST['nom_classe']);
    $niveau = sanitizeInput($_POST['niveau']);
    $id_filiere = filter_input(INPUT_POST, 'id_filiere', FILTER_VALIDATE_INT);

    if (!empty($nom_classe) && !empty($niveau) && $id_filiere) {
        try {
            $stmt = $database->prepare("INSERT INTO classes (nom_classe, niveau, id_filiere) VALUES (:nom_classe, :niveau, :id_filiere)");
            $database->execute($stmt, [
                ':nom_classe' => $nom_classe,
                ':niveau' => $niveau,
                ':id_filiere' => $id_filiere
            ]);
            $_SESSION['message'] = "Classe ajoutée avec succès!";
            $_SESSION['message_type'] = "success";
        } catch (PDOException $e) {
            $_SESSION['message'] = "Erreur lors de l'ajout de la classe: " . $e->getMessage();
            $_SESSION['message_type'] = "danger";
        }
    } else {
        $_SESSION['message'] = "Tous les champs sont requis.";
        $_SESSION['message_type'] = "danger";
    }
    header("Location: gestion_classes.php"); // Assurez-vous que c'est le bon nom de fichier
    exit();
}

// EDIT Classe - Load data
$edit_classe = null;
if (isset($_GET['edit_id'])) {
    $edit_id = filter_input(INPUT_GET, 'edit_id', FILTER_VALIDATE_INT);
    if ($edit_id) {
        try {
            $stmt = $database->prepare("SELECT * FROM classes WHERE id = :id");
            $database->execute($stmt, [':id' => $edit_id]);
            $edit_classe = $database->fetch($stmt);
            if (!$edit_classe) {
                $_SESSION['message'] = "Classe non trouvée pour l'édition.";
                $_SESSION['message_type'] = "warning";
                header("Location: gestion_classes.php"); exit();
            }
        } catch (PDOException $e) {
            $_SESSION['message'] = "Erreur lors de la récupération de la classe: " . $e->getMessage();
            $_SESSION['message_type'] = "danger";
            header("Location: gestion_classes.php"); exit();
        }
    }
}

// UPDATE Classe
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_classe'])) {
    $id = filter_input(INPUT_POST, 'id_classe', FILTER_VALIDATE_INT);
    $nom_classe = sanitizeInput($_POST['nom_classe']);
    $niveau = sanitizeInput($_POST['niveau']);
    $id_filiere = filter_input(INPUT_POST, 'id_filiere', FILTER_VALIDATE_INT);

    if ($id && !empty($nom_classe) && !empty($niveau) && $id_filiere) {
        try {
            $stmt = $database->prepare("UPDATE classes SET nom_classe = :nom_classe, niveau = :niveau, id_filiere = :id_filiere WHERE id = :id");
            $database->execute($stmt, [
                ':nom_classe' => $nom_classe,
                ':niveau' => $niveau,
                ':id_filiere' => $id_filiere,
                ':id' => $id
            ]);
            $_SESSION['message'] = "Classe mise à jour avec succès!";
            $_SESSION['message_type'] = "success";
        } catch (PDOException $e) {
            $_SESSION['message'] = "Erreur lors de la MAJ: " . $e->getMessage();
            $_SESSION['message_type'] = "danger";
        }
    } else {
        $_SESSION['message'] = "Champs requis pour MAJ.";
        $_SESSION['message_type'] = "danger";
    }
    header("Location: gestion_classes.php"); exit();
}

// DELETE Classe
if (isset($_GET['delete_id'])) {
    $delete_id = filter_input(INPUT_GET, 'delete_id', FILTER_VALIDATE_INT);
    if ($delete_id) {
        try {
            $stmt = $database->prepare("DELETE FROM classes WHERE id = :id");
            $database->execute($stmt, [':id' => $delete_id]);
            $_SESSION['message'] = "Classe supprimée!";
            $_SESSION['message_type'] = "success";
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') {
                 $_SESSION['message'] = "Suppression impossible: classe référencée.";
            } else {
                 $_SESSION['message'] = "Erreur suppression: " . $e->getMessage();
            }
            $_SESSION['message_type'] = "danger";
        }
    }
    header("Location: gestion_classes.php"); exit();
}

// LIST Classes
$classes = [];
$count_classes = 0;
try {
    $stmt_classes = $database->prepare("
        SELECT c.id, c.nom_classe, c.niveau, f.nom_filiere
        FROM classes c
        JOIN filieres f ON c.id_filiere = f.id
        ORDER BY f.nom_filiere, c.niveau, c.nom_classe ASC
    ");
    if ($database->execute($stmt_classes)) {
        $classes = $database->fetchAll($stmt_classes);
        $count_classes = count($classes);
    } else {
         throw new PDOException("Erreur exécution listage classes.");
    }
} catch (PDOException $e) {
    // Ne pas définir $message ici pour ne pas écraser les messages de session
    error_log("Classe list error: " . $e->getMessage());
}

// Display session messages
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'];
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

// Inclure l'en-tête
// Chemin vers templates/template_header.php basé sur votre structure
require_once __DIR__ . '/../templates/template_header.php';
?>

<!-- Section de statistiques inspirée par votre design -->
<div class="row stats-card-row">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stat-card">
            <div class="stat-card-icon icon-cr-progress">
                <i class="fas fa-chalkboard"></i>
            </div>
            <div class="stat-card-info">
                <h6>Total Classes</h6>
                <span class="stat-number"><?php echo $count_classes; ?></span>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stat-card">
            <div class="stat-card-icon icon-tasks-pending">
                <i class="fas fa-sitemap"></i>
            </div>
            <div class="stat-card-info">
                <h6>Total Filières</h6>
                <span class="stat-number"><?php echo $count_filieres; ?></span>
            </div>
        </div>
    </div>
    <!-- Vous pouvez ajouter d'autres cartes ici si pertinent -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stat-card">
            <div class="stat-card-icon icon-tasks-not-assigned">
                <i class="fas fa-graduation-cap"></i>
            </div>
            <div class="stat-card-info">
                <h6>Étudiants (Exemple)</h6>
                <span class="stat-number">125</span>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stat-card">
            <div class="stat-card-icon icon-tasks-completed">
                <i class="fas fa-user-tie"></i>
            </div>
            <div class="stat-card-info">
                <h6>Enseignants (Exemple)</h6>
                <span class="stat-number">15</span>
            </div>
        </div>
    </div>
</div>


<!-- Contenu spécifique à la page (votre HTML existant) -->
<?php if (!empty($message)): ?>
    <div class="alert <?php echo 'alert-custom-' . htmlspecialchars($message_type); ?> alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- Add/Edit Form Card -->
<div class="card card-custom">
    <div class="card-header">
        <i class="fas <?php echo $edit_classe ? 'fa-edit' : 'fa-plus-circle'; ?>"></i>
        <?php echo $edit_classe ? ' Modifier la Classe' : ' Ajouter une Classe'; ?>
    </div>
    <div class="card-body">
        <form action="gestion_classes.php" method="POST">
            <?php if ($edit_classe): ?>
                <input type="hidden" name="id_classe" value="<?php echo htmlspecialchars($edit_classe['id']); ?>">
            <?php endif; ?>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="nom_classe" class="form-label">Nom de la classe</label>
                    <input type="text" class="form-control" id="nom_classe" name="nom_classe"
                           value="<?php echo $edit_classe ? htmlspecialchars($edit_classe['nom_classe']) : ''; ?>" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label for="niveau" class="form-label">Niveau</label>
                    <input type="text" class="form-control" id="niveau" name="niveau"
                           value="<?php echo $edit_classe ? htmlspecialchars($edit_classe['niveau']) : ''; ?>" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label for="id_filiere" class="form-label">Filière</label>
                    <select class="form-select" id="id_filiere" name="id_filiere" required>
                        <option value="">-- Sélectionner une filière --</option>
                        <?php if (empty($filieres) && empty($message) ): ?>
                             <option value="" disabled>Aucune filière disponible.</option>
                        <?php endif; ?>
                        <?php foreach ($filieres as $filiere): ?>
                            <option value="<?php echo htmlspecialchars($filiere['id']); ?>"
                                <?php echo ($edit_classe && $edit_classe['id_filiere'] == $filiere['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($filiere['nom_filiere']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                     <?php if (empty($filieres) && empty($message)): ?>
                        <div class="form-text text-warning mt-1">
                            <small><i class="fas fa-exclamation-triangle"></i> Aucune filière n'est enregistrée. Veuillez en <a href="<?php echo BASE_URL; ?>dashboard/admin/gestion_filieres.php">ajouter</a>.</small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="d-flex justify-content-end mt-3">
                <?php if ($edit_classe): ?>
                    <button type="submit" name="update_classe" class="btn btn-custom-edit me-2"><i class="fas fa-save"></i> Mettre à jour</button>
                    <a href="gestion_classes.php" class="btn btn-secondary"><i class="fas fa-times"></i> Annuler</a>
                <?php else: ?>
                    <button type="submit" name="add_classe" class="btn btn-custom-primary" <?php if (empty($filieres)) echo 'disabled'; ?>><i class="fas fa-plus"></i> Ajouter Classe</button>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- List Classes Card -->
<div class="card card-custom">
    <div class="card-header">
        <i class="fas fa-list-ul"></i> Liste des Classes
    </div>
    <div class="card-body">
        <?php if (empty($classes) && empty($filieres) && empty($message)): ?>
             <div class="alert alert-custom-info" role="alert">
                Commencez par ajouter des filières, puis des classes.
            </div>
        <?php elseif (empty($classes) && !empty($filieres) && empty($message)): ?>
             <div class="alert alert-custom-info" role="alert">
                Aucune classe trouvée. Vous pouvez en ajouter en utilisant le formulaire ci-dessus.
            </div>
        <?php elseif (!empty($classes)): ?>
        <div class="table-responsive">
            <table class="table table-hover"> <!-- Enlevé table-striped pour un look plus épuré comme le design -->
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nom de la Classe</th>
                        <th>Niveau</th>
                        <th>Filière</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($classes as $classe): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($classe['id']); ?></td>
                        <td><?php echo htmlspecialchars($classe['nom_classe']); ?></td>
                        <td><?php echo htmlspecialchars($classe['niveau']); ?></td>
                        <td><?php echo htmlspecialchars($classe['nom_filiere']); ?></td>
                        <td class="text-center">
                            <a href="gestion_classes.php?edit_id=<?php echo htmlspecialchars($classe['id']); ?>" class="btn btn-sm btn-custom-edit me-1" title="Modifier">
                                <i class="fas fa-edit"></i>
                            </a>
                            <a href="gestion_classes.php?delete_id=<?php echo htmlspecialchars($classe['id']); ?>"
                               class="btn btn-sm btn-danger" title="Supprimer"
                               onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette classe ? Cette action est irréversible.');">
                                <i class="fas fa-trash-alt"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="mt-2 text-muted"><small>Total des classes : <?php echo $count_classes; ?></small></p>
        <?php endif; ?>
    </div>
</div>
<!-- Fin du contenu spécifique à la page -->

<?php
// Inclure le pied de page
// Chemin vers templates/template_footer.php basé sur votre structure
require_once __DIR__ . '/../templates/template_footer.php';
?>