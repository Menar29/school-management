<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="../css/bootstrap.min.css">
  <link rel="stylesheet" href="style.css">
  <title>Cour</title>
</head>

<body>
  <!-- Navigation-->
  <nav class="navbar navbar-expand-lg navbar-dark">
    <div class="container px-5">
      <a class="navbar-brand" href="index.html">Université</a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navbarSupportedContent">
        <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
          <li class="nav-item"><a class="nav-link" href="../index.php">Accueil</a></li>
          <li class="nav-item"><a class="nav-link" href="../login.php">Connexion</a></li>
          <li class="nav-item"><a class="nav-link" href="../inscrire.php">S'inscrire</a></li>
        </ul>
      </div>
    </div>
  </nav>

  <!-- Formulaire Cour -->
  <div class="wrapper">
    <div class="banner">
      <h2 class="wel_text">Ajout de cours</h2>
    </div>
    <div class="form">
      <h3 class="title">Ajouter un Cour</h3>
      
      <form action="../controllers/cour.php" method="POST" class="form">
        <div class="inp">
          <input type="text" class="form-control input" id="libelle" name="libelle" placeholder="Libelle" required>
        </div>

        <div class="inp">
          <input type="text" class="form-control input" id="niveau" name="niveau" placeholder="Niveau" required>
        </div>

        <div class="inp">
          <select class="form-control input" id="idEn" name="idEn" required>
            <option value="" disabled selected>Enseignant</option>
            <?php foreach ($enseignent as $objet): ?>
              <option value="<?= $objet->id ?>"><?= $objet->nom ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="inp">
          <select class="form-control input" id="idSal" name="idSal" required>
            <option value="" disabled selected>Salle</option>
            <?php foreach ($salle as $key): ?>
              <option value="<?= $key->id ?>"><?= $key->libelle ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <button class="submit" type="submit" name="ajouter">Ajouter</button>
      </form>
    </div>
  </div>

  <script src="https://cdn.startbootstrap.com/sb-forms-latest.js"></script>
</body>
</html>
