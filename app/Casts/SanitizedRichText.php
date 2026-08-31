<?php

namespace App\Casts;

use DOMDocument;
use DOMElement;
use DOMNode;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

final class SanitizedRichText implements CastsAttributes
{
    /** @var list<string> */
    private const ALLOWED_TAGS = ['p', 'br', 'strong', 'em', 'u', 's', 'h1', 'h2', 'ul', 'ol', 'li', 'a'];

    /** @var list<string> */
    private const DANGEROUS_TAGS = ['script', 'style', 'iframe', 'object', 'embed', 'svg', 'math', 'video', 'audio', 'img'];

    /**
     * Cast the given value.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $this->sanitize($value);
    }

    /**
     * Prepare the given value for storage.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $this->sanitize($value);
    }

    public function sanitize(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (! str_contains($value, '<')) {
            return $this->hasMeaningfulText($value) ? $value : null;
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previousErrors = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML(
            '<?xml encoding="UTF-8"><div id="formia-rich-text-root">'.$value.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrors);

        if (! $loaded) {
            return null;
        }

        $root = $document->getElementById('formia-rich-text-root');
        if (! $root instanceof DOMElement) {
            return null;
        }

        $this->sanitizeChildren($root);
        if (! $this->hasMeaningfulText($root->textContent)) {
            return null;
        }

        $html = '';
        foreach ($root->childNodes as $child) {
            $html .= $document->saveHTML($child);
        }

        return trim($html) ?: null;
    }

    private function sanitizeChildren(DOMNode $parent): void
    {
        for ($node = $parent->firstChild; $node !== null; $node = $next) {
            $next = $node->nextSibling;
            if (! $node instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($node->tagName);
            if (in_array($tag, self::DANGEROUS_TAGS, true)) {
                $parent->removeChild($node);

                continue;
            }

            $this->sanitizeChildren($node);
            if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                while ($node->firstChild !== null) {
                    $parent->insertBefore($node->firstChild, $node);
                }
                $parent->removeChild($node);

                continue;
            }

            $this->sanitizeAttributes($node, $tag);
        }
    }

    private function sanitizeAttributes(DOMElement $element, string $tag): void
    {
        $class = $element->getAttribute('class');
        $href = $element->getAttribute('href');
        $target = $element->getAttribute('target');
        $title = $element->getAttribute('title');
        $attributeNames = [];
        foreach ($element->attributes as $attribute) {
            $attributeNames[] = $attribute->name;
        }
        foreach ($attributeNames as $attributeName) {
            $element->removeAttribute($attributeName);
        }

        if (in_array($tag, ['p', 'h1', 'h2'], true)) {
            $alignmentClass = $this->allowedAlignmentClass($class);
            if ($alignmentClass !== null) {
                $element->setAttribute('class', $alignmentClass);
            }

            return;
        }

        if ($tag !== 'a') {
            return;
        }

        if ($this->isSafeLink($href)) {
            $element->setAttribute('href', $href);
        }
        if ($title !== '') {
            $element->setAttribute('title', mb_substr($title, 0, 500));
        }
        if ($target === '_blank' && $this->isSafeLink($href)) {
            $element->setAttribute('target', '_blank');
            $element->setAttribute('rel', 'noopener noreferrer');
        }
    }

    private function allowedAlignmentClass(string $class): ?string
    {
        foreach (preg_split('/\s+/', $class) ?: [] as $candidate) {
            if (in_array($candidate, ['ql-align-center', 'ql-align-right', 'ql-align-justify'], true)) {
                return $candidate;
            }
        }

        return null;
    }

    private function isSafeLink(string $href): bool
    {
        return preg_match('/^(https?:\/\/|mailto:)/i', trim($href)) === 1;
    }

    private function hasMeaningfulText(string $value): bool
    {
        $withoutWhitespace = preg_replace('/[\s\x{00A0}\x{200B}\x{FEFF}]+/u', '', $value);

        return is_string($withoutWhitespace) && $withoutWhitespace !== '';
    }
}
