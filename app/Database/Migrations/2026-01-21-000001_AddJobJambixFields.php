<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Extra columns on `jobs` so a Jambix job / dossier report row can be recorded
 * whole (import wizard + REST API). All nullable and additive - job P&L is still
 * computed from the posted journal lines, these are reference / analysis fields:
 *
 *   pax            - number of travellers on the dossier
 *   category       - Jambix "Cat." (B2C / B2B / RTN ...)
 *   sales_ref      - Jambix quoted sales total (vs the posted sales)
 *   buy_ref        - Jambix quoted buy / cost total (vs the posted cost)
 *   created_on     - when the dossier was created in Jambix
 *   jambix_status  - the raw Jambix status text (Booking, Option, Cancelled ...)
 *   source         - where the row came from ('jambix', 'api', 'manual')
 *   import_batch_id- the import_batches row that created this job (for revert)
 */
class AddJobJambixFields extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('jobs', [
            'pax'             => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'after' => 'end_date'],
            'category'        => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true, 'after' => 'pax'],
            'sales_ref'       => ['type' => 'DECIMAL', 'constraint' => '20,2', 'null' => true, 'after' => 'category'],
            'buy_ref'         => ['type' => 'DECIMAL', 'constraint' => '20,2', 'null' => true, 'after' => 'sales_ref'],
            'created_on'      => ['type' => 'DATE', 'null' => true, 'after' => 'buy_ref'],
            'jambix_status'   => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true, 'after' => 'created_on'],
            'source'          => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true, 'after' => 'jambix_status'],
            'import_batch_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'after' => 'source'],
        ]);

        $this->db->query('ALTER TABLE jobs ADD KEY jobs_import_batch_id (import_batch_id)');
        $this->db->query('ALTER TABLE jobs ADD KEY jobs_category (category)');
    }

    public function down(): void
    {
        $this->db->query('ALTER TABLE jobs DROP KEY jobs_import_batch_id');
        $this->db->query('ALTER TABLE jobs DROP KEY jobs_category');
        $this->forge->dropColumn('jobs', [
            'pax', 'category', 'sales_ref', 'buy_ref', 'created_on', 'jambix_status', 'source', 'import_batch_id',
        ]);
    }
}
