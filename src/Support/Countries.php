<?php

declare(strict_types=1);

namespace Bladewell\Support;

/**
 * Country dial codes for <x-widget.phone>. Only codes: names come from the browser in the page's
 * language (Intl.DisplayNames) and flags are emoji built from the country code, so nothing else ships.
 * Countries sharing a code (+1, +7, +44…) are listed under it; Caribbean +1 countries under their area code.
 */
final class Countries
{
    /** @var array<string, string> ISO 3166 alpha-2 => dial code, without the + */
    public const array DIAL_CODES = [
        'AD' => '376', 'AE' => '971', 'AF' => '93', 'AG' => '1268', 'AI' => '1264', 'AL' => '355', 'AM' => '374', 'AO' => '244',
        'AR' => '54', 'AS' => '1684', 'AT' => '43', 'AU' => '61', 'AW' => '297', 'AX' => '358', 'AZ' => '994', 'BA' => '387',
        'BB' => '1246', 'BD' => '880', 'BE' => '32', 'BF' => '226', 'BG' => '359', 'BH' => '973', 'BI' => '257', 'BJ' => '229',
        'BL' => '590', 'BM' => '1441', 'BN' => '673', 'BO' => '591', 'BQ' => '599', 'BR' => '55', 'BS' => '1242', 'BT' => '975',
        'BW' => '267', 'BY' => '375', 'BZ' => '501', 'CA' => '1', 'CC' => '61', 'CD' => '243', 'CF' => '236', 'CG' => '242',
        'CH' => '41', 'CI' => '225', 'CK' => '682', 'CL' => '56', 'CM' => '237', 'CN' => '86', 'CO' => '57', 'CR' => '506',
        'CU' => '53', 'CV' => '238', 'CW' => '599', 'CX' => '61', 'CY' => '357', 'CZ' => '420', 'DE' => '49', 'DJ' => '253',
        'DK' => '45', 'DM' => '1767', 'DO' => '1809', 'DZ' => '213', 'EC' => '593', 'EE' => '372', 'EG' => '20', 'EH' => '212',
        'ER' => '291', 'ES' => '34', 'ET' => '251', 'FI' => '358', 'FJ' => '679', 'FK' => '500', 'FM' => '691', 'FO' => '298',
        'FR' => '33', 'GA' => '241', 'GB' => '44', 'GD' => '1473', 'GE' => '995', 'GF' => '594', 'GG' => '44', 'GH' => '233',
        'GI' => '350', 'GL' => '299', 'GM' => '220', 'GN' => '224', 'GP' => '590', 'GQ' => '240', 'GR' => '30', 'GT' => '502',
        'GU' => '1671', 'GW' => '245', 'GY' => '592', 'HK' => '852', 'HN' => '504', 'HR' => '385', 'HT' => '509', 'HU' => '36',
        'ID' => '62', 'IE' => '353', 'IL' => '972', 'IM' => '44', 'IN' => '91', 'IO' => '246', 'IQ' => '964', 'IR' => '98',
        'IS' => '354', 'IT' => '39', 'JE' => '44', 'JM' => '1876', 'JO' => '962', 'JP' => '81', 'KE' => '254', 'KG' => '996',
        'KH' => '855', 'KI' => '686', 'KM' => '269', 'KN' => '1869', 'KP' => '850', 'KR' => '82', 'KW' => '965', 'KY' => '1345',
        'KZ' => '7', 'LA' => '856', 'LB' => '961', 'LC' => '1758', 'LI' => '423', 'LK' => '94', 'LR' => '231', 'LS' => '266',
        'LT' => '370', 'LU' => '352', 'LV' => '371', 'LY' => '218', 'MA' => '212', 'MC' => '377', 'MD' => '373', 'ME' => '382',
        'MF' => '590', 'MG' => '261', 'MH' => '692', 'MK' => '389', 'ML' => '223', 'MM' => '95', 'MN' => '976', 'MO' => '853',
        'MP' => '1670', 'MQ' => '596', 'MR' => '222', 'MS' => '1664', 'MT' => '356', 'MU' => '230', 'MV' => '960', 'MW' => '265',
        'MX' => '52', 'MY' => '60', 'MZ' => '258', 'NA' => '264', 'NC' => '687', 'NE' => '227', 'NF' => '672', 'NG' => '234',
        'NI' => '505', 'NL' => '31', 'NO' => '47', 'NP' => '977', 'NR' => '674', 'NU' => '683', 'NZ' => '64', 'OM' => '968',
        'PA' => '507', 'PE' => '51', 'PF' => '689', 'PG' => '675', 'PH' => '63', 'PK' => '92', 'PL' => '48', 'PM' => '508',
        'PR' => '1787', 'PS' => '970', 'PT' => '351', 'PW' => '680', 'PY' => '595', 'QA' => '974', 'RE' => '262', 'RO' => '40',
        'RS' => '381', 'RU' => '7', 'RW' => '250', 'SA' => '966', 'SB' => '677', 'SC' => '248', 'SD' => '249', 'SE' => '46',
        'SG' => '65', 'SH' => '290', 'SI' => '386', 'SJ' => '47', 'SK' => '421', 'SL' => '232', 'SM' => '378', 'SN' => '221',
        'SO' => '252', 'SR' => '597', 'SS' => '211', 'ST' => '239', 'SV' => '503', 'SX' => '1721', 'SY' => '963', 'SZ' => '268',
        'TC' => '1649', 'TD' => '235', 'TG' => '228', 'TH' => '66', 'TJ' => '992', 'TK' => '690', 'TL' => '670', 'TM' => '993',
        'TN' => '216', 'TO' => '676', 'TR' => '90', 'TT' => '1868', 'TV' => '688', 'TW' => '886', 'TZ' => '255', 'UA' => '380',
        'UG' => '256', 'US' => '1', 'UY' => '598', 'UZ' => '998', 'VA' => '39', 'VC' => '1784', 'VE' => '58', 'VG' => '1284',
        'VI' => '1340', 'VN' => '84', 'VU' => '678', 'WF' => '681', 'WS' => '685', 'XK' => '383', 'YE' => '967', 'YT' => '262',
        'ZA' => '27', 'ZM' => '260', 'ZW' => '263',
    ];

    /**
     * Where most numbers keep their leading 0 inside the international form too (Italy and its enclaves);
     * everywhere else a national 012… becomes +60 12….
     */
    public const array KEEPS_LEADING_ZERO = ['IT', 'SM', 'VA'];

    /** Who a shared code means when nothing else decides: +1 is the US, not Canada; +44 the UK, not Jersey. */
    public const array MAIN_COUNTRY = ['US', 'RU', 'GB', 'NO', 'AU', 'RE', 'GP', 'CW', 'MA', 'FI', 'IT'];

    /**
     * The table with each shared code's main country first, so "first match wins" picks it on a tie.
     *
     * @return array<string, string>
     */
    public static function ordered(): array
    {
        return array_merge(array_intersect_key(self::DIAL_CODES, array_flip(self::MAIN_COUNTRY)), self::DIAL_CODES);
    }

    /** "US:1,…,MY:60,SG:65,…" for the browser, a couple of KB, main countries first like ordered(). */
    public static function compact(): string
    {
        $ordered = self::ordered();

        return implode(',', array_map(static fn (string $iso, string $dial): string => "{$iso}:{$dial}", array_keys($ordered), $ordered));
    }

    /**
     * The country to start on: the one asked for, else the region of the locale (en_MY → MY), else the US.
     */
    public static function guess(?string $country = null, ?string $locale = null): string
    {
        $country = strtoupper((string) $country);
        if (isset(self::DIAL_CODES[$country])) {
            return $country;
        }
        $region = class_exists(\Locale::class) ? strtoupper((string) \Locale::getRegion(str_replace('-', '_', $locale ?? app()->getLocale()))) : '';

        return isset(self::DIAL_CODES[$region]) ? $region : 'US';
    }

    /**
     * Splits a stored international number (+60123456789) into its country and national digits. The
     * longest matching code wins; on a shared code, $preferred when it matches, else the main country.
     *
     * @return array{0: string, 1: string} [country, national digits]
     */
    public static function split(?string $number, string $preferred): array
    {
        $digits = preg_replace('/\D/', '', (string) $number) ?? '';
        if ($number === null || ! str_starts_with(trim($number), '+') || $digits === '') {
            return [$preferred, $digits];
        }

        $best = null;
        foreach (self::ordered() as $iso => $dial) {
            if (! str_starts_with($digits, $dial)) {
                continue;
            }
            $bestDial = $best !== null ? self::DIAL_CODES[$best] : '';
            if ($best === null || strlen($dial) > strlen($bestDial) || (strlen($dial) === strlen($bestDial) && $iso === $preferred)) {
                $best = $iso;
            }
        }

        return $best === null ? [$preferred, $digits] : [$best, substr($digits, strlen(self::DIAL_CODES[$best]))];
    }

    /** National digits as they are dialled (0123456789) → +60123456789. Empty stays empty. */
    public static function international(string $country, string $national): string
    {
        $digits = preg_replace('/\D/', '', $national) ?? '';
        if ($digits === '') {
            return '';
        }
        // Without the prefix dialled at home before an area code: a 0 almost everywhere, 8 in Russia and Kazakhstan
        // (8 912 345 67 89, eleven digits; ten, as in 812 for St Petersburg, have none), 06 in Hungary. The same as
        // resources/js/widget/phone.
        $digits = match (true) {
            in_array($country, self::KEEPS_LEADING_ZERO, true) => $digits,
            in_array($country, ['RU', 'KZ'], true) && strlen($digits) === 11 && str_starts_with($digits, '8') => substr($digits, 1),
            $country === 'HU' && str_starts_with($digits, '06') => substr($digits, 2),
            default => preg_replace('/^0/', '', $digits) ?? $digits,
        };

        return '+'.self::DIAL_CODES[$country].$digits;
    }
}
