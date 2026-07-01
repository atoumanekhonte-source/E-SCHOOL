-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1:3306
-- Généré le : mer. 09 juil. 2025 à 13:13
-- Version du serveur : 9.1.0
-- Version de PHP : 8.1.31

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `gestion_school`
--

-- --------------------------------------------------------

--
-- Structure de la table `absences`
--

DROP TABLE IF EXISTS `absences`;
CREATE TABLE IF NOT EXISTS `absences` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_etudiant` int DEFAULT NULL,
  `id_matiere` int DEFAULT NULL,
  `date_absence` date DEFAULT NULL,
  `heure_debut` time DEFAULT NULL,
  `heure_fin` time DEFAULT NULL,
  `raison` text,
  `est_justifie` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `id_etudiant` (`id_etudiant`),
  KEY `id_matiere` (`id_matiere`)
);

--
-- Déchargement des données de la table `absences`
--

INSERT INTO `absences` (`id`, `id_etudiant`, `id_matiere`, `date_absence`, `heure_debut`, `heure_fin`, `raison`, `est_justifie`) VALUES
(1, 1, 1, '2025-06-05', '02:32:00', '02:38:00', 'malade', 1);

-- --------------------------------------------------------

--
-- Structure de la table `classes`
--

DROP TABLE IF EXISTS `classes`;
CREATE TABLE IF NOT EXISTS `classes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nom_classe` varchar(100) DEFAULT NULL,
  `niveau` varchar(50) DEFAULT NULL,
  `id_filiere` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `id_filiere` (`id_filiere`)
) ;

--
-- Déchargement des données de la table `classes`
--

INSERT INTO `classes` (`id`, `nom_classe`, `niveau`, `id_filiere`) VALUES
(1, 'emphie A', 'L3', 1),
(201, 'Seconde L2', 'Seconde', 0);

-- --------------------------------------------------------

--
-- Structure de la table `cours_deposes`
--

DROP TABLE IF EXISTS `cours_deposes`;
CREATE TABLE IF NOT EXISTS `cours_deposes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_matiere` int NOT NULL,
  `id_enseignant` int NOT NULL,
  `titre` varchar(255) NOT NULL,
  `description` text,
  `fichier_support` varchar(255) DEFAULT NULL,
  `lien_visionnage` text,
  `date_depot` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `id_matiere` (`id_matiere`),
  KEY `id_enseignant` (`id_enseignant`)
) ;

--
-- Déchargement des données de la table `cours_deposes`
--

INSERT INTO `cours_deposes` (`id`, `id_matiere`, `id_enseignant`, `titre`, `description`, `fichier_support`, `lien_visionnage`, `date_depot`) VALUES
(1, 1, 1, 'cours php', 'bon jouer', '1749074755_1_Td2_Framwork_HTML_CSS.pdf', NULL, '2025-06-04 22:05:55');

-- --------------------------------------------------------

--
-- Structure de la table `cours_en_ligne`
--

DROP TABLE IF EXISTS `cours_en_ligne`;
CREATE TABLE IF NOT EXISTS `cours_en_ligne` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_matiere` int DEFAULT NULL,
  `id_enseignant` int DEFAULT NULL,
  `lien_visionnage` text,
  `fichier_video` varchar(255) DEFAULT NULL,
  `titre` varchar(255) DEFAULT NULL,
  `description` text,
  `date_cours` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `id_matiere` (`id_matiere`),
  KEY `id_enseignant` (`id_enseignant`)
) ;

--
-- Déchargement des données de la table `cours_en_ligne`
--

INSERT INTO `cours_en_ligne` (`id`, `id_matiere`, `id_enseignant`, `lien_visionnage`, `fichier_video`, `titre`, `description`, `date_cours`) VALUES
(1, 1, 1, 'https://meet.google.com/bvd-ifnt-vpp', NULL, 'php framwork', 'peut', '2025-06-05 22:18:00');

-- --------------------------------------------------------

--
-- Structure de la table `cours_telecharges`
--

DROP TABLE IF EXISTS `cours_telecharges`;
CREATE TABLE IF NOT EXISTS `cours_telecharges` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_cours` int DEFAULT NULL,
  `id_etudiant` int DEFAULT NULL,
  `date_telechargement` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `id_cours` (`id_cours`),
  KEY `id_etudiant` (`id_etudiant`)
) ;

-- --------------------------------------------------------

--
-- Structure de la table `devoirs`
--

DROP TABLE IF EXISTS `devoirs`;
CREATE TABLE IF NOT EXISTS `devoirs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_matiere` int DEFAULT NULL,
  `id_enseignant` int DEFAULT NULL,
  `type` enum('TD','TC') DEFAULT NULL,
  `titre` varchar(255) DEFAULT NULL,
  `fichier` varchar(255) DEFAULT NULL,
  `date_depot` date DEFAULT NULL,
  `date_limite` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `id_matiere` (`id_matiere`),
  KEY `id_enseignant` (`id_enseignant`)
) ;

--
-- Déchargement des données de la table `devoirs`
--

INSERT INTO `devoirs` (`id`, `id_matiere`, `id_enseignant`, `type`, `titre`, `fichier`, `date_depot`, `date_limite`) VALUES
(1, 1, 1, 'TC', 'Teste de connaissance php', '1749073726_1_sujet_Td2_Framwork_HTML_CSS.pdf', '2025-06-04', '2025-06-20');

-- --------------------------------------------------------

--
-- Structure de la table `devoirs_remis`
--

DROP TABLE IF EXISTS `devoirs_remis`;
CREATE TABLE IF NOT EXISTS `devoirs_remis` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_devoir` int DEFAULT NULL,
  `id_etudiant` int DEFAULT NULL,
  `fichier_remis` varchar(255) DEFAULT NULL,
  `date_remise` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `id_devoir` (`id_devoir`),
  KEY `id_etudiant` (`id_etudiant`)
) ;

--
-- Déchargement des données de la table `devoirs_remis`
--

INSERT INTO `devoirs_remis` (`id`, `id_devoir`, `id_etudiant`, `fichier_remis`, `date_remise`) VALUES
(1, 1, 1, '1749080616_1_1749073726_1_sujet_Td2_Framwork_HTML_CSS__2_.pdf', '2025-06-04 23:43:36');

-- --------------------------------------------------------

--
-- Structure de la table `emplois_temps`
--

DROP TABLE IF EXISTS `emplois_temps`;
CREATE TABLE IF NOT EXISTS `emplois_temps` (
  `id` int NOT NULL AUTO_INCREMENT,
  `jour_semaine` enum('Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi') DEFAULT NULL,
  `heure_debut` time DEFAULT NULL,
  `heure_fin` time DEFAULT NULL,
  `id_classe` int DEFAULT NULL,
  `id_matiere` int DEFAULT NULL,
  `salle` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `id_classe` (`id_classe`),
  KEY `id_matiere` (`id_matiere`)
) ;

--
-- Déchargement des données de la table `emplois_temps`
--

INSERT INTO `emplois_temps` (`id`, `jour_semaine`, `heure_debut`, `heure_fin`, `id_classe`, `id_matiere`, `salle`) VALUES
(1, 'Jeudi', '15:50:00', '18:56:00', 1, 1, '13A');

-- --------------------------------------------------------

--
-- Structure de la table `enseignants`
--

DROP TABLE IF EXISTS `enseignants`;
CREATE TABLE IF NOT EXISTS `enseignants` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_utilisateur` int DEFAULT NULL,
  `specialite` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `id_utilisateur` (`id_utilisateur`)
) ;

--
-- Déchargement des données de la table `enseignants`
--

INSERT INTO `enseignants` (`id`, `id_utilisateur`, `specialite`) VALUES
(1, 2, 'HG');

-- --------------------------------------------------------

--
-- Structure de la table `etudiants`
--

DROP TABLE IF EXISTS `etudiants`;
CREATE TABLE IF NOT EXISTS `etudiants` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_utilisateur` int DEFAULT NULL,
  `matricule` varchar(50) DEFAULT NULL,
  `id_classe` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `matricule` (`matricule`),
  KEY `id_utilisateur` (`id_utilisateur`),
  KEY `id_classe` (`id_classe`)
) ;

--
-- Déchargement des données de la table `etudiants`
--

INSERT INTO `etudiants` (`id`, `id_utilisateur`, `matricule`, `id_classe`) VALUES
(1, 101, 'ETU001', 201);

-- --------------------------------------------------------

--
-- Structure de la table `filieres`
--

DROP TABLE IF EXISTS `filieres`;
CREATE TABLE IF NOT EXISTS `filieres` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nom_filiere` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ;

--
-- Déchargement des données de la table `filieres`
--

INSERT INTO `filieres` (`id`, `nom_filiere`) VALUES
(1, 'IDA(Informatique de Developpement d&#039;Applications)');

-- --------------------------------------------------------

--
-- Structure de la table `matieres`
--

DROP TABLE IF EXISTS `matieres`;
CREATE TABLE IF NOT EXISTS `matieres` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nom_matiere` varchar(100) DEFAULT NULL,
  `code_matiere` varchar(20) DEFAULT NULL,
  `id_enseignant` int DEFAULT NULL,
  `id_classe` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `id_enseignant` (`id_enseignant`),
  KEY `id_classe` (`id_classe`)
) ;

--
-- Déchargement des données de la table `matieres`
--

INSERT INTO `matieres` (`id`, `nom_matiere`, `code_matiere`, `id_enseignant`, `id_classe`) VALUES
(1, 'PHP', '6', 1, 1);

-- --------------------------------------------------------

--
-- Structure de la table `messages`
--

DROP TABLE IF EXISTS `messages`;
CREATE TABLE IF NOT EXISTS `messages` (
  `id` int NOT NULL AUTO_INCREMENT,
  `expediteur_id` int DEFAULT NULL,
  `destinataire_id` int DEFAULT NULL,
  `sujet` varchar(255) DEFAULT NULL,
  `contenu` text,
  `date_envoi` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `expediteur_id` (`expediteur_id`),
  KEY `destinataire_id` (`destinataire_id`)
) ;

--
-- Déchargement des données de la table `messages`
--

INSERT INTO `messages` (`id`, `expediteur_id`, `destinataire_id`, `sujet`, `contenu`, `date_envoi`) VALUES
(1, 2, 3, 'TABASKI 2025', 'bonne fete de tabaski', '2025-06-05 01:05:38');

-- --------------------------------------------------------

--
-- Structure de la table `notes`
--

DROP TABLE IF EXISTS `notes`;
CREATE TABLE IF NOT EXISTS `notes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_etudiant` int DEFAULT NULL,
  `id_matiere` int DEFAULT NULL,
  `type_eval` enum('TD','TC','EXAMEN') DEFAULT NULL,
  `note` decimal(5,2) DEFAULT NULL,
  `date_saisie` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `id_etudiant` (`id_etudiant`),
  KEY `id_matiere` (`id_matiere`)
) ;

--
-- Déchargement des données de la table `notes`
--

INSERT INTO `notes` (`id`, `id_etudiant`, `id_matiere`, `type_eval`, `note`, `date_saisie`) VALUES
(1, 1, 1, 'TC', 15.00, '2025-06-05');

-- --------------------------------------------------------

--
-- Structure de la table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` int NOT NULL AUTO_INCREMENT,
  `titre` varchar(255) NOT NULL,
  `contenu` text NOT NULL,
  `date_creation` datetime DEFAULT CURRENT_TIMESTAMP,
  `visible` tinyint(1) DEFAULT '1',
  `id_auteur` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `id_auteur` (`id_auteur`)
) ;

--
-- Déchargement des données de la table `notifications`
--

INSERT INTO `notifications` (`id`, `titre`, `contenu`, `date_creation`, `visible`, `id_auteur`) VALUES
(1, 'Notification Officielle – Suspension des Cours pour la Fête de la Tabaski', 'Chers étudiants, chers enseignants,\r\n\r\nÀ l’occasion de la fête de Tabaski, nous vous informons que les cours seront suspendus du [date de début] au [date de reprise] inclus.\r\n\r\nCette décision vise à permettre à chacun de célébrer cette fête dans la paix, la convivialité et la spiritualité.\r\n\r\n???? Reprise des cours : [jour, date précise] à l’horaire habituel.\r\n\r\nNous vous souhaitons à toutes et à tous une excellente fête de Tabaski, pleine de bénédictions, de santé et de joie en famille.\r\n\r\nBonne fête à toutes et à tous !\r\n\r\n— La Direction', '2025-06-04 03:01:15', 1, NULL);

-- --------------------------------------------------------

--
-- Structure de la table `password_resets`
--

DROP TABLE IF EXISTS `password_resets`;
CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `token` varchar(100) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `email` (`email`(250)),
  KEY `token` (`token`)
) ;

-- --------------------------------------------------------

--
-- Structure de la table `utilisateurs`
--

DROP TABLE IF EXISTS `utilisateurs`;
CREATE TABLE IF NOT EXISTS `utilisateurs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) DEFAULT NULL,
  `prenom` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `mot_de_passe` varchar(255) DEFAULT NULL,
  `role` enum('admin','etudiant','enseignant') DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `date_inscription` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ;

--
-- Déchargement des données de la table `utilisateurs`
--

INSERT INTO `utilisateurs` (`id`, `nom`, `prenom`, `email`, `mot_de_passe`, `role`, `photo`, `date_inscription`) VALUES
(1, 'Faye', 'Ibrahima', 'ibsibzo97@gmail.com', '$2y$10$AE.Y9yAzJT5Mf9nr.nviAuRnqdubgkEHrkWAwrC/JcnwykYFiCFNm', 'admin', '', '2025-06-03 15:32:29'),
(2, 'Diouf', 'Victor', 'viki97@gmail.com', '$2y$10$swEIuwfP9yCXjdO2majGWuv3oOfWQJgT2VSl.XPnR8slOsNkp6GY2', 'enseignant', NULL, '2025-06-03 17:51:19'),
(3, 'Faye', 'Ibou', 'ibrahima.faye42@unchk.edu.sn', '$2y$10$JICXsHfSdGirPFN08ibOXOKpsXQ8jIg4lNtZZ/QhqtrEiXVbksqBy', 'etudiant', NULL, '2025-06-03 18:39:48'),
(101, 'Apprenant', 'Alpha', 'alpha.apprenant@example.com', 'unmotdepassehashe', 'etudiant', NULL, '2025-06-04 22:53:01');
COMMIT;

