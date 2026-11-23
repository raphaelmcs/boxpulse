<?php

namespace App\Workouts;

use App\Support\BoxTheme;
use Illuminate\Validation\Rule;

class WorkoutTextStyle
{
    public static function rules(): array
    {
        return [
            ...self::fieldRules('blocks.*.text_style', 'title', ['description', 'notes']),
            ...self::fieldRules('blocks.*.stations.*.text_style', 'name', ['description']),
        ];
    }

    private static function fieldRules(string $path, string $titleField, array $lineFields): array
    {
        $rules = [$path => ['nullable', 'array:'.implode(',', [$titleField, ...$lineFields])]];
        foreach ([$titleField, ...$lineFields] as $field) {
            $fieldPath = "$path.$field";
            if ($field !== $titleField) {
                $rules[$fieldPath] = ['nullable', 'array', 'max:2000'];
                $fieldPath .= '.*';
            }

            $rules[$fieldPath] = ['nullable', 'array:font_weight,text_color,display_role'];
            $rules["$fieldPath.font_weight"] = ['nullable', Rule::in(['normal', 'bold'])];
            $rules["$fieldPath.text_color"] = ['nullable', 'string', 'regex:/\A#[a-fA-F0-9]{6}\z/'];
            $rules["$fieldPath.display_role"] = ['nullable', Rule::in(['subtitle'])];
        }

        return $rules;
    }

    public static function lines(?string $text): array
    {
        return explode("\n", str_replace(["\r\n", "\r"], "\n", $text ?? ''));
    }

    public static function normalize(array $block, string $titleField = 'title', array $lineFields = ['description', 'notes']): ?array
    {
        $input = $block['text_style'] ?? [];
        $style = array_filter([$titleField => self::line($input[$titleField] ?? null)]);
        foreach ($lineFields as $field) {
            $lines = self::lines($block[$field] ?? null);
            foreach ($input[$field] ?? [] as $index => $value) {
                $line = self::line($value);
                if ($line && is_int($index) && isset($lines[$index]) && trim($lines[$index]) !== '') {
                    $style[$field][$index] = $line;
                }
            }
        }

        return $style ?: null;
    }

    public static function css(?array $style): string
    {
        $weight = $style['font_weight'] ?? (isset($style['display_role']) ? 'bold' : null);
        $color = $style['text_color'] ?? null;

        return ($weight ? 'font-weight:'.($weight === 'normal' ? '400' : '700').';' : '')
            .($color && preg_match('/\A#[a-f0-9]{6}\z/i', $color) ? 'color:'.BoxTheme::accent($color, 0.035).';' : '');
    }

    private static function line(?array $style): array
    {
        $style = array_filter($style ?? []);
        if (isset($style['text_color'])) {
            $style['text_color'] = strtoupper($style['text_color']);
        }

        return $style;
    }
}
