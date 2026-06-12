<?php

use App\Mcp\Tools\Concerns\BuildsNoteContent;

$harness = new class {
    use BuildsNoteContent;
    public function toJson(string $t): string { return $this->textToTiptap($t); }
    public function append(?string $j, string $t): string { return $this->appendToTiptap($j, $t); }
};

it('wraps plain text into a Tiptap doc with one paragraph per line', function () use ($harness) {
    $json = $harness->toJson("Line one\nLine two");
    $doc  = json_decode($json, true);

    expect($doc['type'])->toBe('doc')
        ->and($doc['content'])->toHaveCount(2)
        ->and($doc['content'][0]['content'][0]['text'])->toBe('Line one')
        ->and($doc['content'][1]['content'][0]['text'])->toBe('Line two');
});

it('appends paragraphs to an existing Tiptap doc', function () use ($harness) {
    $start = $harness->toJson('First');
    $json  = $harness->append($start, 'Second');
    $doc   = json_decode($json, true);

    expect($doc['content'])->toHaveCount(2)
        ->and($doc['content'][1]['content'][0]['text'])->toBe('Second');
});

it('appends to null/blank content by starting a fresh doc', function () use ($harness) {
    $json = $harness->append(null, 'Only');
    $doc  = json_decode($json, true);

    expect($doc['content'])->toHaveCount(1)
        ->and($doc['content'][0]['content'][0]['text'])->toBe('Only');
});

it('splits Windows \\r\\n line endings into clean paragraphs without dangling \\r', function () use ($harness) {
    $json = $harness->toJson("Alpha\r\nBeta");
    $doc  = json_decode($json, true);

    expect($doc['content'])->toHaveCount(2)
        ->and($doc['content'][0]['content'][0]['text'])->toBe('Alpha')
        ->and($doc['content'][1]['content'][0]['text'])->toBe('Beta');
});

it('appendToTiptap treats a blank string like null and starts a fresh doc', function () use ($harness) {
    $json = $harness->append('', 'Solo');
    $doc  = json_decode($json, true);

    expect($doc['content'])->toHaveCount(1)
        ->and($doc['content'][0]['content'][0]['text'])->toBe('Solo');
});
