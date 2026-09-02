<?php

namespace App\Controllers;

use App\Libraries\Accounting\JournalPoster;
use App\Libraries\Banking\BankReconciler;
use App\Libraries\Import\SpreadsheetReader;
use App\Models\AccountModel;
use App\Models\BankStatementLineModel;
use App\Models\BankStatementModel;
use App\Models\CurrencyModel;
use Config\Database;

/**
 * Bank statement import + reconciliation workspace. Lives under /banking/reconcile.
 * Mutating actions require the journal.post permission (same as the rest of Banking).
 */
class BankReconController extends BaseController
{
    private BankStatementModel $statements;
    private BankStatementLineModel $lines;
    private AccountModel $accounts;
    private BankReconciler $recon;

    public function __construct()
    {
        $this->statements = model(BankStatementModel::class);
        $this->lines      = model(BankStatementLineModel::class);
        $this->accounts   = model(AccountModel::class);
        $this->recon      = new BankReconciler();
        @ini_set('memory_limit', '512M');
        @set_time_limit(300);
    }

    private function canPost(): bool
    {
        return user_can('journal.post');
    }

    private function deny(string $msg = 'Not allowed.')
    {
        return redirect()->to('banking/reconcile')->with('error', $msg);
    }

    private function find(int $id): ?array
    {
        return $this->statements->find($id);
    }

    private function num($v): float
    {
        return (float) str_replace([',', ' '], '', (string) $v);
    }

    private function grid(array $st): array
    {
        return (new SpreadsheetReader())->grid($st['stored_path'], $st['sheet'] ?: null);
    }

    // ------------------------------------------------------------------ list

    public function index()
    {
        $rows  = $this->statements->recent();
        $accts = [];
        foreach ($this->accounts->cashAccounts() as $a) {
            $accts[(int) $a['id']] = $a['code'] . ' ' . $a['name'];
        }

        return view('banking/reconcile/index', [
            'title'      => 'Bank Reconciliation',
            'statements' => $rows,
            'accts'      => $accts,
        ]);
    }

    // ------------------------------------------------------------------ new / upload

    public function newForm()
    {
        return view('banking/reconcile/new', [
            'title' => 'Import bank statement',
            'banks' => $this->accounts->cashAccounts(),
        ]);
    }

    public function create()
    {
        if (! $this->canPost()) {
            return $this->deny();
        }

        $bankId  = (int) $this->request->getPost('bank_account_id');
        $date    = $this->request->getPost('statement_date');
        $opening = $this->num($this->request->getPost('opening_balance'));
        $closing = $this->num($this->request->getPost('closing_balance'));
        $note    = trim((string) $this->request->getPost('note'));

        $bank = $bankId ? $this->accounts->find($bankId) : null;
        if (! $bank || (int) $bank['is_cash'] !== 1) {
            return redirect()->back()->withInput()->with('error', 'Choose a cash / bank account.');
        }
        if (! $date) {
            return redirect()->back()->withInput()->with('error', 'Enter the statement date.');
        }

        $file = $this->request->getFile('file');
        if (! $file || ! $file->isValid()) {
            return redirect()->back()->withInput()->with('error', 'Choose a .xlsx or .csv file.');
        }
        $ext = strtolower($file->getClientExtension());
        if (! in_array($ext, ['xlsx', 'xls', 'csv'], true)) {
            return redirect()->back()->withInput()->with('error', 'Only .xlsx, .xls or .csv files are supported.');
        }

        $dir = WRITEPATH . 'uploads/bank-statements';
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $newName = $file->getRandomName();
        $file->move($dir, $newName);
        $path = $dir . DIRECTORY_SEPARATOR . $newName;

        try {
            $sheets = (new SpreadsheetReader())->sheetNames($path);
        } catch (\Throwable $e) {
            @unlink($path);

            return redirect()->back()->withInput()->with('error', 'Could not read that file: ' . $e->getMessage());
        }

        $id = $this->statements->insert([
            'bank_account_id' => $bankId,
            'statement_date'  => $date,
            'opening_balance' => $opening,
            'closing_balance' => $closing,
            'note'            => $note ?: null,
            'filename'        => $file->getClientName(),
            'stored_path'     => $path,
            'sheet'           => $sheets[0] ?? null,
            'status'          => 'draft',
            'options'         => json_encode(['headerRow' => 1, 'dateFormat' => 'auto', 'amountMode' => 'credit_in', 'map' => []]),
            'created_by'      => auth()->id(),
        ], true);

        return redirect()->to("banking/reconcile/{$id}/map");
    }

    // ------------------------------------------------------------------ map columns

    public function map(int $id)
    {
        $st = $this->find($id);
        if (! $st) {
            return $this->deny('Statement not found.');
        }
        $opt    = $this->statements->options($st);
        $sheets = (new SpreadsheetReader())->sheetNames($st['stored_path']);

        try {
            $grid = $this->grid($st);
        } catch (\Throwable $e) {
            return $this->deny('Could not read the sheet: ' . $e->getMessage());
        }

        $headerRow = (int) ($opt['headerRow'] ?? 1);

        return view('banking/reconcile/map', [
            'title'   => 'Statement · Map columns',
            'st'      => $st,
            'sheets'  => $sheets,
            'opt'     => $opt,
            'headers' => $this->recon->headers($grid, $headerRow),
            'sample'  => array_slice($this->recon->dataRows($grid, $headerRow), 0, 6),
            'fields'  => BankReconciler::FIELDS,
        ]);
    }

    public function saveMap(int $id)
    {
        if (! $this->canPost()) {
            return $this->deny();
        }
        $st = $this->find($id);
        if (! $st) {
            return $this->deny('Statement not found.');
        }

        $sheet     = $this->request->getPost('sheet') ?: $st['sheet'];
        $headerRow = max(1, (int) $this->request->getPost('header_row'));
        $map       = [];
        foreach (array_keys(BankReconciler::FIELDS) as $field) {
            $col = $this->request->getPost('map_' . $field);
            if ($col !== null && $col !== '') {
                $map[$field] = (int) $col;
            }
        }

        $missing = [];
        foreach (BankReconciler::FIELDS as $field => [$label, $required]) {
            if ($required && ! isset($map[$field])) {
                $missing[] = $label;
            }
        }
        if (! isset($map['amount']) && ! isset($map['debit']) && ! isset($map['credit'])) {
            $missing[] = 'an Amount column (or Debit / Credit)';
        }
        if ($missing) {
            return redirect()->back()->withInput()->with('error', 'Please map: ' . implode(', ', array_unique($missing)));
        }

        $opt = [
            'headerRow'  => $headerRow,
            'dateFormat' => $this->request->getPost('date_format') ?: 'auto',
            'amountMode' => $this->request->getPost('amount_mode') === 'debit_in' ? 'debit_in' : 'credit_in',
            'map'        => $map,
        ];
        $this->statements->update($id, ['sheet' => $sheet]);
        $this->statements->setOptions($id, $opt);

        $st   = $this->find($id);
        $rows = $this->recon->parseRows($this->grid($st), $map, $opt);
        $n    = $this->recon->importLines($id, $rows);
        $m    = $this->recon->autoMatch($id);

        return redirect()->to("banking/reconcile/{$id}")
            ->with('message', "Imported {$n} statement line(s); auto-matched {$m}.");
    }

    // ------------------------------------------------------------------ workspace

    public function review(int $id)
    {
        $st = $this->find($id);
        if (! $st) {
            return $this->deny('Statement not found.');
        }
        $sum  = $this->recon->summary($id);
        $bank = $this->accounts->find((int) $st['bank_account_id']);

        // book lines that are still free to match, for the manual-match pickers
        $freeBook = [];
        foreach ($sum['book'] as $b) {
            $bid = (int) $b['id'];
            if (! isset($sum['matched_book_ids'][$bid]) && ! isset($sum['claimed_elsewhere'][$bid])) {
                $freeBook[] = $b;
            }
        }

        return view('banking/reconcile/review', [
            'title'    => 'Reconcile · ' . ($bank['name'] ?? ''),
            'st'       => $st,
            'bank'     => $bank,
            'sum'      => $sum,
            'freeBook' => $freeBook,
            'expenseAccounts' => $this->postingAccounts(),
        ]);
    }

    public function rematch(int $id)
    {
        if (! $this->canPost()) {
            return $this->deny();
        }
        if (! $this->find($id)) {
            return $this->deny('Statement not found.');
        }
        $m = $this->recon->autoMatch($id);

        return redirect()->to("banking/reconcile/{$id}")->with('message', "Auto-matched {$m} more line(s).");
    }

    public function matchLine(int $id)
    {
        if (! $this->canPost()) {
            return $this->deny();
        }
        $sl = (int) $this->request->getPost('stmt_line_id');
        $bl = (int) $this->request->getPost('book_line_id');
        if (! $sl || ! $bl) {
            return redirect()->to("banking/reconcile/{$id}")->with('error', 'Pick a statement line and a book entry.');
        }
        $ok = $this->recon->match($id, $sl, $bl, 'manual');

        return redirect()->to("banking/reconcile/{$id}")->with($ok ? 'message' : 'error', $ok ? 'Matched.' : 'Could not match those rows.');
    }

    public function unmatchLine(int $id)
    {
        if (! $this->canPost()) {
            return $this->deny();
        }
        $sl = (int) $this->request->getPost('stmt_line_id');
        $this->recon->unmatch($id, $sl);

        return redirect()->to("banking/reconcile/{$id}")->with('message', 'Unmatched.');
    }

    // ------------------------------------------------------------------ add missing entry to books

    public function addEntry(int $id)
    {
        if (! $this->canPost()) {
            return $this->deny();
        }
        $st = $this->find($id);
        if (! $st) {
            return $this->deny('Statement not found.');
        }

        $slId = (int) $this->request->getPost('stmt_line_id');
        $acc  = (int) $this->request->getPost('account_id');
        $memo = trim((string) $this->request->getPost('memo'));
        $sl   = $this->lines->find($slId);

        if (! $sl || (int) $sl['statement_id'] !== $id) {
            return redirect()->to("banking/reconcile/{$id}")->with('error', 'Unknown statement line.');
        }
        if ($sl['matched_line_id'] !== null) {
            return redirect()->to("banking/reconcile/{$id}")->with('error', 'That line is already matched.');
        }
        if (! $acc) {
            return redirect()->to("banking/reconcile/{$id}")->with('error', 'Choose the offset account (e.g. bank charge, interest).');
        }

        $bankId = (int) $st['bank_account_id'];
        $amount = abs((float) $sl['amount']);
        $moneyIn = (float) $sl['amount'] > 0;
        $desc    = $memo ?: ($sl['description'] ?: 'Bank statement item');

        $lines = $moneyIn
            ? [
                ['account_id' => $bankId, 'debit' => $amount, 'credit' => 0, 'memo' => $desc],
                ['account_id' => $acc, 'debit' => 0, 'credit' => $amount, 'memo' => $desc],
            ]
            : [
                ['account_id' => $acc, 'debit' => $amount, 'credit' => 0, 'memo' => $desc],
                ['account_id' => $bankId, 'debit' => 0, 'credit' => $amount, 'memo' => $desc],
            ];

        $poster = new JournalPoster();
        $save   = $poster->save([
            'entry_date'    => $sl['txn_date'],
            'description'   => $desc,
            'source'        => $moneyIn ? 'cash_receipt' : 'cash_payment',
            'reference'     => $sl['reference'] ?: null,
            'currency_id'   => model(CurrencyModel::class)->baseId(),
            'exchange_rate' => 1,
        ], $lines);

        if (! $save['ok']) {
            return redirect()->to("banking/reconcile/{$id}")->with('errors', $save['errors']);
        }
        $post = $poster->post($save['id']);
        if (! $post['ok']) {
            $poster->deleteDraft($save['id']);

            return redirect()->to("banking/reconcile/{$id}")->with('errors', $post['errors']);
        }

        // link the new journal's bank line to this statement row
        $bankLine = Database::connect()->table('journal_lines')
            ->select('id')->where('journal_id', $save['id'])->where('account_id', $bankId)
            ->get()->getRowArray();
        if ($bankLine) {
            $this->recon->match($id, $slId, (int) $bankLine['id'], 'created');
        }

        return redirect()->to("banking/reconcile/{$id}")->with('message', 'Entry posted and matched.');
    }

    // ------------------------------------------------------------------ finish / reopen / delete

    public function finish(int $id)
    {
        if (! $this->canPost()) {
            return $this->deny();
        }
        if (! $this->find($id)) {
            return $this->deny('Statement not found.');
        }
        $sum = $this->recon->summary($id);
        if (! $sum['reconciled']) {
            return redirect()->to("banking/reconcile/{$id}")
                ->with('error', 'Difference is ' . money($sum['difference']) . ' — match or add every line first.');
        }
        $this->statements->update($id, ['status' => 'reconciled', 'reconciled_at' => date('Y-m-d H:i:s')]);

        return redirect()->to("banking/reconcile/{$id}")->with('message', 'Statement reconciled.');
    }

    public function reopen(int $id)
    {
        if (! $this->canPost()) {
            return $this->deny();
        }
        $this->statements->update($id, ['status' => 'draft', 'reconciled_at' => null]);

        return redirect()->to("banking/reconcile/{$id}")->with('message', 'Reopened for editing.');
    }

    public function destroy(int $id)
    {
        if (! $this->canPost()) {
            return $this->deny();
        }
        $st = $this->find($id);
        if (! $st) {
            return $this->deny('Statement not found.');
        }
        $this->recon->clearLines($id);
        $this->statements->delete($id);
        if ($st['stored_path'] && is_file($st['stored_path'])) {
            @unlink($st['stored_path']);
        }

        return redirect()->to('banking/reconcile')->with('message', 'Statement deleted.');
    }

    // ------------------------------------------------------------------ printable statement

    public function report(int $id)
    {
        $st = $this->find($id);
        if (! $st) {
            return $this->deny('Statement not found.');
        }

        return view('banking/reconcile/report', [
            'title' => 'Bank Reconciliation Statement',
            'st'    => $st,
            'bank'  => $this->accounts->find((int) $st['bank_account_id']),
            'sum'   => $this->recon->summary($id),
        ]);
    }

    // ------------------------------------------------------------------ helpers

    /** Accounts sensible as the offset when adding a statement item to the books. */
    private function postingAccounts(): array
    {
        return array_values(array_filter(
            $this->accounts->postable(),
            static fn ($a) => $a['is_cash'] == 0 && in_array($a['type'], ['expense', 'other_expense', 'cogs', 'other_income', 'revenue', 'liability', 'asset'], true)
        ));
    }
}
