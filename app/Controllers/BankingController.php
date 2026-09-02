<?php

namespace App\Controllers;

use App\Libraries\Accounting\JournalPoster;
use App\Models\AccountModel;
use App\Models\CurrencyModel;
use App\Models\JobModel;
use App\Models\JournalModel;

/**
 * Friendly bank-side transaction entry. Everything posts through JournalPoster
 * so period locks, balancing and multi-currency behave like any journal.
 */
class BankingController extends BaseController
{
    private AccountModel $accounts;

    public function __construct()
    {
        $this->accounts = model(AccountModel::class);
    }

    private function canPost(): bool
    {
        return user_can('journal.post');
    }

    public function index()
    {
        $recent = model(JournalModel::class)
            ->select('journals.*, currencies.code AS currency_code')
            ->join('currencies', 'currencies.id = journals.currency_id', 'left')
            ->whereIn('journals.source', ['cash_payment', 'cash_receipt', 'general'])
            ->orderBy('journals.id', 'DESC')
            ->findAll(12);

        $cash = [];
        $ledger = new \App\Libraries\Accounting\Ledger();
        foreach ($this->accounts->cashAccounts() as $c) {
            $cash[] = ['name' => $c['name'], 'code' => $c['code'], 'id' => $c['id'],
                'balance' => $ledger->accountBalance((int) $c['id'], date('Y-m-d'))];
        }

        return view('banking/index', [
            'title'  => 'Banking',
            'cash'   => $cash,
            'recent' => $recent,
        ]);
    }

    // ---------------------------------------------------------------- transfer

    public function transferForm()
    {
        return view('banking/transfer', [
            'title'      => 'Bank Transfer',
            'banks'      => $this->accounts->cashAccounts(),
            'feeAccts'   => $this->expenseAccounts(),
        ]);
    }

    public function transfer()
    {
        if (! $this->canPost()) {
            return redirect()->to('banking')->with('error', 'Not allowed.');
        }
        $from = (int) $this->request->getPost('from_account');
        $to   = (int) $this->request->getPost('to_account');
        $date = $this->request->getPost('date');
        $amt  = $this->num($this->request->getPost('amount'));
        $fee  = $this->num($this->request->getPost('fee'));
        $feeAcc = (int) $this->request->getPost('fee_account');
        $memo = trim((string) $this->request->getPost('memo'));

        if ($from === 0 || $to === 0 || $from === $to) {
            return redirect()->back()->withInput()->with('error', 'Choose two different accounts.');
        }
        if ($amt <= 0) {
            return redirect()->back()->withInput()->with('error', 'Enter an amount.');
        }
        if ($fee > 0 && $feeAcc === 0) {
            return redirect()->back()->withInput()->with('error', 'Choose an account for the bank fee.');
        }

        $lines = [
            ['account_id' => $to, 'debit' => $amt, 'credit' => 0, 'memo' => $memo ?: 'Transfer in'],
            ['account_id' => $from, 'debit' => 0, 'credit' => $amt + $fee, 'memo' => $memo ?: 'Transfer out'],
        ];
        if ($fee > 0) {
            $lines[] = ['account_id' => $feeAcc, 'debit' => $fee, 'credit' => 0, 'memo' => 'Bank charge'];
        }

        return $this->postJournal([
            'entry_date'  => $date,
            'description' => $memo ?: 'Bank transfer',
            'source'      => 'general',
            'reference'   => $this->request->getPost('reference'),
        ], $lines, 'banking/transfer', 'Transfer posted.');
    }

    // ------------------------------------------------------------ spend / receive

    public function spendForm()
    {
        return view('banking/money', [
            'title'     => 'Spend Money',
            'mode'      => 'spend',
            'banks'     => $this->accounts->cashAccounts(),
            'accounts'  => $this->accounts->postable(),
            'jobs'      => model(JobModel::class)->open(),
        ]);
    }

    public function receiveForm()
    {
        return view('banking/money', [
            'title'     => 'Receive Money',
            'mode'      => 'receive',
            'banks'     => $this->accounts->cashAccounts(),
            'accounts'  => $this->accounts->postable(),
            'jobs'      => model(JobModel::class)->open(),
        ]);
    }

    public function money(string $mode)
    {
        if (! $this->canPost()) {
            return redirect()->to('banking')->with('error', 'Not allowed.');
        }
        $mode  = $mode === 'receive' ? 'receive' : 'spend';
        $bank  = (int) $this->request->getPost('bank_account');
        $date  = $this->request->getPost('date');
        $memo  = trim((string) $this->request->getPost('memo'));

        if ($bank === 0) {
            return redirect()->back()->withInput()->with('error', 'Choose a bank / cash account.');
        }

        $accs = $this->request->getPost('line_account') ?? [];
        $jobs = $this->request->getPost('line_job') ?? [];
        $descs = $this->request->getPost('line_desc') ?? [];
        $amts = $this->request->getPost('line_amount') ?? [];

        $lines   = [];
        $total   = 0.0;
        foreach ($accs as $i => $a) {
            $amount = $this->num($amts[$i] ?? 0);
            if (! $a || $amount <= 0) {
                continue;
            }
            $total += $amount;
            $lines[] = [
                'account_id' => (int) $a,
                'job_id'     => $jobs[$i] ?? null,
                'memo'       => $descs[$i] ?? $memo,
                'debit'      => $mode === 'spend' ? $amount : 0,
                'credit'     => $mode === 'spend' ? 0 : $amount,
            ];
        }
        if (! $lines) {
            return redirect()->back()->withInput()->with('error', 'Add at least one line.');
        }

        $lines[] = $mode === 'spend'
            ? ['account_id' => $bank, 'debit' => 0, 'credit' => $total, 'memo' => $memo ?: 'Payment']
            : ['account_id' => $bank, 'debit' => $total, 'credit' => 0, 'memo' => $memo ?: 'Receipt'];

        return $this->postJournal([
            'entry_date'  => $date,
            'description' => $memo ?: ($mode === 'spend' ? 'Spend money' : 'Receive money'),
            'source'      => $mode === 'spend' ? 'cash_payment' : 'cash_receipt',
            'reference'   => $this->request->getPost('reference'),
        ], $lines, 'banking/' . $mode, ucfirst($mode) . ' money posted.');
    }

    // ---------------------------------------------------------------- helpers

    private function postJournal(array $header, array $lines, string $backTo, string $okMsg)
    {
        $header['currency_id']   = model(CurrencyModel::class)->baseId();
        $header['exchange_rate'] = 1;

        $poster = new JournalPoster();
        $save   = $poster->save($header, $lines);
        if (! $save['ok']) {
            return redirect()->back()->withInput()->with('errors', $save['errors']);
        }
        $post = $poster->post($save['id']);
        if (! $post['ok']) {
            $poster->deleteDraft($save['id']);

            return redirect()->back()->withInput()->with('errors', $post['errors']);
        }

        return redirect()->to('journals/' . $save['id'])->with('message', $okMsg);
    }

    private function expenseAccounts(): array
    {
        return array_values(array_filter(
            $this->accounts->postable(),
            static fn ($a) => in_array($a['type'], ['expense', 'other_expense', 'cogs'], true)
        ));
    }

    private function num($v): float
    {
        return (float) str_replace([',', ' '], '', (string) $v);
    }
}
