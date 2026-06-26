<?php

namespace App\Services;

/**
 * A rectangle on the dashboard grid. Mutable: `w` is widened in place during
 * trailing-fill, so the properties are intentionally not readonly.
 */
final class GridRect
{
    public function __construct(
        public int $x,
        public int $y,
        public int $w,
        public int $h,
    ) {}
}
