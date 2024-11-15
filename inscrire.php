<!DOCTYPE html>
<html lang="en">

<head>
  <title>Inscription</title>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="css/bootstrap.min.css" rel="stylesheet">
  <link href="views/style.css" rel="stylesheet">
</head>

<body>
  <!-- Navigation-->
  <nav class="navbar navbar-expand-lg">
    <div class="container px-5">
      <a class="navbar-brand" href="index.html">Université</a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation"><span class="navbar-toggler-icon"></span></button>
      <div class="collapse navbar-collapse" id="navbarSupportedContent">
        <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
          <li class="nav-item"><a class="nav-link" href="index.php">Accueil</a></li>
          <li class="nav-item"><a class="nav-link" href="login.php">Connexion</a></li>
          <li class="nav-item"><a class="nav-link" href="inscrire.php">S'inscrire</a></li>
        </ul>
      </div>
    </div>
  </nav>

  <!-- Formulaire d'inscription -->
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-lg-6 mx-auto">
        <div class="card my-5">
          <div class="card-body">
            <h5 class="card-title text-center">Inscription</h5>
            <form action="connexion.php" method="POST">
              <div class="form-floating mb-3">
                <input type="text" class="form-control" id="floatingInput" name="nom" placeholder="Nom" required>
                <label for="floatingInput">Nom</label>
              </div>
              <div class="form-floating mb-3">
                <input type="text" class="form-control" id="floatingInput" name="prenom" placeholder="Prenom" required>
                <label for="floatingInput">Prénom</label>
              </div>
              <div class="form-floating mb-3">
                <input type="email" class="form-control" id="floatingEmail" name="email" placeholder="nom@example.com" required>
                <label for="floatingEmail">E-mail</label>
              </div>
              <div class="form-floating mb-3">
                <select class="form-control form-select" name="sexe" required>
                  <option value="">Veuillez choisir le sexe</option>
                  <option value="M">Homme</option>
                  <option value="F">Femme</option>
                </select>
              </div>
              <div class="form-floating mb-3">
                <input type="password" class="form-control" id="floatingPassword" name="passe" placeholder="Mot de passe" required>
                <label for="floatingPassword">Mot de passe</label>
              </div>
              <div class="d-grid">
                <button class="btn btn-primary text-uppercase fw-bold" type="submit" name="inscrire">S'Inscrire</button>
              </div>
              <hr class="my-4">
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script src="js/bootstrap.bundle.min.js"></script>
</body>

</html>
