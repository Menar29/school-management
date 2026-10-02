<?php
declare(strict_types=1);

/** Site où vend l'utilisateur : son officine, ou celle choisie par le propriétaire à l'ouverture */
function site_de_vente(array $u): ?int
{
    if ($u['role'] !== 'proprietaire') return (int) $u['site_id'];
    $s = Caisse::sessionOuverte((int) $u['id']);
    return $s ? (int) $s['site_id'] : null;
}

function session_courante(array $u): array
{
    $s = Caisse::sessionOuverte((int) $u['id']);
    if (!$s) {
        if (Auth::isAjax()) json_out(['error' => 'Aucune session de caisse ouverte.'], 409);
        redirect('caisse');
    }
    return $s;
}

function vente(): void
{
    $u = Auth::requirePerm('vendre');
    $s = Caisse::sessionOuverte((int) $u['id']);
    if (!$s) {
        $derniere = Db::one('SELECT * FROM caisse_sessions WHERE user_id = ? ORDER BY id DESC LIMIT 1', [$u['id']]);
        view('caisse/ouvrir', ['title' => 'Ouvrir la caisse', 'u' => $u, 'derniere' => $derniere], 'auth');
    }
    $site = site((int) $s['site_id']);
    $clients = Db::all('SELECT id, nom, type, plafond FROM clients WHERE autorise = 1 AND site_id = ? ORDER BY nom', [$s['site_id']]);
    foreach ($clients as &$c) $c['du'] = du_client_total((int) $c['id']);
    unset($c);
    view('caisse/vente', [
        'title' => 'Caisse',
        'u' => $u,
        'sess' => $s,
        'site' => $site,
        'clients' => $clients,
        'remiseMax' => Perm::REMISE_MAX[$u['role']] ?? 0,
        'verrou' => (int) setting('verrou_minutes', '2'),
        'precision' => Db::one("SELECT * FROM caisse_sessions WHERE user_id = ? AND statut = 'precisions' ORDER BY id DESC LIMIT 1", [$u['id']]),
    ], null);
}

function ouvrir(): void
{
    $u = Auth::requirePerm('vendre');
    if (!is_post() || Caisse::sessionOuverte((int) $u['id'])) redirect('caisse');
    $siteId = $u['role'] === 'proprietaire' ? input_int('site') : (int) $u['site_id'];
    $site = site($siteId);
    if (!$site || $site['type'] !== 'officine') {
        flash('bad', 'Choisissez l\'officine où vous vendez.');
        redirect('caisse');
    }
    $id = Db::insert('caisse_sessions', [
        'site_id' => $siteId, 'user_id' => $u['id'], 'poste' => Auth::posteNom() ?? Auth::appareilNom(),
        'ouverte_at' => now(), 'fond' => max(0, input_int('fond')), 'statut' => 'ouverte',
    ]);
    audit('caisse_ouverte', 'Session #' . $id . ' · fond ' . fmt(input_int('fond')) . ' FCFA', $siteId);
    redirect('caisse');
}

/** Catalogue de l'officine, envoyé à la caisse (gardé sur le poste pour le mode hors connexion) */
function catalogue(): void
{
    $u = Auth::requirePerm('vendre');
    $siteId = site_de_vente($u);
    if (!$siteId) json_out(['error' => 'Aucune session ouverte'], 409);

    $dispo = [];
    foreach (Db::all('SELECT l.produit_id, SUM(l.quantite) q FROM lots l WHERE l.site_id = ? AND l.quantite > 0 AND ' . Stock::NON_PERIME . ' GROUP BY l.produit_id', [$siteId]) as $r) {
        $dispo[(int) $r['produit_id']] = (int) $r['q'];
    }
    $perimes = [];
    foreach (Db::all('SELECT l.produit_id, MAX(l.peremption) p FROM lots l WHERE l.site_id = ? AND l.quantite > 0 AND ' . Stock::PERIME . ' GROUP BY l.produit_id', [$siteId]) as $r) {
        $perimes[(int) $r['produit_id']] = $r['p'];
    }
    $seuils = [];
    foreach (Db::all('SELECT produit_id, seuil FROM seuils WHERE site_id = ?', [$siteId]) as $r) $seuils[(int) $r['produit_id']] = (int) $r['seuil'];
    $alias = [];
    foreach (Db::all('SELECT produit_id, alias FROM produit_alias') as $r) $alias[(int) $r['produit_id']][] = $r['alias'];
    $top = array_map('intval', array_column(Db::all(
        "SELECT vl.produit_id, SUM(vl.fractions) q FROM vente_lignes vl JOIN ventes v ON v.id = vl.vente_id
         WHERE v.site_id = ? AND v.statut = 'payee' AND v.at >= ? GROUP BY vl.produit_id ORDER BY q DESC LIMIT 12",
        [$siteId, date('Y-m-d', strtotime('-30 days'))]), 'produit_id'));

    $out = [];
    foreach (Db::all('SELECT * FROM produits WHERE actif = 1 ORDER BY designation') as $p) {
        $id = (int) $p['id'];
        $units = [];
        foreach (Stock::unitesVente($p) as $k => $un) $units[] = ['u' => $k, 'l' => $un['label'], 'p' => $un['prix'], 'f' => $un['fractions']];
        $q = $dispo[$id] ?? 0;
        $f = max(1, (int) $p['fractions']);
        $st = !$units ? 'noprice' : ($q <= 0 ? (isset($perimes[$id]) ? 'exp' : 'out') : ($q / $f <= max(1, $seuils[$id] ?? 0) ? 'low' : 'ok'));
        $out[] = [
            'id' => $id, 'n' => $p['designation'],
            'd' => trim(($p['dci'] ?: '') . ($p['forme'] ? ' · ' . mb_strtolower($p['forme']) : ''), ' ·'),
            'a' => $alias[$id] ?? [], 'cb' => $p['code_barre'], 'rx' => (int) $p['ordonnance'],
            'u' => $units, 'q' => $q, 'f' => $f, 'st' => $st,
            'qs' => Stock::qte($p, $q), 'us' => ucfirst(Stock::uniteStock($p)), 'ud' => $p['detail_nom'],
            'exp' => isset($perimes[$id]) ? mois_annee($perimes[$id]) : null,
        ];
    }
    json_out(['produits' => $out, 'top' => $top, 'at' => date('H:i')]);
}

/** Vérifie le code d'un responsable avant une remise élevée ou une annulation */
function autoriser(): void
{
    $u = Auth::requirePerm('vendre');
    $b = json_body();
    $siteId = site_de_vente($u) ?? 0;
    $ownerOnly = ($b['action'] ?? '') === 'remise' && (float) ($b['pct'] ?? 0) > Perm::REMISE_MAX['gerant'];
    $s = Auth::superviseur((string) ($b['pin'] ?? ''), $siteId, $ownerOnly);
    if (!$s) {
        audit('autorisation_refusee', ($b['action'] ?? '') . ' · code faux', $siteId);
        json_out(['ok' => false, 'error' => $ownerOnly ? 'Code incorrect. Au-delà de 20 %, seul le code du propriétaire est accepté.' : 'Code incorrect.']);
    }
    json_out(['ok' => true, 'nom' => prenom($s['nom']), 'role' => Perm::role($s['role'])]);
}

function enregistrerVente(): void
{
    $u = Auth::requirePerm('vendre');
    $b = json_body();
    $uuid = substr((string) ($b['uuid'] ?? ''), 0, 40);
    if ($uuid !== '' && ($v = Db::one('SELECT id, numero, total, rendu FROM ventes WHERE client_uuid = ?', [$uuid]))) {
        json_out(['ok' => true, 'id' => (int) $v['id'], 'numero' => $v['numero'], 'total' => (int) $v['total'], 'rendu' => (int) $v['rendu'], 'deja' => true]);
    }
    $s = session_courante($u);
    $siteId = (int) $s['site_id'];
    $offline = !empty($b['offline']);
    $lines = is_array($b['lines'] ?? null) ? $b['lines'] : [];
    if (!$lines) json_out(['error' => 'Le ticket est vide.'], 422);

    try {
        $res = Db::tx(function () use ($u, $s, $siteId, $lines, $b, $uuid, $offline) {
            $brut = 0;
            $prepared = [];
            foreach ($lines as $l) {
                $p = Db::one('SELECT * FROM produits WHERE id = ? AND actif = 1', [(int) ($l['id'] ?? 0)]);
                $n = (int) ($l['n'] ?? 0);
                if (!$p || $n <= 0) throw new RuntimeException('Ligne de ticket invalide.');
                $units = Stock::unitesVente($p);
                $unite = (string) ($l['u'] ?? 'boite');
                if (!isset($units[$unite])) throw new RuntimeException($p['designation'] . ' n\'a pas de prix de vente : il ne peut pas être vendu.');
                $un = $units[$unite];
                if (!$offline && Stock::dispo((int) $p['id'], $siteId) < $un['fractions'] * $n) {
                    $perime = (int) Db::val('SELECT COUNT(*) FROM lots l WHERE l.produit_id = ? AND l.site_id = ? AND l.quantite > 0 AND ' . Stock::PERIME, [$p['id'], $siteId]);
                    $reste = Stock::dispo((int) $p['id'], $siteId);
                    throw new RuntimeException($reste <= 0 && $perime
                        ? $p['designation'] . ' : lot périmé, vente bloquée. Retirez les boîtes du rayon.'
                        : 'Stock insuffisant pour ' . $p['designation'] . ' : il reste ' . Stock::qte($p, $reste) . '.');
                }
                $brut += $un['prix'] * $n;
                $prepared[] = [$p, $unite, $n, $un['prix'], $un['fractions'] * $n];
            }

            // Remise : plafond selon le rôle, au-delà le code d'un responsable
            $remise = max(0, min((int) ($b['remise'] ?? 0), $brut));
            $autorisePar = null;
            if ($remise > 0) {
                $pct = $remise / $brut * 100;
                if ($pct > (Perm::REMISE_MAX[$u['role']] ?? 0) + 0.001) {
                    $sup = Auth::superviseur((string) ($b['pin'] ?? ''), $siteId, $pct > Perm::REMISE_MAX['gerant']);
                    if (!$sup) throw new RuntimeException('Remise de ' . round($pct) . ' % : le code d\'un responsable est nécessaire.');
                    $autorisePar = (int) $sup['id'];
                }
            }
            $total = $brut - $remise;

            $mode = ($b['mode'] ?? 'especes') === 'credit' ? 'credit' : 'especes';
            $clientId = null;
            $recu = null;
            $rendu = null;
            if ($mode === 'credit') {
                $c = Db::one('SELECT * FROM clients WHERE id = ? AND autorise = 1 AND site_id = ?', [(int) ($b['client_id'] ?? 0), $siteId]);
                if (!$c) throw new RuntimeException('Ce client n\'est pas autorisé à acheter à crédit.');
                $du = du_client_total((int) $c['id']);
                if (!$offline && $du + $total > (int) $c['plafond']) {
                    throw new RuntimeException('Plafond de crédit dépassé pour ' . $c['nom'] . ' : ' . fmt($du) . ' FCFA déjà dus sur ' . fmt((int) $c['plafond']) . '.');
                }
                $clientId = (int) $c['id'];
            } else {
                $recu = (int) ($b['recu'] ?? $total);
                if ($recu < $total) throw new RuntimeException('Le montant reçu est inférieur au total.');
                $rendu = $recu - $total;
            }

            $at = now();
            if ($offline && !empty($b['at']) && strtotime((string) $b['at'])) {
                $t = strtotime((string) $b['at']);
                if ($t <= time() && $t >= strtotime($s['ouverte_at'])) $at = date('Y-m-d H:i:s', $t);
            }
            $venteId = Db::insert('ventes', [
                'client_uuid' => $uuid ?: null, 'session_id' => $s['id'], 'site_id' => $siteId, 'user_id' => $u['id'],
                'client_id' => $clientId, 'at' => $at, 'total_brut' => $brut, 'remise' => $remise,
                'remise_autorisee_par' => $autorisePar, 'total' => $total, 'mode' => $mode, 'recu' => $recu, 'rendu' => $rendu,
            ]);
            $numero = numero('V', $venteId, 6);
            Db::update('ventes', ['numero' => $numero], 'id = ?', [$venteId]);

            foreach ($prepared as [$p, $unite, $n, $prix, $fr]) {
                $pris = Stock::sortir((int) $p['id'], $siteId, $fr, 'vente', $numero, null, $offline);
                $cout = 0;
                foreach ($pris as $x) $cout += (int) round(($x['prix_achat'] ?? $p['prix_achat'] ?? 0) * $x['quantite'] / max(1, (int) $p['fractions']));
                $ligneId = Db::insert('vente_lignes', [
                    'vente_id' => $venteId, 'produit_id' => $p['id'], 'unite' => $unite, 'quantite' => $n,
                    'prix_unitaire' => $prix, 'total' => $prix * $n, 'fractions' => $fr, 'cout' => $cout,
                ]);
                foreach ($pris as $x) {
                    if ($x['lot_id']) Db::insert('vente_lots', ['ligne_id' => $ligneId, 'lot_id' => $x['lot_id'], 'quantite' => $x['quantite']]);
                }
            }
            if ($autorisePar) audit('remise_autorisee', $numero . ' · ' . fmt($remise) . ' FCFA (' . round($remise / $brut * 100) . ' %)', $siteId);
            return ['id' => $venteId, 'numero' => $numero, 'total' => $total, 'rendu' => $rendu];
        });
    } catch (RuntimeException $e) {
        json_out(['error' => $e->getMessage()], 422);
    }
    json_out(['ok' => true] + $res);
}

function ticket(): void
{
    $u = Auth::requireLogin();
    $v = Db::one('SELECT v.*, u.nom AS vendeur, c.nom AS client FROM ventes v JOIN users u ON u.id = v.user_id LEFT JOIN clients c ON c.id = v.client_id WHERE v.id = ?', [input_int('id')]);
    if (!$v) not_found();
    if ($u['role'] === 'vendeur' && (int) $v['user_id'] !== (int) $u['id']) forbidden();
    if (!in_array($u['role'], ['proprietaire', 'vendeur'], true) && (int) $v['site_id'] !== (int) $u['site_id']) forbidden();
    $lignes = Db::all('SELECT vl.*, p.designation, p.detail_nom, p.forme, p.contenance FROM vente_lignes vl JOIN produits p ON p.id = vl.produit_id WHERE vl.vente_id = ?', [$v['id']]);
    view('caisse/ticket', ['title' => 'Ticket ' . $v['numero'], 'v' => $v, 'lignes' => $lignes, 'site' => site((int) $v['site_id'])], 'blank');
}

function mesVentes(): void
{
    $u = Auth::requirePerm('vendre');
    $s = Caisse::sessionOuverte((int) $u['id']);
    $precision = Db::one("SELECT * FROM caisse_sessions WHERE user_id = ? AND statut = 'precisions' ORDER BY id DESC LIMIT 1", [$u['id']]);
    if (!$s) {
        $last = Db::one('SELECT * FROM caisse_sessions WHERE user_id = ? ORDER BY id DESC LIMIT 1', [$u['id']]);
        view('caisse/cloturee', ['title' => 'Caisse clôturée', 'u' => $u, 'last' => $last, 'precision' => $precision], 'auth');
    }
    $ventes = Db::all("SELECT v.*, (SELECT GROUP_CONCAT(CONCAT(p.designation, IF(vl.quantite > 1, CONCAT(' ×', vl.quantite), '')) SEPARATOR ', ')
            FROM vente_lignes vl JOIN produits p ON p.id = vl.produit_id WHERE vl.vente_id = v.id) AS articles, c.nom AS client
        FROM ventes v LEFT JOIN clients c ON c.id = v.client_id WHERE v.session_id = ? ORDER BY v.at DESC, v.id DESC", [$s['id']]);
    $compte = $_SESSION['comptage'][$s['id']] ?? null;
    view('caisse/ventes', [
        'title' => 'Mes ventes et clôture', 'u' => $u, 'sess' => $s, 'site' => site((int) $s['site_id']),
        'ventes' => $ventes, 'r' => Caisse::resume((int) $s['id']), 'compte' => $compte, 'precision' => $precision,
    ], null);
}

function cloturer(): void
{
    $u = Auth::requirePerm('vendre');
    $s = session_courante($u);
    $etape = input('etape');
    if ($etape === 'compter') {
        $b = [];
        $total = 0;
        foreach (Caisse::COUPURES as $c) {
            $n = max(0, input_int('b' . $c));
            $b[$c] = $n;
            $total += $n * $c;
        }
        $pieces = max(0, input_int('pieces'));
        $b['pieces'] = $pieces;
        $_SESSION['comptage'][$s['id']] = ['billets' => $b, 'total' => $total + $pieces];
        redirect('caisse/ventes');
    }
    if ($etape === 'recompter') {
        unset($_SESSION['comptage'][$s['id']]);
        redirect('caisse/ventes');
    }
    if ($etape === 'confirmer') {
        $c = $_SESSION['comptage'][$s['id']] ?? null;
        if (!$c) redirect('caisse/ventes');
        $r = Caisse::resume((int) $s['id']);
        $ecart = $c['total'] - $r['attendu'];
        $explication = trim((string) input('explication'));
        if ($ecart !== 0 && $explication === '') {
            flash('bad', 'Expliquez l\'écart avant de clôturer.');
            redirect('caisse/ventes');
        }
        Db::update('caisse_sessions', [
            'cloturee_at' => now(), 'attendu' => $r['attendu'], 'compte' => $c['total'], 'ecart' => $ecart,
            'billetage' => json_encode($c['billets']), 'explication' => $explication ?: null, 'statut' => 'cloturee',
        ], 'id = ?', [$s['id']]);
        unset($_SESSION['comptage'][$s['id']]);
        audit('caisse_cloturee', 'Session #' . $s['id'] . ' · attendu ' . fmt($r['attendu']) . ' · compté ' . fmt($c['total']) . ' · écart ' . fmt_signe($ecart), (int) $s['site_id']);
        flash('ok', 'Caisse clôturée. Remettez ' . fmt($c['total']) . ' FCFA au propriétaire ou au gérant.');
        redirect('caisse/ventes');
    }
    redirect('caisse/ventes');
}

function annuler(): void
{
    $u = Auth::requirePerm('vendre');
    $s = session_courante($u);
    $v = Db::one("SELECT * FROM ventes WHERE id = ? AND session_id = ? AND statut = 'payee'", [input_int('id'), $s['id']]);
    if (!$v) {
        flash('bad', 'Vente introuvable ou déjà annulée.');
        redirect('caisse/ventes');
    }
    $motif = trim((string) input('motif'));
    $sup = $u['role'] === 'vendeur' ? Auth::superviseur((string) input('pin'), (int) $s['site_id']) : $u;
    if (!$sup) {
        audit('annulation_refusee', $v['numero'] . ' · code faux', (int) $s['site_id']);
        flash('bad', 'Code incorrect : la vente ' . $v['numero'] . ' n\'est pas annulée.');
        redirect('caisse/ventes');
    }
    Db::tx(function () use ($v, $sup, $motif) {
        foreach (Db::all('SELECT vlt.* FROM vente_lots vlt JOIN vente_lignes vl ON vl.id = vlt.ligne_id WHERE vl.vente_id = ?', [$v['id']]) as $x) {
            Stock::remettre((int) $x['lot_id'], (int) $x['quantite'], 'annulation', $v['numero'], $motif ?: null);
        }
        Db::update('ventes', ['statut' => 'annulee', 'annulee_par' => $sup['id'], 'annulee_at' => now(), 'motif_annulation' => $motif ?: null], 'id = ?', [$v['id']]);
    });
    audit('vente_annulee', $v['numero'] . ' · ' . fmt((int) $v['total']) . ' FCFA · autorisée par ' . $sup['nom'] . ($motif ? ' · ' . $motif : ''), (int) $s['site_id']);
    flash('ok', 'Vente ' . $v['numero'] . ' annulée. Le stock a été remis en rayon.');
    redirect('caisse/ventes');
}

function depense(): void
{
    $u = Auth::requirePerm('vendre');
    $s = session_courante($u);
    $montant = input_int('montant');
    $libelle = trim((string) input('libelle'));
    if ($montant <= 0 || $libelle === '') {
        flash('bad', 'Indiquez le motif et le montant de la dépense.');
        redirect('caisse/ventes');
    }
    $r = Caisse::resume((int) $s['id']);
    if ($montant > $r['attendu']) {
        flash('bad', 'Il n\'y a pas assez d\'espèces dans la caisse pour cette dépense.');
        redirect('caisse/ventes');
    }
    Db::insert('depenses', ['at' => now(), 'site_id' => $s['site_id'], 'session_id' => $s['id'], 'user_id' => $u['id'], 'libelle' => $libelle, 'montant' => $montant]);
    audit('depense_caisse', $libelle . ' · ' . fmt($montant) . ' FCFA', (int) $s['site_id']);
    flash('ok', 'Dépense enregistrée. Gardez le justificatif avec les espèces.');
    redirect('caisse/ventes');
}

function repondrePrecision(): void
{
    $u = Auth::requirePerm('vendre');
    $s = Db::one("SELECT * FROM caisse_sessions WHERE id = ? AND user_id = ? AND statut = 'precisions'", [input_int('id'), $u['id']]);
    $txt = trim((string) input('explication'));
    if ($s && $txt !== '') {
        $old = $s['explication'] ? $s['explication'] . "\n" : '';
        Db::update('caisse_sessions', ['explication' => $old . 'Réponse : ' . $txt, 'statut' => 'cloturee'], 'id = ?', [$s['id']]);
        audit('caisse_precision', 'Session #' . $s['id'] . ' · ' . $txt, (int) $s['site_id']);
        flash('ok', 'Réponse envoyée.');
    }
    redirect(Caisse::sessionOuverte((int) $u['id']) ? 'caisse' : 'caisse/ventes');
}

function signalerPerime(): void
{
    $u = Auth::requirePerm('vendre');
    $p = Db::one('SELECT designation FROM produits WHERE id = ?', [(int) (json_body()['id'] ?? 0)]);
    if ($p) audit('perime_signale', $p['designation'] . ' · retiré du rayon par ' . prenom($u['nom']), site_de_vente($u));
    json_out(['ok' => true]);
}
