<?php
declare(strict_types=1);

final class Auth
{
    public const MAX_ESSAIS = 5;
    public const BLOCAGE_MIN = 15;
    public const COOKIE_POSTE = 'pharma_poste';

    private static ?array $user = null;
    private static bool $loaded = false;
    private static $poste = false;

    // ---------- Utilisateur connecté ----------

    public static function user(): ?array
    {
        if (self::$loaded) return self::$user;
        self::$loaded = true;
        $id = $_SESSION['uid'] ?? null;
        if (!$id) return null;
        $u = Db::one('SELECT * FROM users WHERE id = ?', [$id]);
        // Compte désactivé, mot de passe réinitialisé ou appareil retiré : la session est fermée
        if (!$u || !$u['actif'] || (int) $u['session_version'] !== (int) ($_SESSION['sv'] ?? 0)) {
            self::logout();
            return null;
        }
        if (!empty($_SESSION['device_id'])) {
            $p = Db::one('SELECT revoked_at FROM postes WHERE id = ?', [$_SESSION['device_id']]);
            if (!$p || $p['revoked_at']) {
                self::logout();
                return null;
            }
        }
        self::$user = $u;
        return $u;
    }

    public static function id(): int
    {
        return (int) (self::user()['id'] ?? 0);
    }

    public static function role(): string
    {
        return self::user()['role'] ?? '';
    }

    public static function isOwner(): bool
    {
        return self::role() === 'proprietaire';
    }

    public static function siteId(): ?int
    {
        $s = self::user()['site_id'] ?? null;
        return $s ? (int) $s : null;
    }

    public static function can(string $perm): bool
    {
        return Perm::can(self::user(), $perm);
    }

    /** Page protégée : connexion obligatoire, caisse verrouillée et inactivité gérées ici */
    public static function requireLogin(): array
    {
        $u = self::user();
        if (!$u) {
            if (self::isAjax()) json_out(['error' => 'Session expirée, reconnectez-vous.'], 401);
            redirect('login');
        }
        // Déconnexion de l'espace propriétaire après 30 min sans activité
        $idle = $u['role'] === 'proprietaire' ? 30 * 60 : 12 * 3600;
        if (isset($_SESSION['last']) && time() - $_SESSION['last'] > $idle) {
            self::logout();
            start_session();
            flash('info', 'Vous avez été déconnecté après une période d\'inactivité.');
            redirect('login');
        }
        $_SESSION['last'] = time();
        if (!empty($_SESSION['locked'])) {
            if (self::isAjax()) json_out(['error' => 'locked'], 423);
            redirect('verrou');
        }
        return $u;
    }

    public static function requirePerm(string $perm): array
    {
        $u = self::requireLogin();
        if (!Perm::can($u, $perm)) forbidden();
        return $u;
    }

    public static function isAjax(): bool
    {
        return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';
    }

    // ---------- Connexion ----------

    /**
     * Vérifie identifiant et mot de passe.
     * Retourne ['ok' => true, 'user' => ...] ou ['ok' => false, 'error' => code, 'left' => n]
     */
    public static function attempt(string $login, string $password): array
    {
        $login = mb_strtolower(trim($login));
        $u = Db::one('SELECT * FROM users WHERE login = ?', [$login]);
        if (!$u) {
            self::log($login, null, 'inexistant');
            return ['ok' => false, 'error' => 'bad', 'left' => null];
        }
        if (!$u['actif']) {
            self::log($login, (int) $u['id'], 'desactive');
            return ['ok' => false, 'error' => 'desactive'];
        }
        if ($u['locked_until'] && strtotime($u['locked_until']) > time()) {
            self::log($login, (int) $u['id'], 'bloque');
            return ['ok' => false, 'error' => 'locked'];
        }
        if (!password_verify($password, $u['password_hash'])) {
            $fails = (int) $u['failed_attempts'] + 1;
            if ($u['locked_until'] && strtotime($u['locked_until']) <= time()) $fails = 1;
            $lock = $fails >= self::MAX_ESSAIS ? date('Y-m-d H:i:s', time() + self::BLOCAGE_MIN * 60) : null;
            Db::update('users', ['failed_attempts' => $fails, 'locked_until' => $lock], 'id = ?', [$u['id']]);
            self::log($login, (int) $u['id'], $lock ? 'bloque' : 'mauvais_mdp');
            return $lock ? ['ok' => false, 'error' => 'locked'] : ['ok' => false, 'error' => 'bad', 'left' => self::MAX_ESSAIS - $fails];
        }
        // Les vendeurs et magasiniers ne se connectent que sur un poste enregistré de leur site
        if (in_array($u['role'], ['vendeur', 'magasinier'], true)) {
            $p = self::poste();
            if ($p && $p['type'] === 'caisse' && (int) $p['site_id'] !== (int) $u['site_id']) {
                self::log($login, (int) $u['id'], 'mauvais_site');
                return ['ok' => false, 'error' => 'site'];
            }
            if (!$p && setting('postes_obligatoires', '0') === '1') {
                self::log($login, (int) $u['id'], 'poste_inconnu');
                return ['ok' => false, 'error' => 'poste'];
            }
        }
        if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
            Db::update('users', ['password_hash' => password_hash($password, PASSWORD_DEFAULT)], 'id = ?', [$u['id']]);
        }
        Db::update('users', ['failed_attempts' => 0, 'locked_until' => null], 'id = ?', [$u['id']]);
        return ['ok' => true, 'user' => $u];
    }

    /** Ouvre la session après toutes les étapes (mot de passe, double vérification...) */
    public static function complete(array $u, string $how = 'ok'): void
    {
        session_regenerate_id(true);
        $csrf = $_SESSION['csrf'] ?? null;
        $_SESSION = ['uid' => (int) $u['id'], 'sv' => (int) $u['session_version'], 'last' => time()];
        if ($csrf) $_SESSION['csrf'] = $csrf;

        $device = self::poste();
        if (!$device && in_array($u['role'], ['proprietaire', 'gerant'], true)) {
            $device = self::enregistrerAppareil($u);
        }
        if ($device && $device['type'] === 'appareil') $_SESSION['device_id'] = (int) $device['id'];
        if ($device) {
            Db::update('postes', ['last_seen_at' => now(), 'last_user' => prenom($u['nom'])], 'id = ?', [$device['id']]);
        }
        Db::update('users', ['last_login_at' => now(), 'last_login_device' => $device['nom'] ?? self::appareilNom()], 'id = ?', [$u['id']]);
        self::log($u['login'], (int) $u['id'], $how);
        self::$loaded = false;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        self::$user = null;
        self::$loaded = true;
    }

    public static function log(string $login, ?int $userId, string $result): void
    {
        $p = self::poste();
        Db::insert('login_log', [
            'at' => now(), 'login' => mb_substr($login, 0, 60), 'user_id' => $userId,
            'appareil' => $p['nom'] ?? self::appareilNom(), 'ip' => client_ip(), 'resultat' => $result,
        ]);
    }

    public static function checkPassword(string $pw, string $role, string $temp = ''): array
    {
        $common = ['12345678', '123456789', '1234567890', 'password', 'motdepasse', 'azerty123', 'azertyuiop', 'pharmacie', 'pharma2026', 'remede123', 'qwerty123', '00000000', '11111111'];
        $min = $role === 'proprietaire' ? 12 : 8;
        return [
            ['ok' => mb_strlen($pw) >= $min, 't' => "Au moins $min caractères"],
            ['ok' => $pw !== '' && $pw !== $temp, 't' => 'Différent du mot de passe provisoire'],
            ['ok' => $pw !== '' && !in_array(mb_strtolower($pw), $common, true), 't' => 'Pas un mot de passe trop courant (12345678, pharmacie…)'],
        ];
    }

    // ---------- Verrouillage de la caisse ----------

    public static function lock(): void
    {
        $_SESSION['locked'] = true;
        $_SESSION['pin_fails'] = 0;
    }

    /** Retourne 'ok', 'bad' (avec essais restants) ou 'closed' (session fermée) */
    public static function unlock(string $pin): array
    {
        $u = Db::one('SELECT * FROM users WHERE id = ?', [$_SESSION['uid'] ?? 0]);
        if (!$u) return ['r' => 'closed'];
        if ($u['pin_hash'] && password_verify($pin, $u['pin_hash'])) {
            unset($_SESSION['locked']);
            $_SESSION['pin_fails'] = 0;
            $_SESSION['last'] = time();
            self::log($u['login'], (int) $u['id'], 'deverrouillage');
            return ['r' => 'ok'];
        }
        $_SESSION['pin_fails'] = ($_SESSION['pin_fails'] ?? 0) + 1;
        if ($_SESSION['pin_fails'] >= 3) {
            self::log($u['login'], (int) $u['id'], 'pin_3_echecs');
            self::logout();
            return ['r' => 'closed'];
        }
        return ['r' => 'bad', 'left' => 3 - $_SESSION['pin_fails']];
    }

    /**
     * Autorisation d'une action réservée (remise élevée, annulation) :
     * le code à 4 chiffres d'un gérant du site ou du propriétaire.
     */
    public static function superviseur(string $pin, int $siteId, bool $proprietaireSeulement = false): ?array
    {
        if (!preg_match('/^\d{4}$/', $pin)) return null;
        $sql = "SELECT * FROM users WHERE actif = 1 AND pin_hash IS NOT NULL AND (role = 'proprietaire'"
             . ($proprietaireSeulement ? ')' : " OR (role = 'gerant' AND site_id = ?))");
        foreach (Db::all($sql, $proprietaireSeulement ? [] : [$siteId]) as $s) {
            if (password_verify($pin, $s['pin_hash'])) return $s;
        }
        return null;
    }

    // ---------- Postes et appareils ----------

    public static function poste(): ?array
    {
        if (self::$poste !== false) return self::$poste;
        self::$poste = null;
        $t = $_COOKIE[self::COOKIE_POSTE] ?? '';
        if (is_string($t) && strlen($t) === 64) {
            $p = Db::one('SELECT * FROM postes WHERE token_hash = ? AND revoked_at IS NULL', [hash('sha256', $t)]);
            self::$poste = $p ?: null;
        }
        return self::$poste;
    }

    public static function posteNom(): ?string
    {
        return self::poste()['nom'] ?? null;
    }

    public static function creerPoste(string $nom, ?int $siteId, string $type, ?int $userId = null): array
    {
        $token = bin2hex(random_bytes(32));
        $id = Db::insert('postes', [
            'nom' => $nom, 'site_id' => $siteId, 'type' => $type, 'token_hash' => hash('sha256', $token),
            'user_id' => $userId, 'created_at' => now(), 'last_seen_at' => now(),
        ]);
        setcookie(self::COOKIE_POSTE, $token, [
            'expires' => time() + 5 * 365 * 86400, 'path' => '/', 'httponly' => true,
            'secure' => (bool) config('https'), 'samesite' => 'Lax',
        ]);
        $_COOKIE[self::COOKIE_POSTE] = $token;
        self::$poste = false;
        return Db::one('SELECT * FROM postes WHERE id = ?', [$id]);
    }

    private static function enregistrerAppareil(array $u): array
    {
        return self::creerPoste(self::appareilNom() . ' · ' . prenom($u['nom']), $u['site_id'] ? (int) $u['site_id'] : null, 'appareil', (int) $u['id']);
    }

    public static function appareilNom(): string
    {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $os = match (true) {
            str_contains($ua, 'Android') => 'Téléphone Android',
            str_contains($ua, 'iPhone') => 'iPhone',
            str_contains($ua, 'iPad') => 'iPad',
            str_contains($ua, 'Windows') => 'PC Windows',
            str_contains($ua, 'Mac OS') => 'Mac',
            str_contains($ua, 'Linux') => 'PC Linux',
            default => 'Appareil inconnu',
        };
        return $os;
    }

    public static function isMobile(): bool
    {
        return (bool) preg_match('/Android|iPhone|iPad|Mobile/i', $_SERVER['HTTP_USER_AGENT'] ?? '');
    }
}
