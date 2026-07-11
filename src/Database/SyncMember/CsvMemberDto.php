<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Database\SyncMember;

use Markocupic\SacEventToolBundle\String\Formatter\PhoneNumberFormatter;

final readonly class CsvMemberDto
{
    public function __construct(
        public int $sacMemberId,
        public string $username,
        /**
         * @var array<string>
         */
        public array $sectionId,
        public string $firstname,
        public string $lastname,
        public string $addressExtra,
        public string $street,
        public string $poBox,
        public string $postal,
        public string $city,
        public string $country,
        public string $dateOfBirth,
        public string $phone,
        public string $mobile,
        public string $email,
        public string $gender,
        public string $profession,
        public string $language,
        public string $entryYear,
        public int $honoraryMember,
        public string $membershipType,
        public string $sectionInfo1,
        public string $sectionInfo2,
        public string $sectionInfo3,
        public string $debit,
    ) {
    }

    /**
     * Parses a single SAC member CSV export line (Hitobito "sac_mitglieder" layout)
     * into a normalized CsvMemberDto. Values are trimmed and converted from
     * ISO-8859-1 to UTF-8 before mapping.
     *
     * Column index => target property (only the columns actually consumed are listed):
     *   0 id => sacMemberId, username
     *   1 layer_id_padded => sectionId (leading zeros stripped)
     *   2 last_name => lastname
     *   3 first_name => firstname
     *   4 adresszusatz => addressExtra
     *   5 address => street
     *   6 postfach => poBox
     *   7 zip_code => postal
     *   8 town => city
     *   9 country => country (falls back to $defaultCountry when empty)
     *   10 birthday => dateOfBirth (unix timestamp as string, '' when empty)
     *   12 phone_number_landline => phone
     *   14 phone_number_mobile => mobile
     *   16 email => email
     *   17 gender => gender (Weiblich=female, Männlich=male, else other)
     *   19 language => language ('d' maps to $defaultLocale)
     *   20 eintrittsjahr => entryYear
     *   22 ehrenmitglied => honoraryMember (Yes => 1, else 0)
     *   23 beitragskategorie => membershipType
     *   24 s_info_1 => sectionInfo1
     *   25 s_info_2 => sectionInfo2
     *   26 s_info_3 => sectionInfo3
     *   27 bemerkungen => profession (actually holds the profession)
     *   28 saldo => debit
     *
     * @see https://github.com/hitobito/hitobito_sac_cas/blob/8cc963b20ddf746e746be1d8afe13e81083c2217/app/domain/export/tabular/people/sac_mitglieder.rb#L25
     *
     * @param array<int, scalar|null> $line
     */
    public static function fromCsv(array $line, string $defaultCountry = 'CH', string $defaultLocale = 'de'): self
    {
        // Normalize encoding
        $line = array_map(
            static function ($value) {
                if (empty($value) || is_numeric($value) || !\is_string($value)) {
                    return $value;
                }

                return mb_convert_encoding(trim($value), 'UTF-8', 'ISO-8859-1');
            },
            $line,
        );

        return new self(
            sacMemberId: (int) $line[0],
            username: $line[0],
            sectionId: [ltrim((string) $line[1], '0')],
            firstname: $line[3],
            lastname: $line[2],
            addressExtra: $line[4],
            street: trim($line[5]),
            poBox: $line[6],
            postal: $line[7],
            city: $line[8],
            country: empty($line[9]) ? $defaultCountry : strtoupper($line[9]),
            dateOfBirth: '' !== $line[10] ? (string) strtotime($line[10]) : '',
            phone: PhoneNumberFormatter::format($line[12]),
            mobile: PhoneNumberFormatter::format($line[14]),
            email: $line[16],
            gender: match ($line[17]) {
                'Weiblich' => 'female',
                'Männlich' => 'male',
                default => 'other',
            },
            profession: $line[27],
            language: 'd' === strtolower($line[19])
                ? $defaultLocale
                : strtolower($line[19]),
            entryYear: $line[20],
            honoraryMember: 'Yes' === $line[22] ? 1 : 0,
            membershipType: $line[23],
            sectionInfo1: $line[24],
            sectionInfo2: $line[25],
            sectionInfo3: $line[26],
            debit: $line[28],
        );
    }

    /**
     * @return array<string, mixed> all public properties keyed by their name
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
