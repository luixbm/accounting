<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * accounts.parent_id and accounts.currency_id both cascaded on delete, so
 * removing a header account silently destroyed its whole sub-tree, and removing
 * a currency wiped every account that used it. Switch both to SET NULL: a child
 * is orphaned to the top level (and the delete guard still blocks the UI path),
 * a currency-less account just loses its currency tag.
 */
class SoftenAccountFkCascades extends Migration
{
    public function up(): void
    {
        $this->db->query('ALTER TABLE accounts DROP FOREIGN KEY accounts_parent_id_foreign');
        $this->db->query('ALTER TABLE accounts DROP FOREIGN KEY accounts_currency_id_foreign');
        $this->db->query('ALTER TABLE accounts ADD CONSTRAINT accounts_parent_id_foreign FOREIGN KEY (parent_id) REFERENCES accounts (id) ON DELETE SET NULL ON UPDATE SET NULL');
        $this->db->query('ALTER TABLE accounts ADD CONSTRAINT accounts_currency_id_foreign FOREIGN KEY (currency_id) REFERENCES currencies (id) ON DELETE SET NULL ON UPDATE SET NULL');
    }

    public function down(): void
    {
        $this->db->query('ALTER TABLE accounts DROP FOREIGN KEY accounts_parent_id_foreign');
        $this->db->query('ALTER TABLE accounts DROP FOREIGN KEY accounts_currency_id_foreign');
        $this->db->query('ALTER TABLE accounts ADD CONSTRAINT accounts_parent_id_foreign FOREIGN KEY (parent_id) REFERENCES accounts (id) ON DELETE CASCADE ON UPDATE SET NULL');
        $this->db->query('ALTER TABLE accounts ADD CONSTRAINT accounts_currency_id_foreign FOREIGN KEY (currency_id) REFERENCES currencies (id) ON DELETE CASCADE ON UPDATE SET NULL');
    }
}
