<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * One row per company: how to talk to LHDN's MyInvois e-Invoice API, and who
 * this company is for MyInvois. `client_secret_enc` is reversibly encrypted
 * (App\Libraries\Einvoice\Secret) - the only secret in this app that isn't a
 * one-way hash, because MyInvois needs the raw value to authenticate.
 * `cached_token`/`token_expires_at` avoid re-authenticating on every call
 * (MyInvois rate-limits logins). `document_version` starts at '1.0' (no
 * signature validation required) and moves to '1.1' once a digital signing
 * certificate is in place (see App\Libraries\Einvoice\DocumentSigner, not
 * built yet).
 */
class CreateEinvoiceSettings extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'               => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'company_id'       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'environment'      => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'sandbox'],
            'enabled'          => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'document_version' => ['type' => 'VARCHAR', 'constraint' => 5, 'default' => '1.0'],
            'client_id'        => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'client_secret_enc' => ['type' => 'TEXT', 'null' => true],
            'cached_token'     => ['type' => 'TEXT', 'null' => true],
            'token_expires_at' => ['type' => 'DATETIME', 'null' => true],
            'tax_id'           => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'id_type'          => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'BRN'],
            'id_value'         => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'sst_no'           => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'msic_code'        => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true],
            'business_activity' => ['type' => 'VARCHAR', 'constraint' => 160, 'null' => true],
            'addr_line1'       => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'addr_line2'       => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'addr_city'        => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'addr_postcode'    => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true],
            'addr_state'       => ['type' => 'VARCHAR', 'constraint' => 5, 'null' => true],
            'addr_country'     => ['type' => 'VARCHAR', 'constraint' => 3, 'default' => 'MYS'],
            'contact_phone'    => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'contact_email'    => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'created_at'       => ['type' => 'DATETIME', 'null' => true],
            'updated_at'       => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('company_id');
        $this->forge->createTable('einvoice_settings');
    }

    public function down(): void
    {
        $this->forge->dropTable('einvoice_settings');
    }
}
