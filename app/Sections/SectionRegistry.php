<?php

namespace App\Sections;

use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Mews\Purifier\Facades\Purifier;

/**
 * Reads the section definitions in config/sections.php and turns them into
 * validation rules, sanitized data, defaults and the admin builder schema.
 */
class SectionRegistry
{
    /** Link URLs: site paths, in-page anchors, http(s), tel: and mailto:. */
    public const LINK_URL_PATTERN = '#^(/[^\s]*|\#[\w\-]+|https?://[^\s]+|tel:\+?[\d\-\s().]+|mailto:[^\s@]+@[^\s@]+)$#i';

    /**
     * @return array<string, array<string, mixed>>
     */
    public function types(): array
    {
        return config('sections.types');
    }

    public function exists(string $type): bool
    {
        return array_key_exists($type, $this->types());
    }

    /**
     * @return array<string, mixed>
     */
    public function definition(string $type): array
    {
        return $this->types()[$type] ?? throw new InvalidArgumentException("Unknown section type [{$type}].");
    }

    /**
     * Starting data for a newly added section.
     *
     * @return array<string, mixed>
     */
    public function defaults(string $type): array
    {
        $definition = $this->definition($type);
        $data = [];

        foreach ($definition['fields'] as $field) {
            $data[$field['key']] = $field['default'] ?? $this->emptyValue($field);
        }

        return array_replace($data, $definition['defaults'] ?? []);
    }

    /**
     * Validation rules for a list of sections submitted as `$prefix` (one array item
     * per section: id?, type, data, is_visible). Rules depend on each item's type.
     *
     * @param  array<int, mixed>  $sections
     * @return array<string, mixed>
     */
    public function rules(array $sections, string $prefix = 'sections'): array
    {
        $rules = [
            $prefix => ['array', 'max:40'],
            "{$prefix}.*.id" => ['nullable', 'integer'],
            "{$prefix}.*.type" => ['required', 'string', Rule::in(array_keys($this->types()))],
            "{$prefix}.*.is_visible" => ['boolean'],
            "{$prefix}.*.data" => ['nullable', 'array'],
        ];

        foreach ($sections as $i => $section) {
            $type = is_array($section) ? ($section['type'] ?? null) : null;

            if (is_string($type) && $this->exists($type)) {
                $rules += $this->fieldRules($this->definition($type)['fields'], "{$prefix}.{$i}.data");
            }
        }

        return $rules;
    }

    /**
     * Friendly attribute names ("Hero › Heading") for validation messages.
     *
     * @param  array<int, mixed>  $sections
     * @return array<string, string>
     */
    public function attributes(array $sections, string $prefix = 'sections'): array
    {
        $names = [];

        foreach ($sections as $i => $section) {
            $type = is_array($section) ? ($section['type'] ?? null) : null;

            if (! is_string($type) || ! $this->exists($type)) {
                continue;
            }

            $label = $this->definition($type)['label'];

            foreach ($this->definition($type)['fields'] as $field) {
                $names["{$prefix}.{$i}.data.{$field['key']}"] = "{$label} › {$field['label']}";

                foreach ($field['fields'] ?? [] as $sub) {
                    $names["{$prefix}.{$i}.data.{$field['key']}.*.{$sub['key']}"] = "{$label} › {$field['label']} › {$sub['label']}";
                }
            }
        }

        return $names;
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     * @return array<string, mixed>
     */
    public function fieldRules(array $fields, string $prefix): array
    {
        $rules = [];

        foreach ($fields as $field) {
            $key = "{$prefix}.{$field['key']}";
            $presence = ($field['required'] ?? false) ? 'required' : 'nullable';

            switch ($field['type']) {
                case 'text':
                case 'textarea':
                case 'code':
                    $rules[$key] = [$presence, 'string', 'max:'.($field['max'] ?? 1000)];
                    break;

                case 'richtext':
                    $rules[$key] = [$presence, 'string', 'max:50000'];
                    break;

                case 'image':
                    $rules[$key] = [$presence, 'integer', 'exists:media,id'];
                    break;

                case 'link':
                    $rules[$key] = [$presence, 'array'];
                    $rules["{$key}.label"] = ['nullable', 'string', 'max:60', "required_with:{$key}.url"];
                    $rules["{$key}.url"] = ['nullable', 'string', 'max:2048', 'regex:'.self::LINK_URL_PATTERN, "required_with:{$key}.label"];
                    $rules["{$key}.new_tab"] = ['nullable', 'boolean'];
                    break;

                case 'toggle':
                    $rules[$key] = ['nullable', 'boolean'];
                    break;

                case 'select':
                    $rules[$key] = [$presence, Rule::in(array_map('strval', array_keys($field['options'])))];
                    break;

                case 'number':
                    $rules[$key] = [$presence, 'integer', 'min:'.($field['min'] ?? 0), 'max:'.($field['max'] ?? 1000)];
                    break;

                case 'icon':
                    $rules[$key] = ['nullable', Rule::in(config('sections.icons'))];
                    break;

                case 'services':
                case 'areas':
                case 'reviews':
                case 'faqs':
                    $rules[$key] = ['nullable', 'array', 'max:50'];
                    $rules["{$key}.*"] = ['integer', 'exists:'.$field['type'].',id'];
                    break;

                case 'repeater':
                    $rules[$key] = array_values(array_filter([
                        ($field['min_items'] ?? 0) > 0 ? 'required' : 'nullable',
                        'array',
                        isset($field['min_items']) ? 'min:'.$field['min_items'] : null,
                        'max:'.($field['max_items'] ?? 20),
                    ]));
                    $rules += $this->fieldRules($field['fields'], "{$key}.*");
                    break;

                default:
                    throw new InvalidArgumentException("Unknown field type [{$field['type']}].");
            }
        }

        return $rules;
    }

    /**
     * Keep only defined keys, cast values and purify rich text.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function sanitize(string $type, array $data): array
    {
        return $this->sanitizeFields($this->definition($type)['fields'], $data);
    }

    /**
     * Every media ID referenced in a section's data (images, repeater images).
     *
     * @param  array<string, mixed>  $data
     * @return list<int>
     */
    public function mediaIds(string $type, array $data): array
    {
        return array_values(array_unique($this->collectMedia($this->definition($type)['fields'], $data)));
    }

    /**
     * Schema for the Vue builder: every type with its fields, plus icon SVGs.
     *
     * @return array<string, mixed>
     */
    public function forAdmin(): array
    {
        return [
            'types' => Arr::map($this->types(), fn (array $t, string $type) => [
                'type' => $type,
                'label' => $t['label'],
                'description' => $t['description'],
                'icon' => $t['icon'],
                'fields' => $t['fields'],
                'defaults' => $this->defaults($type),
            ]),
            'icons' => collect(config('sections.icons'))
                ->mapWithKeys(fn (string $name) => [$name => svg("lucide-{$name}")->toHtml()])
                ->all(),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function sanitizeFields(array $fields, array $data): array
    {
        $clean = [];

        foreach ($fields as $field) {
            $value = $data[$field['key']] ?? null;

            $clean[$field['key']] = match ($field['type']) {
                'text', 'textarea' => is_string($value) && trim($value) !== '' ? trim($value) : null,
                'code' => is_string($value) && trim($value) !== '' ? $value : null,
                'richtext' => is_string($value) && trim(strip_tags($value)) !== '' ? Purifier::clean($value, 'rich_text') : null,
                'image', 'number' => $value === null || $value === '' ? null : (int) $value,
                'toggle' => (bool) $value,
                'select', 'icon' => $value === null || $value === '' ? ($field['default'] ?? null) : (string) $value,
                'link' => $this->sanitizeLink($value),
                'services', 'areas', 'reviews', 'faqs' => array_values(array_map('intval', array_filter((array) $value))),
                'repeater' => array_values(array_filter(
                    array_map(fn ($row) => $this->sanitizeFields($field['fields'], (array) $row), (array) $value),
                    fn (array $row) => array_filter($row, fn ($v) => $v !== null && $v !== false && $v !== []) !== [],
                )),
            };
        }

        return $clean;
    }

    /**
     * @return array{label: string, url: string, new_tab: bool}|null
     */
    private function sanitizeLink(mixed $value): ?array
    {
        if (! is_array($value) || blank($value['url'] ?? null) || blank($value['label'] ?? null)) {
            return null;
        }

        return [
            'label' => trim($value['label']),
            'url' => trim($value['url']),
            'new_tab' => (bool) ($value['new_tab'] ?? false),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     * @return list<int>
     */
    private function collectMedia(array $fields, array $data): array
    {
        $ids = [];

        foreach ($fields as $field) {
            $value = $data[$field['key']] ?? null;

            if ($field['type'] === 'image' && $value) {
                $ids[] = (int) $value;
            }

            if ($field['type'] === 'repeater' && is_array($value)) {
                foreach ($value as $row) {
                    array_push($ids, ...$this->collectMedia($field['fields'], (array) $row));
                }
            }
        }

        return $ids;
    }

    private function emptyValue(array $field): mixed
    {
        return match ($field['type']) {
            'repeater', 'services', 'areas', 'reviews', 'faqs' => [],
            'toggle' => false,
            default => null,
        };
    }
}
