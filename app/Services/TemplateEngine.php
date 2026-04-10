<?php

namespace App\Services;

use RuntimeException;

/**
 * TemplateEngine
 * ──────────────
 * Loads tender templates from resources/tender-templates/{type}.json and
 * renders sections by substituting {{variables}} with values from a context
 * array. Templates are pure JSON — easy to add new types or edit clauses.
 */
class TemplateEngine
{
    private string $templatesPath;

    public function __construct()
    {
        $this->templatesPath = resource_path('tender-templates');
    }

    /** Available template types and their Arabic labels. */
    public function availableTypes(): array
    {
        return [
            'it'           => 'مشروع تقني',
            'construction' => 'مشروع إنشاءات',
            'consulting'   => 'خدمات استشارية',
            'operations'   => 'تشغيل وصيانة',
            'legal'        => 'خدمات قانونية',
        ];
    }

    /**
     * Load a template by type.
     *
     * @return array{type:string,label:string,sections:array<int,array{key:string,title:string,template:string}>}
     */
    public function load(string $type): array
    {
        $file = $this->templatesPath . DIRECTORY_SEPARATOR . $type . '.json';
        if (! is_file($file)) {
            throw new RuntimeException("Template not found: {$type}");
        }
        $json = file_get_contents($file);
        $data = json_decode($json, true);
        if (! is_array($data) || ! isset($data['sections'])) {
            throw new RuntimeException("Invalid template format: {$type}");
        }
        return $data;
    }

    /**
     * Render every section in a template by interpolating context variables
     * into each section's `template` string. Returns an array of:
     *   [{key, title, content}]
     *
     * Supported variables (any can be missing — they'll be left as a dash):
     *   {{title}}, {{description}}, {{org}}, {{date}}, {{type_label}},
     *   {{duration}}, {{tasks_list}}, {{deliverables_list}},
     *   {{evaluation_list}}, {{clauses_block}}, {{special_conditions}}
     */
    public function render(string $type, array $context): array
    {
        $template = $this->load($type);
        $rendered = [];
        $order = 0;
        foreach ($template['sections'] as $section) {
            $content = $this->interpolate($section['template'], $context);
            $rendered[] = [
                'key'     => $section['key'],
                'title'   => $section['title'],
                'content' => $content,
                'order'   => $order++,
            ];
        }
        return $rendered;
    }

    /**
     * Replace {{var}} placeholders. Lists ({{tasks_list}}, etc) accept arrays
     * and render them as bullet lists.
     */
    private function interpolate(string $template, array $context): string
    {
        return preg_replace_callback('/\{\{(\w+)\}\}/', function ($match) use ($context) {
            $key = $match[1];
            if (! array_key_exists($key, $context)) {
                return '—';
            }
            $value = $context[$key];
            if (is_array($value)) {
                if (empty($value)) return '—';
                return implode("\n", array_map(fn ($v) => '• ' . (is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : $v), $value));
            }
            return (string) ($value ?? '—');
        }, $template) ?? $template;
    }
}
