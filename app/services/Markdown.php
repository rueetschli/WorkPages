<?php
/**
 * Markdown-to-HTML renderer using Parsedown.
 *
 * Renders Markdown to HTML via Parsedown with safe mode enabled.
 * Post-processes @mentions and #tags from AP14 Smart Text Commands.
 */
class Markdown
{
    private static ?Parsedown $parser = null;

    /**
     * Get or create the Parsedown instance.
     */
    private static function getParser(): Parsedown
    {
        if (self::$parser === null) {
            self::$parser = new Parsedown();
            self::$parser->setSafeMode(true);
        }
        return self::$parser;
    }

    /**
     * Render Markdown text to safe HTML.
     */
    public static function render(string $text): string
    {
        $html = self::getParser()->text($text);

        // AP14: @mentions - @[Name](user:ID) rendered as styled span
        $html = preg_replace_callback(
            '/@\[([^\]]+)\]\(user:(\d+)\)/',
            function ($matches) {
                $name = htmlspecialchars($matches[1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                return '<span class="mention" data-user-id="' . (int) $matches[2] . '">@' . $name . '</span>';
            },
            $html
        );

        // AP14: #tags - rendered as styled link to task filter
        $html = preg_replace_callback(
            '/(?:^|(?<=\s))#([a-z0-9][a-z0-9._-]{0,49})(?=\s|$|[.,;:!?)])/m',
            function ($matches) {
                $tag = $matches[1];
                $baseUrl = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
                $safeBase = htmlspecialchars($baseUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                return '<a href="' . $safeBase . '/?r=tasks&amp;tag=' . htmlspecialchars($tag, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" class="tag-ref">#' . htmlspecialchars($tag, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</a>';
            },
            $html
        );

        return $html;
    }
}
