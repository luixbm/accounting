<?php

namespace App\Models;

/**
 * Per-company recurring journal template. Its lines live in
 * recurring_journal_lines; App\Libraries\Accounting\RecurringJournal turns one
 * into a draft general journal.
 */
class RecurringJournalModel extends TenantModel
{
    protected $table         = 'recurring_journals';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps  = true;
    protected $allowedFields = [
        'name', 'description', 'source', 'reference', 'currency_id', 'frequency',
        'next_date', 'is_active', 'last_generated_on', 'last_journal_id', 'created_by',
    ];

    protected $validationRules = [
        'name'        => 'required|max_length[80]',
        'description' => 'required|max_length[255]',
        'source'      => 'permit_empty|in_list[general,memorial,opening,adjustment]',
        'frequency'   => 'permit_empty|in_list[monthly,quarterly,yearly,manual]',
        'currency_id' => 'required|is_natural_no_zero',
    ];

    public const FREQUENCIES = ['monthly', 'quarterly', 'yearly', 'manual'];

    /** @return list<array<string,mixed>> active templates whose next_date has arrived */
    public function due(): array
    {
        return $this
            ->where('is_active', 1)
            ->where('next_date IS NOT NULL', null, false)
            ->where('next_date <=', date('Y-m-d'))
            ->orderBy('next_date', 'ASC')
            ->findAll();
    }

    /** Move next_date forward by one frequency step (no-op for "manual"). */
    public function advance(int $id): void
    {
        $row = $this->find($id);
        if (! $row || empty($row['next_date'])) {
            return;
        }
        $step = match ($row['frequency']) {
            'quarterly' => '+3 months',
            'yearly'    => '+1 year',
            'manual'    => null,
            default     => '+1 month',
        };
        if ($step === null) {
            return;
        }
        $this->update($id, ['next_date' => date('Y-m-d', strtotime($row['next_date'] . ' ' . $step))]);
    }
}
