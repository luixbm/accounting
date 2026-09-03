<?php

namespace App\Libraries;

use CodeIgniter\HTTP\Files\UploadedFile;

/**
 * Stores and removes user profile photos.
 *
 * Files land in public/assets/avatars/ as "user-<id>-<time>.<ext>"; the
 * web-root-relative path is written straight to users.avatar_path (that column
 * is not in Shield's UserModel::$allowedFields, so a plain builder update is the
 * simplest route). Any previous file for the user is deleted first.
 *
 * Raster uploads are downscaled to <= MAX_EDGE px when GD is available - phone
 * photos are routinely several MB and we only ever show a small circle.
 */
class AvatarStore
{
    public const DIR      = 'assets/avatars';
    public const MAX_BYTES = 2 * 1024 * 1024;
    public const MAX_EDGE  = 512;
    public const EXTS      = ['png', 'jpg', 'jpeg', 'webp', 'gif'];

    /**
     * @return array{ok:bool, error?:string, path?:string}
     */
    public static function save(int $userId, ?UploadedFile $file): array
    {
        if ($userId <= 0) {
            return ['ok' => false, 'error' => 'Unknown user.'];
        }
        if (! $file || ! $file->isValid() || $file->getError() === UPLOAD_ERR_NO_FILE) {
            return ['ok' => false, 'error' => 'No file uploaded.'];
        }
        $ext = strtolower($file->getExtension() ?: $file->getClientExtension());
        if (! in_array($ext, self::EXTS, true)) {
            return ['ok' => false, 'error' => 'Photo must be a PNG, JPG, WebP or GIF image.'];
        }
        if ($file->getSize() > self::MAX_BYTES) {
            return ['ok' => false, 'error' => 'Photo must be under 2 MB.'];
        }

        $dir = rtrim(FCPATH, '/\\') . DIRECTORY_SEPARATOR . self::DIR;
        if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
            return ['ok' => false, 'error' => 'Could not create the upload folder.'];
        }

        self::deleteFileFor($userId);

        $name = 'user-' . $userId . '-' . time() . '.' . ($ext === 'jpeg' ? 'jpg' : $ext);
        $file->move($dir, $name, true);
        $abs = $dir . DIRECTORY_SEPARATOR . $name;

        self::downscale($abs, $ext);

        $path = self::DIR . '/' . $name;
        db_connect()->table('users')->where('id', $userId)->update(['avatar_path' => $path]);

        return ['ok' => true, 'path' => $path];
    }

    /** Remove the user's photo (file + column). Safe to call when there is none. */
    public static function remove(int $userId): void
    {
        if ($userId <= 0) {
            return;
        }
        self::deleteFileFor($userId);
        db_connect()->table('users')->where('id', $userId)->update(['avatar_path' => null]);
    }

    private static function deleteFileFor(int $userId): void
    {
        $row  = db_connect()->table('users')->select('avatar_path')->where('id', $userId)->get()->getRowArray();
        $path = (string) ($row['avatar_path'] ?? '');
        if ($path !== '') {
            $abs = rtrim(FCPATH, '/\\') . DIRECTORY_SEPARATOR . ltrim($path, '/\\');
            if (is_file($abs)) {
                @unlink($abs);
            }
        }
    }

    /** Best-effort in-place downscale of a raster image. No-op without GD. */
    private static function downscale(string $abs, string $ext): void
    {
        if ($ext === 'gif' || ! extension_loaded('gd') || ! is_file($abs)) {
            return;
        }
        $info = @getimagesize($abs);
        if (! $info) {
            return;
        }
        [$w, $h] = $info;
        if ($w <= self::MAX_EDGE && $h <= self::MAX_EDGE) {
            return;
        }
        $scale = self::MAX_EDGE / max($w, $h);
        $nw    = max(1, (int) round($w * $scale));
        $nh    = max(1, (int) round($h * $scale));

        $src = match ($ext) {
            'png'  => @imagecreatefrompng($abs),
            'webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($abs) : false,
            default => @imagecreatefromjpeg($abs),
        };
        if (! $src) {
            return;
        }
        $dst = imagecreatetruecolor($nw, $nh);
        if ($ext === 'png' || $ext === 'webp') {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
        }
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        match ($ext) {
            'png'  => imagepng($dst, $abs, 6),
            'webp' => function_exists('imagewebp') ? imagewebp($dst, $abs, 82) : imagejpeg($dst, $abs, 82),
            default => imagejpeg($dst, $abs, 82),
        };
        imagedestroy($src);
        imagedestroy($dst);
    }
}
