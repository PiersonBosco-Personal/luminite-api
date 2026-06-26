<?php

namespace App\Services;

final class WidgetPlacementService
{
    public const COLS = 12;

    /**
     * A gap-filler's height may stretch up to this multiple of its default_h.
     *
     * @var float
     */
    public const HEIGHT_STRETCH_FACTOR = 1.5;

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
     * Picker single-add: place(), then fill the band's trailing leftover when
     * the widget landed as a gap-filler (x > 0). A new-row widget (x == 0)
     * keeps its natural width.
     *
     * @param  GridRect[]  $existing
     * @param  array{default_w:int,default_h:int,min_w:int,min_h:int}  $widget
     */
    public function placeOne(array $existing, array $widget): GridRect
    {
        $rect = $this->place($existing, $widget);

        if ($rect->x > 0) {
            $rect->w = self::COLS - $rect->x;
        }

        return $rect;
    }

    /**
     * Batch packer (MCP init): place each widget in order against the
     * accumulating set (plus any read-only $protect widgets), then trailing-fill
     * the rightmost widget of every band that holds >= 2 widgets.
     *
     * @param  array{default_w:int,default_h:int,min_w:int,min_h:int}[]  $widgets  ordered
     * @param  GridRect[]  $protect  already-placed widgets to pack around (never modified)
     * @return GridRect[] one rect per input widget, in the same order
     */
    public function packSequence(array $widgets, array $protect = []): array
    {
        $placed = [];
        foreach ($widgets as $widget) {
            $placed[] = $this->place([...$protect, ...$placed], $widget);
        }

        $this->fillTrailing($placed, $protect);

        return $placed;
    }

    /**
     * Widen the rightmost widget of every band (grouped by top y) that holds
     * >= 2 widgets, to consume trailing leftover. Only widgets in $mutable are
     * modified; $protect widgets are considered for band membership and
     * collision but never changed. Rects in $mutable must be non-overlapping
     * (as guaranteed by place()).
     *
     * @param  GridRect[]  $mutable  modified in place
     * @param  GridRect[]  $protect
     */
    public function fillTrailing(array $mutable, array $protect = []): void
    {
        $all = [...$protect, ...$mutable];

        $byBand = [];
        foreach ($all as $rect) {
            $byBand[$rect->y][] = $rect;
        }

        foreach ($byBand as $band) {
            if (count($band) < 2) {
                continue;
            }

            // Strict >: on a tie the earlier rect wins. $all is protect-first,
            // so a tied protected widget is chosen and the band is
            // conservatively skipped.
            $last = $band[0];
            foreach ($band as $r) {
                if ($r->x + $r->w > $last->x + $last->w) {
                    $last = $r;
                }
            }

            if (! in_array($last, $mutable, true)) {
                continue; // rightmost is a protected widget — never widen it
            }

            $trailing = self::COLS - ($last->x + $last->w);
            if ($trailing <= 0) {
                continue;
            }

            $others = array_filter($all, fn (GridRect $r) => $r !== $last);
            if ($this->rectIsFree($others, $last->x, $last->y, $last->w + $trailing, $last->h)) {
                $last->w += $trailing;
            }
        }
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
