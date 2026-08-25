<?php

namespace Condoedge\Utils\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * The placeholder avatar for anyone without a photo, served from this app instead of
 * ui-avatars.com so no name ever reaches a third party (Odoo #6903).
 *
 * Only initials are ever passed in, which keeps the whole system down to a few hundred
 * distinct URLs — every one of them immutable and cached by the browser after first use.
 */
class InitialsAvatarController extends Controller
{
    protected const COLOR = '#7F9CF5';
    protected const BACKGROUND = '#EBF4FF';

    public function __invoke(Request $request, $initials = '')
    {
        $initials = $this->sanitize($initials);

        return response($this->svg($initials), 200, [
            'Content-Type' => 'image/svg+xml; charset=utf-8',
            // Long, but not `immutable`: the body is derived from constants that may be
            // restyled one day, and a year-long promise would outlive the change.
            'Cache-Control' => 'public, max-age=2592000',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'",
        ]);
    }

    /**
     * The URL is public, so nothing but letters and digits may reach the markup — that
     * is what makes SVG injection impossible rather than merely escaped.
     */
    protected function sanitize($initials): string
    {
        $letters = preg_replace('/[^\p{L}\p{N}]/u', '', (string) $initials);

        return mb_strtoupper(mb_substr($letters ?? '', 0, 2));
    }

    protected function svg(string $initials): string
    {
        $text = htmlspecialchars($initials, ENT_QUOTES | ENT_XML1, 'UTF-8');

        // dy rather than dominant-baseline: it centres the same way in print and in
        // every renderer, which dominant-baseline does not.
        return '<svg xmlns="http://www.w3.org/2000/svg" width="128" height="128" viewBox="0 0 128 128" role="img">'
            .'<rect width="128" height="128" fill="'.static::BACKGROUND.'"/>'
            .'<text x="64" y="64" dy=".35em" fill="'.static::COLOR.'"'
            .' font-family="Helvetica,Arial,sans-serif" font-size="52" font-weight="500"'
            .' text-anchor="middle">'.$text.'</text>'
            .'</svg>';
    }
}
