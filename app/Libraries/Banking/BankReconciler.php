<?php

namespace App\Libraries\Banking;

use App\Libraries\Import\SpreadsheetReader;
use App\Models\BankStatementLineModel;
use App\Models\BankStatementModel;
use Config\Database;

/**
 * Parses an uploaded bank statement into bank_statement_lines and matches
 * those rows against posted journal lines that hit the statement's bank
 * account. Matching is 1 statement row <-> 0..1 journal_lines row.
 */
class BankReconciler
{
    /** Logical field => [label, required?] used by the column-map screen. */
    public const FIELDS = [
        'date'        => ['Date', true],
        'description' => ['Description', true],
        'reference'   => ['Reference', false],
        'amount'      => ['Amount (one signed column)', false],
        'debit'       => ['Money out / Debit column', false],
        'credit'      => ['Money in / Credit column', false],
        'balance'     => ['Running balance (ignored)', false],
    ];

    private $db;
    private BankStatementModel $statements;
    private BankStatementLineModel $lines;

    public function __construct()
    {
        $this->db         = Database::connect();
        $this->statements = model(BankStatementModel::class);
        $this->lines      = model(BankStatementLineModel::class);
    }

    // ---------------------------------------------------------------- grid helpers

    /** @param list<list<mixed>> $grid @return array<int,string> col index => header */
    public function headers(array $grid, int $headerRow): array
    {
        $row = $grid[$headerRow - 1] ?? [];
        $out = [];
        foreach ($row as $i => $val) {
            $txt     = trim((string) $val);
            $out[$i] = $txt !== '' ? $txt : ('Column ' . $this->colLetter($i));
        }
        $width = max(array_map('count', array_slice($grid, 0, 50) ?: [[]]));
        for ($i = count($out); $i < $width; $i++) {
            $out[$i] = 'Column ' . $this->colLetter($i);
        }

        return $out;
    }

    /** @param list<list<mixed>> $grid @return list<array{n:int,cells:list<mixed>}> */
    public function dataRows(array $grid, int $headerRow): array
    {
        $out = [];
        foreach ($grid as $idx => $cells) {
            if ($idx < $headerRow) {
                continue;
            }
            $out[] = ['n' => $idx + 1, 'cells' => $cells];
        }

        return $out;
    }

    // ---------------------------------------------------------------- parse

    /**
     * @param list<list<mixed>>   $grid
     * @param array<string,int>   $map
     * @param array<string,mixed> $opt  headerRow, dateFormat, amountMode (credit_in|debit_in)
     *
     * @return list<array{n:int,date:?string,description:string,reference:string,amount:float}>
     */
    public function parseRows(array $grid, array $map, array $opt): array
    {
        $headerRow = (int) ($opt['headerRow'] ?? 1);
        $dateFmt   = $opt['dateFormat'] ?? 'auto';
        $mode      = $opt['amountMode'] ?? 'credit_in';

        $out = [];
        foreach ($this->dataRows($grid, $headerRow) as $r) {
            $cells = $r['cells'];
            $get   = static fn (string $f) => isset($map[$f]) ? trim((string) ($cells[$map[$f]] ?? '')) : '';

            $dateRaw = $get('date');
            $date    = SpreadsheetReader::toDate($dateRaw, $dateFmt);
            $desc    = $get('description');

            if (isset($map['amount'])) {
                $amount = SpreadsheetReader::toNumber($cells[$map['amount']] ?? null);
                if ($mode === 'debit_in') {
                    $amount = -$amount;
                }
            } else {
                $d      = isset($map['debit']) ? abs(SpreadsheetReader::toNumber($cells[$map['debit']] ?? null)) : 0.0;
                $c      = isset($map['credit']) ? abs(SpreadsheetReader::toNumber($cells[$map['credit']] ?? null)) : 0.0;
                $amount = $mode === 'debit_in' ? $d - $c : $c - $d;
            }

            if ($date === null && $desc === '' && abs($amount) < 0.005) {
                continue; // blank row
            }
            if (abs($amount) < 0.005) {
                continue; // opening-balance / summary row with no movement
            }

            $out[] = [
                'n'           => $r['n'],
                'date'        => $date,
                'description' => mb_substr($desc, 0, 255),
                'reference'   => mb_substr($get('reference'), 0, 100),
                'amount'      => round($amount, 2),
            ];
        }

        return $out;
    }

    /**
     * Replace the statement's imported lines. Rows with an unreadable date fall
     * back to the statement date.
     *
     * @param list<array<string,mixed>> $rows result of parseRows()
     */
    public function importLines(int $statementId, array $rows): int
    {
        $st = $this->statements->find($statementId);
        if (! $st) {
            return 0;
        }
        $this->clearLines($statementId);

        $i = 0;
        foreach ($rows as $row) {
            $this->lines->insert([
                'statement_id' => $statementId,
                'txn_date'     => $row['date'] ?: $st['statement_date'],
                'description'  => $row['description'],
                'reference'    => $row['reference'] ?: null,
                'amount'       => $row['amount'],
                'sort_no'      => $i++,
            ]);
        }

        return $i;
    }

    /** Unlink then delete every line of a statement. */
    public function clearLines(int $statementId): void
    {
        $this->lines->where('statement_id', $statementId)->delete();
    }

    // ---------------------------------------------------------------- book side

    /**
     * Posted, non-reversal journal lines that hit the bank account up to the
     * statement date - the "live" ledger view used for reconciliation.
     *
     * @return list<array<string,mixed>>  each: id, journal_id, journal_no,
     *         entry_date, memo, jdesc, effect (debit_base - credit_base)
     */
    public function bookLines(array $st): array
    {
        return $this->db->table('journal_lines jl')
            ->select('jl.id, jl.journal_id, j.journal_no, j.entry_date, jl.memo,
                      j.description AS jdesc, (jl.debit_base - jl.credit_base) AS effect')
            ->join('journals j', 'j.id = jl.journal_id')
            ->where('jl.company_id', (int) $st['company_id'])
            ->where('jl.account_id', (int) $st['bank_account_id'])
            ->where('j.status', 'posted')
            ->where('j.reversal_of', null)
            ->where('j.entry_date <=', $st['statement_date'])
            ->orderBy('j.entry_date', 'ASC')->orderBy('j.id', 'ASC')
            ->get()->getResultArray();
    }

    /**
     * journal_lines ids already claimed by a statement line, optionally
     * excluding one statement so its own matches don't block a re-match.
     *
     * @return array<int,int> journal_line_id => statement_id
     */
    private function claimedBookIds(int $companyId, int $bankAccountId, ?int $exceptStatement = null): array
    {
        $rows = $this->db->table('bank_statement_lines sl')
            ->select('sl.matched_line_id, sl.statement_id')
            ->join('bank_statements s', 's.id = sl.statement_id')
            ->where('s.company_id', $companyId)
            ->where('s.bank_account_id', $bankAccountId)
            ->where('sl.matched_line_id IS NOT NULL')
            ->get()->getResultArray();

        $out = [];
        foreach ($rows as $r) {
            if ($exceptStatement !== null && (int) $r['statement_id'] === $exceptStatement) {
                continue;
            }
            $out[(int) $r['matched_line_id']] = (int) $r['statement_id'];
        }

        return $out;
    }

    // ---------------------------------------------------------------- matching

    /**
     * Link every still-unmatched statement line to the closest unclaimed book
     * line with the same signed amount (date within $window days).
     *
     * @return int number of new matches
     */
    public function autoMatch(int $statementId, int $window = 10): int
    {
        $st = $this->statements->find($statementId);
        if (! $st) {
            return 0;
        }

        $claimed = $this->claimedBookIds((int) $st['company_id'], (int) $st['bank_account_id'], $statementId);
        $book    = [];
        foreach ($this->bookLines($st) as $b) {
            if (isset($claimed[(int) $b['id']])) {
                continue;
            }
            $book[] = $b;
        }

        $usedThisRun = [];
        $made        = 0;

        foreach ($this->lines->forStatement($statementId) as $sl) {
            if ($sl['matched_line_id'] !== null) {
                continue;
            }
            $target  = round((float) $sl['amount'], 2);
            $slTs    = strtotime((string) $sl['txn_date']);
            $bestIdx = null;
            $bestGap = PHP_INT_MAX;

            foreach ($book as $i => $b) {
                if (isset($usedThisRun[$i])) {
                    continue;
                }
                if (abs(round((float) $b['effect'], 2) - $target) > 0.005) {
                    continue;
                }
                $gap = abs((int) floor(($slTs - strtotime((string) $b['entry_date'])) / 86400));
                if ($gap <= $window && $gap < $bestGap) {
                    $bestGap = $gap;
                    $bestIdx = $i;
                }
            }

            if ($bestIdx !== null) {
                $usedThisRun[$bestIdx] = true;
                $this->lines->update((int) $sl['id'], [
                    'matched_line_id' => (int) $book[$bestIdx]['id'],
                    'match_type'      => 'auto',
                ]);
                $made++;
            }
        }

        return $made;
    }

    public function match(int $statementId, int $statementLineId, int $journalLineId, string $type = 'manual'): bool
    {
        $sl = $this->lines->find($statementLineId);
        if (! $sl || (int) $sl['statement_id'] !== $statementId) {
            return false;
        }

        return $this->lines->update($statementLineId, [
            'matched_line_id' => $journalLineId,
            'match_type'      => $type,
        ]);
    }

    public function unmatch(int $statementId, int $statementLineId): bool
    {
        $sl = $this->lines->find($statementLineId);
        if (! $sl || (int) $sl['statement_id'] !== $statementId) {
            return false;
        }

        return $this->lines->update($statementLineId, ['matched_line_id' => null, 'match_type' => null]);
    }

    // ---------------------------------------------------------------- summary

    /**
     * The reconciliation position.
     *
     * @return array<string,mixed>
     */
    public function summary(int $statementId): array
    {
        $st = $this->statements->find($statementId);
        if (! $st) {
            return [];
        }

        $stmtLines = $this->lines->forStatement($statementId);
        $book      = $this->bookLines($st);

        $matchedBookIds = [];
        foreach ($stmtLines as $sl) {
            if ($sl['matched_line_id'] !== null) {
                $matchedBookIds[(int) $sl['matched_line_id']] = true;
            }
        }
        $claimedElsewhere = $this->claimedBookIds((int) $st['company_id'], (int) $st['bank_account_id'], $statementId);

        $matchedStmtTotal = 0.0;
        $unmatchedStmt    = [];
        $unmatchedStmtTot = 0.0;
        $stmtLineTotal    = 0.0;
        foreach ($stmtLines as $sl) {
            $amt = (float) $sl['amount'];
            $stmtLineTotal += $amt;
            if ($sl['matched_line_id'] !== null) {
                $matchedStmtTotal += $amt;
            } else {
                $unmatchedStmt[]   = $sl;
                $unmatchedStmtTot += $amt;
            }
        }

        $bookBalance      = 0.0;
        $matchedBookTotal = 0.0;
        $unmatchedBook    = [];
        $unmatchedBookTot = 0.0;
        foreach ($book as $b) {
            $eff          = (float) $b['effect'];
            $bookBalance += $eff;
            if (isset($matchedBookIds[(int) $b['id']])) {
                $matchedBookTotal += $eff;
            } elseif (! isset($claimedElsewhere[(int) $b['id']])) {
                $unmatchedBook[]   = $b;
                $unmatchedBookTot += $eff;
            }
        }

        $projectedBook = $bookBalance + $unmatchedStmtTot;
        $projectedBank = (float) $st['closing_balance'] + $unmatchedBookTot;
        $difference    = round($projectedBook - $projectedBank, 2);

        $importCheck = round((float) $st['closing_balance'] - (float) $st['opening_balance'] - $stmtLineTotal, 2);

        // "Reconciled" means the maths ties out AND every statement line has been
        // dealt with (matched or posted to the books). Outstanding ledger entries
        // that simply haven't cleared the bank yet are allowed to remain.
        $reconciled = abs($difference) < 0.5 && count($unmatchedStmt) === 0;

        return [
            'statement'          => $st,
            'stmt_lines'         => $stmtLines,
            'book'               => $book,
            'matched_book_ids'   => $matchedBookIds,
            'claimed_elsewhere'  => $claimedElsewhere,
            'unmatched_stmt'     => $unmatchedStmt,
            'unmatched_book'     => $unmatchedBook,
            'counts'             => [
                'stmt'           => count($stmtLines),
                'stmt_unmatched' => count($unmatchedStmt),
                'book'           => count($book),
                'book_unmatched' => count($unmatchedBook),
            ],
            'book_balance'       => round($bookBalance, 2),
            'matched_stmt_total' => round($matchedStmtTotal, 2),
            'matched_book_total' => round($matchedBookTotal, 2),
            'unmatched_stmt_total' => round($unmatchedStmtTot, 2),
            'unmatched_book_total' => round($unmatchedBookTot, 2),
            'stmt_line_total'    => round($stmtLineTotal, 2),
            'difference'         => $difference,
            'reconciled'         => $reconciled,
            'ties_out'           => abs($difference) < 0.5,
            'import_check'       => $importCheck,
        ];
    }

    // ---------------------------------------------------------------- misc

    private function colLetter(int $i): string
    {
        $s = '';
        $i++;
        while ($i > 0) {
            $i--;
            $s = chr(65 + $i % 26) . $s;
            $i = intdiv($i, 26);
        }

        return $s;
    }
}
