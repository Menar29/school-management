<?php
declare(strict_types=1);

function besoin_deux_etapes(array $u): bool
{
    return in_array($u['role'], ['proprietaire', 'gerant'], true) && setting('deux_etapes', '1') === '1';
}

/** Utilisateur dont le mot de passe vient d'être vérifié, en attente d'une étape suivante */
function utilisateur_en_attente(): array
{
    $p = $_SESSION['pending'] ?? null;
    if (!$p || time() - $p['at'] > 600) {
        unset($_SESSION['pending']);
        flash('info', 'Reprenez la connexion depuis le début.');
        redirect('login');
    }
    $u = Db::one('SELECT * FROM users WHERE id = ? AND actif = 1', [$p['uid']]);
    if (!$u) redirect('login');
    return $u;
}

function etape_suivante(array $u): never
{
    if ($u['must_change_pw']) redirect('mot-de-passe');
    if (besoin_deux_etapes($u)) redirect($u['totp_enabled'] ? 'deux-etapes' : 'deux-etapes/config');
    $how = $_SESSION['pending']['how'] ?? 'ok';
    unset($_SESSION['pending']);
    Auth::complete($u, $how);
    redirect('');
}

function login(): void
{
    if (Auth::user() && empty($_SESSION['locked'])) redirect('');
    $err = null;
    $login = '';
    if (is_post()) {
        $login = mb_strtolower((string) input('login'));
        $res = Auth::attempt($login, (string) ($_POST['password'] ?? ''));
        if ($res['ok']) {
            Auth::logout();
            $_SESSION['pending'] = ['uid' => (int) $res['user']['id'], 'at' => time()];
            etape_suivante($res['user']);
        }
        $poste = Auth::poste();
        $site = site($poste['site_id'] ?? null);
        $err = match ($res['error']) {
            'locked' => ['warn', 'Trop d\'essais. Ce compte est bloqué ' . Auth::BLOCAGE_MIN . ' minutes et le propriétaire est prévenu. Il peut le débloquer plus tôt.'],
            'site' => ['bad', 'Ce compte n\'est pas rattaché à ' . ($site ? 'l\'officine ' . $site['nom_court'] : 'ce site') . '. Connectez-vous sur un poste de votre site.'],
            'poste' => ['bad', 'Ce poste n\'est pas enregistré. Demandez au propriétaire de l\'enregistrer depuis l\'écran Sécurité.'],
            'desactive' => ['bad', 'Ce compte est désactivé. Adressez-vous au propriétaire.'],
            default => ['bad', 'Identifiant ou mot de passe incorrect.' . ($res['left'] !== null ? ' ' . $res['left'] . ($res['left'] > 1 ? ' essais restants' : ' essai restant') . ' avant blocage.' : '')],
        };
    }
    view('auth/login', ['title' => 'Connexion', 'err' => $err, 'login' => $login], 'auth');
}

function logout(): void
{
    if (is_post()) Auth::logout();
    redirect('login');
}

function premierMotDePasse(): void
{
    $u = utilisateur_en_attente();
    if (!$u['must_change_pw']) etape_suivante($u);
    $errors = [];
    if (is_post()) {
        $n1 = (string) ($_POST['n1'] ?? '');
        $n2 = (string) ($_POST['n2'] ?? '');
        $pin = (string) ($_POST['pin'] ?? '');
        foreach (Auth::checkPassword($n1, $u['role']) as $rule) {
            if (!$rule['ok']) $errors[] = $rule['t'];
        }
        if (password_verify($n1, $u['password_hash'])) $errors[] = 'Différent du mot de passe provisoire';
        if ($n1 !== $n2) $errors[] = 'Les deux mots de passe sont différents.';
        if (!preg_match('/^\d{4}$/', $pin)) $errors[] = 'Le code de déverrouillage comporte 4 chiffres.';
        if (in_array($pin, ['0000', '1234', '1111', '4321'], true)) $errors[] = 'Choisissez un code moins facile à deviner que ' . $pin . '.';
        if (!$errors) {
            Db::update('users', [
                'password_hash' => password_hash($n1, PASSWORD_DEFAULT),
                'pin_hash' => password_hash($pin, PASSWORD_DEFAULT),
                'must_change_pw' => 0,
            ], 'id = ?', [$u['id']]);
            Db::insert('audit_log', ['at' => now(), 'user_id' => $u['id'], 'site_id' => $u['site_id'], 'poste' => Auth::posteNom(), 'action' => 'mot_de_passe_choisi', 'details' => 'Première connexion']);
            etape_suivante(Db::one('SELECT * FROM users WHERE id = ?', [$u['id']]));
        }
    }
    view('auth/mot-de-passe', ['title' => 'Première connexion', 'u' => $u, 'errors' => $errors], 'auth');
}

function deuxEtapes(): void
{
    $u = utilisateur_en_attente();
    if (!$u['totp_enabled']) redirect('deux-etapes/config');
    $err = null;
    if (is_post()) {
        $code = trim((string) input('code'));
        $ok = Totp::verify($u['totp_secret'], $code);
        if (!$ok && preg_match('/^[A-Za-z0-9-]{8,12}$/', $code)) {
            foreach (Db::all('SELECT * FROM backup_codes WHERE user_id = ? AND used_at IS NULL', [$u['id']]) as $b) {
                if (password_verify(strtoupper(str_replace('-', '', $code)), $b['code_hash'])) {
                    Db::update('backup_codes', ['used_at' => now()], 'id = ?', [$b['id']]);
                    $ok = true;
                    break;
                }
            }
        }
        if ($ok) {
            unset($_SESSION['pending']);
            Auth::complete($u, 'ok_2fa');
            redirect('');
        }
        Auth::log($u['login'], (int) $u['id'], 'code_2fa_faux');
        $err = 'Code incorrect. Vérifiez que l\'heure de votre téléphone est à jour.';
    }
    view('auth/deux-etapes', ['title' => 'Vérification en deux étapes', 'err' => $err], 'auth');
}

function deuxEtapesConfig(): void
{
    $u = utilisateur_en_attente();
    if ($u['totp_enabled'] && empty($_SESSION['pending']['codes'])) redirect('deux-etapes');
    if (empty($_SESSION['pending']['secret'])) $_SESSION['pending']['secret'] = Totp::secret();
    $secret = $_SESSION['pending']['secret'];
    $err = null;

    if (is_post() && input('etape') === 'fin' && !empty($_SESSION['pending']['codes'])) {
        unset($_SESSION['pending']);
        Auth::complete($u, 'ok_2fa');
        redirect('');
    }
    if (is_post() && input('etape') === 'verifier') {
        if (Totp::verify($secret, (string) input('code'))) {
            $codes = [];
            Db::run('DELETE FROM backup_codes WHERE user_id = ?', [$u['id']]);
            for ($i = 0; $i < 8; $i++) {
                $c = strtoupper(bin2hex(random_bytes(4)));
                $codes[] = substr($c, 0, 4) . '-' . substr($c, 4);
                Db::insert('backup_codes', ['user_id' => $u['id'], 'code_hash' => password_hash($c, PASSWORD_DEFAULT)]);
            }
            Db::update('users', ['totp_secret' => $secret, 'totp_enabled' => 1], 'id = ?', [$u['id']]);
            Db::insert('audit_log', ['at' => now(), 'user_id' => $u['id'], 'site_id' => $u['site_id'], 'poste' => Auth::posteNom(), 'action' => 'deux_etapes_activee', 'details' => '']);
            $_SESSION['pending']['codes'] = $codes;
            $_SESSION['pending']['at'] = time();
        } else {
            $err = 'Code incorrect. Saisissez le code à 6 chiffres affiché dans l\'application.';
        }
    }
    view('auth/deux-etapes-config', [
        'title' => 'Activer la vérification en deux étapes',
        'secret' => $secret,
        'uri' => Totp::uri($secret, $u['login'], config('pharmacie')),
        'codes' => $_SESSION['pending']['codes'] ?? null,
        'err' => $err,
    ], 'auth');
}

function verrou(): void
{
    $u = Auth::user();
    if (!$u) redirect('login');
    if (empty($_SESSION['locked'])) redirect('caisse');
    $err = null;
    if (is_post()) {
        $r = Auth::unlock((string) input('pin'));
        if ($r['r'] === 'ok') redirect('caisse');
        if ($r['r'] === 'closed') {
            start_session();
            flash('warn', '3 codes faux : la session est fermée. Reconnectez-vous avec votre identifiant et votre mot de passe.');
            redirect('login');
        }
        $err = 'Code incorrect. ' . $r['left'] . ' essai(s) avant fermeture de la session.';
    }
    $sess = Db::one("SELECT ouverte_at FROM caisse_sessions WHERE user_id = ? AND statut = 'ouverte' ORDER BY id DESC LIMIT 1", [$u['id']]);
    view('auth/verrou', ['title' => 'Caisse verrouillée', 'u' => $u, 'err' => $err, 'sess' => $sess, 'delai' => (int) setting('verrou_minutes', '2')], 'auth');
}

function verrouiller(): void
{
    if (!Auth::user()) json_out(['ok' => false], 401);
    Auth::lock();
    json_out(['ok' => true]);
}

function enregistrerPoste(): void
{
    $err = null;
    if (is_post()) {
        $code = preg_replace('/\D/', '', (string) input('code'));
        $nom = trim((string) input('nom'));
        $c = Db::one('SELECT * FROM poste_codes WHERE code = ? AND used_at IS NULL AND expires_at > ? ORDER BY id DESC LIMIT 1', [$code, now()]);
        if ($nom === '') {
            $err = 'Donnez un nom à ce poste, par exemple « Caisse 1 · comptoir principal ».';
        } elseif (!$c) {
            $err = 'Code inconnu ou expiré. Demandez un nouveau code au propriétaire (valable 10 minutes).';
        } else {
            Db::update('poste_codes', ['used_at' => now()], 'id = ?', [$c['id']]);
            $site = site((int) $c['site_id']);
            Auth::creerPoste($nom, (int) $c['site_id'], 'caisse');
            Db::insert('audit_log', ['at' => now(), 'user_id' => $c['created_by'], 'site_id' => $c['site_id'], 'poste' => $nom, 'action' => 'poste_enregistre', 'details' => $nom . ' · ' . $site['nom']]);
            flash('ok', 'Poste enregistré pour ' . $site['nom'] . '. Seuls les comptes de ce site pourront s\'y connecter.');
            redirect('login');
        }
    }
    view('auth/poste', ['title' => 'Enregistrer ce poste', 'err' => $err], 'auth');
}
