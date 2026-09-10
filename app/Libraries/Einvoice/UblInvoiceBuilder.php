<?php

namespace App\Libraries\Einvoice;

/**
 * Maps a posted sales invoice to the MyInvois UBL 2.1 "Invoice" JSON, document
 * version 1.0 (no digital signature). The shape is LHDN's array/"_" alternative
 * representation - every leaf is `[{"_": value, ...attrs}]`.
 *
 * Scope: a plain domestic invoice with no line-level tax (SST not charged).
 * Allowances/charges, foreign-currency tax exchange rate, prepayments and
 * self-billed variants are out of scope for now.
 */
class UblInvoiceBuilder
{
    /** LHDN "general public" buyer TIN, used when the buyer has no TIN. */
    public const GENERAL_PUBLIC_TIN = 'EI00000000010';

    /** @param array<string,mixed> $inv sales_invoices row (+ currency_code)
     *  @param list<array<string,mixed>> $lines
     *  @param array<string,mixed> $settings einvoice_settings row
     *  @param array<string,mixed> $company companies row
     *  @param array<string,mixed> $customer customers row
     *  @param array<string,string> $buyerCf einvoice_tin / einvoice_id_type / einvoice_id_value
     *  @return array<string,mixed>
     */
    public static function build(array $inv, array $lines, array $settings, array $company, array $customer, array $buyerCf): array
    {
        $ccy       = strtoupper((string) ($inv['currency_code'] ?? 'MYR'));
        $classCode = (string) (acc_setting('einvoiceClassCode') ?: '022');

        $issued = strtotime((string) ($inv['invoice_date'] ?? 'now')) ?: time();

        $subtotal = 0.0;
        $ublLines = [];
        foreach (array_values($lines) as $i => $l) {
            $amt   = round((float) ($l['amount'] ?? 0), 2);
            $qty   = (float) ($l['units'] ?? 0) ?: 1.0;
            $subtotal += $amt;
            $ublLines[] = [
                'ID'                  => self::n((string) ($i + 1)),
                'InvoicedQuantity'    => self::n($qty, ['unitCode' => 'C62']),
                'LineExtensionAmount' => self::n($amt, ['currencyID' => $ccy]),
                'TaxTotal'            => [[
                    'TaxAmount'   => self::n(0, ['currencyID' => $ccy]),
                    'TaxSubtotal' => [[
                        'TaxableAmount' => self::n($amt, ['currencyID' => $ccy]),
                        'TaxAmount'     => self::n(0, ['currencyID' => $ccy]),
                        'TaxCategory'   => self::taxCategory(),
                    ]],
                ]],
                'Item' => [[
                    'CommodityClassification' => [[
                        'ItemClassificationCode' => self::n($classCode, ['listID' => 'CLASS']),
                    ]],
                    'Description' => self::n(mb_substr((string) ($l['description'] ?? 'Service'), 0, 300) ?: 'Service'),
                ]],
                'Price'             => [['PriceAmount' => self::n(round($qty ? $amt / $qty : $amt, 2), ['currencyID' => $ccy])]],
                'ItemPriceExtension' => [['Amount' => self::n($amt, ['currencyID' => $ccy])]],
            ];
        }

        $total = round((float) ($inv['total'] ?? $subtotal), 2);

        return [
            '_D' => 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2',
            '_A' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
            '_B' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
            'Invoice' => [[
                'ID'                   => self::n((string) $inv['internal_no']),
                'IssueDate'            => self::n(gmdate('Y-m-d', $issued)),
                'IssueTime'            => self::n(gmdate('H:i:s\Z')),
                'InvoiceTypeCode'      => self::n('01', ['listVersionID' => '1.0']),
                'DocumentCurrencyCode' => self::n($ccy),
                'TaxCurrencyCode'      => self::n('MYR'),
                'AccountingSupplierParty' => [[
                    'Party' => [self::supplierParty($settings, $company)],
                ]],
                'AccountingCustomerParty' => [[
                    'Party' => [self::buyerParty($customer, $buyerCf)],
                ]],
                'TaxTotal' => [[
                    'TaxAmount'   => self::n(0, ['currencyID' => $ccy]),
                    'TaxSubtotal' => [[
                        'TaxableAmount' => self::n($subtotal, ['currencyID' => $ccy]),
                        'TaxAmount'     => self::n(0, ['currencyID' => $ccy]),
                        'TaxCategory'   => self::taxCategory(),
                    ]],
                ]],
                'LegalMonetaryTotal' => [[
                    'LineExtensionAmount' => self::n($subtotal, ['currencyID' => $ccy]),
                    'TaxExclusiveAmount'  => self::n($subtotal, ['currencyID' => $ccy]),
                    'TaxInclusiveAmount'  => self::n($total, ['currencyID' => $ccy]),
                    'PayableAmount'       => self::n($total, ['currencyID' => $ccy]),
                ]],
                'InvoiceLine' => $ublLines,
            ]],
        ];
    }

    // ---------------------------------------------------------------- parties

    /** @return array<string,mixed> */
    private static function supplierParty(array $s, array $company): array
    {
        $name = (string) ($company['legal_name'] ?: $company['name'] ?? 'Supplier');

        $party = [
            'PartyIdentification' => self::partyIds(
                (string) ($s['tax_id'] ?? ''),
                strtoupper((string) ($s['id_type'] ?? 'BRN')),
                (string) ($s['id_value'] ?? ''),
                (string) ($s['sst_no'] ?? '')
            ),
            'PostalAddress'    => [self::address(
                [$s['addr_line1'] ?? '', $s['addr_line2'] ?? ''],
                (string) ($s['addr_city'] ?? ''),
                (string) ($s['addr_postcode'] ?? ''),
                (string) ($s['addr_state'] ?? '17'),
                (string) ($s['addr_country'] ?? 'MYS')
            )],
            'PartyLegalEntity' => [['RegistrationName' => self::n($name)]],
            'Contact'          => [self::contact((string) ($s['contact_phone'] ?? ''), (string) ($s['contact_email'] ?? ''))],
        ];

        if (! empty($s['msic_code'])) {
            $party = ['IndustryClassificationCode' => self::n((string) $s['msic_code'], [
                'name' => mb_substr((string) ($s['business_activity'] ?? ''), 0, 300),
            ])] + $party;
        }

        return $party;
    }

    /** @return array<string,mixed> */
    private static function buyerParty(array $c, array $cf): array
    {
        $tin  = trim((string) ($cf['einvoice_tin'] ?? ''));
        $idT  = strtoupper(trim((string) ($cf['einvoice_id_type'] ?? 'BRN')) ?: 'BRN');
        $idV  = trim((string) ($cf['einvoice_id_value'] ?? ''));
        if ($tin === '') {
            $tin = self::GENERAL_PUBLIC_TIN;
            $idV = $idV ?: 'NA';
        }

        return [
            'PostalAddress'    => [self::address(
                preg_split('/\r\n|\r|\n/', (string) ($c['address'] ?? '')) ?: [],
                'NA',
                '',
                '17',
                self::iso3((string) ($c['country'] ?? ''))
            )],
            'PartyLegalEntity' => [['RegistrationName' => self::n((string) ($c['name'] ?? 'Buyer'))]],
            'PartyIdentification' => self::partyIds($tin, $idT, $idV, ''),
            'Contact'          => [self::contact((string) ($c['phone'] ?? ''), (string) ($c['email'] ?? ''))],
        ];
    }

    // ---------------------------------------------------------------- helpers

    /** @return list<array<string,mixed>> */
    private static function partyIds(string $tin, string $idType, string $idValue, string $sst): array
    {
        $idType = in_array($idType, ['BRN', 'NRIC', 'PASSPORT', 'ARMY'], true) ? $idType : 'BRN';

        return [
            ['ID' => self::n($tin !== '' ? $tin : 'NA', ['schemeID' => 'TIN'])],
            ['ID' => self::n($idValue !== '' ? $idValue : 'NA', ['schemeID' => $idType])],
            ['ID' => self::n($sst !== '' ? $sst : 'NA', ['schemeID' => 'SST'])],
            ['ID' => self::n('NA', ['schemeID' => 'TTX'])],
        ];
    }

    /** @param list<string> $lines @return array<string,mixed> */
    private static function address(array $lines, string $city, string $postcode, string $state, string $country): array
    {
        $lines = array_values(array_filter(array_map('trim', $lines), static fn ($l) => $l !== ''));
        if (! $lines) {
            $lines = ['NA'];
        }
        $out = [
            'CityName'             => self::n($city !== '' ? $city : 'NA'),
            'CountrySubentityCode' => self::n($state !== '' ? $state : '17'),
            'AddressLine'          => array_map(static fn ($l) => ['Line' => self::n(mb_substr($l, 0, 150))], array_slice($lines, 0, 3)),
            'Country'              => [[
                'IdentificationCode' => self::n($country, ['listID' => 'ISO3166-1', 'listAgencyID' => '6']),
            ]],
        ];
        if ($postcode !== '') {
            $out = ['PostalZone' => self::n($postcode)] + $out;
        }

        return $out;
    }

    /** @return array<string,mixed> */
    private static function contact(string $phone, string $email): array
    {
        return [
            'Telephone'     => self::n($phone !== '' ? $phone : 'NA'),
            'ElectronicMail' => self::n($email !== '' ? $email : 'NA'),
        ];
    }

    /** TaxCategory "06" = Not Applicable (no tax charged). @return list<array<string,mixed>> */
    private static function taxCategory(): array
    {
        return [[
            'ID'        => self::n('06'),
            'TaxScheme' => [[
                'ID' => self::n('OTH', ['schemeID' => 'UN/ECE 5153', 'schemeAgencyID' => '6']),
            ]],
        ]];
    }

    private static function iso3(string $country): string
    {
        $c = strtoupper(trim($country));
        if (preg_match('/^[A-Z]{3}$/', $c)) {
            return $c;
        }
        $map = ['MY' => 'MYS', 'MALAYSIA' => 'MYS', 'SG' => 'SGP', 'SINGAPORE' => 'SGP', 'ID' => 'IDN', 'INDONESIA' => 'IDN'];

        return $map[$c] ?? 'MYS';
    }

    /** Wrap a scalar as LHDN's `[{"_": value, ...attrs}]`. @return list<array<string,mixed>> */
    private static function n($value, array $attrs = []): array
    {
        return [['_' => $value] + $attrs];
    }
}
