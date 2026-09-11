<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * MyInvois sandbox and production are, in practice, two entirely separate
 * registrations: LHDN issues a different Client ID/Secret for each, and
 * preprod ties them to a synthetic test TIN (an "IG..." identity) rather than
 * the company's real TIN. Keeping one shared tax_id/client_id pair per company
 * meant flipping the Environment dropdown silently kept the wrong identity.
 *
 * This splits those per-environment fields into `einvoice_credentials` (one
 * row per company+environment); `einvoice_settings` keeps only what's genuinely
 * shared (which environment is active, the company's real-world profile).
 * Existing data is preserved as the credentials row for each company's
 * current `environment`.
 */
class SplitEinvoiceCredentials extends Migration
{
    private array $movedColumns = ['client_id', 'client_secret_enc', 'cached_token', 'token_expires_at', 'tax_id', 'id_type', 'id_value', 'sst_no'];

    public function up(): void
    {
        $this->forge->addField([
            'id'                => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'company_id'        => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'environment'       => ['type' => 'VARCHAR', 'constraint' => 10],
            'client_id'         => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'client_secret_enc' => ['type' => 'TEXT', 'null' => true],
            'cached_token'      => ['type' => 'TEXT', 'null' => true],
            'token_expires_at'  => ['type' => 'DATETIME', 'null' => true],
            'tax_id'            => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'id_type'           => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'BRN'],
            'id_value'          => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'sst_no'            => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'created_at'        => ['type' => 'DATETIME', 'null' => true],
            'updated_at'        => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['company_id', 'environment']);
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('einvoice_credentials');

        // carry the existing single profile over as that company's "current
        // environment" credentials row
        $now = date('Y-m-d H:i:s');
        foreach ($this->db->table('einvoice_settings')->get()->getResultArray() as $r) {
            $this->db->table('einvoice_credentials')->insert([
                'company_id'        => $r['company_id'],
                'environment'       => $r['environment'] ?: 'sandbox',
                'client_id'         => $r['client_id'],
                'client_secret_enc' => $r['client_secret_enc'],
                'cached_token'      => $r['cached_token'],
                'token_expires_at'  => $r['token_expires_at'],
                'tax_id'            => $r['tax_id'],
                'id_type'           => $r['id_type'],
                'id_value'          => $r['id_value'],
                'sst_no'            => $r['sst_no'],
                'created_at'        => $now,
                'updated_at'        => $now,
            ]);
        }

        $this->forge->dropColumn('einvoice_settings', $this->movedColumns);
    }

    public function down(): void
    {
        $this->forge->addColumn('einvoice_settings', [
            'client_id'         => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'client_secret_enc' => ['type' => 'TEXT', 'null' => true],
            'cached_token'      => ['type' => 'TEXT', 'null' => true],
            'token_expires_at'  => ['type' => 'DATETIME', 'null' => true],
            'tax_id'            => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'id_type'           => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'BRN'],
            'id_value'          => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'sst_no'            => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
        ]);

        foreach ($this->db->table('einvoice_settings')->get()->getResultArray() as $r) {
            $cred = $this->db->table('einvoice_credentials')
                ->where('company_id', $r['company_id'])->where('environment', $r['environment'])
                ->get()->getRowArray();
            if ($cred) {
                $this->db->table('einvoice_settings')->where('id', $r['id'])->update([
                    'client_id'         => $cred['client_id'],
                    'client_secret_enc' => $cred['client_secret_enc'],
                    'cached_token'      => $cred['cached_token'],
                    'token_expires_at'  => $cred['token_expires_at'],
                    'tax_id'            => $cred['tax_id'],
                    'id_type'           => $cred['id_type'],
                    'id_value'          => $cred['id_value'],
                    'sst_no'            => $cred['sst_no'],
                ]);
            }
        }

        $this->forge->dropTable('einvoice_credentials');
    }
}
