<?php

    class Provider
    {
        private $serve = "localhost";
        private $user = "menar";
        private $password = "Djilloumenar@2003";
        private $database = "university";
        

        public function connection() {
            try {
                $cons = new PDO("mysql:host=$this->serve;dbname=$this->database", $this->user, $this->password);
                $cons->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $cons->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);
                return $cons;
            } catch (PDOException $th) {
                error_log("Erreur : ".$th->getMessage(),3 ,'logs/db_errors.log');
                die('ERREUR');
            }
        }
    }
    
    