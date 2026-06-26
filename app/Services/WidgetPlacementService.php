<?php

namespace App\Services;

final class WidgetPlacementService
{
    public const COLS = 12;

    /** A gap-filler's height may stretch up to this multiple of its default_h. */
    public const float HEIGHT_STRETCH_FACTOR = 1.5;

    /**
     * Position + NATURAL width for ONE new widget against the already-placed set.
     * Never stretches trailing width and never mutates $existing.
     *
     * @param  GridRect[]  $existing
     * @param  array{default_w:int,default_h:int,min_w:int,min_h:int}  $widget
     */
    public function place(array $existing, array $widget): GridRect
    {
        if ($existing === []) {
            return new GridRect(0, 0, $widget['default_w'], $widget['default_h']);
        }

        $maxStretchH = (int) round(self::HEIGHT_STRETCH_FACTOR * $widget['default_h']);

        foreach ($this->candidateYs($existing) as $y) {
            $band = array_filter($existing, fn (GridRect $e) => $e->y <= $y && $y < $e->y + $e->h);
            if ($band === []) {
                continue;
            }

            $usedRight = max(array_map(fn (GridRect $e) => $e->x + $e->w, $band));
            $leftover = self::COLS - $usedRight;
            if ($leftover < $widget['min_w']) {
                continue;
            }

            $bandHeight = max(array_map(fn (GridRect $e) => $e->y + $e->h, $band)) - $y;
            if ($widget['min_h'] > $bandHeight) {
                continue;
            }

            $h = $this->clamp($bandHeight, $widget['min_h'], $maxStretchH);
            $w = min($widget['default_w'], $leftover);

            if ($this->rectIsFree($existing, $usedRight, $y, $w, $h)) {
                return new GridRect($usedRight, $y, $w, $h);
            }
        }

        $newY = max(array_map(fn (GridRect $e) => $e->y + $e->h, $existing));

        return new GridRect(0, $newY, $widget['default_w'], $widget['default_h']);
    }

    /**
     * Candidate band tops: 0 plus every existing widget's top, ascending & unique.
     * Sub-band gaps (existing widgets' bottoms) are not scanned here; that is
     * intentional for this first-fit pass.
     *
     * @param  GridRect[]  $existing
     * @return int[]
     */
    private function candidateYs(array $existing): array
    {
        $ys = [0];
        foreach ($existing as $e) {
            $ys[] = $e->y;
        }
        $ys = array_values(array_unique($ys));
        sort($ys);

        return $ys;
    }

    /**
     * True iff [x,x+w) x [y,y+h) overlaps no rectangle in $rects (AABB test).
     *
     * @param  GridRect[]  $rects
     */
    private function rectIsFree(array $rects, int $x, int $y, int $w, int $h): bool
    {
        foreach ($rects as $r) {
            $overlapX = $x < $r->x + $r->w && $r->x < $x + $w;
            $overlapY = $y < $r->y + $r->h && $r->y < $y + $h;
            if ($overlapX && $overlapY) {
                return false;
            }
        }

        return true;
    }

    private function clamp(int $v, int $lo, int $hi): int
    {
        return max($lo, min($v, $hi));
    }
}
