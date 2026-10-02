<?php

namespace App\Services;

/**
 * Canonicalises the gender dimension and infers it from a file title.
 *
 * Two jobs, deliberately kept in one place:
 *
 *  1. normalize()  — three write paths currently store three vocabularies for one
 *     dimension: indexing allows Male|Female|Corporate|Joint, commissioning adds
 *     Government, RecertificationController writes lowercase male|female. This
 *     collapses all of them onto the indexing vocabulary.
 *
 *  2. classify()   — infers gender from a holder name. Used by gender:backfill to
 *     fill the 133,773 legacy rows that predate the 2026-07-28 gender columns.
 *
 * PRECISION OVER RECALL. Every rule here is one a Kano registry clerk would apply
 * without hesitation. Gender-neutral honorifics (Dr, Engr, Prof, Barr, Hon, Chief)
 * are matched only so they are explicitly NOT treated as male — the common failure
 * mode for this kind of inference. Bare given names are never guessed from: a name
 * dictionary would lift coverage a long way past 30% but would bake an unmeasurable
 * error rate into a statistic whose headline finding is a 3-7% female share.
 *
 * Anything inferred must be stored with gender_source so it can be told apart from
 * captured data, and reversed. See docs/prs-2025/20-live-data-implementation.md §2.
 */
class GenderNormalizer
{
    /**
     * The client's four values, and the only ones that may be stored.
     * Government bodies fold into Corporate — there is no fifth value.
     */
    public const MALE      = 'Male';
    public const FEMALE    = 'Female';
    public const CORPORATE = 'Corporate';
    public const JOINT     = 'Joint';

    public const CANON = [self::MALE, self::FEMALE, self::CORPORATE, self::JOINT];

    public const SOURCE_CAPTURED     = 'captured';
    public const SOURCE_OSS          = 'oss_sex';
    public const SOURCE_PAIR         = 'pair';
    public const SOURCE_HONORIFIC    = 'honorific';
    public const SOURCE_ORGANISATION = 'organisation';
    public const SOURCE_JOINT        = 'joint';

    /**
     * Male honorifics. Word-anchored, so ALH matches "ALH." and "ALH YAKUBU" but
     * never "ALHASSAN", which is a given name and appears 1,526 times as one.
     */
    private const MALE_TITLES = [
        'ALHAJI', 'ALHAJJI', 'ALHAJ', 'ALH', 'ALHAJI\'S',
        'MALLAM', 'MALAM', 'MALLAN', 'MAL',
        'MR', 'MISTER', 'SARKI', 'SARKIN', 'SHEIKH', 'SHEHUN', 'IMAM', 'USTAZ',
    ];

    /** Female honorifics. */
    private const FEMALE_TITLES = [
        'HAJIYA', 'HAJIA', 'HAJJIYA', 'HAJJIA', 'HAJARIYA',
        'MRS', 'MISS', 'MS', 'MADAM', 'MALLAMA', 'MALAMA',
    ];

    /**
     * Titles that carry status, not sex. Listed so they are skipped rather than
     * silently swept into Male — 1,347 rows lead with Dr alone.
     */
    private const NEUTRAL_TITLES = [
        'DR', 'PROF', 'PROFESSOR', 'ENGR', 'ENG', 'ARC', 'ARCH', 'BARR', 'HON',
        'CHIEF', 'LATE', 'SIR', 'AMB', 'CAPT', 'GEN', 'COL', 'MAJ', 'PHARM', 'SURV',
    ];

    /** Public bodies. Checked before company markers — "Kano State University" has no Ltd. */
    private const GOVERNMENT_MARKERS = [
        'MINISTRY', 'GOVERNMENT', 'GOVT', 'KANO STATE', 'FEDERAL', 'LOCAL GOVT',
        'AUTHORITY', 'AGENCY', 'COMMISSION', 'PARASTATAL', 'KAMMA', 'KASCO',
        'UNIVERSITY', 'POLYTECHNIC', 'COLLEGE', 'EMIRATE', 'COUNCIL',
    ];

    /** Company / institution markers. */
    private const ORGANISATION_MARKERS = [
        'LTD', 'LIMITED', 'PLC', 'NIG LTD', 'NIGERIA LIMITED', 'INC', 'INCORPORATED',
        'ENTERPRISE', 'ENTERPRISES', 'COMPANY', 'VENTURE', 'VENTURES', 'INVESTMENT',
        'INVESTMENTS', 'GLOBAL', 'INTERNATIONAL', 'RESOURCE', 'RESOURCES', 'SERVICES',
        '& SONS', 'AND SONS', 'CONSTRUCTION', 'HOLDINGS', 'ASSOCIATES', 'ASSOCIATION',
        'FOUNDATION', 'SOCIETY', 'COOPERATIVE', 'CO-OPERATIVE', 'UNION', 'SCHOOL',
        'ACADEMY', 'HOSPITAL', 'CLINIC', 'MOSQUE', 'CHURCH', 'TRADING', 'STORES',
        'FARMS', 'HOMES', 'PROPERTIES', 'PROPERTY', 'DEVELOPMENT', 'DEVELOPERS',
        'CONSULT', 'CONSULTANTS', 'CONSULTING', 'INDUSTRIES', 'INDUSTRIAL', 'MANUFACTURING',
        'PETROLEUM', 'ENERGY', 'ESTATE LTD', 'ESTATES', 'GROUP', 'BANK', 'INSURANCE',
        'TRUSTEES', 'ENGINEERING', 'CONTRACTORS', 'MERCHANTS', 'HOTEL', 'PHARMACY',
        'MOTORS', 'TEXTILE', 'INSTITUTE', 'CENTRE', 'PROJECTS', 'ORGANISATION',
        'ORGANIZATION', 'EDUCATIONAL', 'ISLAMIYYA', 'MAKARANTA',
    ];

    /** Connectors that can join two parties in one title. */
    private const JOINT_CONNECTORS = [' AND ', ' & ', '/'];

    /**
     * Fold any stored gender value onto the canonical vocabulary.
     * Returns null for blanks and for values that mean "unknown".
     */
    public function normalize(?string $raw): ?string
    {
        $v = strtoupper(trim((string) $raw));

        if ($v === '' || in_array($v, ['UNKNOWN', 'N/A', 'NA', 'NOT RECORDED', 'SELECT GENDER'], true)) {
            return null;
        }

        return match (true) {
            in_array($v, ['M', 'MALE'], true)                        => self::MALE,
            in_array($v, ['F', 'FEMALE'], true)                      => self::FEMALE,
            in_array($v, ['CORPORATE', 'COMPANY', 'ORGANISATION',
                          'ORGANIZATION', 'ORG'], true)              => self::CORPORATE,
            in_array($v, ['JOINT', 'MULTIPLE'], true)                => self::JOINT,
            in_array($v, ['GOVERNMENT', 'GOVT', 'GOVERNMENTAL'], true) => self::CORPORATE,
            default                                                  => null,
        };
    }

    /**
     * Infer gender from a holder name.
     *
     * @return array{0: string, 1: string}|null  [gender, source], or null if undecidable.
     */
    public function classify(?string $name): ?array
    {
        $n = $this->canonicalise($name);

        if ($n === '') {
            return null;
        }

        // 1. Public bodies and companies first. "Alhaji Musa & Sons Ltd" is a company,
        //    not a man, and the honorific would otherwise win. Both land on Corporate:
        //    the client's vocabulary has four values and Government is not one of them.
        if ($this->containsAny($n, self::GOVERNMENT_MARKERS)
            || $this->containsAny($n, self::ORGANISATION_MARKERS)) {
            return [self::CORPORATE, self::SOURCE_ORGANISATION];
        }

        $male   = $this->countTitles($n, self::MALE_TITLES);
        $female = $this->countTitles($n, self::FEMALE_TITLES);

        if ($male === 0 && $female === 0) {
            // A status title with no sex marker, or a bare given name. Not guessed.
            return null;
        }

        // 2. Two named parties. Only a connector makes a title joint. Without one,
        //    a second title is almost always patronymic — "Yakubu Alhaji Wada" is
        //    Yakubu son of Alhaji Wada, one man, not two.
        if (($male + $female) > 1 && $this->hasConnector($n)) {
            return [self::JOINT, self::SOURCE_JOINT];
        }

        // 3. The leading title is the holder's own; anything later belongs to a
        //    parent. "Hafsatu Alhaji Musa" is a woman, and counting titles alone
        //    would call her male.
        $leading = $this->leadingTitle($n);

        if ($leading === self::MALE || $leading === self::FEMALE) {
            return [$leading, self::SOURCE_HONORIFIC];
        }

        // 4. Mid-name title only, so it is the father's: "Hafsatu Alhaji Musa" is a
        //    woman named after a man. Declining costs ~3.7% of honorific hits and
        //    removes the one error class that misreads women as men — which is the
        //    exact bias the 3-7% female share finding cannot absorb.
        return null;
    }

    /** Upper-case, strip punctuation that breaks word anchoring, collapse whitespace. */
    private function canonicalise(?string $name): string
    {
        $n = strtoupper(trim((string) $name));
        $n = str_replace(['.', ',', "'", '"', '(', ')', '-'], ' ', $n);
        $n = preg_replace('/\s+/', ' ', $n);

        return trim((string) $n);
    }

    private function containsAny(string $haystack, array $needles): bool
    {
        $padded = ' ' . $haystack . ' ';

        foreach ($needles as $needle) {
            if (str_contains($padded, ' ' . $needle . ' ')) {
                return true;
            }
        }

        return false;
    }

    /** Count word-anchored title matches, ignoring the gender-neutral ones entirely. */
    private function countTitles(string $name, array $titles): int
    {
        $count  = 0;
        $tokens = explode(' ', $name);

        foreach ($tokens as $token) {
            if ($token === '' || in_array($token, self::NEUTRAL_TITLES, true)) {
                continue;
            }

            if (in_array($token, $titles, true)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Gender of the first token, skipping status titles so "Dr Hajiya Binta"
     * still reads as female. Returns null when the name does not open with one.
     */
    private function leadingTitle(string $name): ?string
    {
        foreach (explode(' ', $name) as $token) {
            if ($token === '' || in_array($token, self::NEUTRAL_TITLES, true)) {
                continue;
            }

            if (in_array($token, self::MALE_TITLES, true)) {
                return self::MALE;
            }

            if (in_array($token, self::FEMALE_TITLES, true)) {
                return self::FEMALE;
            }

            return null;
        }

        return null;
    }

    private function hasConnector(string $name): bool
    {
        foreach (self::JOINT_CONNECTORS as $connector) {
            if (str_contains($name, trim($connector) === '/' ? '/' : $connector)) {
                return true;
            }
        }

        return false;
    }
}
