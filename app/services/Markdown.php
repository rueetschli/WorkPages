<?php
/**
 * Minimal Markdown-to-HTML renderer.
 *
 * Supports: headings (#, ##, ###), bold, italic, unordered lists (-, *),
 * ordered lists (1.), inline code, code blocks, links, blockquotes, horizontal rules, paragraphs.
 *
 * All output is HTML-escaped first, then Markdown syntax is converted.
 * This prevents XSS by design.
 */
class Markdown
{
    /**
     * Render Markdown text to safe HTML.
     */
    public static function render(string $text): string
    {
        $text = str_replace("\r\n", "\n", $text);
        $text = str_replace("\r", "\n", $text);

        $lines = explode("\n", $text);
        $html = '';
        $inCodeBlock = false;
        $codeBuffer = '';
        $inList = false;
        $listType = '';
        $inBlockquote = false;
        $blockquoteBuffer = [];
        $paragraphBuffer = [];

        $lineCount = count($lines);

        for ($i = 0; $i < $lineCount; $i++) {
            $line = $lines[$i];

            // Fenced code blocks
            if (preg_match('/^```/', $line)) {
                if ($inCodeBlock) {
                    // Close list/paragraph if open
                    $html .= self::flushParagraph($paragraphBuffer);
                    $paragraphBuffer = [];
                    $html .= '<pre><code>' . htmlspecialchars($codeBuffer, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</code></pre>' . "\n";
                    $codeBuffer = '';
                    $inCodeBlock = false;
                } else {
                    $html .= self::flushParagraph($paragraphBuffer);
                    $paragraphBuffer = [];
                    if ($inList) {
                        $html .= $listType === 'ul' ? "</ul>\n" : "</ol>\n";
                        $inList = false;
                    }
                    if ($inBlockquote) {
                        $html .= self::flushBlockquote($blockquoteBuffer);
                        $blockquoteBuffer = [];
                        $inBlockquote = false;
                    }
                    $inCodeBlock = true;
                }
                continue;
            }

            if ($inCodeBlock) {
                $codeBuffer .= ($codeBuffer !== '' ? "\n" : '') . $line;
                continue;
            }

            // Blank line
            if (trim($line) === '') {
                $html .= self::flushParagraph($paragraphBuffer);
                $paragraphBuffer = [];
                if ($inList) {
                    $html .= $listType === 'ul' ? "</ul>\n" : "</ol>\n";
                    $inList = false;
                }
                if ($inBlockquote) {
                    $html .= self::flushBlockquote($blockquoteBuffer);
                    $blockquoteBuffer = [];
                    $inBlockquote = false;
                }
                continue;
            }

            // Horizontal rule
            if (preg_match('/^(-{3,}|\*{3,}|_{3,})$/', trim($line))) {
                $html .= self::flushParagraph($paragraphBuffer);
                $paragraphBuffer = [];
                if ($inList) {
                    $html .= $listType === 'ul' ? "</ul>\n" : "</ol>\n";
                    $inList = false;
                }
                $html .= "<hr>\n";
                continue;
            }

            // Headings
            if (preg_match('/^(#{1,6})\s+(.+)$/', $line, $m)) {
                $html .= self::flushParagraph($paragraphBuffer);
                $paragraphBuffer = [];
                if ($inList) {
                    $html .= $listType === 'ul' ? "</ul>\n" : "</ol>\n";
                    $inList = false;
                }
                $level = strlen($m[1]);
                $content = self::renderInline(htmlspecialchars($m[2], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
                $html .= "<h{$level}>{$content}</h{$level}>\n";
                continue;
            }

            // Blockquotes
            if (preg_match('/^>\s?(.*)$/', $line, $m)) {
                $html .= self::flushParagraph($paragraphBuffer);
                $paragraphBuffer = [];
                if ($inList) {
                    $html .= $listType === 'ul' ? "</ul>\n" : "</ol>\n";
                    $inList = false;
                }
                $inBlockquote = true;
                $blockquoteBuffer[] = $m[1];
                continue;
            }

            if ($inBlockquote) {
                $html .= self::flushBlockquote($blockquoteBuffer);
                $blockquoteBuffer = [];
                $inBlockquote = false;
            }

            // Unordered list items
            if (preg_match('/^[\-\*]\s+(.+)$/', $line, $m)) {
                $html .= self::flushParagraph($paragraphBuffer);
                $paragraphBuffer = [];
                if (!$inList || $listType !== 'ul') {
                    if ($inList) {
                        $html .= $listType === 'ul' ? "</ul>\n" : "</ol>\n";
                    }
                    $html .= "<ul>\n";
                    $inList = true;
                    $listType = 'ul';
                }
                $content = self::renderInline(htmlspecialchars($m[1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
                $html .= "<li>{$content}</li>\n";
                continue;
            }

            // Ordered list items
            if (preg_match('/^\d+\.\s+(.+)$/', $line, $m)) {
                $html .= self::flushParagraph($paragraphBuffer);
                $paragraphBuffer = [];
                if (!$inList || $listType !== 'ol') {
                    if ($inList) {
                        $html .= $listType === 'ul' ? "</ul>\n" : "</ol>\n";
                    }
                    $html .= "<ol>\n";
                    $inList = true;
                    $listType = 'ol';
                }
                $content = self::renderInline(htmlspecialchars($m[1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
                $html .= "<li>{$content}</li>\n";
                continue;
            }

            // Close list if current line is not a list item
            if ($inList) {
                $html .= $listType === 'ul' ? "</ul>\n" : "</ol>\n";
                $inList = false;
            }

            // Regular paragraph line
            $paragraphBuffer[] = $line;
        }

        // Flush remaining
        if ($inCodeBlock) {
            $html .= '<pre><code>' . htmlspecialchars($codeBuffer, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</code></pre>' . "\n";
        }
        if ($inList) {
            $html .= $listType === 'ul' ? "</ul>\n" : "</ol>\n";
        }
        if ($inBlockquote) {
            $html .= self::flushBlockquote($blockquoteBuffer);
        }
        $html .= self::flushParagraph($paragraphBuffer);

        return $html;
    }

    /**
     * Flush buffered paragraph lines into a <p> tag.
     */
    private static function flushParagraph(array &$buffer): string
    {
        if (empty($buffer)) {
            return '';
        }
        $text = implode("\n", $buffer);
        $escaped = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $inline = self::renderInline($escaped);
        $buffer = [];
        return "<p>{$inline}</p>\n";
    }

    /**
     * Flush blockquote buffer.
     */
    private static function flushBlockquote(array &$buffer): string
    {
        if (empty($buffer)) {
            return '';
        }
        $text = implode("\n", $buffer);
        $escaped = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $inline = self::renderInline($escaped);
        $buffer = [];
        return "<blockquote><p>{$inline}</p></blockquote>\n";
    }

    /**
     * Render inline Markdown formatting (bold, italic, code, links).
     * Input must already be HTML-escaped.
     */
    private static function renderInline(string $text): string
    {
        // Inline code (backticks) - must be processed first to avoid interference
        $text = preg_replace('/`([^`]+)`/', '<code>$1</code>', $text);

        // Bold + Italic (***text*** or ___text___)
        $text = preg_replace('/\*\*\*(.+?)\*\*\*/', '<strong><em>$1</em></strong>', $text);
        $text = preg_replace('/___(.+?)___/', '<strong><em>$1</em></strong>', $text);

        // Bold (**text** or __text__)
        $text = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text);
        $text = preg_replace('/__(.+?)__/', '<strong>$1</strong>', $text);

        // Italic (*text* or _text_)
        $text = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $text);
        $text = preg_replace('/(?<!\w)_(.+?)_(?!\w)/', '<em>$1</em>', $text);

        // Links [text](url) - URL was already escaped, decode for href
        $text = preg_replace_callback(
            '/\[([^\]]+)\]\(([^)]+)\)/',
            function ($matches) {
                $linkText = $matches[1];
                $url = html_entity_decode($matches[2], ENT_QUOTES, 'UTF-8');
                $safeUrl = htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                // Only allow http, https, mailto protocols
                if (preg_match('/^(https?:|mailto:|\/)/i', $url)) {
                    return '<a href="' . $safeUrl . '" rel="noopener">' . $linkText . '</a>';
                }
                return $linkText;
            },
            $text
        );

        // Line breaks within paragraph (two trailing spaces or explicit \n)
        $text = str_replace("  \n", "<br>\n", $text);

        return $text;
    }
}
