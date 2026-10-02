<?php
declare(strict_types=1);

function index(): void
{
    $u = Auth::requirePerm('transferts');
    $mon = $u['role'] === 'proprietaire' ? null : (int) $u['site_id'];
    $w = $mon ? " WHERE t.site_from = $mon OR t.site_to = $mon" : '';
    $rows = Db::all("SELECT t.*, u.nom auteur, r.nom recepteur, (SELECT COUNT(DISTINCT produit_id) FROM transfert_lignes tl WHERE tl.transfert_id = t.id) nb
        FROM transferts t JOIN users u ON u.id = t.created_by LEFT JOIN users r ON r.id = t.recu_by $w ORDER BY t.created_at DESC LIMIT 100");
    $aRecevoir = [];
    foreach ($rows as $t) {
        if ($t['statut'] === 'en_route' && (!$mon || (int) $t['site_to'] === $mon)) {
            $t['lignes'] = Db::all('SELECT tl.*, p.designation, p.fractions, p.detail_nom, p.forme, p.contenance FROM transfert_lignes tl JOIN produits p ON p.id = tl.produit_id WHERE tl.transfert_id = ?', [$t['id']]);
            $aRecevoir[] = $t;
        }
    }
    view('admin/transferts', ['title' => 'Transferts', 'u' => $u, 'rows' => $rows, 'aRecevoir' => $aRecevoir, 'mon' => $mon]);
}

function envoyer(): void
{
    $u = Auth::requirePerm('transferts');
    $from = $u['role'] === 'proprietaire' ? input_int('from') : (int) $u['site_id'];
    $to = input_int('to');
    if (!site($from) || !site($to) || $from === $to) {
        flash('bad', 'Choisissez deux sites différents.');
        redirect('transferts');
    }
    $lignes = [];
    foreach ((array) ($_POST['produit'] ?? []) as $i => $pid) {
        $p = Db::one('SELECT * FROM produits WHERE id = ?', [(int) $pid]);
        $q = max(0, (int) ($_POST['b'][$i] ?? 0)) * (int) ($p['fractions'] ?? 1) + max(0, (int) ($_POST['d'][$i] ?? 0));
        if ($p && $q > 0) $lignes[] = [$p, $q];
    }
    if (!$lignes) {
        flash('bad', 'Ajoutez au moins un produit avec une quantité.');
        redirect('transferts');
    }
    try {
        $id = Db::tx(function () use ($u, $from, $to, $lignes) {
            $id = Db::insert('transferts', ['site_from' => $from, 'site_to' => $to, 'statut' => 'en_route', 'created_by' => $u['id'], 'created_at' => now(), 'note' => trim((string) input('note')) ?: null]);
            $num = numero('T', $id);
            Db::update('transferts', ['numero' => $num], 'id = ?', [$id]);
            foreach ($lignes as [$p, $q]) {
                foreach (Stock::sortir((int) $p['id'], $from, $q, 'transfert_out', $num, 'Vers ' . site($to)['nom_court']) as $x) {
                    Db::insert('transfert_lignes', ['transfert_id' => $id, 'produit_id' => $p['id'], 'numero_lot' => $x['numero'], 'peremption' => $x['peremption'], 'prix_achat' => $x['prix_achat'], 'envoye' => $x['quantite']]);
                }
            }
            return $id;
        });
    } catch (RuntimeException $e) {
        flash('bad', $e->getMessage() . ' Les lots périmés ne peuvent pas être transférés.');
        redirect('transferts');
    }
    audit('transfert_envoye', numero('T', $id) . ' · ' . site($from)['nom_court'] . ' → ' . site($to)['nom_court'] . ' · ' . count($lignes) . ' produit(s)', $from);
    flash('ok', 'Transfert ' . numero('T', $id) . ' envoyé. Le stock reste « en route » jusqu\'à la confirmation de ' . site($to)['nom'] . '.');
    redirect('transferts');
}

function recevoir(): void
{
    $u = Auth::requirePerm('transferts');
    $t = Db::one("SELECT * FROM transferts WHERE id = ? AND statut = 'en_route'", [input_int('id')]);
    if (!$t || ($u['role'] !== 'proprietaire' && (int) $t['site_to'] !== (int) $u['site_id'])) not_found();
    $ecarts = [];
    Db::tx(function () use ($t, &$ecarts) {
        foreach (Db::all('SELECT tl.*, p.fractions, p.designation, p.detail_nom, p.forme, p.contenance FROM transfert_lignes tl JOIN produits p ON p.id = tl.produit_id WHERE tl.transfert_id = ?', [$t['id']]) as $l) {
            $v = $_POST['recu'][$l['id']] ?? null;
            $recu = is_array($v) ? max(0, (int) ($v['b'] ?? 0)) * (int) $l['fractions'] + max(0, (int) ($v['d'] ?? 0)) : (int) $l['envoye'];
            Db::update('transfert_lignes', ['recu' => $recu], 'id = ?', [$l['id']]);
            if ($recu > 0) Stock::entrer((int) $l['produit_id'], (int) $t['site_to'], $l['numero_lot'], $l['peremption'], $recu, $l['prix_achat'] !== null ? (int) $l['prix_achat'] : null, 'transfert_in', $t['numero'], 'Depuis ' . site((int) $t['site_from'])['nom_court']);
            if ($recu !== (int) $l['envoye']) $ecarts[] = $l['designation'] . ' : envoyé ' . Stock::qte($l, (int) $l['envoye']) . ', reçu ' . Stock::qte($l, $recu);
        }
        Db::update('transferts', ['statut' => $ecarts ? 'ecart' : 'recu', 'recu_by' => Auth::id(), 'recu_at' => now(), 'note' => $ecarts ? implode(' · ', $ecarts) : $t['note']], 'id = ?', [$t['id']]);
    });
    audit($ecarts ? 'transfert_ecart' : 'transfert_recu', $t['numero'] . ($ecarts ? ' · ' . implode(' · ', $ecarts) : ''), (int) $t['site_to']);
    flash($ecarts ? 'warn' : 'ok', $ecarts ? 'Transfert reçu avec un écart, signalé au propriétaire.' : 'Transfert reçu : le stock est à jour.');
    redirect('transferts');
}
