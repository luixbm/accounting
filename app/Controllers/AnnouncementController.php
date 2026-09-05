<?php

namespace App\Controllers;

use App\Models\AnnouncementModel;

/**
 * Notice board CRUD. Gated by settings.manage (route filter). What viewers see
 * is resolved by AnnouncementModel::current() via the current_announcements()
 * helper - the layout renders pinned ones as dismissible banners and the
 * dashboard shows them all in a card.
 */
class AnnouncementController extends BaseController
{
    private const LEVELS = ['info', 'warning', 'success'];

    private function model(): AnnouncementModel
    {
        return model(AnnouncementModel::class);
    }

    public function index()
    {
        $rows = $this->model()
            ->orderBy('is_active', 'DESC')
            ->orderBy('pinned', 'DESC')
            ->orderBy('id', 'DESC')
            ->findAll();

        return view('announcements/index', [
            'title' => lang('Nav.announcements'),
            'rows'  => $rows,
        ]);
    }

    public function new()
    {
        return view('announcements/form', [
            'title' => lang('Announce.new'),
            'row'   => null,
        ]);
    }

    public function edit(int $id)
    {
        $row = $this->model()->find($id);
        if (! $row) {
            return redirect()->to('announcements')->with('error', lang('Announce.not_found'));
        }

        return view('announcements/form', [
            'title' => lang('Announce.edit'),
            'row'   => $row,
        ]);
    }

    public function create()
    {
        $data = $this->payload();
        $data['created_by'] = auth()->id();
        if (! $this->model()->insert($data)) {
            return redirect()->back()->withInput()->with('errors', $this->model()->errors());
        }

        return redirect()->to('announcements')->with('message', lang('Announce.saved'));
    }

    public function update(int $id)
    {
        if (! $this->model()->find($id)) {
            return redirect()->to('announcements')->with('error', lang('Announce.not_found'));
        }
        if (! $this->model()->update($id, $this->payload())) {
            return redirect()->back()->withInput()->with('errors', $this->model()->errors());
        }

        return redirect()->to('announcements')->with('message', lang('Announce.saved'));
    }

    public function toggle(int $id)
    {
        $row = $this->model()->find($id);
        if ($row) {
            $this->model()->update($id, ['is_active' => $row['is_active'] ? 0 : 1]);
        }

        return redirect()->to('announcements');
    }

    public function delete(int $id)
    {
        $this->model()->delete($id);

        return redirect()->to('announcements')->with('message', lang('Announce.deleted'));
    }

    /** @return array<string,mixed> */
    private function payload(): array
    {
        $level = (string) $this->request->getPost('level');
        $clean = fn (string $k): ?string => ($v = trim((string) $this->request->getPost($k))) !== '' ? $v : null;

        return [
            'title'         => trim((string) $this->request->getPost('title')),
            'body'          => $clean('body'),
            'level'         => in_array($level, self::LEVELS, true) ? $level : 'info',
            'pinned'        => $this->request->getPost('pinned') !== null ? 1 : 0,
            'show_on_login' => $this->request->getPost('show_on_login') !== null ? 1 : 0,
            'is_active'     => $this->request->getPost('is_active') !== null ? 1 : 0,
            'starts_on'     => $clean('starts_on'),
            'ends_on'       => $clean('ends_on'),
        ];
    }
}
