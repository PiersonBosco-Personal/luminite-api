<?php

use App\AI\Contracts\AIProvider;
use App\AI\Providers\OpenAiProvider;
use Illuminate\Support\Facades\Http;

it('posts to the OpenAI embeddings endpoint and returns the vector', function () {
    Http::fake([
        '*/embeddings' => Http::response([
            'data' => [['embedding' => [0.1, 0.2, 0.3]]],
        ], 200),
    ]);

    $provider = new OpenAiProvider('sk-test', 'text-embedding-3-small', 'https://api.openai.com/v1');
    $vector = $provider->embed('hello world');

    expect($vector)->toBe([0.1, 0.2, 0.3]);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/embeddings')
            && $request->hasHeader('Authorization', 'Bearer sk-test')
            && $request['model'] === 'text-embedding-3-small'
            && $request['input'] === 'hello world';
    });
});

it('throws when the embeddings request fails', function () {
    Http::fake(['*/embeddings' => Http::response('nope', 500)]);

    $provider = new OpenAiProvider('sk-test', 'text-embedding-3-small', 'https://api.openai.com/v1');

    expect(fn () => $provider->embed('x'))->toThrow(RuntimeException::class);
});

it('throws on a 200 response with a malformed body', function () {
    Http::fake(['*/embeddings' => Http::response(['data' => []], 200)]);

    $provider = new OpenAiProvider('sk-test', 'text-embedding-3-small', 'https://api.openai.com/v1');

    expect(fn () => $provider->embed('x'))->toThrow(RuntimeException::class);
});

it('resolves AIProvider from the container as the OpenAI provider', function () {
    config(['ai.provider' => 'openai', 'ai.openai.key' => 'sk-x', 'ai.openai.embed_model' => 'text-embedding-3-small', 'ai.openai.base_url' => 'https://api.openai.com/v1']);

    // Re-bind fresh from config (the global test bind swaps in a fake; here we assert the real wiring).
    app()->forgetInstance(AIProvider::class);
    (new App\Providers\AIServiceProvider(app()))->register();

    expect(app(AIProvider::class))->toBeInstanceOf(OpenAiProvider::class);
});
