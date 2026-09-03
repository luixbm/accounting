<?php

namespace App\Models;

/**
 * Company-scoped notice board. `current()` returns what a viewer should see now
 * (active + inside the optional date window); the CRUD screen lists everything.
 */
class AnnouncementModel extends TenantModel
{
    protected $table         = 'announcements';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps  = true;
    protected $allowedFields = [
        'title', 'body', 'level', 'pinned', 'is_active', 'starts_on', 'ends_on', 'created_by',
    ];

    protected $validationRules = [
        'title' => 'required|max_length[160]',
        'level' => 'permit_empty|in_list[info,warning,success]',
    ];

    /** @return list<array<string,mixed>> live announcements, most prominent first */
    public function current(): array
    {
        $today = date('Y-m-d');

        return $this
            ->where('is_active', 1)
            ->groupStart()->where('starts_on', null)->orWhere('starts_on <=', $today)->groupEnd()
            ->groupStart()->where('ends_on', null)->orWhere('ends_on >=', $today)->groupEnd()
            ->orderBy('pinned', 'DESC')
            ->orderBy('created_at', 'DESC')
            ->orderBy('id', 'DESC')
            ->findAll();
    }
}
