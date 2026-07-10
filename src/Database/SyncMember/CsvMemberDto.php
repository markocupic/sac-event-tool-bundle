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
        public string $streetExtra,
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
        public string $membershipType,
        public string $sectionInfo1,
        public string $sectionInfo2,
        public string $sectionInfo3,
        public string $sectionInfo4,
        public string $debit,
        public string $memberStatus,
    ) {
    }

    /**
     * Parses a SAC CSV export line according to the Hitobito field layout
     * and returns a normalized CsvMemberDto.
     *
     * @see https://github.com/hitobito/hitobito_sac_cas/blob/6507d29ce25346bbc84b9820c162a1fb384f8460/app/domain/export/tabular/people/sac_mitglieder.rb#L23
     * :id, :layer_navision_id_padded, :last_name, :first_name, :adresszusatz,
     * :address, :postfach, :zip_code, :town, :country, :birthday, :empty,
     * :phone_number_landline, :empty, :phone_number_mobile, :empty, :email, :gender,
     * :empty, :language, :eintrittsjahr, :begünstigt, :ehrenmitglied,
     * :beitragskategorie, :s_info_1, :s_info_2, :s_info_3, :bemerkungen, :saldo,
     * :empty, :anzahl_die_alpen, :anzahl_sektionsbulletin
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
            streetExtra: $line[6],
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
            profession: $line[18],
            language: 'd' === strtolower($line[19])
                ? $defaultLocale
                : strtolower($line[19]),
            entryYear: $line[20],
            membershipType: $line[23],
            sectionInfo1: $line[24],
            sectionInfo2: $line[25],
            sectionInfo3: $line[26],
            sectionInfo4: $line[27],
            debit: $line[28],
            memberStatus: $line[29],
        );
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
