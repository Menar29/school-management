
CREATE DATABASE IF NOT EXISTS university_db;

USE university_db;
REATE TABLE IF NOT EXISTS login(
        id   int(11),
        nom varchar(50),
        prenom varchar(50) ,
        email varchar(100),
        sexe   enum('M','F'),
        passe  varchar(255))
);

CREATE TABLE IF NOT EXISTS Enseignent(
    id INT PRIMARY KEY AUTO_INCREMENT,
    nom VARCHAR(50) NOT NULL,
    prenom VARCHAR(50) NOT NULL,
    sexe CHAR(1) NOT NULL CHECK(sexe in('H', 'F')),
    email VARCHAR(30) NOT NULL,
    adresse VARCHAR(50) NOT NULL
);

CREATE TABLE IF NOT EXISTS Etudiant(
    id INT PRIMARY KEY AUTO_INCREMENT,
    nom VARCHAR(50) NOT NULL,
    prenom VARCHAR(50) NOT NULL,
    sexe CHAR(1) NOT NULL CHECK(sexe in('H', 'F'))
);

CREATE TABLE IF NOT EXISTS Salle(
    id INT PRIMARY KEY AUTO_INCREMENT,
    libelle VARCHAR(50) NOT NULL,
    place INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS Cour(
    id INT PRIMARY KEY AUTO_INCREMENT,
    libelle VARCHAR(50) NOT NULL,
    niveau VARCHAR(50) NOT NULL,
    idEn INT NOT NULL,
    idSal INT NOT NULL,
    Foreign Key (idEn) REFERENCES Enseignent (id) ON DELETE CASCADE,
    Foreign Key (idSal) REFERENCES Salle (id) ON DELETE CASCADE
);
mysqldump -u menar -p niversity_db > /var/www/html/sauvegarde1.sql
