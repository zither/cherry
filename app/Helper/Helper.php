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
        $html = html_entity_decode($html, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401);
        // Handle '&' char which causes warnings
        $html = str_replace('&', '&amp;', $html);
        // fix invalid html entities
        if (preg_match('/&amp;amp%3B/', $html)) {
            $html = urldecode($html);
        }

        $previousValue = libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        $hack =  '<?xml encoding="utf-8" ?><meta http-equiv="content-type" content="text/html; charset=utf-8">';
        $doc->loadHTML($hack . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_use_internal_errors($previousValue);

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
        $savedHtml = $doc->saveHTML();
        return str_replace( $hack, '', $savedHtml);
    }
}