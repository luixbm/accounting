<?php

return [
    'saved' => 'Pengaturan E-Invoice disimpan.',
    'saved_no_secret' => 'Tersimpan — tetapi Client secret untuk perusahaan ini belum ada. Tempel di kolom Client secret lalu simpan lagi.',

    'intro' => 'Hubungkan perusahaan ini ke sistem e-Invoice MyInvois LHDN. Mulai dengan Sandbox dan pastikan pengiriman tervalidasi di sana sebelum beralih ke Production.',

    'connection_h'   => 'Koneksi',
    'enabled'        => 'Aktif untuk perusahaan ini',
    'enabled_hint'   => 'Hanya jika dicentang, tombol "Kirim ke LHDN" akan muncul pada invoice penjualan perusahaan ini.',
    'environment'    => 'Environment',
    'env_sandbox'    => 'Sandbox (uji coba)',
    'env_production' => 'Production (live)',
    'client_id'      => 'Client ID',
    'client_secret'  => 'Client secret',
    'client_secret_set'    => 'Secret sudah tersimpan. Biarkan kosong untuk mempertahankannya.',
    'client_secret_unset'  => 'Belum diatur.',

    'test_h'    => 'Tes koneksi',
    'test_hint' => 'Meminta token dari LHDN menggunakan Client ID dan secret yang tersimpan. Simpan dulu jika Anda baru mengubahnya.',
    'test_btn'  => 'Tes koneksi',
    'test_missing'    => 'Masukkan dan simpan Client ID serta secret terlebih dahulu.',
    'test_secret_bad' => 'Secret tersimpan tidak dapat didekripsi — masukkan ulang lalu simpan.',
    'test_ok'   => 'Terhubung ke {0} — token diterima (kedaluwarsa sekitar {1}).',
    'test_fail' => 'LHDN menolak login: {0}',

    'profile_h'    => 'Profil pajak perusahaan (untuk LHDN)',
    'tax_id'       => 'TIN (Tax Identification Number)',
    'id_type'      => 'Jenis ID registrasi',
    'id_value'     => 'Nomor ID registrasi',
    'sst_no'       => 'No. registrasi SST',
    'msic_code'    => 'Kode MSIC',
    'business_activity' => 'Deskripsi kegiatan usaha',

    'address_h'   => 'Alamat terdaftar',
    'addr_line1'  => 'Alamat baris 1',
    'addr_line2'  => 'Alamat baris 2',
    'addr_city'   => 'Kota',
    'addr_postcode' => 'Kode pos',
    'addr_state'  => 'Kode negara bagian',
    'addr_country' => 'Kode negara',

    'contact_h'     => 'Kontak',
    'contact_phone' => 'Telepon',
    'contact_email' => 'Email',
];
