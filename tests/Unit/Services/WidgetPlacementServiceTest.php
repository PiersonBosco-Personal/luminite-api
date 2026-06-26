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

it('placeOne fills the gap width when slotting beside existing content', function () {
    $existing = [new GridRect(0, 0, 4, 5)];           // activity at the left, leftover 8
    $r = svc()->placeOne($existing, wmeta(4, 5, 3, 3)); // deadline: natural 4 → filled to 8
    expect(tuple($r))->toBe([4, 0, 8, 5]);
});

it('placeOne keeps natural width for a widget that opens a new row', function () {
    $existing = [new GridRect(0, 0, 8, 6)];
    $r = svc()->placeOne($existing, wmeta(6, 5, 6, 4)); // min_w 6 > leftover 4 → new row, not stretched
    expect(tuple($r))->toBe([0, 6, 6, 5]);
});

it('packSequence packs three small widgets across one band and stacks the rest', function () {
    $rects = svc()->packSequence([
        wmeta(8, 6, 8, 6),  // tasks_board
        wmeta(6, 6, 4, 4),  // notes_list
        wmeta(4, 5, 3, 3),  // activity_feed
        wmeta(4, 5, 3, 3),  // deadline_tracker
        wmeta(4, 4, 3, 3),  // label_breakdown
        wmeta(4, 8, 3, 5),  // ai_chat
    ]);
    expect(array_map('tuple', $rects))->toBe([
        [0, 0, 8, 6],   // board
        [8, 0, 4, 6],   // notes (natural width 4)
        [0, 6, 4, 5],   // activity
        [4, 6, 4, 5],   // deadline
        [8, 6, 4, 5],   // label — 3rd across, height matched to band (5)
        [0, 11, 4, 8],  // ai_chat — band y6 full → new row
    ]);
});

it('packSequence trailing-fills the last widget when a band has leftover', function () {
    $rects = svc()->packSequence([
        wmeta(6, 5, 4, 4),  // A: opens row, natural 6
        wmeta(4, 5, 3, 3),  // B: slots beside at x=6, natural 4 → x+w=10, trailing 2
    ]);
    expect(array_map('tuple', $rects))->toBe([
        [0, 0, 6, 5],
        [6, 0, 6, 5],   // B stretched from 4 to 6 to fill the trailing 2
    ]);
});

it('packSequence packs around protected existing widgets without moving them', function () {
    $protect = [new GridRect(0, 0, 8, 6)];            // pre-existing board
    $rects = svc()->packSequence([wmeta(4, 5, 3, 3)], $protect); // activity slots beside
    expect(tuple($rects[0]))->toBe([8, 0, 4, 6]);
});

it('fillTrailing does not widen a mutable widget when the rightmost is protected', function () {
    $protect = [new GridRect(8, 0, 4, 5)];                          // rightmost: x+w = 12
    $mutable = [new GridRect(0, 0, 6, 5), new GridRect(6, 0, 2, 5)]; // mutable-rightmost x+w = 8
    svc()->fillTrailing($mutable, $protect);
    expect([$mutable[0]->w, $mutable[1]->w])->toBe([6, 2]);         // neither widened
});
