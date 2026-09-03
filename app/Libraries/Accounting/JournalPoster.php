<?php

namespace App\Libraries\Accounting;

use App\Models\AccountModel;
use App\Models\CurrencyModel;
use App\Models\FiscalPeriodModel;
use App\Models\JournalLineModel;
use App\Models\JournalModel;
use RuntimeException;

/**
 * Validates and posts journals, and creates reversing entries when voiding.
 *
 * All amounts are stored twice on each line: in the journal's transaction
 * currency (debit / credit) and in the base currency (debit_base / credit_base).
 * Reports always read the *_base columns.
 */
class JournalPoster
{
    private JournalModel $journals;
    private JournalLineModel $lines;
    private AccountModel $accounts;
    private CurrencyModel $currencies;
    private FiscalPeriodModel $periods;

    /** Rounding tolerance for the base-currency balance check (IDR). */
    private const TOLERANCE = 0.02;

    public function __construct()
    {
        $this->journals   = model(JournalModel::class);
        $this->lines      = model(JournalLineModel::class);
        $this->accounts   = model(AccountModel::class);
        $this->currencies = model(CurrencyModel::class);
        $this->periods    = model(FiscalPeriodModel::class);
    }

    /**
     * Build a journal number like  JV-2601-0007 .
     */
    public function generateJournalNo(string $source, string $date): string
    {
        $prefixMap = [
            'general' => 'JV', 'cash_receipt' => 'RV', 'cash_payment' => 'PV',
            'sales' => 'SJ', 'purchase' => 'PJ', 'memorial' => 'MJ',
            'opening' => 'OB', 'adjustment' => 'AJ',
        ];
        $p    = $prefixMap[$source] ?? 'JV';
        $stem = $p . '-' . date('ym', strtotime($date)) . '-';
        $seq  = $this->journals->nextSequence($stem);

        return $stem . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Normalise raw line input and compute base-currency amounts.
     *
     * @param array<int,array<string,mixed>> $rawLines
     *
     * @return array{lines: array<int,array<string,mixed>>, totalBase: float, totalTxnDebit: float, totalTxnCredit: float}
     */
    public function prepareLines(array $rawLines, float $rate): array
    {
        $clean = [];
        $no    = 1;
        foreach ($rawLines as $l) {
            $accountId = (int) ($l['account_id'] ?? 0);
            if ($accountId === 0) {
                continue;
            }
            $debit  = round((float) ($l['debit'] ?? 0), 2);
            $credit = round((float) ($l['credit'] ?? 0), 2);

            // A caller may pass an explicit base amount (multi-currency settlement:
            // the A/R line clears at the invoice rate, cash at the settlement rate,
            // and a realized-FX line carries base only with no transaction amount).
            $hasExplicitBase = array_key_exists('debit_base', $l) || array_key_exists('credit_base', $l);
            $debitBase  = $hasExplicitBase ? round((float) ($l['debit_base'] ?? 0), 2) : round($debit * $rate, 2);
            $creditBase = $hasExplicitBase ? round((float) ($l['credit_base'] ?? 0), 2) : round($credit * $rate, 2);

            if ($debit === 0.0 && $credit === 0.0 && $debitBase === 0.0 && $creditBase === 0.0) {
                continue;
            }

            $clean[] = [
                'line_no'     => $no++,
                'account_id'  => $accountId,
                'memo'        => trim((string) ($l['memo'] ?? '')) ?: null,
                'debit'       => $debit,
                'credit'      => $credit,
                'debit_base'  => $debitBase,
                'credit_base' => $creditBase,
                'customer_id' => ! empty($l['customer_id']) ? (int) $l['customer_id'] : null,
                'supplier_id' => ! empty($l['supplier_id']) ? (int) $l['supplier_id'] : null,
                'job_id'      => ! empty($l['job_id']) ? (int) $l['job_id'] : null,
            ];
        }

        // Absorb sub-cent rounding drift on the base side into the largest line.
        $dSum = array_sum(array_column($clean, 'debit_base'));
        $cSum = array_sum(array_column($clean, 'credit_base'));
        $diff = round($dSum - $cSum, 2);
        if ($clean !== [] && $diff !== 0.0 && abs($diff) <= self::TOLERANCE) {
            $idx = $this->largestLineIndex($clean);
            if ($diff > 0) {
                $clean[$idx]['credit_base'] = round($clean[$idx]['credit_base'] + $diff, 2);
            } else {
                $clean[$idx]['debit_base'] = round($clean[$idx]['debit_base'] - $diff, 2);
            }
        }

        return [
            'lines'          => $clean,
            'totalBase'      => round(array_sum(array_column($clean, 'debit_base')), 2),
            'totalTxnDebit'  => round(array_sum(array_column($clean, 'debit')), 2),
            'totalTxnCredit' => round(array_sum(array_column($clean, 'credit')), 2),
        ];
    }

    private function largestLineIndex(array $lines): int
    {
        $best = 0;
        $max  = -1.0;
        foreach ($lines as $i => $l) {
            $v = max($l['debit_base'], $l['credit_base']);
            if ($v > $max) {
                $max  = $v;
                $best = $i;
            }
        }

        return $best;
    }

    /**
     * Validate a set of prepared lines against posting rules.
     *
     * @param array<int,array<string,mixed>> $lines
     *
     * @return list<string> list of error messages (empty = OK)
     */
    public function validateLines(array $lines, string $entryDate): array
    {
        $errors = [];

        if (count($lines) < 2) {
            $errors[] = 'A journal needs at least two lines.';
        }

        $txnDebit  = round(array_sum(array_column($lines, 'debit')), 2);
        $txnCredit = round(array_sum(array_column($lines, 'credit')), 2);
        if (abs($txnDebit - $txnCredit) > 0.001) {
            $errors[] = sprintf('Out of balance: debit %s vs credit %s (transaction currency).', number_format($txnDebit, 2), number_format($txnCredit, 2));
        }

        $baseDebit  = round(array_sum(array_column($lines, 'debit_base')), 2);
        $baseCredit = round(array_sum(array_column($lines, 'credit_base')), 2);
        if (abs($baseDebit - $baseCredit) > self::TOLERANCE) {
            $errors[] = sprintf('Out of balance in base currency: %s vs %s.', number_format($baseDebit, 2), number_format($baseCredit, 2));
        }

        if ($baseDebit === 0.0) {
            $errors[] = 'Journal total is zero.';
        }

        foreach ($lines as $l) {
            $acc = $this->accounts->find($l['account_id']);
            if (! $acc) {
                $errors[] = 'Line references an unknown account.';

                continue;
            }
            if ((int) $acc['is_group'] === 1) {
                $errors[] = sprintf('Account %s - %s is a header account and cannot be posted to.', $acc['code'], $acc['name']);
            }
            if ((int) $acc['is_active'] === 0) {
                $errors[] = sprintf('Account %s - %s is inactive.', $acc['code'], $acc['name']);
            }
            if ($l['debit'] > 0 && $l['credit'] > 0) {
                $errors[] = sprintf('Account %s has both a debit and a credit on one line.', $acc['code']);
            }
            if ($acc['subledger'] === 'customer' && empty($l['customer_id'])) {
                $errors[] = sprintf('Account %s - %s requires a customer on every line.', $acc['code'], $acc['name']);
            }
            if ($acc['subledger'] === 'supplier' && empty($l['supplier_id'])) {
                $errors[] = sprintf('Account %s - %s requires a supplier on every line.', $acc['code'], $acc['name']);
            }
        }

        if ($this->periods->isDateLocked($entryDate)) {
            $errors[] = sprintf('The accounting period containing %s is closed.', $entryDate);
        }

        return $errors;
    }

    /**
     * Persist a journal + lines. Does NOT post it.
     *
     * @param array<string,mixed>            $header
     * @param array<int,array<string,mixed>> $rawLines
     *
     * @return array{ok: bool, id?: int, errors?: list<string>}
     */
    public function save(array $header, array $rawLines, ?int $journalId = null): array
    {
        $currency = $this->currencies->find((int) $header['currency_id']);
        if (! $currency) {
            return ['ok' => false, 'errors' => ['Unknown currency.']];
        }
        $rate    = $this->currencies->isBase((int) $currency['id']) ? 1.0 : max(0.0, (float) ($header['exchange_rate'] ?? 1));
        $prepared = $this->prepareLines($rawLines, $rate);

        if ($prepared['lines'] === []) {
            return ['ok' => false, 'errors' => ['Enter at least one line with an amount.']];
        }

        if ($this->periods->isDateLocked($header['entry_date'])) {
            return ['ok' => false, 'errors' => [sprintf('The accounting period containing %s is closed.', $header['entry_date'])]];
        }

        $db = db_connect();
        $db->transStart();

        $data = [
            'entry_date'    => $header['entry_date'],
            'reference'     => trim((string) ($header['reference'] ?? '')) ?: null,
            'description'   => $header['description'],
            'source'        => $header['source'] ?? 'general',
            'currency_id'   => (int) $header['currency_id'],
            'exchange_rate' => $rate,
            'total_debit'   => $prepared['totalBase'],
            'total_credit'  => $prepared['totalBase'],
        ];

        if ($journalId === null) {
            $data['status']     = 'draft';
            $data['created_by'] = auth()->id();
            $data['journal_no'] = $this->generateJournalNo($data['source'], $data['entry_date']);
            $journalId          = $this->journals->insert($data, true);
        } else {
            $existing = $this->journals->find($journalId);
            if (! $existing || $existing['status'] !== 'draft') {
                $db->transComplete();

                return ['ok' => false, 'errors' => ['Only draft journals can be edited.']];
            }
            $this->journals->update($journalId, $data);
            $this->lines->where('journal_id', $journalId)->delete();
        }

        foreach ($prepared['lines'] as $line) {
            $line['journal_id'] = $journalId;
            $this->lines->insert($line);
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return ['ok' => false, 'errors' => ['Database error while saving the journal.']];
        }

        return ['ok' => true, 'id' => (int) $journalId];
    }

    /**
     * Post a draft journal.
     *
     * @return array{ok: bool, errors?: list<string>}
     */
    public function post(int $journalId): array
    {
        $journal = $this->journals->find($journalId);
        if (! $journal) {
            return ['ok' => false, 'errors' => ['Journal not found.']];
        }
        if ($journal['status'] !== 'draft') {
            return ['ok' => false, 'errors' => ['Only draft journals can be posted.']];
        }

        $lines  = $this->lines->where('journal_id', $journalId)->orderBy('line_no', 'ASC')->findAll();
        $errors = $this->validateLines($lines, $journal['entry_date']);
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $this->journals->update($journalId, [
            'status'    => 'posted',
            'posted_by' => auth()->id(),
            'posted_at' => date('Y-m-d H:i:s'),
        ]);

        return ['ok' => true];
    }

    /**
     * Void a posted journal and book the mirror-image reversing entry
     * (also posted) so the ledger stays balanced.
     *
     * @return array{ok: bool, reversalId?: int, errors?: list<string>}
     */
    public function void(int $journalId, string $reason): array
    {
        $journal = $this->journals->find($journalId);
        if (! $journal) {
            return ['ok' => false, 'errors' => ['Journal not found.']];
        }
        if ($journal['status'] !== 'posted') {
            return ['ok' => false, 'errors' => ['Only posted journals can be voided.']];
        }

        // The reversing entry is dated today; that period must be open.
        // The original journal's period may be closed - that is exactly when
        // a reversal (rather than an edit) is the right tool.
        $revDate = date('Y-m-d');
        if ($this->periods->isDateLocked($revDate)) {
            return ['ok' => false, 'errors' => ["The current period ({$revDate}) is closed, so the reversing entry cannot be booked."]];
        }

        $lines = $this->lines->where('journal_id', $journalId)->orderBy('line_no', 'ASC')->findAll();

        $db = db_connect();
        $db->transStart();

        $revNo = $this->generateJournalNo($journal['source'], $revDate);
        $revId = $this->journals->insert([
            'journal_no'    => $revNo,
            'entry_date'    => $revDate,
            'reference'     => $journal['journal_no'],
            'description'   => 'Reversal of ' . $journal['journal_no'] . ' - ' . $reason,
            'source'        => $journal['source'],
            'currency_id'   => $journal['currency_id'],
            'exchange_rate' => $journal['exchange_rate'],
            'status'        => 'posted',
            'total_debit'   => $journal['total_credit'],
            'total_credit'  => $journal['total_debit'],
            'reversal_of'   => $journalId,
            'created_by'    => auth()->id(),
            'posted_by'     => auth()->id(),
            'posted_at'     => date('Y-m-d H:i:s'),
        ], true);

        foreach ($lines as $l) {
            $this->lines->insert([
                'journal_id'  => $revId,
                'line_no'     => $l['line_no'],
                'account_id'  => $l['account_id'],
                'memo'        => 'Reversal: ' . (string) $l['memo'],
                'debit'       => $l['credit'],
                'credit'      => $l['debit'],
                'debit_base'  => $l['credit_base'],
                'credit_base' => $l['debit_base'],
                'customer_id' => $l['customer_id'],
                'supplier_id' => $l['supplier_id'],
                'job_id'      => $l['job_id'] ?? null,
            ]);
        }

        $this->journals->update($journalId, [
            'status'      => 'void',
            'voided_by'   => auth()->id(),
            'voided_at'   => date('Y-m-d H:i:s'),
            'void_reason' => $reason,
        ]);

        $db->transComplete();

        if ($db->transStatus() === false) {
            return ['ok' => false, 'errors' => ['Database error while voiding.']];
        }

        return ['ok' => true, 'reversalId' => (int) $revId];
    }

    public function deleteDraft(int $journalId): array
    {
        $journal = $this->journals->find($journalId);
        if (! $journal) {
            return ['ok' => false, 'errors' => ['Journal not found.']];
        }
        if ($journal['status'] !== 'draft') {
            return ['ok' => false, 'errors' => ['Only draft journals can be deleted.']];
        }
        $this->journals->delete($journalId); // lines cascade

        return ['ok' => true];
    }

    /**
     * Hard-delete a POSTED journal (lines cascade). Unlike void() this leaves no
     * reversing entry - it is only for "un-posting" a source document (purchase /
     * sales invoice) so it can be corrected or removed. Refuses when the journal
     * is entangled: it is itself a reversal, it has already been reversed, its
     * period is closed, or one of its lines is matched on a bank reconciliation.
     *
     * @return array{ok: bool, errors?: list<string>}
     */
    public function deletePosted(int $journalId): array
    {
        $journal = $this->journals->find($journalId);
        if (! $journal) {
            return ['ok' => false, 'errors' => ['Journal not found.']];
        }
        if ($journal['status'] !== 'posted') {
            return ['ok' => false, 'errors' => ['Only posted journals can be un-posted.']];
        }
        if (! empty($journal['reversal_of'])) {
            return ['ok' => false, 'errors' => ['This journal is a reversing entry.']];
        }
        if ($this->journals->where('reversal_of', $journalId)->countAllResults() > 0) {
            return ['ok' => false, 'errors' => ['This journal has already been reversed - void handling applies.']];
        }
        if ($this->periods->isDateLocked($journal['entry_date'])) {
            return ['ok' => false, 'errors' => [sprintf('The accounting period containing %s is closed - void it instead.', $journal['entry_date'])]];
        }

        $db  = db_connect();
        $hit = $db->table('bank_statement_lines bsl')
            ->join('journal_lines jl', 'jl.id = bsl.matched_line_id')
            ->where('jl.journal_id', $journalId)
            ->countAllResults();
        if ($hit > 0) {
            return ['ok' => false, 'errors' => ['A bank reconciliation is matched to this entry - unmatch it first.']];
        }

        $this->journals->delete($journalId); // journal_lines cascade

        return ['ok' => true];
    }
}
