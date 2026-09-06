<?php

namespace App\Controllers;

use Config\LoginPage;

/**
 * Setup -> Login Page: the appearance of the public sign-in page (seasonal
 * event, animation style, accent colour, background image, hero copy). Values
 * are stored globally via the Settings library and read by app/Views/auth/login.php.
 */
class LoginPageController extends BaseController
{
    /** Text keys stored as-is. */
    private const TEXT_KEYS = ['brand', 'mark', 'tagline', 'headline', 'subtitle', 'footer'];

    public function index()
    {
        return view('settings/login_page', [
            'title' => lang('LoginPage.title'),
            'cfg'   => $this->values(),
        ]);
    }

    public function save()
    {
        foreach (self::TEXT_KEYS as $k) {
            setting()->set('LoginPage.' . $k, trim((string) $this->request->getPost($k)));
        }

        $style = (string) $this->request->getPost('style');
        setting()->set('LoginPage.style', in_array($style, LoginPage::STYLES, true) ? $style : 'luxury');

        $event = (string) $this->request->getPost('event');
        setting()->set('LoginPage.event', in_array($event, LoginPage::EVENTS, true) ? $event : 'default');

        $anim = (string) $this->request->getPost('animation');
        setting()->set('LoginPage.animation', in_array($anim, LoginPage::ANIMATIONS, true) ? $anim : 'full');

        $accent = trim((string) $this->request->getPost('accent'));
        setting()->set('LoginPage.accent', preg_match('/^#[0-9a-fA-F]{6}$/', $accent) ? strtoupper($accent) : '');

        $this->handleBackground();

        return redirect()->to('settings/login-page')->with('message', lang('LoginPage.saved'));
    }

    /** @return array<string,string> effective values (DB override, else config default) */
    private function values(): array
    {
        $cfg  = new LoginPage();
        $keys = array_merge(self::TEXT_KEYS, ['style', 'event', 'animation', 'accent', 'bgImage']);
        $out  = [];
        foreach ($keys as $k) {
            $db      = (string) (setting('LoginPage.' . $k) ?? '');
            $out[$k] = $db !== '' ? $db : (string) ($cfg->{$k} ?? '');
        }

        return $out;
    }

    private function handleBackground(): void
    {
        $current = (string) (setting('LoginPage.bgImage') ?? '');

        if ($this->request->getPost('remove_bg')) {
            if ($current !== '' && is_file(FCPATH . $current)) {
                @unlink(FCPATH . $current);
            }
            setting()->set('LoginPage.bgImage', '');

            return;
        }

        $file = $this->request->getFile('bg_image');
        if (! $file || ! $file->isValid() || $file->getError() === UPLOAD_ERR_NO_FILE) {
            return;
        }
        $ext = strtolower($file->getExtension() ?: $file->getClientExtension());
        if (! in_array($ext, ['png', 'jpg', 'jpeg', 'webp'], true) || $file->getSize() > 4 * 1024 * 1024) {
            session()->setFlashdata('error', lang('LoginPage.bad_image'));

            return;
        }

        $dir = FCPATH . 'assets/branding';
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        if ($current !== '' && is_file(FCPATH . $current)) {
            @unlink(FCPATH . $current);
        }
        $name = 'login-bg-' . time() . '.' . $ext;
        $file->move($dir, $name, true);
        setting()->set('LoginPage.bgImage', 'assets/branding/' . $name);
    }
}
