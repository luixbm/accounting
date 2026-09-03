<?php

/**
 * Layar data induk & pengaturan.
 */
return [
    // bagan akun
    'n_shown'          => '{0} ditampilkan',
    'of_total'         => 'dari {0}',
    'new_account'      => '+ Akun Baru',
    'start_coa_h'      => 'Mulai bagan akun perusahaan ini',
    'start_coa_note'   => 'Salin bagan yang sudah ada dari perusahaan lain, lalu sesuaikan. Atau tambahkan akun satu per satu.',
    'copy_coa'         => 'Salin bagan akun',
    'all_types'        => 'Semua tipe',
    'all_groups'       => 'Semua grup',
    'group'            => 'Grup',
    'col_parent'       => 'Induk',
    'col_normal'       => 'Normal',
    'col_subledger'    => 'Buku pembantu',
    'col_flags'        => 'Tanda',
    'flag_header'      => 'induk',
    'flag_cash'        => 'kas',
    'no_accounts_match'=> 'Tidak ada akun yang cocok dengan saringan ini.',
    'account_name'     => 'Nama Akun',
    'normal_balance'   => 'Saldo Normal',
    'debit_d'          => 'Debit (D)',
    'credit_k'         => 'Kredit (K)',
    'parent_header'    => 'Induk (akun header)',
    'subledger'        => 'Buku pembantu',
    'sl_none'          => 'Tidak ada',
    'sl_customer'      => 'Pelanggan (Piutang)',
    'sl_supplier'      => 'Pemasok (Utang)',
    'cashflow_section' => 'Bagian arus kas',
    'denomination_ccy' => 'Mata Uang Denominasi',
    'base_opt'         => '— dasar —',
    'flags'            => 'Tanda',
    'flag_header_full' => 'Akun header (tanpa posting)',
    'flag_cash_full'   => 'Akun kas / bank',
    'danger_zone'      => 'Zona berbahaya',
    'deactivate'       => 'Nonaktifkan',
    'reactivate'       => 'Aktifkan kembali',
    'delete_account_confirm' => 'Hapus akun {0}? Hanya bisa jika tidak ada entri jurnal dan tidak ada sub-akun.',
    'danger_note'      => 'Menonaktifkan menyembunyikannya dari pemilih tetapi menjaga riwayat. Hapus hanya untuk akun yang tidak pernah dipakai.',

    // pelanggan / pemasok
    'address'          => 'Alamat',
    'no_movement_period' => 'Tidak ada mutasi pada periode ini.',

    // mata uang
    'currencies_rates_h' => 'Mata Uang & Kurs',
    'ccy_note'         => 'Mata uang dipakai bersama. Kurs bersifat per perusahaan — di bawah ini kurs untuk {0}, dikuotasi terhadap mata uang dasarnya {1}.',
    'symbol'           => 'Simbol',
    'decimals'         => 'Desimal',
    'role_here'        => 'Peran di sini',
    'base_badge'       => 'dasar',
    'foreign'          => 'asing',
    'add_currency'     => 'Tambah mata uang',
    'recent_rates'     => '{0} — kurs terbaru',
    'per_1_of'         => '({0} per 1 {1})',
    'no_rates_note'    => 'Belum ada kurs — 1.0 akan dipakai.',
    'save_rate'        => 'Simpan kurs',
    'rate'             => 'Kurs',

    // periode
    'periods_h'        => 'Periode Akuntansi — {0}',
    'periods_note'     => 'Menutup satu bulan mengunci jurnalnya dari posting dan pembatalan.',
    'month'            => 'Bulan',
    'close_month_confirm' => 'Tutup {0} {1}?',
];
