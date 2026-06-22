<?php
// Configuration de la connexion à la base de données

class Database {
    private $host = 'localhost';
    private $db_name = 'gestion_school';
    private $username = 'root';
    private $password = '';
    private $conn;

    // Méthode pour établir la connexion
    public function connect() {
        $this->conn = null;

        try {
            $this->conn = new PDO(
                'mysql:host=' . $this->host . ';dbname=' . $this->db_name,
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->exec('SET NAMES utf8');
        } catch(PDOException $e) {
            error_log('Erreur de connexion: ' . $e->getMessage());
            // En production, ne pas afficher les détails de l'erreur à l'utilisateur
            die('Une erreur est survenue lors de la connexion à la base de données. Veuillez réessayer plus tard ou contacter l\'administrateur.');
        }

        return $this->conn;
    }

    // Méthode pour préparer une requête
    public function prepare($sql) {
        if (!$this->conn) $this->connect(); // Assure la connexion
        return $this->conn->prepare($sql);
    }

    // Méthode pour exécuter une requête
    public function execute($stmt, $params = []) {
        try {
            return $stmt->execute($params);
        } catch(PDOException $e) {
            error_log('Erreur d\'exécution: ' . $e->getMessage() . ' | Query: ' . $stmt->queryString);
            return false;
        }
    }

    // Méthode pour récupérer tous les résultats
    public function fetchAll($stmt) {
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Méthode pour récupérer un seul résultat
    public function fetch($stmt) {
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Méthode pour récupérer le dernier ID inséré
    public function lastInsertId() {
        return $this->conn->lastInsertId();
    }

    // Méthode pour compter les lignes
    public function rowCount($stmt) {
        return $stmt->rowCount();
    }
}


function sanitizeInput($data) {
    if (!is_string($data)) {
        $data = ''; // ou tu peux retourner null selon tes besoins
    }
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';  // <-- ici on évite l'erreur en donnant une valeur par défaut
    $email_value = $email;
}


// Création d'une instance de la base de données pour une utilisation globale si nécessaire
// Il est généralement préférable de l'injecter où c'est nécessaire plutôt que de la rendre globale.
// $database = new Database();
// $db = $database->connect(); // $db sera disponible globalement après inclusion de ce fichier.

// La fonction sanitizeInput est mieux placée dans helpers.php
?>