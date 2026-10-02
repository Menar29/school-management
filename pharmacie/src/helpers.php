<?php
declare(strict_types=1);

// ---------- Sortie HTML ----------

function e($v): string
{
    return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Montant en FCFA avec espace fine insécable : 118 500 */
function fmt($n): string
{
    if ($n === null || $n === '') return '—';
    return number_format((float) $n, 0, ',', "\u{202F}");
}

/** Montant signé : + 63 000 / − 2 000 */
function fmt_signe(int $n): string
{
    if ($n === 0) return '0';
    return ($n < 0 ? '− ' : '+ ') . fmt(abs($n));
}

function url(string $route = '', array $params = []): string
{
    $q = $route !== '' ? ['r' => $route] + $params : $params;
    return 'index.php' . ($q ? '?' . http_build_query($q) : '');
}

function redirect(string $route = '', array $params = []): never
{
    header('Location: ' . url($route, $params));
    exit;
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function today(): string
{
    return date('Y-m-d');
}

const JOURS = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
const MOIS = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

/** Lundi 28 septembre 2026 */
function date_longue(?string $d = null): string
{
    $t = $d ? strtotime($d) : time();
    return ucfirst(JOURS[(int) date('w', $t)]) . ' ' . (int) date('j', $t) . ' ' . MOIS[(int) date('n', $t) - 1] . ' ' . date('Y', $t);
}

/** « Aujourd'hui 18:52 », « Hier 19:20 » ou « 25/09 · 18:30 » */
function quand(?string $dt): string
{
    if (!$dt) return '—';
    $t = strtotime($dt);
    $d = date('Y-m-d', $t);
    if ($d === today()) return "Aujourd'hui " . date('H:i', $t);
    if ($d === date('Y-m-d', strtotime('-1 day'))) return 'Hier ' . date('H:i', $t);
    return date('d/m', $t) . ' · ' . date('H:i', $t);
}

function date_fr(?string $d): string
{
    return $d ? date('d/m/Y', strtotime($d)) : '—';
}

/** Péremption au format MM/AAAA */
function mois_annee(?string $d): string
{
    return $d ? date('m/Y', strtotime($d)) : '—';
}

/** « 03/2028 » ou « 2028-03 » ou « 2028-03-31 » -> 2028-03-01 ; null si illisible */
function parse_peremption(?string $s): ?string
{
    $s = trim((string) $s);
    if (preg_match('#^(\d{1,2})[/.-](\d{4})$#', $s, $m)) return sprintf('%04d-%02d-01', $m[2], $m[1]);
    if (preg_match('#^(\d{4})-(\d{2})(-\d{2})?$#', $s, $m)) return sprintf('%04d-%02d-01', $m[1], $m[2]);
    if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $s, $m)) return sprintf('%04d-%02d-01', $m[3], $m[2]);
    return null;
}

/** Fin du mois de péremption : un lot « 08/2026 » est vendable jusqu'au 31/08/2026 */
function peremption_fin(?string $d): ?string
{
    return $d ? date('Y-m-t', strtotime($d)) : null;
}

function initiales(string $nom): string
{
    $p = preg_split('/\s+/', trim($nom));
    $s = count($p) > 1 ? mb_substr($p[0], 0, 1) . mb_substr($p[1], 0, 1) : mb_substr($p[0], 0, 2);
    return mb_strtoupper($s);
}

function prenom(string $nom): string
{
    return explode(' ', trim($nom))[0];
}

function norm(string $s): string
{
    $s = mb_strtolower($s);
    if (class_exists('Normalizer')) {
        $s = Normalizer::normalize($s, Normalizer::FORM_D);
        $s = preg_replace('/\p{Mn}/u', '', $s);
    }
    return $s;
}

// ---------- Requête ----------

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function input(string $k, $default = '')
{
    $v = $_POST[$k] ?? $_GET[$k] ?? $default;
    return is_string($v) ? trim($v) : $v;
}

function input_int(string $k, int $default = 0): int
{
    $v = input($k, null);
    if ($v === null || $v === '') return $default;
    return (int) preg_replace('/[^\d-]/', '', (string) $v);
}

function json_body(): array
{
    $d = json_decode(file_get_contents('php://input') ?: '[]', true);
    return is_array($d) ? $d : [];
}

function json_out($data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '';
}

// ---------- CSRF et messages ----------

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    $t = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($t) || !hash_equals(csrf_token(), $t)) {
        http_response_code(419);
        exit('Session expirée : rechargez la page et recommencez.');
    }
}

function flash(string $type, string $msg): void
{
    $_SESSION['flash'][] = [$type, $msg];
}

function flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

// ---------- Vues ----------

function view(string $name, array $data = [], ?string $layout = 'admin'): never
{
    extract($data, EXTR_SKIP);
    ob_start();
    require ROOT . '/views/' . $name . '.php';
    $content = ob_get_clean();
    if ($layout) {
        require ROOT . '/views/layout/' . $layout . '.php';
    } else {
        echo $content;
    }
    exit;
}

function not_found(string $msg = 'Page introuvable.'): never
{
    http_response_code(404);
    view('erreur', ['titre' => 'Introuvable', 'message' => $msg], Auth::user() ? 'admin' : 'auth');
}

function forbidden(string $msg = "Vous n'avez pas accès à cette page."): never
{
    http_response_code(403);
    view('erreur', ['titre' => 'Accès refusé', 'message' => $msg], Auth::user() ? 'admin' : 'auth');
}

const ICONS = [
    'dashboard'    => 'M4 4h7v7H4zM13 4h7v4h-7zM13 10h7v10h-7zM4 13h7v7H4z',
    'caisses'      => 'M3 7h16a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H3zM3 7V6a2 2 0 0 1 2-2h11M16 13h2',
    'creances'     => 'M6 3h12v18l-3-2-3 2-3-2-3 2zM9 8h6M9 12h6',
    'reappro'      => 'M20 11a8 8 0 0 0-14.9-4M4 3v4h4M4 13a8 8 0 0 0 14.9 4M20 21v-4h-4',
    'commandes'    => 'M9 3h6v3H9zM7 4.5H5v16h14v-16h-2M9 11h6M9 15h4',
    'receptions'   => 'M3 8l9-5 9 5v8l-9 5-9-5zM3 8l9 5 9-5M12 13v8',
    'fournisseurs' => 'M2 6h12v10H2zM14 9h4l3 3v4h-7M5 18a2 2 0 1 0 4 0a2 2 0 1 0-4 0M15 18a2 2 0 1 0 4 0a2 2 0 1 0-4 0',
    'stock'        => 'M12 3l9 5-9 5-9-5zM3 12.5l9 5 9-5M3 17l9 5 9-5',
    'produits'     => 'M10.5 3.5a5 5 0 0 1 7 7l-7 7a5 5 0 0 1-7-7zM7 7l7 7',
    'transferts'   => 'M4 8h14l-3-3M20 16H6l3 3',
    'utilisateurs' => 'M9 11a4 4 0 1 0 0-8a4 4 0 1 0 0 8M2 21a7 7 0 0 1 14 0M17 11a3 3 0 1 0 0-6M22 21a6 6 0 0 0-4-5.6',
    'droits'       => 'M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6zM9 12l2 2 4-4',
    'securite'     => 'M6 11h12v10H6zM8 11V8a4 4 0 0 1 8 0v3M12 15v2',
    'journal'      => 'M5 3h14v18H5zM9 7h6M9 11h6M9 15h4',
    'clients'      => 'M9 11a4 4 0 1 0 0-8a4 4 0 1 0 0 8M2 21a7 7 0 0 1 14 0',
    'caisse'       => 'M4 9h16v11H4zM7 9V5h10v4M8 13h2M12 13h2M8 16.5h8',
    'inventaire'   => 'M9 3h6v3H9zM7 4.5H5v16h14v-16h-2M9 12l2 2 4-4',
    'plus'         => 'M12 5v14M5 12h14',
    'chev'         => 'M9 5l7 7-7 7',
    'back'         => 'M15 5l-7 7 7 7',
    'search'       => 'M11 4a7 7 0 1 0 0 14a7 7 0 1 0 0-14M20 20l-4-4',
    'x'            => 'M6 6l12 12M18 6L6 18',
    'print'        => 'M7 9V3h10v6M7 17H4v-7h16v7h-3M7 14h10v7H7z',
    'check'        => 'M5 12.5l4.5 4.5L19 7.5',
    'alert'        => 'M12 8v5M12 16.5v0M10.3 3.9L2.4 18a2 2 0 0 0 1.7 3h15.8a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z',
    'lock'         => 'M6 11h12v10H6zM8 11V8a4 4 0 0 1 8 0v3',
    'out'          => 'M15 4h4v16h-4M10 8l-4 4 4 4M6 12h10',
    'menu'         => 'M4 7h16M4 12h16M4 17h16',
    'cross'        => 'M9.5 3h5v6.5H21v5h-6.5V21h-5v-6.5H3v-5h6.5z',
    'trash'        => 'M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13',
    'phone'        => 'M7 3h10v18H7zM11 18h2',
    'pc'           => 'M3 5h18v11H3zM8 20h8M12 16v4',
    'tablet'       => 'M5 3h14v18H5zM11 18h2',
];

function icon(string $name, string $class = 'ic'): string
{
    $d = ICONS[$name] ?? ICONS['chev'];
    if ($name === 'cross') {
        return '<svg class="' . e($class) . '" viewBox="0 0 24 24" aria-hidden="true" style="fill:currentColor;stroke:none"><path d="' . $d . '"/></svg>';
    }
    return '<svg class="' . e($class) . '" viewBox="0 0 24 24" aria-hidden="true"><path d="' . $d . '"/></svg>';
}

function pill(string $text, string $cls = 'mute'): string
{
    return '<span class="pill ' . e($cls) . '">' . e($text) . '</span>';
}

function site_tag(?array $site): string
{
    if (!$site) return '<span class="site">Tous les sites</span>';
    return '<span class="site ' . e($site['couleur']) . '">' . e($site['nom_court'] ?? $site['nom']) . '</span>';
}

// ---------- Paramètres, journal, sites ----------

function setting(string $k, $default = null)
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (Db::all('SELECT k, v FROM settings') as $r) $cache[$r['k']] = $r['v'];
    }
    return $cache[$k] ?? $default;
}

function setting_set(string $k, string $v): void
{
    Db::run('INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)', [$k, $v]);
}

function audit(string $action, string $details = '', ?int $siteId = null): void
{
    $u = Auth::user();
    Db::insert('audit_log', [
        'at'      => now(),
        'user_id' => $u['id'] ?? null,
        'site_id' => $siteId ?? ($u['site_id'] ?? null),
        'poste'   => Auth::posteNom(),
        'action'  => $action,
        'details' => $details,
    ]);
}

/** Tous les sites, indexés par id, avec un nom court (REMEDE, MADOBI, Magasin) */
function sites(): array
{
    static $s = null;
    if ($s === null) {
        $s = [];
        foreach (Db::all('SELECT * FROM sites WHERE actif = 1 ORDER BY type DESC, id') as $r) {
            $r['nom_court'] = $r['type'] === 'magasin' ? 'Magasin' : $r['code'];
            $s[(int) $r['id']] = $r;
        }
    }
    return $s;
}

function site(?int $id): ?array
{
    return $id ? (sites()[$id] ?? null) : null;
}

function officines(): array
{
    return array_filter(sites(), fn($s) => $s['type'] === 'officine');
}

/** Sites que l'utilisateur connecté peut consulter */
function sites_visibles(): array
{
    $u = Auth::user();
    if (!$u || $u['role'] === 'proprietaire') return sites();
    return array_filter(sites(), fn($s) => (int) $s['id'] === (int) $u['site_id']);
}

/** Filtre de site choisi dans l'écran (« all » ou un id), limité aux sites autorisés */
function site_filtre(string $param = 'site'): ?int
{
    $u = Auth::user();
    if ($u['role'] !== 'proprietaire') return (int) $u['site_id'];
    $v = input($param, 'all');
    return ($v === 'all' || $v === '' || !isset(sites()[(int) $v])) ? null : (int) $v;
}

function numero(string $prefix, int $id, int $pad = 4): string
{
    return $prefix . '-' . str_pad((string) $id, $pad, '0', STR_PAD_LEFT);
}

/** Sélecteur d'officine du propriétaire (« Les deux officines | REMEDE | MADOBI ») */
function site_seg(string $route, ?int $current, array $params = [], bool $avecMagasin = false, string $tous = 'Tous les sites'): string
{
    if (!Auth::isOwner()) return '';
    $opts = ['all' => $tous];
    foreach (sites() as $s) {
        if ($s['type'] === 'magasin' && !$avecMagasin) continue;
        $opts[(string) $s['id']] = $s['nom_court'];
    }
    $h = '<div class="seg" role="group" aria-label="Site affiché">';
    foreach ($opts as $k => $label) {
        $on = ($k === 'all' && $current === null) || (string) $current === $k;
        $h .= '<a class="' . ($on ? 'on' : '') . '" href="' . e(url($route, ['site' => $k] + $params)) . '" aria-current="' . ($on ? 'true' : 'false') . '" style="text-decoration:none;display:inline-flex;align-items:center;padding:0 14px;min-height:40px;border-radius:8px;font-weight:600;font-size:14px;color:var(--ink-2)' . ($on ? ';background:#fff;color:var(--ink);box-shadow:0 1px 3px rgba(19,28,24,.12)' : '') . '">' . e($label) . '</a>';
    }
    return $h . '</div>';
}

/** En-tête de page : titre, sous-titre, actions */
function page_header(string $title, string $sub = '', string $actions = '', string $crumb = ''): string
{
    return '<header class="top"><div class="flex" style="gap:12px;flex-wrap:nowrap;min-width:0"><button class="ibtn burger" data-open-menu aria-label="Menu">' . icon('menu') . '</button><div style="min-width:0">'
        . ($crumb ? '<div class="crumb">' . $crumb . '</div>' : '')
        . '<h1 class="h1">' . e($title) . '</h1>' . ($sub ? '<p class="small muted">' . $sub . '</p>' : '') . '</div></div>'
        . ($actions ? '<div class="acts">' . $actions . '</div>' : '') . '</header>';
}

function flash_html(): string
{
    $h = '';
    foreach (flashes() as [$t, $m]) $h .= '<div class="banner ' . e($t) . '" role="status">' . e($m) . '</div>';
    return $h ? '<div class="flashes">' . $h . '</div>' : '';
}

const STATUTS_CMD_R = ['creee' => 'Créée', 'envoyee' => 'Envoyée', 'partielle' => 'Livrée en partie'];
const STATUTS_CMD = [
    'proposee' => ['Proposée par le gérant', 'info'],
    'creee' => ['Créée', 'mute'],
    'envoyee' => ['Envoyée', 'info'],
    'partielle' => ['Livrée en partie', 'warn'],
    'livree' => ['Livrée', 'ok'],
    'annulee' => ['Annulée', 'mute'],
];

