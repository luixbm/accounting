<?php

namespace App\Controllers;

use App\Libraries\Accounting\JournalPoster;
use App\Models\AccountModel;
use App\Models\CurrencyModel;
use App\Models\CustomerModel;
use App\Models\ExchangeRateModel;
use App\Models\JournalLineModel;
use App\Models\JournalModel;
use App\Models\SupplierModel;

class JournalController extends BaseController
{
    private JournalModel $journals;

    public function __construct()
    {
        $this->journals = model(JournalModel::class);
    }

    public function index()
    {
        $filters = [
            'status' => $this->request->getGet('status'),
            'source' => $this->request->getGet('source'),
            'from'   => $this->request->getGet('from'),
            'to'     => $this->request->getGet('to'),
            'q'      => $this->request->getGet('q'),
        ];

        return view('journals/index', [
            'title'   => 'Journals',
            'rows'    => $this->journals->listing($filters),
            'pager'   => $this->journals->pager,
            'filters' => $filters,
            'sources' => JournalModel::SOURCES,
        ]);
    }

    public function new()
    {
        if (! user_can('journal.create')) {
            return redirect()->to('journals')->with('error', 'Not allowed.');
        }

        return view('journals/form', $this->formData(null));
    }

    public function edit(int $id)
    {
        $journal = $this->journals->find($id);
        if (! $journal) {
            return redirect()->to('journals')->with('error', 'Journal not found.');
        }
        if ($journal['status'] !== 'draft') {
            return redirect()->to('journals/' . $id)->with('error', 'Only draft journals can be edited.');
        }
        if (! user_can('journal.create')) {
            return redirect()->to('journals')->with('error', 'Not allowed.');
        }

        $lines = model(JournalLineModel::class)->where('journal_id', $id)->orderBy('line_no')->findAll();

        return view('journals/form', $this->formData($journal, $lines));
    }

    public function create()
    {
        return $this->persist(null);
    }

    public function update(int $id)
    {
        return $this->persist($id);
    }

    private function persist(?int $id)
    {
        if (! user_can('journal.create')) {
            return redirect()->to('journals')->with('error', 'Not allowed.');
        }

        $header = [
            'entry_date'    => $this->request->getPost('entry_date'),
            'reference'     => $this->request->getPost('reference'),
            'description'   => $this->request->getPost('description'),
            'source'        => $this->request->getPost('source') ?: 'general',
            'currency_id'   => (int) $this->request->getPost('currency_id'),
            'exchange_rate' => (float) ($this->request->getPost('exchange_rate') ?: 1),
        ];

        if (! $this->validate([
            'entry_date'  => 'required|valid_date[Y-m-d]',
            'description' => 'required|max_length[255]',
            'currency_id' => 'required|is_natural_no_zero',
        ])) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $rawLines = $this->collectLines();
        $poster   = new JournalPoster();
        $result   = $poster->save($header, $rawLines, $id);

        if (! $result['ok']) {
            return redirect()->back()->withInput()->with('errors', $result['errors']);
        }

        $jid    = $result['id'];
        $action = $this->request->getPost('action');

        if ($action === 'post' && user_can('journal.post')) {
            $postResult = $poster->post($jid);
            if (! $postResult['ok']) {
                return redirect()->to('journals/' . $jid)->with('errors', $postResult['errors']);
            }

            return redirect()->to('journals/' . $jid)->with('message', 'Journal saved and posted.');
        }

        return redirect()->to('journals/' . $jid)->with('message', 'Journal saved as draft.');
    }

    public function show(int $id)
    {
        $journal = $this->journals
            ->select('journals.*, currencies.code AS currency_code, currencies.is_base AS currency_is_base')
            ->join('currencies', 'currencies.id = journals.currency_id', 'left')
            ->find($id);
        if (! $journal) {
            return redirect()->to('journals')->with('error', 'Journal not found.');
        }

        $lines    = model(JournalLineModel::class)->forJournal($id);
        $reversal = $journal['reversal_of'] ? $this->journals->find($journal['reversal_of']) : null;
        $reversedBy = $this->journals->where('reversal_of', $id)->first();

        return view('journals/show', [
            'title'      => $journal['journal_no'],
            'journal'    => $journal,
            'lines'      => $lines,
            'reversal'   => $reversal,
            'reversedBy' => $reversedBy,
        ]);
    }

    public function post(int $id)
    {
        if (! user_can('journal.post')) {
            return redirect()->to('journals/' . $id)->with('error', 'You do not have permission to post journals.');
        }
        $result = (new JournalPoster())->post($id);

        return $result['ok']
            ? redirect()->to('journals/' . $id)->with('message', 'Journal posted.')
            : redirect()->to('journals/' . $id)->with('errors', $result['errors']);
    }

    public function void(int $id)
    {
        if (! user_can('journal.void')) {
            return redirect()->to('journals/' . $id)->with('error', 'You do not have permission to void journals.');
        }
        $reason = trim((string) $this->request->getPost('reason'));
        if ($reason === '') {
            return redirect()->to('journals/' . $id)->with('error', 'A reason is required to void a journal.');
        }
        $result = (new JournalPoster())->void($id, $reason);

        return $result['ok']
            ? redirect()->to('journals/' . $id)->with('message', 'Journal voided; reversing entry ' . ($result['reversalId'] ?? '') . ' posted.')
            : redirect()->to('journals/' . $id)->with('errors', $result['errors']);
    }

    public function delete(int $id)
    {
        if (! user_can('journal.delete')) {
            return redirect()->to('journals/' . $id)->with('error', 'You do not have permission to delete journals.');
        }
        $result = (new JournalPoster())->deleteDraft($id);

        return $result['ok']
            ? redirect()->to('journals')->with('message', 'Draft journal deleted.')
            : redirect()->to('journals/' . $id)->with('errors', $result['errors']);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function collectLines(): array
    {
        $accounts  = $this->request->getPost('line_account') ?? [];
        $memos     = $this->request->getPost('line_memo') ?? [];
        $debits    = $this->request->getPost('line_debit') ?? [];
        $credits   = $this->request->getPost('line_credit') ?? [];
        $customers = $this->request->getPost('line_customer') ?? [];
        $suppliers = $this->request->getPost('line_supplier') ?? [];
        $jobs      = $this->request->getPost('line_job') ?? [];

        $out = [];
        foreach ($accounts as $i => $accId) {
            $out[] = [
                'account_id'  => $accId,
                'memo'        => $memos[$i] ?? null,
                'debit'       => $this->num($debits[$i] ?? 0),
                'credit'      => $this->num($credits[$i] ?? 0),
                'customer_id' => $customers[$i] ?? null,
                'supplier_id' => $suppliers[$i] ?? null,
                'job_id'      => $jobs[$i] ?? null,
            ];
        }

        return $out;
    }

    private function num($v): float
    {
        return (float) str_replace([',', ' '], '', (string) $v);
    }

    /**
     * @param array<string,mixed>|null $journal
     * @param array<int,mixed>         $lines
     *
     * @return array<string,mixed>
     */
    private function formData(?array $journal, array $lines = []): array
    {
        return [
            'title'      => $journal ? 'Edit ' . $journal['journal_no'] : 'New Journal',
            'journal'    => $journal,
            'lines'      => $lines,
            'accounts'   => model(AccountModel::class)->postable(),
            'customers'  => model(CustomerModel::class)->active(),
            'suppliers'  => model(SupplierModel::class)->active(),
            'jobs'       => model(\App\Models\JobModel::class)->open(),
            'currencies' => model(CurrencyModel::class)->active(),
            'sources'    => JournalModel::SOURCES,
            'baseId'     => model(CurrencyModel::class)->baseId(),
            'canPost'    => user_can('journal.post'),
            'lastRates'  => $this->rateLookup(),
        ];
    }

    /**
     * currency_id => latest rate, for the JS rate helper.
     *
     * @return array<int,float>
     */
    private function rateLookup(): array
    {
        $rates = model(ExchangeRateModel::class);
        $out   = [];
        foreach (model(CurrencyModel::class)->active() as $c) {
            $out[(int) $c['id']] = $rates->rateFor((int) $c['id'], date('Y-m-d'));
        }

        return $out;
    }
}
