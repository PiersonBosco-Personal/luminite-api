<?php

namespace App\Mcp\Validation;

use App\Mcp\Tools\ToolInputException;

/**
 * The initialize_project security boundary. Validates and normalizes the raw
 * payload entirely in PHP: every cap, enum, index range, and unknown-key rule.
 * Index-based references only — a payload cannot name anything outside itself.
 */
class InitializeProjectPayload
{
    public const PRIORITIES = ['low', 'medium', 'high', 'urgent'];

    /** Same rule as StoreLabelRequest (developer decision: no fixed palette). */
    public const COLOR_PATTERN = '/^#[0-9a-f]{6}$/';

    public const MAX_TEXT             = 5000;
    public const MAX_TECH_STACK       = 30;
    public const MAX_SECTIONS         = 6;
    public const MAX_LABELS           = 10;
    public const MAX_TASKS            = 25;
    public const MAX_WIDGETS          = 6;
    public const MAX_TITLE            = 200;
    public const MAX_TASK_DESCRIPTION = 2000;
    public const MAX_STACK_NAME       = 100;
    public const MAX_STACK_VERSION    = 50;
    public const MAX_SECTION_NAME     = 100;
    public const MAX_LABEL_NAME       = 50;

    /** Hard cap on the number of accumulated errors to prevent message bloat. */
    private const MAX_ERRORS = 50;

    /** @var string[] */
    private array $errors = [];

    /**
     * @param  string[]  $validWidgetSlugs  active slugs from the widgets table
     * @return array  normalized payload: details, tech_stack, sections, labels, tasks, widgets
     * @throws ToolInputException  listing every problem found
     */
    public function validate(array $args, array $validWidgetSlugs): array
    {
        $this->errors = [];

        $this->rejectUnknownKeys($args, ['details', 'tech_stack', 'sections', 'labels', 'tasks', 'widgets'], 'payload');

        $details  = $this->validateDetails($args['details'] ?? null);
        $stack    = $this->validateTechStack($args['tech_stack'] ?? []);
        $sections = $this->validateSections($args['sections'] ?? []);
        $labels   = $this->validateLabels($args['labels'] ?? []);
        $tasks    = $this->validateTasks($args['tasks'] ?? [], count($sections), count($labels));
        $widgets  = $this->validateWidgets($args['widgets'] ?? [], $validWidgetSlugs);

        if ($this->errors) {
            throw new ToolInputException(implode(' ', array_unique($this->errors)));
        }

        return [
            'details'    => $details,
            'tech_stack' => $stack,
            'sections'   => $sections,
            'labels'     => $labels,
            'tasks'      => $tasks,
            'widgets'    => $widgets,
        ];
    }

    /**
     * Append an error message, but only while under the hard cap.
     * At throw time, array_unique removes any duplicates before implode.
     */
    private function addError(string $message): void
    {
        if (count($this->errors) < self::MAX_ERRORS) {
            $this->errors[] = $message;
        }
    }

    private function rejectUnknownKeys(array $arr, array $allowed, string $where): void
    {
        foreach (array_diff(array_keys($arr), $allowed) as $key) {
            // Keys are attacker-controlled: scrub invalid UTF-8 / control chars
            // and bound the length so the message stays JSON-encodable.
            $key = mb_substr((string) preg_replace('/[^\P{C}\n\t]+/u', '', (string) $key), 0, 50);

            $this->addError("Unknown key '{$key}' in {$where}.");
        }
    }

    /**
     * Sanitize a field value to a plain string, strip control characters
     * (keeping \n and \t), and trim whitespace.
     *
     * Returns '' for null (absent optional fields stay silent).
     * Records an error and returns '' for any other non-string type.
     * Records an error and returns '' when the value contains invalid UTF-8.
     */
    private function clean(mixed $value, string $where): string
    {
        if ($value === null) {
            return '';
        }

        if (! is_string($value)) {
            $this->addError("{$where} must be a string.");

            return '';
        }

        $result = preg_replace('/[^\P{C}\n\t]+/u', '', $value);

        if ($result === null) {
            $this->addError("{$where} contains invalid UTF-8.");

            return '';
        }

        return trim($result);
    }

    private function validateDetails(mixed $details): array
    {
        if (! is_array($details)) {
            $this->addError('details is required and must be an object.');

            return ['description' => '', 'goals' => '', 'architecture_notes' => ''];
        }

        $this->rejectUnknownKeys($details, ['description', 'goals', 'architecture_notes'], 'details');

        $out = [];
        foreach (['description', 'goals', 'architecture_notes'] as $field) {
            $out[$field] = $this->clean($details[$field] ?? null, "details.{$field}");
            if (mb_strlen($out[$field]) > self::MAX_TEXT) {
                $this->addError("details.{$field} exceeds " . self::MAX_TEXT . ' characters.');
            }
        }

        if ($out['description'] === '') {
            $this->addError('details.description is required.');
        }

        return $out;
    }

    private function validateTechStack(mixed $stack): array
    {
        if (! is_array($stack)) {
            $this->addError('tech_stack must be an array.');

            return [];
        }

        if (count($stack) > self::MAX_TECH_STACK) {
            $this->addError('tech_stack exceeds ' . self::MAX_TECH_STACK . ' total entries (parents + children).');

            return [];
        }

        $out   = [];
        $total = 0;

        foreach (array_values($stack) as $i => $entry) {
            if (! is_array($entry)) {
                $this->addError("tech_stack[{$i}] must be an object.");
                continue;
            }

            $this->rejectUnknownKeys($entry, ['name', 'version', 'children'], "tech_stack[{$i}]");

            $parent = $this->validateStackEntry($entry, "tech_stack[{$i}]");
            $total++;

            $parent['children'] = [];

            $children = $entry['children'] ?? [];
            if (! is_array($children)) {
                $this->addError("tech_stack[{$i}].children must be an array.");
                $children = [];
            }

            if (count($children) > self::MAX_TECH_STACK) {
                $this->addError('tech_stack exceeds ' . self::MAX_TECH_STACK . ' total entries (parents + children).');
                $children = [];
            }

            foreach (array_values($children) as $j => $child) {
                if (! is_array($child)) {
                    $this->addError("tech_stack[{$i}].children[{$j}] must be an object.");
                    continue;
                }

                // One level deep only — children cannot have children.
                $this->rejectUnknownKeys($child, ['name', 'version'], "tech_stack[{$i}].children[{$j}]");

                $parent['children'][] = $this->validateStackEntry($child, "tech_stack[{$i}].children[{$j}]");
                $total++;
            }

            $out[] = $parent;
        }

        if ($total > self::MAX_TECH_STACK) {
            $this->addError('tech_stack exceeds ' . self::MAX_TECH_STACK . ' total entries (parents + children).');
        }

        return $out;
    }

    private function validateStackEntry(array $entry, string $where): array
    {
        $name = $this->clean($entry['name'] ?? null, "{$where}.name");
        if ($name === '' || mb_strlen($name) > self::MAX_STACK_NAME) {
            $this->addError("{$where}.name is required and must be 1-" . self::MAX_STACK_NAME . ' characters.');
        }

        $version = $this->clean($entry['version'] ?? null, "{$where}.version");
        if (mb_strlen($version) > self::MAX_STACK_VERSION) {
            $this->addError("{$where}.version exceeds " . self::MAX_STACK_VERSION . ' characters.');
        }

        return ['name' => $name, 'version' => $version === '' ? null : $version];
    }

    private function validateSections(mixed $sections): array
    {
        if (! is_array($sections)) {
            $this->addError('sections must be an array of strings.');

            return [];
        }

        if (count($sections) > self::MAX_SECTIONS) {
            $this->addError('sections exceeds ' . self::MAX_SECTIONS . ' entries.');

            return [];
        }

        $out = [];
        foreach (array_values($sections) as $i => $name) {
            $name = $this->clean($name, "sections[{$i}]");
            if ($name === '' || mb_strlen($name) > self::MAX_SECTION_NAME) {
                $this->addError("sections[{$i}] must be a string of 1-" . self::MAX_SECTION_NAME . ' characters.');
            }
            $out[] = $name;
        }

        return $out;
    }

    private function validateLabels(mixed $labels): array
    {
        if (! is_array($labels)) {
            $this->addError('labels must be an array.');

            return [];
        }

        if (count($labels) > self::MAX_LABELS) {
            $this->addError('labels exceeds ' . self::MAX_LABELS . ' entries.');

            return [];
        }

        $out = [];
        foreach (array_values($labels) as $i => $label) {
            if (! is_array($label)) {
                $this->addError("labels[{$i}] must be an object.");
                continue;
            }

            $this->rejectUnknownKeys($label, ['name', 'color'], "labels[{$i}]");

            $name = $this->clean($label['name'] ?? null, "labels[{$i}].name");
            if ($name === '' || mb_strlen($name) > self::MAX_LABEL_NAME) {
                $this->addError("labels[{$i}].name is required and must be 1-" . self::MAX_LABEL_NAME . ' characters.');
            }

            $color = strtolower($this->clean($label['color'] ?? null, "labels[{$i}].color"));
            if (! preg_match(self::COLOR_PATTERN, $color)) {
                $this->addError("labels[{$i}].color must be a hex color like #2ebbcc.");
            }

            $out[] = ['name' => $name, 'color' => $color];
        }

        return $out;
    }

    private function validateTasks(mixed $tasks, int $sectionCount, int $labelCount): array
    {
        if (! is_array($tasks)) {
            $this->addError('tasks must be an array.');

            return [];
        }

        if (count($tasks) > self::MAX_TASKS) {
            $this->addError('tasks exceeds ' . self::MAX_TASKS . ' entries.');

            return [];
        }

        $out = [];
        foreach (array_values($tasks) as $i => $task) {
            if (! is_array($task)) {
                $this->addError("tasks[{$i}] must be an object.");
                continue;
            }

            $this->rejectUnknownKeys($task, ['title', 'description', 'priority', 'section', 'labels'], "tasks[{$i}]");

            $title = $this->clean($task['title'] ?? null, "tasks[{$i}].title");
            if ($title === '' || mb_strlen($title) > self::MAX_TITLE) {
                $this->addError("tasks[{$i}].title is required and must be 1-" . self::MAX_TITLE . ' characters.');
            }

            $description = $this->clean($task['description'] ?? null, "tasks[{$i}].description");
            if (mb_strlen($description) > self::MAX_TASK_DESCRIPTION) {
                $this->addError("tasks[{$i}].description exceeds " . self::MAX_TASK_DESCRIPTION . ' characters.');
            }

            $priority = $task['priority'] ?? 'medium';
            if (! in_array($priority, self::PRIORITIES, true)) {
                $this->addError("tasks[{$i}].priority must be one of: " . implode('|', self::PRIORITIES) . '.');
                $priority = 'medium';
            }

            $section = $task['section'] ?? null;
            if (! is_int($section) || $section < 0 || $section >= $sectionCount) {
                $this->addError("tasks[{$i}].section must be an integer index into sections[] (0-" . max(0, $sectionCount - 1) . ').');
                $section = 0;
            }

            $labelIdx = $task['labels'] ?? [];
            if (! is_array($labelIdx)) {
                $this->addError("tasks[{$i}].labels must be an array of integer indexes.");
                $labelIdx = [];
            }

            $validIdx = [];
            foreach ($labelIdx as $idx) {
                if (! is_int($idx) || $idx < 0 || $idx >= $labelCount) {
                    $this->addError("tasks[{$i}].labels contains an index outside labels[] (0-" . max(0, $labelCount - 1) . ').');
                    continue;
                }
                $validIdx[] = $idx;
            }

            $out[] = [
                'title'       => $title,
                'description' => $description === '' ? null : $description,
                'priority'    => $priority,
                'section'     => $section,
                'labels'      => array_values(array_unique($validIdx)),
            ];
        }

        return $out;
    }

    private function validateWidgets(mixed $widgets, array $validSlugs): array
    {
        if (! is_array($widgets)) {
            $this->addError('widgets must be an array of slugs.');

            return [];
        }

        if (count($widgets) > self::MAX_WIDGETS) {
            $this->addError('widgets exceeds ' . self::MAX_WIDGETS . ' entries.');

            return [];
        }

        $out = [];
        foreach (array_values($widgets) as $i => $slug) {
            $slug = is_string($slug) ? trim($slug) : '';
            if (! in_array($slug, $validSlugs, true)) {
                $this->addError("widgets[{$i}] is not a known widget slug.");
                continue;
            }
            $out[] = $slug;
        }

        return array_values(array_unique($out));
    }
}
