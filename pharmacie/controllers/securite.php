<?php
declare(strict_types=1);

const RESULTATS = [
    'ok' => ['Réussie', 'ok'], 'ok_2fa' => ['Réussie · 2 étapes', 'ok'], 'deverrouillage' => ['Déverrouillage', 'ok'],
    'mauvais_mdp' => ['Refusée · mot de passe', 'warn'], 'inexistant' => ['Refusée · inexistant', 'bad'], 'bloque' => ['Bloqué · 5 échecs', 'bad'],
    'mauvais_site' => ['Refusée · mauvais site', 'warn'], 'desactive' => ['Refusée · compte désactivé', 'warn'], 'poste_inconnu' => ['Refusée · poste non enregistré', 'warn'],
    'code_2fa_faux' => ['Refusée · code 2 étapes', 'warn'], 'debloque' => ['Débloqué par le propriétaire', 'mute'], 'pin_3_echecs' => ['Session fermée · 3 codes faux', 'bad'],
];

function index(): void
{
    $u = Auth::requirePerm('securite');
    $hier = date('Y-m-d H:i:s', time() - 86400);
    $refus = Db::one("SELECT COUNT(*) n, SUM(resultat = 'inexistant') inex FROM login_log WHERE at >= ? AND resultat NOT IN ('ok', 'ok_2fa', 'deverrouillage')", [$hier]);
    $bloques = Db::all('SELECT * FROM users WHERE locked_until > ?', [now()]);
    $postes = Db::all('SELECT * FROM postes ORDER BY revoked_at IS NOT NULL, type, last_seen_at DESC');
    $deux = Db::one("SELECT SUM(totp_enabled = 1) actives, COUNT(*) total FROM users WHERE actif = 1 AND role IN ('proprietaire', 'gerant')");
    $log = Db::all('SELECT * FROM login_log ORDER BY at DESC, id DESC LIMIT 60');
    $code = $_SESSION['code_poste'] ?? null;
    if ($code && strtotime($code['exp']) < time()) { unset($_SESSION['code_poste']); $code = null; }
    view('admin/securite', compact('u', 'refus', 'bloques', 'postes', 'deux', 'log', 'code') + ['title' => 'Sécurité et connexions']);
}

function retirerAppareil(): void
{
    Auth::requirePerm('securite');
    $p = Db::one('SELECT * FROM postes WHERE id = ? AND revoked_at IS NULL', [input_int('id')]);
    if ($p) {
        Db::update('postes', ['revoked_at' => now()], 'id = ?', [$p['id']]);
        audit('appareil_retire', $p['nom']);
        flash('ok', $p['nom'] . ' est retiré : il est déconnecté immédiatement.');
    }
    redirect('securite');
}

function debloquer(): void
{
    Auth::requirePerm('securite');
    $c = Db::one('SELECT * FROM users WHERE id = ?', [input_int('id')]);
    if ($c) {
        Db::update('users', ['locked_until' => null, 'failed_attempts' => 0], 'id = ?', [$c['id']]);
        Auth::log($c['login'], (int) $c['id'], 'debloque');
        audit('compte_debloque', $c['login']);
        flash('ok', 'Compte ' . $c['login'] . ' débloqué.');
    }
    redirect('securite');
}

function regles(): void
{
    Auth::requirePerm('securite');
    $lock = in_array(input('verrou'), ['1', '2', '5'], true) ? input('verrou') : setting('verrou_minutes', '2');
    $new = ['verrou_minutes' => $lock, 'deux_etapes' => input('deux_etapes') ? '1' : '0', 'postes_obligatoires' => input('postes') ? '1' : '0'];
    $chg = [];
    foreach ($new as $k => $v) {
        if (setting($k) !== $v) { setting_set($k, $v); $chg[] = $k . ' = ' . $v; }
    }
    if ($chg) audit('regles_acces', implode(' · ', $chg));
    flash('ok', 'Règles d\'accès enregistrées.');
    redirect('securite');
}

function codePoste(): void
{
    $u = Auth::requirePerm('securite');
    $site = site(input_int('site'));
    if (!$site) redirect('securite');
    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $exp = date('Y-m-d H:i:s', time() + 600);
    Db::insert('poste_codes', ['code' => $code, 'site_id' => $site['id'], 'created_by' => $u['id'], 'expires_at' => $exp]);
    $_SESSION['code_poste'] = ['code' => $code, 'site' => $site['nom'], 'exp' => $exp];
    audit('code_poste_genere', $site['nom'], (int) $site['id']);
    redirect('securite');
}

function journal(): void
{
    $u = Auth::requirePerm('journal');
    $site = site_filtre();
    $q = trim((string) input('q'));
    $page = max(1, input_int('p', 1));
    $where = ['1'];
    $params = [];
    if ($site) { $where[] = 'a.site_id = ?'; $params[] = $site; }
    if ($q !== '') { $where[] = '(a.action LIKE ? OR a.details LIKE ? OR u.nom LIKE ?)'; array_push($params, "%$q%", "%$q%", "%$q%"); }
    if ($d = input('du')) { $where[] = 'a.at >= ?'; $params[] = $d; }
    $w = implode(' AND ', $where);
    $total = (int) Db::val("SELECT COUNT(*) FROM audit_log a LEFT JOIN users u ON u.id = a.user_id WHERE $w", $params);
    $rows = Db::all("SELECT a.*, u.nom FROM audit_log a LEFT JOIN users u ON u.id = a.user_id WHERE $w ORDER BY a.at DESC, a.id DESC LIMIT 100 OFFSET " . (($page - 1) * 100), $params);
    view('admin/journal', ['title' => 'Journal d\'activité', 'u' => $u, 'rows' => $rows, 'site' => $site, 'q' => $q, 'page' => $page, 'pages' => max(1, (int) ceil($total / 100)), 'total' => $total]);
}
