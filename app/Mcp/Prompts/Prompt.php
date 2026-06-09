<?php

namespace App\Mcp\Prompts;

abstract class Prompt
{
    /** MCP prompt descriptor: ['name' => ..., 'description' => ...]. */
    abstract public function definition(): array;

    /** MCP prompt messages: [['role' => 'user', 'content' => ['type' => 'text', 'text' => ...]]]. */
    abstract public function messages(): array;
}
