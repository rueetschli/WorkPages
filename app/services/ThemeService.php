<?php
/**
 * ThemeService - Generates CSS custom properties from system settings (AP20).
 *
 * Supports preset themes and custom color configuration.
 * Outputs inline CSS variables for the layout.
 */
class ThemeService
{
    /**
     * All available theme presets.
     * Each preset defines primary, secondary, accent, background, and text colors.
     */
    private const PRESETS = [
        'blau' => [
            'label'     => 'Blau',
            'primary'   => '#2563eb',
            'secondary' => '#1e293b',
            'accent'    => '#3b82f6',
            'bg'        => '#f9fafb',
            'text'      => '#111827',
        ],
        'gruen' => [
            'label'     => 'Gruen',
            'primary'   => '#16a34a',
            'secondary' => '#14532d',
            'accent'    => '#22c55e',
            'bg'        => '#f9fafb',
            'text'      => '#111827',
        ],
        'rot' => [
            'label'     => 'Rot',
            'primary'   => '#dc2626',
            'secondary' => '#7f1d1d',
            'accent'    => '#ef4444',
            'bg'        => '#f9fafb',
            'text'      => '#111827',
        ],
        'violett' => [
            'label'     => 'Violett',
            'primary'   => '#7c3aed',
            'secondary' => '#3b0764',
            'accent'    => '#8b5cf6',
            'bg'        => '#f9fafb',
            'text'      => '#111827',
        ],
        'neutral' => [
            'label'     => 'Neutral',
            'primary'   => '#4b5563',
            'secondary' => '#1f2937',
            'accent'    => '#6b7280',
            'bg'        => '#f9fafb',
            'text'      => '#111827',
        ],
        'grau' => [
            'label'     => 'Grau',
            'primary'   => '#374151',
            'secondary' => '#111827',
            'accent'    => '#9ca3af',
            'bg'        => '#f9fafb',
            'text'      => '#111827',
        ],
        'teal' => [
            'label'     => 'Teal',
            'primary'   => '#0d9488',
            'secondary' => '#134e4a',
            'accent'    => '#14b8a6',
            'bg'        => '#f9fafb',
            'text'      => '#111827',
        ],
        'orange' => [
            'label'     => 'Orange',
            'primary'   => '#ea580c',
            'secondary' => '#7c2d12',
            'accent'    => '#f97316',
            'bg'        => '#f9fafb',
            'text'      => '#111827',
        ],
    ];

    /**
     * Get all available presets.
     */
    public static function getPresets(): array
    {
        return self::PRESETS;
    }

    /**
     * Get the resolved colors based on current system settings.
     * Returns an associative array with keys: primary, secondary, accent, bg, text.
     */
    public static function getActiveColors(): array
    {
        $settings = SystemSettingsService::get();
        $mode = $settings['theme_mode'] ?? 'preset';

        if ($mode === 'custom') {
            return [
                'primary'   => self::sanitizeHex($settings['color_primary'] ?? '#2563eb'),
                'secondary' => self::sanitizeHex($settings['color_secondary'] ?? '#1e293b'),
                'accent'    => self::sanitizeHex($settings['color_accent'] ?? '#3b82f6'),
            ];
        }

        $preset = $settings['theme_preset'] ?? 'blau';
        if (!isset(self::PRESETS[$preset])) {
            $preset = 'blau';
        }

        return [
            'primary'   => self::PRESETS[$preset]['primary'],
            'secondary' => self::PRESETS[$preset]['secondary'],
            'accent'    => self::PRESETS[$preset]['accent'],
        ];
    }

    /**
     * Generate inline CSS style block with custom properties.
     * This is injected into the <head> section of the layout.
     */
    public static function renderCssVariables(): string
    {
        $colors = self::getActiveColors();

        $css  = "<style id=\"wp-theme-vars\">\n";
        $css .= ":root {\n";
        $css .= "    --wp-color-primary: {$colors['primary']};\n";
        $css .= "    --wp-color-secondary: {$colors['secondary']};\n";
        $css .= "    --wp-color-accent: {$colors['accent']};\n";
        $css .= "}\n";
        $css .= "</style>\n";

        return $css;
    }

    /**
     * Validate a HEX color string.
     */
    public static function isValidHex(string $color): bool
    {
        return preg_match('/^#[0-9a-fA-F]{6}$/', $color) === 1;
    }

    /**
     * Sanitize a HEX color, returning fallback if invalid.
     */
    private static function sanitizeHex(string $color, string $fallback = '#2563eb'): string
    {
        return self::isValidHex($color) ? $color : $fallback;
    }
}
