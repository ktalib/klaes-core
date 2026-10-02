<?php

namespace App\Enums;

/**
 * Every printable KLAES document that carries a QR code.
 *
 * This is deliberately an enum and not a `document_types` table: each type
 * needs a resolver class regardless, and a table listing types that the code
 * must also enumerate is two sources of truth that disagree the first time
 * somebody inserts a row without writing the resolver. A missing resolver
 * should be a deploy-time error, not a runtime "unknown document type".
 *
 * `code()` is the single byte written into the QR token, so the numbers are
 * part of the wire format — NEVER renumber an existing case. Add new types
 * with the next free number.
 */
enum DocumentType: string
{
    // Information Products
    case ROFO           = 'ROFO';
    case OP             = 'OP';
    case COFO           = 'COFO';
    case SITE_PLAN      = 'SITE_PLAN';

    // Deeds Registration
    case RDS            = 'RDS';
    case COR            = 'COR';
    case DEEDS_BILL     = 'DEEDS_BILL';
    case DEEDS_BALANCE  = 'DEEDS_BALANCE';

    // Registry & Workflow
    case ST             = 'ST';
    case SLTR           = 'SLTR';
    case RECOMMENDATION = 'RECOMMENDATION';
    case TRACKING_SHEET = 'TRACKING_SHEET';
    case COMMISSIONING  = 'COMMISSIONING';
    case CONFIRMATION   = 'CONFIRMATION';

    /**
     * Wire-format code. Part of the token bytes — do not renumber.
     */
    public function code(): int
    {
        return match ($this) {
            self::ROFO           => 1,
            self::OP             => 2,
            self::COFO           => 3,
            self::SITE_PLAN      => 4,
            self::RDS            => 5,
            self::COR            => 6,
            self::DEEDS_BILL     => 7,
            self::DEEDS_BALANCE  => 8,
            self::ST             => 9,
            self::SLTR           => 10,
            self::RECOMMENDATION => 11,
            self::TRACKING_SHEET => 12,
            self::COMMISSIONING  => 13,
            self::CONFIRMATION   => 14,
        };
    }

    public static function fromCode(int $code): ?self
    {
        foreach (self::cases() as $case) {
            if ($case->code() === $code) {
                return $case;
            }
        }

        return null;
    }

    public function label(): string
    {
        return match ($this) {
            self::ROFO           => 'Letter of Grant / RofO',
            self::OP             => 'Occupancy Permit (OP)',
            self::COFO           => 'Certificate of Occupancy',
            self::SITE_PLAN      => 'Site / Parcel Plan',
            self::RDS            => 'RDS',
            self::COR            => 'Certificate of Registration',
            self::DEEDS_BILL     => 'Deeds Bill',
            self::DEEDS_BALANCE  => 'Deeds Balance',
            self::ST             => 'Sectional Title (ST)',
            self::SLTR           => 'SLTR',
            self::RECOMMENDATION => 'Land Recommendation',
            self::TRACKING_SHEET => 'Tracking Sheet',
            self::COMMISSIONING  => 'File Commissioning Sheet',
            self::CONFIRMATION   => 'Confirmation Sheet',
        };
    }

    /**
     * Console grouping — mirrors the category tabs on the verification page.
     */
    public function group(): string
    {
        return match ($this) {
            self::ROFO, self::OP, self::COFO, self::SITE_PLAN => 'ip',
            self::RDS, self::COR, self::DEEDS_BILL, self::DEEDS_BALANCE => 'deeds',
            default => 'registry',
        };
    }

    /**
     * Where this document's tracking ID comes from.
     *
     * ST is the exception that matters: ST files have NO grouping table — their
     * tracking ID is auto-generated when the file is commissioned. Everywhere
     * else "no grouping row" is a verification failure, so if ST inherits the
     * generic path every ST document in the Ministry verifies as broken.
     */
    public function trackingIdSource(): string
    {
        return match ($this) {
            self::ST             => 'commissioning',
            self::TRACKING_SHEET => 'file_tracker',
            self::SITE_PLAN      => 'none',
            default              => 'grouping',
        };
    }
}
