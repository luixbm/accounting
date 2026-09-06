<?php

namespace App\Controllers;

use App\Libraries\Accounting\RecurringJournal;
use App\Models\AccountModel;
use App\Models\CurrencyModel;
use App\Models\CustomerModel;
use App\Models\JobModel;
use App\Models\JournalModel;
use App\Models\RecurringJournalLineModel;
use App\Models\RecurringJournalModel;
use App\Models\SupplierModel;
use Config\Database;

/**
 * Recurring journal templates. Header CRUD + a line editor + a "Generate"
 * action that produces a draft general journal (RecurringJournal::generate)
 * and drops the user into the normal journal editor to review and post.
 */
class RecurringJournalController extends BaseController
{
    private function guard(): bool
    {
        return user_can('journal.create');
    }

    private function templates(): RecurringJournalModel
    {
        return model(RecurringJournalModel::class);
    }

    private function lines(): RecurringJournalLineModel
    {
        return model(RecurringJournalLineModel::class);
    }

    public function index()
    {
        if (! $this->guard()) {
            return redirect()->to('journals')->with('error', lang('App.not_allowed'));
        }

        $rows  = $this->templates()->orderBy('is_active', 'DESC')->orderBy('name', 'ASC')->findAll();
        $today = date('Y-m-d');
        foreach ($rows as &$r) {
            $ls        = $this->lines()->forTemplate((int) $r['id']);
            $r['nlines'] = count($ls);
            $r['net']  = array_sum(array_map(static fn ($l) => (float) $l['debit'], $ls));
            $r['due']  = $r['is_active'] && ! empty($r['next_date']) && $r['next_date'] <= $today;
        }
        unset($r);

        return view('journals/recurring/index', [
            'title' => lang('Nav.recurring_journals'),
            'rows'  => $rows,
        ]);
    }

    public function new()
    {
        if (! $this->guard()) {
            return redirect()->to('journals')->with('error', lang('App.not_allowed'));
        }

        return view('journals/recurring/form', $this->formData(null));
    }

    public function edit(int $id)
    {
        if (! $this->guard()) {
            return redirect()->to('journals')->with('error', lang('App.not_allowed'));
        }
        $row = $this->templates()->find($id);
        if (! $row) {
            return redirect()->to('journals/recurring')->with('error', lang('Recurring.not_found'));
        }

        return view('journals/recurring/form', $this->formData($row));
    }

    public function create()
    {
        if (! $this->guard()) {
            return redirect()->to('journals')->with('error', lang('App.not_allowed'));
        }
        $data               = $this->payload();
        $data['created_by'] = auth()->id();
        if (! $this->templates()->insert($data)) {
            return redirect()->back()->withInput()->with('errors', $this->templates()->errors());
        }

        return redirect()->to('journals/recurring/' . $this->templates()->getInsertID())
            ->with('message', lang('Recurring.saved'));
    }

    public function update(int $id)
    {
        if (! $this->guard()) {
            return redirect()->to('journals')->with('error', lang('App.not_allowed'));
        }
        if (! $this->templates()->find($id)) {
            return redirect()->to('journals/recurring')->with('error', lang('Recurring.not_found'));
        }
        if (! $this->templates()->update($id, $this->payload())) {
            return redirect()->back()->withInput()->with('errors', $this->templates()->errors());
        }

        return redirect()->to('journals/recurring/' . $id)->with('message', lang('Recurring.saved'));
    }

    public function show(int $id)
    {
        if (! $this->guard()) {
            return redirect()->to('journals')->with('error', lang('App.not_allowed'));
        }
        $tpl = $this->templates()->find($id);
        if (! $tpl) {
            return redirect()->to('journals/recurring')->with('error', lang('Recurring.not_found'));
        }

        return view('journals/recurring/show', [
            'title'      => $tpl['name'],
            'tpl'        => $tpl,
            'lines'      => $this->lines()->forTemplate($id),
            'accounts'   => model(AccountModel::class)->postable(),
            'customers'  => model(CustomerModel::class)->active(),
            'suppliers'  => model(SupplierModel::class)->active(),
            'jobs'       => model(JobModel::class)->open(),
            'currencyOf' => $this->currencyLabel((int) $tpl['currency_id']),
        ]);
    }

    public function saveLines(int $id)
    {
        if (! $this->guard()) {
            return redirect()->to('journals')->with('error', lang('App.not_allowed'));
        }
        if (! $this->templates()->find($id)) {
            return redirect()->to('journals/recurring')->with('error', lang('Recurring.not_found'));
        }

        $accounts  = $this->request->getPost('line_account') ?? [];
        $memos     = $this->request->getPost('line_memo') ?? [];
        $debits    = $this->request->getPost('line_debit') ?? [];
        $credits   = $this->request->getPost('line_credit') ?? [];
        $customers = $this->request->getPost('line_customer') ?? [];
        $suppliers = $this->request->getPost('line_supplier') ?? [];
        $jobs      = $this->request->getPost('line_job') ?? [];

        $num  = static fn ($v): float => round((float) str_replace([',', ' '], '', (string) $v), 2);
        $rows = [];
        $n    = 0;
        foreach ($accounts as $i => $accId) {
            $accId = (int) $accId;
            $d     = $num($debits[$i] ?? 0);
            $c     = $num($credits[$i] ?? 0);
            if ($accId === 0 || (abs($d) < 0.005 && abs($c) < 0.005)) {
                continue;
            }
            $rows[] = [
                'template_id' => $id,
                'line_no'     => ++$n,
                'account_id'  => $accId,
                'memo'        => trim((string) ($memos[$i] ?? '')) ?: null,
                'debit'       => $d,
                'credit'      => $c,
                'customer_id' => ((int) ($customers[$i] ?? 0)) ?: null,
                'supplier_id' => ((int) ($suppliers[$i] ?? 0)) ?: null,
                'job_id'      => ((int) ($jobs[$i] ?? 0)) ?: null,
            ];
        }

        $db = Database::connect();
        $db->transStart();
        $db->table('recurring_journal_lines')->where('template_id', $id)->delete();
        if ($rows) {
            $db->table('recurring_journal_lines')->insertBatch(array_map(static function ($r) {
                $r['created_at'] = $r['updated_at'] = date('Y-m-d H:i:s');

                return $r;
            }, $rows));
        }
        $db->transComplete();

        return redirect()->to('journals/recurring/' . $id)->with('message', lang('Recurring.lines_saved'));
    }

    public function generate(int $id)
    {
        if (! $this->guard()) {
            return redirect()->to('journals')->with('error', lang('App.not_allowed'));
        }
        $tpl = $this->templates()->find($id);
        if (! $tpl) {
            return redirect()->to('journals/recurring')->with('error', lang('Recurring.not_found'));
        }

        $entryDate = $this->request->getPost('entry_date') ?: null;
        $res       = RecurringJournal::generate($id, $entryDate);
        if (! $res['ok']) {
            return redirect()->to('journals/recurring/' . $id)->with('errors', $res['errors']);
        }

        return redirect()->to('journals/' . $res['id'] . '/edit')
            ->with('message', lang('Recurring.generated', [esc($tpl['name'])]));
    }

    public function toggle(int $id)
    {
        if (! $this->guard()) {
            return redirect()->to('journals')->with('error', lang('App.not_allowed'));
        }
        $row = $this->templates()->find($id);
        if ($row) {
            $this->templates()->update($id, ['is_active' => $row['is_active'] ? 0 : 1]);
        }

        return redirect()->to('journals/recurring');
    }

    public function delete(int $id)
    {
        if (! $this->guard()) {
            return redirect()->to('journals')->with('error', lang('App.not_allowed'));
        }
        if ($this->templates()->find($id)) {
            $this->templates()->delete($id);   // recurring_journal_lines cascade
        }

        return redirect()->to('journals/recurring')->with('message', lang('Recurring.deleted'));
    }

    // ------------------------------------------------------------------ helpers

    /** @return array<string,mixed> */
    private function formData(?array $row): array
    {
        return [
            'title'      => $row ? lang('Recurring.edit') : lang('Recurring.new'),
            'row'        => $row,
            'currencies' => model(CurrencyModel::class)->active(),
            'baseId'     => model(CurrencyModel::class)->baseId(),
            'sources'    => array_intersect_key(JournalModel::SOURCES, array_flip(JournalModel::MANUAL_SOURCES)),
            'freqs'      => RecurringJournalModel::FREQUENCIES,
        ];
    }

    private function currencyLabel(int $currencyId): string
    {
        $c = model(CurrencyModel::class)->find($currencyId);

        return $c ? (string) $c['code'] : '';
    }

    /** @return array<string,mixed> */
    private function payload(): array
    {
        $source = (string) $this->request->getPost('source');
        $freq   = (string) $this->request->getPost('frequency');
        $next   = trim((string) $this->request->getPost('next_date'));

        return [
            'name'        => trim((string) $this->request->getPost('name')),
            'description' => trim((string) $this->request->getPost('description')),
            'source'      => in_array($source, JournalModel::MANUAL_SOURCES, true) ? $source : 'general',
            'reference'   => trim((string) $this->request->getPost('reference')) ?: null,
            'currency_id' => (int) $this->request->getPost('currency_id'),
            'frequency'   => in_array($freq, RecurringJournalModel::FREQUENCIES, true) ? $freq : 'monthly',
            'next_date'   => $next !== '' ? $next : null,
            'is_active'   => $this->request->getPost('is_active') !== null ? 1 : 0,
        ];
    }
}
