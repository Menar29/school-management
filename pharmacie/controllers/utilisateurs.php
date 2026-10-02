<?php
declare(strict_types=1);

function mot_de_passe_provisoire(): string
{
    $m = ['Natron', 'Fleuve', 'Baobab', 'Dune', 'Sable', 'Mangue', 'Pluie', 'Kado', 'Sahel', 'Karite', 'Gombo', 'Tamarin', 'Datte', 'Oasis', 'Savane', 'Girafe'];
    return $m[random_int(0, count($m) - 1)] . '-' . random_int(10, 99) . '-' . $m[random_int(0, count($m) - 1)];
}

function index(): void
{
    $u = Auth::requirePerm('utilisateurs');
    $f = input('site', 'all');
    $where = $f === 'all' ? '' : ' WHERE u.site_id = ' . (int) $f;
    $rows = Db::all('SELECT u.* FROM users u' . $where . " ORDER BY u.actif DESC, FIELD(u.role, 'proprietaire', 'gerant', 'vendeur', 'magasinier'), u.nom");
    view('admin/utilisateurs', ['title' => 'Utilisateurs', 'u' => $u, 'rows' => $rows, 'f' => $f, 'logins' => array_column(Db::all('SELECT login FROM users'), 'login')]);
}

function creer(): void
{
    $u = Auth::requirePerm('utilisateurs');
    $nom = trim((string) input('nom'));
    $login = mb_strtolower(trim((string) input('login')));
    $role = (string) input('role');
    $site = site(input_int('site'));
    $err = null;
    if (mb_strlen($nom) < 2) $err = 'Indiquez le prénom et le nom.';
    elseif (!preg_match('/^[a-z0-9.]{3,60}$/', $login)) $err = 'Identifiant : minuscules, chiffres et point uniquement (3 caractères minimum).';
    elseif (Db::val('SELECT id FROM users WHERE login = ?', [$login])) $err = 'L\'identifiant ' . $login . ' est déjà pris.';
    elseif (!in_array($role, ['vendeur', 'gerant', 'magasinier'], true)) $err = 'Choisissez le rôle.';
    elseif (!$site) $err = 'Choisissez le site de rattachement.';
    elseif ($role !== 'magasinier' && $site['type'] !== 'officine') $err = 'Un vendeur ou un gérant est rattaché à une officine.';
    if ($err) {
        flash('bad', $err);
        redirect('utilisateurs');
    }
    $pw = mot_de_passe_provisoire();
    $id = Db::insert('users', [
        'nom' => $nom, 'login' => $login, 'telephone' => trim((string) input('telephone')) ?: null, 'role' => $role, 'site_id' => $site['id'],
        'password_hash' => password_hash($pw, PASSWORD_DEFAULT), 'must_change_pw' => 1, 'created_at' => now(),
    ]);
    audit('utilisateur_cree', $nom . ' · ' . $login . ' · ' . Perm::role($role) . ' · ' . $site['nom'], (int) $site['id']);
    view('admin/utilisateur-mdp', ['title' => 'Utilisateur créé', 'u' => $u, 'cible' => Db::one('SELECT * FROM users WHERE id = ?', [$id]), 'pw' => $pw, 'nouveau' => true]);
}

function cible(): array
{
    $c = Db::one('SELECT * FROM users WHERE id = ?', [input_int('id')]);
    if (!$c) not_found();
    if ($c['role'] === 'proprietaire' && (int) $c['id'] !== Auth::id()) forbidden();
    return $c;
}

function modifier(): void
{
    Auth::requirePerm('utilisateurs');
    $c = cible();
    $d = ['nom' => trim((string) input('nom')) ?: $c['nom'], 'telephone' => trim((string) input('telephone')) ?: null];
    if ($c['role'] !== 'proprietaire') {
        $role = (string) input('role');
        $site = site(input_int('site'));
        if (in_array($role, ['vendeur', 'gerant', 'magasinier'], true) && $site && ($role === 'magasinier' || $site['type'] === 'officine')) {
            if ($role !== $c['role'] || (int) $site['id'] !== (int) $c['site_id']) {
                $d += ['role' => $role, 'site_id' => $site['id'], 'session_version' => (int) $c['session_version'] + 1];
                if ($role === 'gerant') $d['totp_enabled'] = 0;
            }
        } else {
            flash('bad', 'Rôle ou site invalide.');
            redirect('utilisateurs');
        }
    }
    Db::update('users', $d, 'id = ?', [$c['id']]);
    audit('utilisateur_modifie', $c['login'] . (isset($d['role']) ? ' · ' . Perm::role($d['role']) . ' · ' . site((int) $d['site_id'])['nom'] : ''));
    flash('ok', 'Utilisateur modifié.' . (isset($d['role']) ? ' Ses sessions ouvertes ont été fermées.' : ''));
    redirect('utilisateurs');
}

function reinitialiser(): void
{
    $u = Auth::requirePerm('utilisateurs');
    $c = cible();
    if ((int) $c['id'] === (int) $u['id']) {
        flash('bad', 'Pour changer votre propre mot de passe, déconnectez-vous puis utilisez un code de secours, ou demandez-le à un administrateur technique.');
        redirect('utilisateurs');
    }
    $pw = mot_de_passe_provisoire();
    Db::update('users', [
        'password_hash' => password_hash($pw, PASSWORD_DEFAULT), 'must_change_pw' => 1, 'failed_attempts' => 0, 'locked_until' => null,
        'session_version' => (int) $c['session_version'] + 1, 'totp_enabled' => input('totp') ? 0 : $c['totp_enabled'],
    ], 'id = ?', [$c['id']]);
    audit('mot_de_passe_reinitialise', $c['login'] . (input('totp') ? ' · double vérification à reconfigurer' : ''), $c['site_id'] ? (int) $c['site_id'] : null);
    view('admin/utilisateur-mdp', ['title' => 'Mot de passe réinitialisé', 'u' => $u, 'cible' => $c, 'pw' => $pw, 'nouveau' => false]);
}

function activer(): void
{
    $u = Auth::requirePerm('utilisateurs');
    $c = cible();
    if ((int) $c['id'] === (int) $u['id']) redirect('utilisateurs');
    $actif = $c['actif'] ? 0 : 1;
    Db::update('users', ['actif' => $actif, 'session_version' => (int) $c['session_version'] + 1], 'id = ?', [$c['id']]);
    audit($actif ? 'utilisateur_reactive' : 'utilisateur_desactive', $c['login'], $c['site_id'] ? (int) $c['site_id'] : null);
    flash('ok', $actif ? prenom($c['nom']) . ' peut de nouveau se connecter.' : prenom($c['nom']) . ' est désactivé : toutes ses sessions sont fermées. Son historique est conservé.');
    redirect('utilisateurs');
}

function droits(): void
{
    $u = Auth::requireLogin();
    if (!in_array($u['role'], ['proprietaire', 'gerant'], true)) forbidden();
    view('admin/droits', ['title' => 'Rôles et droits', 'u' => $u]);
}
