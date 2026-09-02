<?php

namespace App\Database\Seeds;

use App\Models\AccountModel;
use CodeIgniter\Database\Seeder;

/**
 * A generic Indonesian-style chart of accounts.
 *
 * Columns per row: [code, name, type, parentCode, isGroup, isCash, subledger, currencyCode, normalBalanceOverride]
 */
class ChartOfAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $defs = [
            // --- ASSETS -----------------------------------------------------
            ['1000', 'AKTIVA', 'asset', null, 1, 0, 'none', null, null],
            ['1100', 'Aktiva Lancar', 'asset', '1000', 1, 0, 'none', null, null],
            ['1101', 'Kas', 'asset', '1100', 0, 1, 'none', null, null],
            ['1102', 'Kas Kecil', 'asset', '1100', 0, 1, 'none', null, null],
            ['1110', 'Bank IDR', 'asset', '1100', 0, 1, 'none', 'IDR', null],
            ['1111', 'Bank USD', 'asset', '1100', 0, 1, 'none', 'USD', null],
            ['1112', 'Bank EUR', 'asset', '1100', 0, 1, 'none', 'EUR', null],
            ['1120', 'Piutang Usaha', 'asset', '1100', 0, 0, 'customer', null, null],
            ['1121', 'Piutang Karyawan', 'asset', '1100', 0, 0, 'none', null, null],
            ['1122', 'Piutang Lain-lain', 'asset', '1100', 0, 0, 'none', null, null],
            ['1129', 'Cadangan Kerugian Piutang', 'contra_asset', '1100', 0, 0, 'none', null, 'K'],
            ['1130', 'Persediaan', 'asset', '1100', 0, 0, 'none', null, null],
            ['1140', 'Uang Muka Pembelian', 'asset', '1100', 0, 0, 'none', null, null],
            ['1150', 'PPN Masukan', 'asset', '1100', 0, 0, 'none', null, null],
            ['1160', 'Biaya Dibayar Dimuka', 'asset', '1100', 0, 0, 'none', null, null],
            ['1170', 'Uang Muka Pajak (PPh 25)', 'asset', '1100', 0, 0, 'none', null, null],

            ['1200', 'Aktiva Tetap', 'asset', '1000', 1, 0, 'none', null, null],
            ['1210', 'Tanah', 'asset', '1200', 0, 0, 'none', null, null],
            ['1220', 'Bangunan', 'asset', '1200', 0, 0, 'none', null, null],
            ['1221', 'Akumulasi Penyusutan Bangunan', 'contra_asset', '1200', 0, 0, 'none', null, 'K'],
            ['1230', 'Kendaraan', 'asset', '1200', 0, 0, 'none', null, null],
            ['1231', 'Akumulasi Penyusutan Kendaraan', 'contra_asset', '1200', 0, 0, 'none', null, 'K'],
            ['1240', 'Peralatan & Inventaris Kantor', 'asset', '1200', 0, 0, 'none', null, null],
            ['1241', 'Akumulasi Penyusutan Peralatan', 'contra_asset', '1200', 0, 0, 'none', null, 'K'],

            // --- LIABILITIES ---------------------------------------------------
            ['2000', 'KEWAJIBAN', 'liability', null, 1, 0, 'none', null, null],
            ['2100', 'Kewajiban Lancar', 'liability', '2000', 1, 0, 'none', null, null],
            ['2110', 'Hutang Usaha', 'liability', '2100', 0, 0, 'supplier', null, null],
            ['2120', 'Hutang Gaji', 'liability', '2100', 0, 0, 'none', null, null],
            ['2130', 'Hutang PPh 21', 'liability', '2100', 0, 0, 'none', null, null],
            ['2131', 'Hutang PPh 23', 'liability', '2100', 0, 0, 'none', null, null],
            ['2132', 'Hutang PPh 25/29', 'liability', '2100', 0, 0, 'none', null, null],
            ['2133', 'Hutang PPh Pasal 4(2)', 'liability', '2100', 0, 0, 'none', null, null],
            ['2140', 'PPN Keluaran', 'liability', '2100', 0, 0, 'none', null, null],
            ['2150', 'Pendapatan Diterima Dimuka', 'liability', '2100', 0, 0, 'none', null, null],
            ['2160', 'Biaya Yang Masih Harus Dibayar', 'liability', '2100', 0, 0, 'none', null, null],
            ['2200', 'Kewajiban Jangka Panjang', 'liability', '2000', 1, 0, 'none', null, null],
            ['2210', 'Hutang Bank', 'liability', '2200', 0, 0, 'none', null, null],
            ['2220', 'Hutang Pemegang Saham', 'liability', '2200', 0, 0, 'none', null, null],

            // --- EQUITY ------------------------------------------------------
            ['3000', 'EKUITAS', 'equity', null, 1, 0, 'none', null, null],
            ['3100', 'Modal Disetor', 'equity', '3000', 0, 0, 'none', null, null],
            ['3200', 'Laba Ditahan', 'equity', '3000', 0, 0, 'none', null, null],
            ['3300', 'Prive / Dividen', 'equity', '3000', 0, 0, 'none', null, 'D'],

            // --- REVENUE ---------------------------------------------------
            ['4000', 'PENDAPATAN', 'revenue', null, 1, 0, 'none', null, null],
            ['4100', 'Pendapatan Jasa', 'revenue', '4000', 0, 0, 'none', null, null],
            ['4200', 'Pendapatan Penjualan', 'revenue', '4000', 0, 0, 'none', null, null],
            ['4900', 'Potongan Penjualan', 'revenue', '4000', 0, 0, 'none', null, 'D'],

            // --- COST OF SALES ---------------------------------------------
            ['5000', 'BEBAN POKOK PENJUALAN', 'cogs', null, 1, 0, 'none', null, null],
            ['5100', 'Beban Pokok Penjualan', 'cogs', '5000', 0, 0, 'none', null, null],
            ['5200', 'Beban Proyek / Jasa Langsung', 'cogs', '5000', 0, 0, 'none', null, null],
            ['5300', 'Beban Akomodasi & Transportasi Langsung', 'cogs', '5000', 0, 0, 'none', null, null],

            // --- OPERATING EXPENSES --------------------------------------------
            ['6000', 'BEBAN OPERASIONAL', 'expense', null, 1, 0, 'none', null, null],
            ['6100', 'Beban Gaji', 'expense', '6000', 0, 0, 'none', null, null],
            ['6110', 'Beban Tunjangan & Lembur', 'expense', '6000', 0, 0, 'none', null, null],
            ['6120', 'Beban BPJS', 'expense', '6000', 0, 0, 'none', null, null],
            ['6200', 'Beban Sewa', 'expense', '6000', 0, 0, 'none', null, null],
            ['6210', 'Beban Listrik & Air', 'expense', '6000', 0, 0, 'none', null, null],
            ['6220', 'Beban Telepon & Internet', 'expense', '6000', 0, 0, 'none', null, null],
            ['6230', 'Beban Alat Tulis Kantor', 'expense', '6000', 0, 0, 'none', null, null],
            ['6240', 'Beban Perlengkapan Kantor', 'expense', '6000', 0, 0, 'none', null, null],
            ['6250', 'Beban Transportasi Kantor', 'expense', '6000', 0, 0, 'none', null, null],
            ['6260', 'Beban Perjalanan Dinas', 'expense', '6000', 0, 0, 'none', null, null],
            ['6270', 'Beban Pemeliharaan', 'expense', '6000', 0, 0, 'none', null, null],
            ['6280', 'Beban Penyusutan', 'expense', '6000', 0, 0, 'none', null, null],
            ['6290', 'Beban Asuransi', 'expense', '6000', 0, 0, 'none', null, null],
            ['6300', 'Beban Iklan & Promosi', 'expense', '6000', 0, 0, 'none', null, null],
            ['6310', 'Beban Jasa Profesional & Konsultan', 'expense', '6000', 0, 0, 'none', null, null],
            ['6320', 'Beban Administrasi Bank', 'expense', '6000', 0, 0, 'none', null, null],
            ['6330', 'Beban Perizinan & Legalitas', 'expense', '6000', 0, 0, 'none', null, null],
            ['6340', 'Beban Konsumsi & Rumah Tangga', 'expense', '6000', 0, 0, 'none', null, null],
            ['6390', 'Beban Operasional Lain-lain', 'expense', '6000', 0, 0, 'none', null, null],

            // --- OTHER INCOME ------------------------------------------------
            ['7000', 'PENDAPATAN LAIN-LAIN', 'other_income', null, 1, 0, 'none', null, null],
            ['7100', 'Pendapatan Bunga / Jasa Giro', 'other_income', '7000', 0, 0, 'none', null, null],
            ['7200', 'Pendapatan Lain-lain', 'other_income', '7000', 0, 0, 'none', null, null],
            ['7300', 'Laba Selisih Kurs', 'other_income', '7000', 0, 0, 'none', null, null],

            // --- OTHER EXPENSES ------------------------------------------------
            ['8000', 'BEBAN LAIN-LAIN', 'other_expense', null, 1, 0, 'none', null, null],
            ['8100', 'Beban Bunga', 'other_expense', '8000', 0, 0, 'none', null, null],
            ['8200', 'Beban Pajak (PPh Final / Badan)', 'other_expense', '8000', 0, 0, 'none', null, null],
            ['8300', 'Rugi Selisih Kurs', 'other_expense', '8000', 0, 0, 'none', null, null],
            ['8400', 'Selisih Pembulatan', 'other_expense', '8000', 0, 0, 'none', null, null],
        ];

        $currencies = [];
        foreach ($this->db->table('currencies')->get()->getResultArray() as $c) {
            $currencies[$c['code']] = (int) $c['id'];
        }

        $now      = date('Y-m-d H:i:s');
        $builder  = $this->db->table('accounts');
        $idByCode = [];

        // First pass: insert every account without a parent link.
        foreach ($defs as [$code, $name, $type, $parent, $isGroup, $isCash, $subledger, $curCode, $nbOverride]) {
            if ($builder->getWhere(['code' => $code])->getRow()) {
                $idByCode[$code] = (int) $builder->getWhere(['code' => $code])->getRow()->id;

                continue;
            }
            $builder->insert([
                'code'           => $code,
                'name'           => $name,
                'type'           => $type,
                'normal_balance' => $nbOverride ?? AccountModel::normalBalanceFor($type),
                'parent_id'      => null,
                'is_group'       => $isGroup,
                'is_cash'        => $isCash,
                'subledger'      => $subledger,
                'currency_id'    => $curCode ? ($currencies[$curCode] ?? null) : null,
                'is_active'      => 1,
                'created_at'     => $now,
                'updated_at'     => $now,
            ]);
            $idByCode[$code] = (int) $this->db->insertID();
        }

        // Second pass: wire up parent_id.
        foreach ($defs as [$code, , , $parent]) {
            if ($parent !== null && isset($idByCode[$code], $idByCode[$parent])) {
                $builder->where('id', $idByCode[$code])->update(['parent_id' => $idByCode[$parent]]);
            }
        }
    }
}
