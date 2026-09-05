<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * In-app review queue for the n8n supplier-invoice pipeline (POST
 * /api/v1/purchase/lines/review). Each POST becomes one batch (one source
 * file / vendor push); each proposed cost line becomes one item, stored with
 * its dry-run match result so a human can confirm it from the Invoice Review
 * page instead of only via a Teams/Slack message.
 */
class CreateCostReview extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'              => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'company_id'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'source'          => ['type' => 'VARCHAR', 'constraint' => 40, 'default' => 'n8n'],
            'file_name'       => ['type' => 'VARCHAR', 'constraint' => 190, 'null' => true],
            'file_url'        => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'vendor'          => ['type' => 'VARCHAR', 'constraint' => 190, 'null' => true],
            'item_count'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 0],
            'confirmed_count' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 0],
            'status'          => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'pending'],
            'confirmed_at'    => ['type' => 'DATETIME', 'null' => true],
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
            'updated_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['company_id', 'status']);
        $this->forge->createTable('cost_review_batches');

        $this->forge->addField([
            'id'                  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'batch_id'            => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'company_id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'line_no'             => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 1],
            // the raw item exactly as received (booking_ref / supplier / service_date /
            // party_name / budget / cost / remark / ...) - re-sent verbatim on confirm.
            'payload'             => ['type' => 'TEXT'],
            'party_name'          => ['type' => 'VARCHAR', 'constraint' => 190, 'null' => true],
            'description'         => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'requested_amount'    => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => true],
            'matched_budget'      => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => true],
            'variance'            => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => true],
            'over_budget'         => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            // match_status: would_apply | unchanged | applied | ambiguous | not_found | error
            'match_type'          => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'match_status'        => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'error'],
            'match_message'       => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'invoice_id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'invoice_internal_no' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'confirmed_at'        => ['type' => 'DATETIME', 'null' => true],
            'confirmed_by'        => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'created_at'          => ['type' => 'DATETIME', 'null' => true],
            'updated_at'          => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('batch_id');
        $this->forge->addKey(['company_id', 'match_status']);
        $this->forge->createTable('cost_review_items');
    }

    public function down(): void
    {
        $this->forge->dropTable('cost_review_items');
        $this->forge->dropTable('cost_review_batches');
    }
}
