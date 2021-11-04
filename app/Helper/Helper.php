<?php

namespace Cherry\Helper;

use Psr\Http\Message\ServerRequestInterface as Request;

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
}