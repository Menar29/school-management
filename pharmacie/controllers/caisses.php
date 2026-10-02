<?php
declare(strict_types=1);

function index(): void
{
    $u = Auth::requirePerm('caisses.controler');
    $site = site_filtre();
    $jour = input('jour', today());
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $jour)) $jour = today();
    $ws = $site ? ' AND s.site_id = ' . $site : '';

    // Sessions du jour choisi, plus toutes celles qui attendent encore une action
    $rows = Db::all("SELECT s.*, u.nom FROM caisse_sessions s JOIN users u ON u.id = s.user_id
        WHERE (DATE(s.ouverte_at) = ? OR (? = CURDATE() AND s.statut <> 'validee')) $ws
        ORDER BY FIELD(s.statut, 'cloturee', 'precisions', 'ouverte', 'validee'), s.ouverte_at DESC", [$jour, $jour]);
    $attendu = 0; $valide = 0; $nValide = 0; $ecart = 0; $aVoir = 0;
    foreach ($rows as &$r) {
        $r['res'] = Caisse::resume((int) $r['id']);
        $r['st'] = Caisse::statut($r);
        $attendu += $r['res']['attendu'];
        if ($r['statut'] === 'validee') { $valide += (int) $r['compte']; $nValide++; }
        if ($r['statut'] !== 'ouverte') $ecart += (int) $r['ecart'];
        if ($r['statut'] === 'cloturee' && (int) $r['ecart'] !== 0) $aVoir++;
    }
    unset($r);
    $selId = input_int('sel', (int) ($rows[0]['id'] ?? 0));
    $sel = null;
    foreach ($rows as $r) if ((int) $r['id'] === $selId) $sel = $r;
    if (!$sel && $selId) {
        $sel = Db::one('SELECT s.*, u.nom FROM caisse_sessions s JOIN users u ON u.id = s.user_id WHERE s.id = ?' . ($site ? ' AND s.site_id = ' . $site : ''), [$selId]);
        if ($sel) { $sel['res'] = Caisse::resume((int) $sel['id']); $sel['st'] = Caisse::statut($sel); }
    }
    if (!$sel && $rows) $sel = $rows[0];

    $mouvements = $u['role'] === 'proprietaire' ? Db::all('SELECT * FROM mouvements_especes ORDER BY at DESC, id DESC LIMIT 12') : [];
    $jours = Db::all('SELECT DISTINCT DATE(ouverte_at) d FROM caisse_sessions s WHERE 1 ' . $ws . ' ORDER BY d DESC LIMIT 30');

    view('admin/caisses', [
        'title' => 'Caisses et versements', 'u' => $u, 'site' => $site, 'jour' => $jour, 'jours' => $jours,
        'rows' => $rows, 'sel' => $sel, 'attendu' => $attendu, 'valide' => $valide, 'nValide' => $nValide,
        'ecart' => $ecart, 'aVoir' => $aVoir, 'mouvements' => $mouvements,
        'especes' => $u['role'] === 'proprietaire' ? especes_proprietaire() : null,
        'ventesSel' => $sel ? Db::all("SELECT v.*, (SELECT GROUP_CONCAT(p.designation SEPARATOR ', ') FROM vente_lignes vl JOIN produits p ON p.id = vl.produit_id WHERE vl.vente_id = v.id) articles FROM ventes v WHERE session_id = ? ORDER BY at DESC", [$sel['id']]) : [],
    ]);
}

function session_controlable(array $u, int $id): array
{
    $s = Db::one('SELECT s.*, u.nom FROM caisse_sessions s JOIN users u ON u.id = s.user_id WHERE s.id = ?', [$id]);
    if (!$s || ($u['role'] !== 'proprietaire' && (int) $s['site_id'] !== (int) $u['site_id'])) not_found();
    return $s;
}

/** Le propriétaire reçoit les espèces : elles entrent dans sa caisse */
function valider(): void
{
    $u = Auth::requirePerm('caisses.valider');
    $s = session_controlable($u, input_int('id'));
    if (!in_array($s['statut'], ['cloturee', 'precisions'], true)) {
        flash('bad', 'Cette caisse n\'est pas clôturée ou a déjà été validée.');
        redirect('caisses', ['sel' => $s['id']]);
    }
    Db::tx(function () use ($u, $s) {
        Db::update('caisse_sessions', ['statut' => 'validee', 'valide_par' => $u['id'], 'valide_at' => now(), 'remarque_admin' => trim((string) input('remarque')) ?: $s['remarque_admin']], 'id = ?', [$s['id']]);
        Db::insert('mouvements_especes', [
            'at' => now(), 'type' => 'versement', 'libelle' => 'Versement validé · ' . prenom($s['nom']) . ' (' . site((int) $s['site_id'])['nom_court'] . ')',
            'montant' => (int) $s['compte'], 'ref' => 'S-' . $s['id'], 'user_id' => $u['id'],
        ]);
    });
    audit('versement_valide', prenom($s['nom']) . ' · ' . fmt((int) $s['compte']) . ' FCFA · écart ' . fmt_signe((int) $s['ecart']), (int) $s['site_id']);
    flash('ok', 'Versement de ' . fmt((int) $s['compte']) . ' FCFA validé.');
    redirect('caisses', ['sel' => $s['id']]);
}

function precisions(): void
{
    $u = Auth::requirePerm('caisses.controler');
    $s = session_controlable($u, input_int('id'));
    $q = trim((string) input('remarque'));
    if ($q === '' || $s['statut'] !== 'cloturee') redirect('caisses', ['sel' => $s['id']]);
    Db::update('caisse_sessions', ['statut' => 'precisions', 'remarque_admin' => $q], 'id = ?', [$s['id']]);
    audit('caisse_precisions', 'Session #' . $s['id'] . ' · ' . $q, (int) $s['site_id']);
    flash('ok', 'Question envoyée à ' . prenom($s['nom']) . '. Elle s\'affichera à sa prochaine connexion à la caisse.');
    redirect('caisses', ['sel' => $s['id']]);
}

/** Dépense payée par le propriétaire (électricité, loyer...) */
function depense(): void
{
    $u = Auth::requirePerm('caisses.valider');
    $m = input_int('montant');
    $l = trim((string) input('libelle'));
    if ($m <= 0 || $l === '') {
        flash('bad', 'Indiquez le motif et le montant.');
        redirect('caisses');
    }
    $siteId = input_int('site') ?: null;
    Db::tx(function () use ($u, $m, $l, $siteId) {
        Db::insert('depenses', ['at' => now(), 'site_id' => $siteId, 'session_id' => null, 'user_id' => $u['id'], 'libelle' => $l, 'montant' => $m]);
        Db::insert('mouvements_especes', ['at' => now(), 'type' => 'depense', 'libelle' => 'Dépense · ' . $l, 'montant' => -$m, 'user_id' => $u['id']]);
    });
    audit('depense', $l . ' · ' . fmt($m) . ' FCFA', $siteId);
    flash('ok', 'Dépense enregistrée.');
    redirect('caisses');
}
