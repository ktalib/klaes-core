<?php

namespace App\Services\Prs\Support;

/**
 * Canonical mortgagee names for the bank facility ranking (section 04).
 *
 * pra.Mortgagee is free text and fragments badly. Measured 2026-08-02, one bank
 * across four spellings:
 *
 *   BANK OF THE NORTH LIMITED   377
 *   BANK OF THE NORTH LTD        87
 *   BANK OF THE NORTH LIMITED.   64
 *   BANK OF THE NORTH            61   -> 589 rows, ranked as four separate banks
 *
 * The same happens to UBA, First Bank, Union Bank, GTB, Afribank and Kano
 * Co-operative. A naive GROUP BY understates every major lender and produces a
 * ranking that is simply wrong.
 *
 * Two stages: strip the legal suffix and punctuation, then fold known aliases.
 * Anything unrecognised keeps its cleaned name rather than being bucketed into
 * "Other" — an unranked bank should still be visible, per audit fix #7.
 */
class BankNameNormalizer
{
    /** Legal-form suffixes that carry no identity. */
    private const SUFFIXES = [
        'PLC', 'LTD', 'LIMITED', 'INC', 'NIG', 'NIGERIA', 'OF NIGERIA',
        'NIGERIA PLC', 'NIGERIA LTD', 'NIGERIA LIMITED', 'INTERNATIONAL',
    ];

    /**
     * Aliases folded onto one canonical name. Keys are already suffix-stripped.
     * Historical names are kept distinct from their successors on purpose: a
     * 1991 Bank of the North mortgage is not a Unity Bank mortgage, and merging
     * them would rewrite history the register actually recorded.
     */
    private const ALIASES = [
        'UNITED BANK FOR AFRICA'          => 'United Bank for Africa',
        'UBA'                             => 'United Bank for Africa',
        'FIRST BANK'                      => 'First Bank of Nigeria',
        'FIRST BANK FOR'                  => 'First Bank of Nigeria',
        'UNION BANK'                      => 'Union Bank of Nigeria',
        'GUARANTY TRUST BANK'             => 'Guaranty Trust Bank',
        'GTBANK'                          => 'Guaranty Trust Bank',
        'GT BANK'                         => 'Guaranty Trust Bank',
        'JAIZ BANK'                       => 'Jaiz Bank',
        'JAIZ'                            => 'Jaiz Bank',
        'FIDELITY BANK'                   => 'Fidelity Bank',
        'FIDELITY'                        => 'Fidelity Bank',
        'UNITY BANK'                      => 'Unity Bank',
        'BANK OF THE NORTH'               => 'Bank of the North',
        'FEDERAL MORTGAGE BANK'           => 'Federal Mortgage Bank of Nigeria',
        'FEDERAL MORTGAGE BANK OF NIGERIA' => 'Federal Mortgage Bank of Nigeria',
        'FMBN'                            => 'Federal Mortgage Bank of Nigeria',
        'KANO CO OPERATIVE BANK'          => 'Kano Co-operative Bank',
        'KANO COOPERATIVE BANK'           => 'Kano Co-operative Bank',
        'AFRIBANK'                        => 'Afribank Nigeria',
        'AFRICAN INTERNATIONAL BANK'      => 'African International Bank',
        'SAVANNAH BANK'                   => 'Savannah Bank of Nigeria',
        'TROPICAL COMMERCIAL BANK'        => 'Tropical Commercial Bank',
        'DIAMOND BANK'                    => 'Diamond Bank',
        'SKYE BANK'                       => 'Skye Bank',
        'HABIB NIGERIA BANK'              => 'Habib Nigeria Bank',
        'HABIB BANK'                      => 'Habib Nigeria Bank',
        'NIGERIA ARAB BANK'               => 'Nigeria Arab Bank',
        'INTERNATIONAL BANK FOR WEST AFRICA' => 'International Bank for West Africa',
        'BARCLAYS BANK'                   => 'Barclays Bank of Nigeria',
        'ZENITH BANK'                     => 'Zenith Bank',
        'ACCESS BANK'                     => 'Access Bank',
        'STERLING BANK'                   => 'Sterling Bank',
        'ECOBANK'                         => 'Ecobank Nigeria',
        'KEYSTONE BANK'                   => 'Keystone Bank',
        'WEMA BANK'                       => 'Wema Bank',
        'STANBIC IBTC'                    => 'Stanbic IBTC Bank',
        'POLARIS BANK'                    => 'Polaris Bank',
        'TAJ BANK'                        => 'TAJ Bank',
    ];

    /**
     * Is this mortgagee a lending institution rather than a private person?
     *
     * Mortgagee is not always a bank. Measured on 2026 registrations, the raw
     * ranking returned "Alh Hamisu Sule Garo" and "Nathaniel O Onah" alongside
     * Jaiz and Fidelity — private mortgages, where an individual holds the
     * security. PRS's section 04 ranks *banks by facility*, so private lenders
     * are counted and reported separately rather than ranked among institutions
     * or silently dropped.
     */
    public function isInstitution(?string $canonical): bool
    {
        $v = strtoupper((string) $canonical);

        if ($v === '') {
            return false;
        }

        if (in_array($canonical, array_values(self::ALIASES), true)) {
            return true;
        }

        foreach (['BANK', 'MORTGAGE', 'FINANCE', 'MICROFINANCE', 'BUILDING SOCIETY',
                  'CO-OPERATIVE', 'COOPERATIVE', 'INSURANCE', 'TRUST', 'CAPITAL',
                  'PLC', 'LEASING', 'CREDIT'] as $marker) {
            if (str_contains($v, $marker)) {
                return true;
            }
        }

        return false;
    }

    public function normalize(?string $raw): ?string
    {
        $v = strtoupper(trim((string) $raw));

        if ($v === '') {
            return null;
        }

        // Punctuation, then collapse whitespace: "UNITED BANK FOR AFRICA LTD." and
        // "UNITED  BANK FOR AFRICA LIMITED" must reduce to the same key.
        $v = str_replace(['.', ',', '(', ')', '-', "'"], ' ', $v);
        $v = trim((string) preg_replace('/\s+/', ' ', $v));

        // Strip trailing legal forms repeatedly — "NIG LTD" is two of them.
        $changed = true;
        while ($changed) {
            $changed = false;
            foreach (self::SUFFIXES as $suffix) {
                if (str_ends_with($v, ' ' . $suffix)) {
                    $v       = trim(substr($v, 0, -strlen($suffix) - 1));
                    $changed = true;
                }
            }
        }

        if ($v === '') {
            return null;
        }

        if (isset(self::ALIASES[$v])) {
            return self::ALIASES[$v];
        }

        // Longest-prefix match, so "JAIZ BANK OF NIGERIA" still lands on Jaiz Bank.
        $best = null;
        foreach (self::ALIASES as $key => $canonical) {
            if (str_starts_with($v, $key) && (!$best || strlen($key) > strlen($best))) {
                $best = $key;
            }
        }

        if ($best !== null) {
            return self::ALIASES[$best];
        }

        // Unrecognised: keep it, title-cased. Never bucketed into "Other" — an
        // absent or unranked lender must stay visible in the ranking.
        return ucwords(strtolower($v));
    }
}
