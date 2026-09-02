<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Company profile and the special "control" accounts the reports rely on.
 * Any value here can be overridden at runtime and stored in the database
 * via the Settings library, e.g. setting('Accounting.companyName', '...').
 */
class Accounting extends BaseConfig
{
    public string $companyName = 'PT Contoh Nusantara';
    public string $companyAddress = '';
    public string $companyNpwp = '';

    /** Relative path (under public/) to the uploaded company logo. */
    public string $logoPath = '';

    /** Default UI theme: light | green | blue | dark. */
    public string $theme = 'light';

    /** Default UI language: id | en. Each user can switch their own. */
    public string $locale = 'id';

    /** ISO code of the base (reporting) currency. */
    public string $baseCurrency = 'IDR';

    /** First month of the fiscal year (1 = January). */
    public int $fiscalYearStartMonth = 1;

    /**
     * Control accounts, referenced by account code. The seeder creates
     * matching accounts; change these if you renumber the chart.
     */
    public string $arControlCode = '1120';   // Piutang Usaha
    public string $apControlCode = '2110';   // Hutang Usaha
    public string $retainedEarningsCode = '3200'; // Laba Ditahan
    public string $fxGainCode = '7300';      // Laba Selisih Kurs
    public string $fxLossCode = '8300';      // Rugi Selisih Kurs
    public string $roundingCode = '8400';    // Selisih Pembulatan

    // --- tax (purchase / sales modules)
    public string $ppnRate = '11';           // VAT %
    public string $ppnInputCode = '1150';    // PPN Masukan (asset)
    public string $ppnOutputCode = '2140';   // PPN Keluaran (liability)
    public string $pph23Rate = '2';          // withholding %
    public string $pph23PayableCode = '2131'; // Hutang PPh 23 (liability)
    public string $pph23PrepaidCode = '1170'; // Uang Muka PPh 23 (asset) - customer-withheld on our sales
}
