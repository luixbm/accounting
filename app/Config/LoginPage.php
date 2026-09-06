<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Appearance of the public sign-in page. Every value can be overridden at
 * runtime and stored in the settings table via the Settings library, e.g.
 * setting('LoginPage.event', 'independence'). Edited from Setup -> Login Page
 * (LoginPageController); read by app/Views/auth/login.php.
 */
class LoginPage extends BaseConfig
{
    /** Overall page style: luxury (split hero + canvas) | simple (plain light card). */
    public string $style = 'luxury';

    /** Wordmark on the hero panel. Empty -> falls back to the company name. */
    public string $brand = 'LuixSpace';

    /** 1-2 character logo mark next to the wordmark. */
    public string $mark = 'LX';

    /** Small line under the brand. */
    public string $tagline = '';

    /** Hero headline. "\n" becomes a line break; the last line takes the accent colour. */
    public string $headline = "Luix Space\nAccounting Power";

    public string $subtitle = 'Multi-company, multi-currency accounting for travel operations.';

    /** Seasonal preset: default | independence | nyepi | eid | christmas | custom */
    public string $event = 'default';

    /** Background animation: full | light | off */
    public string $animation = 'full';

    /** Accent colour override (#rrggbb). Empty -> the event preset's colour. */
    public string $accent = '';

    /** Relative path under public/ to an uploaded background image. Empty -> the preset gradient. */
    public string $bgImage = '';

    /** Footer line. Empty -> "© {year} {brand or company}". */
    public string $footer = '';

    public const STYLES     = ['luxury', 'simple'];
    public const EVENTS     = ['default', 'independence', 'nyepi', 'eid', 'christmas', 'custom'];
    public const ANIMATIONS = ['full', 'light', 'off'];

    /**
     * Accent colour + CSS background gradient for each seasonal preset.
     *
     * @var array<string,array{accent:string,gradient:string}>
     */
    public const PRESETS = [
        'default'      => ['accent' => '#D4AF37', 'gradient' => 'radial-gradient(circle at 30% 40%, #16283a 0%, #0A1118 70%)'],
        'independence' => ['accent' => '#E23D3D', 'gradient' => 'linear-gradient(135deg, #2a0d0d 0%, #0A1118 55%, #241b1b 100%)'],
        'nyepi'        => ['accent' => '#9FB2C4', 'gradient' => 'radial-gradient(circle at 50% 30%, #10161d 0%, #05070a 80%)'],
        'eid'          => ['accent' => '#2FA36B', 'gradient' => 'linear-gradient(135deg, #08211a 0%, #0A1118 55%, #1c2b16 100%)'],
        'christmas'    => ['accent' => '#C9A227', 'gradient' => 'linear-gradient(135deg, #23100f 0%, #0A1118 50%, #0f2417 100%)'],
        'custom'       => ['accent' => '#D4AF37', 'gradient' => 'radial-gradient(circle at 30% 40%, #16283a 0%, #0A1118 70%)'],
    ];
}
