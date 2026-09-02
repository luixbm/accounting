<?php

/**
 * Report Center — catalogue titles + descriptions and per-report page titles.
 * Keys match Config\Reports::$items; `<key>` = title, `<key>_d` = description.
 */
return [
    'title' => 'Laporan',

    // categories
    'cat_financial' => 'Keuangan',
    'cat_gl'        => 'Buku Besar',
    'cat_cashbank'  => 'Kas & Bank',
    'cat_sales'     => 'Penjualan',
    'cat_purchase'  => 'Pembelian',
    'cat_job'       => 'Job',

    // ---- Financial
    'pnl'          => 'Laba Rugi',
    'pnl_d'        => 'Laporan laba rugi untuk suatu periode',
    'pnl-monthly'  => 'Laba Rugi — multi periode',
    'pnl-monthly_d' => 'Laba rugi bulanan untuk suatu periode',
    'pnl-yearly'   => 'Laba Rugi — multi tahun',
    'pnl-yearly_d' => 'Laba rugi tahunan',
    'bs'           => 'Neraca',
    'bs_d'         => 'Neraca standar per suatu tanggal',
    'bs-monthly'   => 'Neraca — multi periode',
    'bs-monthly_d' => 'Kolom neraca akhir bulan',
    'cashflow'     => 'Arus Kas',
    'cashflow_d'   => 'Arus kas masuk & keluar untuk suatu periode',
    'cashflow-m'   => 'Arus Kas — multi periode',
    'cashflow-m_d' => 'Arus kas masuk & keluar bulanan',

    // ---- General Ledger
    'trial-balance'   => 'Neraca Saldo',
    'trial-balance_d' => 'Saldo awal, mutasi, dan saldo akhir per akun',
    'journal-list'    => 'Daftar Jurnal',
    'journal-list_d'  => 'Daftar jurnal umum untuk suatu periode',
    'realized-fx'     => 'Laba / Rugi Selisih Kurs',
    'realized-fx_d'   => 'Keuntungan & kerugian selisih kurs terealisasi',
    'gl-account'      => 'Buku Besar',
    'gl-account_d'    => 'Rincian untuk satu akun',
    'gl-details'      => 'Rincian Buku Besar',
    'gl-details_d'    => 'Posting untuk akun & periode terpilih',

    // ---- Cash & Bank
    'bank-history'  => 'Riwayat Bank',
    'bank-history_d' => 'Seluruh mutasi pada akun bank / kas',
    'payment-bank'  => 'Pembayaran per Bank',
    'payment-bank_d' => 'Pembayaran & penerimaan dikelompokkan per bank',
    'bank-recon'    => 'Rekonsiliasi Bank',
    'bank-recon_d'  => 'Impor rekening koran & cocokkan saldo',
    'consolidation' => 'Konsolidasi',
    'consolidation_d' => 'Laporan gabungan lintas perusahaan',

    // ---- Sales
    's-register'     => 'Register Penjualan',
    's-register_d'   => 'Seluruh penjualan per pelanggan untuk suatu periode',
    's-monthly'      => 'Penjualan Bulanan',
    's-monthly_d'    => 'Penjualan bulanan per pelanggan',
    's-outstanding'  => 'Faktur Belum Lunas',
    's-outstanding_d' => 'Faktur pelanggan yang belum dibayar',
    's-aging-sum'    => 'Umur Piutang (ringkas)',
    's-aging-sum_d'  => 'Piutang per pelanggan & bucket',
    's-aging'        => 'Umur Piutang (rinci)',
    's-aging_d'      => 'Piutang berdasarkan umur per faktur',
    's-detail'       => 'Rincian Faktur Penjualan',
    's-detail_d'     => 'Rincian per baris lintas faktur',
    's-receipts'     => 'Daftar Penerimaan',
    's-receipts_d'   => 'Uang yang diterima dari pelanggan',
    's-invoice-paid' => 'Faktur Terbayar',
    's-invoice-paid_d' => 'Penerimaan yang dialokasikan ke faktur penjualan',
    's-customers'    => 'Daftar Pelanggan',
    's-customers_d'  => 'Seluruh pelanggan dengan saldo piutang',

    // ---- Purchase
    'p-register'     => 'Register Pembelian',
    'p-register_d'   => 'Seluruh pembelian per pemasok untuk suatu periode',
    'p-monthly'      => 'Pembelian Bulanan',
    'p-monthly_d'    => 'Pembelian bulanan per pemasok',
    'p-outstanding'  => 'Faktur Belum Lunas',
    'p-outstanding_d' => 'Faktur pemasok yang belum dibayar',
    'p-aging-sum'    => 'Umur Utang (ringkas)',
    'p-aging-sum_d'  => 'Utang per pemasok & bucket',
    'p-aging'        => 'Umur Utang (rinci)',
    'p-aging_d'      => 'Utang berdasarkan umur per faktur',
    'p-detail'       => 'Rincian Faktur Pembelian',
    'p-detail_d'     => 'Rincian per baris lintas faktur',
    'p-payments'     => 'Pembayaran Terlaksana',
    'p-payments_d'   => 'Uang yang sudah dibayarkan ke pemasok',
    'p-paylist'      => 'Daftar Pembayaran',
    'p-paylist_d'    => 'Baris pembelian belum dibayar, disaring per tanggal janji bayar',
    'p-invoice-paid' => 'Faktur Terbayar',
    'p-invoice-paid_d' => 'Pembayaran yang dialokasikan ke faktur pembelian',
    'p-suppliers'    => 'Daftar Pemasok',
    'p-suppliers_d'  => 'Seluruh pemasok dengan saldo utang',

    // ---- Job
    'job-list'     => 'Daftar Job',
    'job-list_d'   => 'Pendapatan, biaya & margin per job',
    'job-detail'   => 'Laba Rugi Job — Penjualan vs Pembelian',
    'job-detail_d' => 'Penjualan & pembelian per pelanggan / pemasok untuk job pada rentang tanggal kedatangan',

    // page titles that are not 1:1 with a catalogue key
    'consolidated'        => 'Laporan Konsolidasi',
    'bs_comparative'      => 'Neraca — komparatif',
    'pnl_comparative'     => 'Laba Rugi — komparatif',
    'cashflow_comparative' => 'Arus Kas — komparatif',
    'outstanding_p'       => 'Faktur Pembelian Belum Lunas',
    'outstanding_s'       => 'Faktur Penjualan Belum Lunas',
];
