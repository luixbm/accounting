<?php

namespace App\Controllers;

use App\Libraries\AvatarStore;

/**
 * Self-service profile page - any logged-in user manages their own photo.
 * (Admin-side photo management for other users lives in UserController.)
 */
class ProfileController extends BaseController
{
    public function index()
    {
        return view('profile/index', [
            'title' => lang('Nav.profile'),
            'user'  => auth()->user(),
        ]);
    }

    public function update()
    {
        $id = (int) (auth()->id() ?? 0);
        if ($id <= 0) {
            return redirect()->to('login');
        }

        if ($this->request->getPost('remove_photo')) {
            AvatarStore::remove($id);

            return redirect()->to('profile')->with('message', lang('Profile.photo_removed'));
        }

        $res = AvatarStore::save($id, $this->request->getFile('photo'));
        if (! $res['ok']) {
            return redirect()->to('profile')->with('error', $res['error']);
        }

        return redirect()->to('profile')->with('message', lang('Profile.photo_updated'));
    }
}
