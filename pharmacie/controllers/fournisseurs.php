<?php
declare(strict_types=1);

function index(): void
{
    $u = Auth::requirePerm('fournisseurs');
    $q = trim((string) input('q'));
    $list = Db::all("SELECT * FROM fournisseurs WHERE (? = '' OR nom LIKE ? OR code LIKE ?) ORDER BY actif DESC, code", [$q, "%$q%", "%$q%"]);
    foreach ($list as &$f) {
        $f['du'] = du_fournisseur((int) $f['id']);
        $f['nb_cmd'] = (int) Db::val("SELECT COUNT(*) FROM commandes WHERE fournisseur_id = ? AND statut <> 'annulee'", [$f['id']]);
    }
    unset($f);
    $sel = null;
    $selId = input_int('id', (int) ($list[0]['id'] ?? 0));
    foreach ($list as $f) if ((int) $f['id'] === $selId) $sel = $f;
    $data = ['title' => 'Fournisseurs', 'u' => $u, 'list' => $list, 'sel' => $sel, 'q' => $q, 'tab' => input('tab', 'commandes')];
    if ($sel) {
        $data['commandes'] = Db::all("SELECT c.*, COUNT(cl.id) nb, SUM(cl.quantite * cl.prix_achat) montant, SUM(cl.recu < cl.quantite) manq FROM commandes c LEFT JOIN commande_lignes cl ON cl.commande_id = c.id WHERE c.fournisseur_id = ? GROUP BY c.id ORDER BY c.created_at DESC LIMIT 50", [$sel['id']]);
        foreach ($data['commandes'] as &$c) { $c['recu'] = valeur_recue_commande((int) $c['id']); $c['paye'] = paye_commande((int) $c['id']); }
        unset($c);
        $data['produits'] = Db::all('SELECT id, designation, prix_achat, prix_achat_prec FROM produits WHERE fournisseur_id = ? AND actif = 1 ORDER BY designation', [$sel['id']]);
        $data['paiements'] = Db::all('SELECT p.*, c.numero FROM paiements_fournisseurs p LEFT JOIN commandes c ON c.id = p.commande_id WHERE p.fournisseur_id = ? ORDER BY p.at DESC', [$sel['id']]);
        $data['achete'] = (int) Db::val("SELECT COALESCE(SUM(rl.quantite * rl.prix_facture), 0) FROM reception_lignes rl JOIN receptions r ON r.id = rl.reception_id WHERE r.statut = 'validee' AND r.fournisseur_id = ? AND r.validee_at >= ?", [$sel['id'], date('Y-01-01')]);
        $livrees = array_filter($data['commandes'], fn($c) => in_array($c['statut'], ['livree', 'partielle'], true));
        $data['fill'] = $livrees ? (int) round(count(array_filter($livrees, fn($c) => (int) $c['manq'] === 0)) / count($livrees) * 100) : null;
    }
    view('admin/fournisseurs', $data);
}

function enregistrer(): void
{
    Auth::requirePerm('fournisseurs');
    $id = input_int('id');
    $d = [
        'code' => mb_strtoupper(trim((string) input('code'))),
        'nom' => trim((string) input('nom')),
        'contact' => trim((string) input('contact')) ?: null,
        'telephone' => trim((string) input('telephone')) ?: null,
        'adresse' => trim((string) input('adresse')) ?: null,
        'delai_jours' => max(0, input_int('delai_jours', 7)),
        'conditions' => trim((string) input('conditions')) ?: null,
        'actif' => input('actif', '1') ? 1 : 0,
    ];
    if ($d['code'] === '' || $d['nom'] === '') {
        flash('bad', 'Le code et le nom sont obligatoires.');
        redirect('fournisseurs', $id ? ['id' => $id] : []);
    }
    if (Db::val('SELECT id FROM fournisseurs WHERE code = ? AND id <> ?', [$d['code'], $id])) {
        flash('bad', 'Le code ' . $d['code'] . ' est déjà utilisé.');
        redirect('fournisseurs', $id ? ['id' => $id] : []);
    }
    if ($id) Db::update('fournisseurs', $d, 'id = ?', [$id]);
    else $id = Db::insert('fournisseurs', $d + ['created_at' => now()]);
    audit('fournisseur_enregistre', $d['code'] . ' · ' . $d['nom']);
    flash('ok', 'Fournisseur enregistré.');
    redirect('fournisseurs', ['id' => $id]);
}
