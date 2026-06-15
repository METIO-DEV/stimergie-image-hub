<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

class SafeHtml
{
    private const ALLOWED_TAGS = [
        'a',
        'blockquote',
        'br',
        'em',
        'h2',
        'h3',
        'h4',
        'hr',
        'i',
        'li',
        'ol',
        'p',
        'strong',
        'u',
        'ul',
    ];

    private const DROP_WITH_CONTENT = [
        'base',
        'button',
        'embed',
        'form',
        'iframe',
        'input',
        'link',
        'math',
        'meta',
        'object',
        'script',
        'style',
        'svg',
        'template',
    ];

    public function clean(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);

        $document->loadHTML(
            '<?xml encoding="utf-8" ?><div id="safe-html-root">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementById('safe-html-root');

        if (! $root) {
            return e($html);
        }

        $this->cleanChildren($root);

        $output = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $output .= $document->saveHTML($child);
        }

        return $output;
    }

    private function cleanChildren(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMElement) {
                $tagName = strtolower($child->tagName);

                if (in_array($tagName, self::DROP_WITH_CONTENT, true)) {
                    $child->parentNode?->removeChild($child);

                    continue;
                }

                if (! in_array($tagName, self::ALLOWED_TAGS, true)) {
                    $this->unwrap($child);

                    continue;
                }

                $this->cleanAttributes($child);
            }

            $this->cleanChildren($child);
        }
    }

    private function cleanAttributes(DOMElement $element): void
    {
        foreach (iterator_to_array($element->attributes) as $attribute) {
            $name = strtolower($attribute->name);
            $value = trim($attribute->value);

            if ($element->tagName === 'a' && $name === 'href' && $this->isSafeHref($value)) {
                continue;
            }

            if ($element->tagName === 'a' && $name === 'target' && $value === '_blank') {
                $element->setAttribute('rel', 'noopener noreferrer');

                continue;
            }

            if ($element->tagName === 'a' && $name === 'rel' && $value === 'noopener noreferrer') {
                continue;
            }

            $element->removeAttribute($attribute->name);
        }
    }

    private function isSafeHref(string $href): bool
    {
        return str_starts_with($href, '#')
            || (str_starts_with($href, '/') && ! str_starts_with($href, '//'))
            || preg_match('/^(https?:|mailto:|tel:)/i', $href) === 1;
    }

    private function unwrap(DOMElement $element): void
    {
        $parent = $element->parentNode;

        if (! $parent) {
            return;
        }

        while ($element->firstChild) {
            $parent->insertBefore($element->firstChild, $element);
        }

        $parent->removeChild($element);
    }
}
