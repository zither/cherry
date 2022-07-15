<?php

namespace Cherry\Helper;

use Psr\Http\Message\ServerRequestInterface as Request;
use DOMDocument;
use DOMNode;

class Helper
{
    public static function isApi(Request $request)
    {
        $headers = $request->getHeader('accept');
        foreach ($headers as $header) {
            if (preg_match('#application/(.+\+)?json#', $header)) {
                return true;
            }
        }
        return false;
    }

    public static function stripTags(string $html)
    {
        //@Todo remove invalid links in html
        $html = strip_tags($html, ['a', 'p', 'br', 'img', 'blockquote']);
        // fix invalid html entities
        if (preg_match('/&amp;amp%3B/', $html)) {
            $html = urldecode($html);
        }
        $doc = new DOMDocument();
        $hack =  '<?xml encoding="utf-8" ?>';
        $doc->loadHTML($hack . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $images = $doc->getElementsByTagName('img');
        $attributeWhitelist = ['src', 'rel', 'alt', 'title', 'class'];
        /** @var DOMNode $image */
        foreach ($images as $image) {
            foreach ($image->attributes as $attribute) {
                if (!in_array($attribute->name, $attributeWhitelist)) {
                    $image->removeAttribute($attribute->name);
                }
            }
        }
        return str_replace( $hack, '', $doc->saveHTML());
    }
}