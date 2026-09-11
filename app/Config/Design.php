<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * UI design (shell layout). Orthogonal to the colour theme (light/green/blue/
 * dark) and the UI language - those still apply inside whichever design is
 * active. The chosen key is stored as `Accounting.design` in settings and
 * resolved by the `active_design()` helper.
 */
class Design extends BaseConfig
{
    public const DEFAULT = 'classic';

    /** key => label shown in Setup -> Settings */
    public const DESIGNS = [
        'classic' => 'Classic — left sidebar',
        'modern'  => 'Modern — top bar',
        'forest'  => 'Forest — green header & tabs',
    ];
}
