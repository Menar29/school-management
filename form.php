<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <meta name="description" content="" />
    <meta name="author" content="" />
    <title>Welcome</title>
    <!-- Favicon-->
    <link rel="icon" type="image/x-icon" href="assets/favicon.ico" />
    <!-- Bootstrap icons-->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.5.0/font/bootstrap-icons.css" rel="stylesheet" />
    <!-- Core theme CSS (includes Bootstrap)-->
    <link href="css/bootstrap.min.css" rel="stylesheet" />
    <!-- Custom Theme CSS-->
    <link href="views/style.css" rel="stylesheet" />
</head>

<body class="d-flex flex-column h-100">
    <!-- Navigation-->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container px-5">
            <a class="navbar-brand" href="index.html">Université</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation"><span class="navbar-toggler-icon"></span></button>
            <div class="collapse navbar-collapse" id="navbarSupportedContent">
                <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                    <li class="nav-item"><a class="nav-link" href="index.php">Acceuil</a></li>
                    <li class="nav-item"><a class="nav-link" href="login.php">Connexion</a></li>
                    <li class="nav-item"><a class="nav-link" href="inscrire.php">S'inscrire</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Section-->
    <section class="py-5">
        <div class="container px-4 px-lg-5 mt-5">
            <div class="row gx-4 gx-lg-5 row-cols-2 row-cols-md-3 row-cols-xl-4 justify-content-center">
                <!-- Card for Enseignant -->
                <div class="col mb-5">
                    <div class="card h-100">
                        <img class="card-img-top" src="images/enseignant.png" alt="Enseignant" />
                        <div class="card-body p-4">
                            <div class="text-center">
                                <h5 class="fw-bolder">Enseignant</h5>
                            </div>
                        </div>
                        <div class="card-footer p-4 pt-0 border-top-0 bg-transparent">
                            <div class="text-center">
                            <a class="btn btn-outline-dark mt-auto" style="background-color: #0ef; color:white; margin-left: 10px;" href="views/AjoutEnseignent.php">AJOUTER</a>
                                <a class="btn btn-outline-dark mt-auto" style="background-color: #3282F6; color:white" href="views/ListEnseignent.php">AFFICHER</a>
                                
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card for Etudiant -->
                <div class="col mb-5">
                    <div class="card h-100">
                        <img class="card-img-top" src="images/etudiant.png" alt="Etudiant" />
                        <div class="card-body p-4">
                            <div class="text-center">
                                <h5 class="fw-bolder">Etudiant</h5>
                            </div>
                        </div>
                        <div class="card-footer p-4 pt-0 border-top-0 bg-transparent">
                            <div class="text-center">
                            <a class="btn btn-outline-dark mt-auto" style="background-color: #0ef; color:white; margin-left: 10px;" href="views/AjoutEtudiant.php">AJOUTER</a>
                                <a class="btn btn-outline-dark mt-auto" style="background-color: #3282F6; color:white" href="views/ListEtudiant.php">AFFICHER</a>
                                
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card for Salle -->
                <div class="col mb-5">
                    <div class="card h-100">
                        <img class="card-img-top" src="images/salle.png" alt="Salle" />
                        <div class="card-body p-4">
                            <div class="text-center">
                                <h5 class="fw-bolder">Salle</h5>
                            </div>
                        </div>
                        <div class="card-footer p-4 pt-0 border-top-0 bg-transparent">
                            <div class="text-center">
                            <a class="btn btn-outline-dark mt-auto" style="background-color: #0ef; color:white; margin-left: 10px;" href="views/AjoutSalle.php">AJOUTER</a>
                                <a class="btn btn-outline-dark mt-auto" style="background-color: #3282F6; color:white" href="views/ListSalle.php">AFFICHER</a>
                                
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card for Cour -->
                <div class="col mb-5">
                    <div class="card h-100">
                        <img class="card-img-top" src="images/cours.png" alt="Cour" />
                        <div class="card-body p-4">
                            <div class="text-center">
                                <h5 class="fw-bolder">Cour</h5>
                            </div>
                        </div>
                        <div class="card-footer p-4 pt-0 border-top-0 bg-transparent">
                            <div class="text-center">
                            <a class="btn btn-outline-dark mt-auto" style="background-color: #0ef; color:white; margin-left: 10px;" href="views/AjoutCour.php">AJOUTER</a>
                                <a class="btn btn-outline-dark mt-auto" style="background-color: #3282F6; color:white" href="views/ListCour.php">AFFICHER</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Bootstrap core JS-->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Core theme JS-->
    <script src="js/scripts.js"></script>
</body>

</html>
