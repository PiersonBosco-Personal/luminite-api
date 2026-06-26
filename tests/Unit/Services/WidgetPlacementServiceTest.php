<?php

use App\Services\GridRect;
use App\Services\WidgetPlacementService;

function svc(): WidgetPlacementService
{
    return new WidgetPlacementService;
}

/** @return array{default_w:int,default_h:int,min_w:int,min_h:int} */
function wmeta(int $dw, int $dh, int $mw, int $mh): array
{
    return ['default_w' => $dw, 'default_h' => $dh, 'min_w' => $mw, 'min_h' => $mh];
}

function tuple(GridRect $r): array
{
    return [$r->x, $r->y, $r->w, $r->h];
}

it('places the first widget at the origin with its natural size', function () {
    $r = svc()->place([], wmeta(8, 6, 8, 6));
    expect(tuple($r))->toBe([0, 0, 8, 6]);
});

it('slots a widget into the gap beside an existing one at natural width', function () {
    $existing = [new GridRect(0, 0, 8, 6)];          // task board
    $r = svc()->place($existing, wmeta(6, 6, 4, 4));  // notes: default 6 wide, shrinks to the 4-wide gap
    expect(tuple($r))->toBe([8, 0, 4, 6]);
});

it('drops to a new row when the gap is too narrow for min_w', function () {
    $existing = [new GridRect(0, 0, 8, 6)];           // leftover is 4 wide
    $r = svc()->place($existing, wmeta(6, 5, 6, 4));   // min_w 6 > 4 → new row
    expect(tuple($r))->toBe([0, 6, 6, 5]);
});

it('skips a band shorter than the widget min_h and opens a new row', function () {
    $existing = [new GridRect(0, 0, 4, 4)];           // band height 4
    $r = svc()->place($existing, wmeta(4, 8, 3, 5));   // min_h 5 > 4 → new row
    expect(tuple($r))->toBe([0, 4, 4, 8]);
});

it('caps a gap-filler height at 1.5x its default', function () {
    $existing = [new GridRect(0, 0, 8, 10)];          // very tall band (10)
    $r = svc()->place($existing, wmeta(4, 4, 3, 3));   // cap = round(1.5 * 4) = 6
    expect(tuple($r))->toBe([8, 0, 4, 6]);            // height capped at 6, not 10
});

it('avoids overlap in an irregular manual layout', function () {
    $existing = [
        new GridRect(0, 0, 4, 4),                     // top-left
        new GridRect(4, 2, 8, 4),                     // occupies the right side at rows 2-5
    ];
    // The would-be slot {4,0,4,4} collides with the second rect at rows 2-3,
    // band y=2 is full, so the widget opens a new row below everything (y=6).
    $r = svc()->place($existing, wmeta(4, 4, 3, 3));
    expect(tuple($r))->toBe([0, 6, 4, 4]);
});
