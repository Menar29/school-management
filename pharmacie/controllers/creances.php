<?php
declare(strict_types=1);

function index(): void
{
    $u = Auth::requirePerm('creances');
    $site = site_filtre();
    $clients = creances($site);
    usort($clients, fn($a, $b) => $b['du'] <=> $a['du']);
    $total = array_sum(array_column($clients, 'du'));
    $retard = array_filter($clients, fn($c) => ($c['age'] ?? 0) > 30);
    $mois = (int) Db::val('SELECT COALESCE(SUM(cp.montant), 0) FROM creance_paiements cp JOIN clients c ON c.id = cp.client_id WHERE cp.at >= ?' . ($site ? ' AND c.site_id = ' . $site : ''), [date('Y-m-01')]);
    $edit = input_int('edit') ? Db::one('SELECT * FROM clients WHERE id = ?', [input_int('edit')]) : null;
    view('admin/creances', compact('u', 'site', 'clients', 'total', 'retard', 'mois', 'edit') + ['title' => 'Créances clients']);
}

function enregistrerClient(): void
{
    $u = Auth::requirePerm('creances.gerer');
    $id = input_int('id');
    $data = [
        'nom' => trim((string) input('nom')),
        'type' => input('type') === 'Professionnel' ? 'Professionnel' : 'Particulier',
        'telephone' => trim((string) input('telephone')) ?: null,
        'site_id' => input_int('site_id'),
        'plafond' => max(0, input_int('plafond')),
        'autorise' => input('autorise') ? 1 : 0,
    ];
    if ($data['nom'] === '' || !site($data['site_id'])) {
        flash('bad', 'Indiquez le nom du client et son officine.');
        redirect('creances');
    }
    if ($id) {
        Db::update('clients', $data, 'id = ?', [$id]);
        audit('client_modifie', $data['nom'] . ' · plafond ' . fmt($data['plafond']) . ($data['autorise'] ? '' : ' · crédit suspendu'), $data['site_id']);
    } else {
        Db::insert('clients', $data + ['created_at' => now()]);
        audit('client_autorise', $data['nom'] . ' · plafond ' . fmt($data['plafond']), $data['site_id']);
    }
    flash('ok', 'Client enregistré.');
    redirect('creances');
}

function encaisser(): void
{
    $u = Auth::requirePerm('creances.gerer');
    $c = Db::one('SELECT * FROM clients WHERE id = ?', [input_int('id')]);
    $m = input_int('montant');
    if (!$c || $m <= 0) redirect('creances');
    $du = du_client_total((int) $c['id']);
    if ($m > $du) {
        flash('bad', $c['nom'] . ' ne doit que ' . fmt($du) . ' FCFA.');
        redirect('creances');
    }
    Db::tx(function () use ($u, $c, $m) {
        Db::insert('creance_paiements', ['client_id' => $c['id'], 'at' => now(), 'montant' => $m, 'user_id' => $u['id'], 'note' => trim((string) input('note')) ?: 'Espèces']);
        Db::insert('mouvements_especes', ['at' => now(), 'type' => 'creance', 'libelle' => 'Remboursement · ' . $c['nom'], 'montant' => $m, 'user_id' => $u['id']]);
    });
    audit('creance_encaissee', $c['nom'] . ' · ' . fmt($m) . ' FCFA', (int) $c['site_id']);
    flash('ok', fmt($m) . ' FCFA encaissés de ' . $c['nom'] . '.');
    redirect('creances');
}

function historique(): void
{
    $u = Auth::requirePerm('creances');
    $c = Db::one('SELECT * FROM clients WHERE id = ?', [input_int('id')]);
    if (!$c || ($u['role'] !== 'proprietaire' && (int) $c['site_id'] !== (int) $u['site_id'])) not_found();
    $lignes = Db::all("SELECT at, numero AS ref, total AS debit, NULL AS credit, id AS vente_id FROM ventes WHERE client_id = ? AND mode = 'credit' AND statut = 'payee'
        UNION ALL SELECT at, note, NULL, montant, NULL FROM creance_paiements WHERE client_id = ? ORDER BY at", [$c['id'], $c['id']]);
    view('admin/creance-historique', ['title' => 'Historique · ' . $c['nom'], 'c' => $c, 'lignes' => $lignes, 'du' => du_client_total((int) $c['id'])]);
}
