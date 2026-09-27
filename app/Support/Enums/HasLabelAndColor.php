<?php

namespace App\Support\Enums;

/**
 * Every status or type enum implements this, so the UI can render it as a badge
 * (docs/ARCHITECTURE.md §12.7). Use together with the EnumHelpers trait.
 */
interface HasLabelAndColor
{
    /**
     * Human-readable, translated label, e.g. `__('Checked in')`.
     */
    public function label(): string;

    /**
     * Bootstrap 5 contextual colour used for the badge:
     * primary, secondary, success, danger, warning or info.
     * (Not light or dark: they lose contrast in one of the two colour modes.)
     */
    public function color(): string;
}
