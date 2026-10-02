<?php
declare(strict_types=1);

/** Calculs sur les sessions de caisse, partagés entre l'espace vendeur et l'espace propriétaire */
final class Caisse
{
    public const COUPURES = [10000, 5000, 2000, 1000, 500];

    public static function sessionOuverte(int $userId): ?array
    {
        return Db::one("SELECT * FROM caisse_sessions WHERE user_id = ? AND statut = 'ouverte' ORDER BY id DESC LIMIT 1", [$userId]);
    }

    /** Ventes espèces, crédit, dépenses, remises et montant attendu d'une session */
    public static function resume(int $sessionId): array
    {
        $s = Db::one('SELECT * FROM caisse_sessions WHERE id = ?', [$sessionId]);
        $v = Db::one("SELECT
                COALESCE(SUM(CASE WHEN statut = 'payee' AND mode = 'especes' THEN total END), 0) AS especes,
                COALESCE(SUM(CASE WHEN statut = 'payee' AND mode = 'credit' THEN total END), 0) AS credit,
                COALESCE(SUM(CASE WHEN statut = 'payee' THEN remise END), 0) AS remises,
                SUM(statut = 'payee') AS nb, SUM(statut = 'annulee') AS annulees
            FROM ventes WHERE session_id = ?", [$sessionId]);
        $dep = (int) Db::val('SELECT COALESCE(SUM(montant), 0) FROM depenses WHERE session_id = ?', [$sessionId]);
        $depenses = Db::all('SELECT * FROM depenses WHERE session_id = ? ORDER BY at', [$sessionId]);
        $attendu = (int) $s['fond'] + (int) $v['especes'] - $dep;
        return [
            'session' => $s,
            'fond' => (int) $s['fond'],
            'especes' => (int) $v['especes'],
            'credit' => (int) $v['credit'],
            'remises' => (int) $v['remises'],
            'nb' => (int) $v['nb'],
            'annulees' => (int) $v['annulees'],
            'depenses' => $dep,
            'liste_depenses' => $depenses,
            'attendu' => $s['statut'] === 'ouverte' ? $attendu : (int) $s['attendu'],
        ];
    }

    public static function statut(array $s): array
    {
        return match ($s['statut']) {
            'ouverte' => ['Ouverte', 'info'],
            'validee' => ['Validée', 'ok'],
            'precisions' => ['Précisions demandées', 'warn'],
            default => (int) $s['ecart'] !== 0 ? ['À examiner', 'bad'] : ['À valider', 'warn'],
        };
    }
}
