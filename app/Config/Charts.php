<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Default rendering model for the dashboard's monthly value charts (Sales,
 * EBITDA, Budget vs Actual). Stored as `Accounting.chartStyle` in settings and
 * resolved by the `chart_style()` helper; `Svg::series()` dispatches on it.
 * Horizontal-bar and combo charts are not affected.
 */
class Charts extends BaseConfig
{
    public const DEFAULT = 'bars';

    /** key => label shown in Setup -> Settings */
    public const STYLES = [
        'bars'  => 'Bars',
        'lines' => 'Lines',
        'area'  => 'Area',
    ];
}
