<?php

if (!function_exists('makeMailButton')) {
    function makeMailButton($label, $url, $color = null, $extraStyles = '')
    {
        $rawA = makeAElement($label, $url, $color, $extraStyles);

    	return makeCenteredElement($rawA, bgColor: $color);
    }
}

if (!function_exists('makeAElement')) {
    function makeAElement($label, $url, $bgColor = null, $color = null, $extraStyles = "")
    {
        $bgColor = $bgColor ?? '#003AB3';
        $color = $color ?: '#ffffff';

        return '<a href="'.$url.'" style="border-color: '.$bgColor .'!important;outline: none !important; background-color:'.$bgColor.'; border-radius:10px; border-width:0px; color:'.$color.' !important; display:inline-block; font-size:16px; font-weight:normal; letter-spacing:0px; line-height:normal; padding:12px 18px 12px 18px; text-align:center; text-decoration:none; border-style:solid;'.$extraStyles.'" class="button button-'.( $color ?? 'primary' ).'" target="_blank" rel="noopener">'.__($label).'</a>';
    }
}

if (!function_exists('makeMailSimpleImage')) {
    function makeMailSimpleImage($path, $extraStyle = "", $alt = "default")
    {
        return "<img style='" . $extraStyle . "' src='" . $path . "' alt='" . $alt . "'>";
    }
}

if (!function_exists('makeMailImage')) {
    function makeMailImage($file, $extraStyle = "")
    {
    	$src = \Storage::url(thumb($file->path));
    	$alt = $file->name;

    	return makeCenteredElement('<img src="'.$src.'" alt="'.$alt.'" />', $extraStyle);
    }
}

if (!function_exists('makeCenteredElement')) {
    function makeCenteredElement($element, $extraStyle = "", $internalTableStyle = "", $bgColor = '')
    {
    	return '<table border="0" cellpadding="0" cellspacing="0" class="module" data-role="module-button" data-type="button" role="module" style="table-layout:fixed;'.$extraStyle.'" width="100%" data-muid="ba02837a-1128-47c9-8ae5-263f3cd5a779">
            <tbody>
                <tr>
                    <td align="center" bgcolor="" class="outer-td" style="padding:0px 0px 0px 0px;">
                        <table border="0" cellpadding="0" cellspacing="0" class="wrapper-mobile" style="text-align:center;'.$internalTableStyle.'">
                        <tbody>
                            <tr>
                            <td align="center" bgcolor="'.$bgColor.'" class="inner-td" style="border-radius:6px; font-size:16px; text-align:left; background-color:inherit;">
                                '.$element.'
                            </td>
                            </tr>
                        </tbody>
                        </table>
                    </td>
                </tr>
            </tbody>
            </table>';
    }
}

if (!function_exists('makeParagraph')) {
    function makeParagraph($text, $extraStyle = "")
    {
    	return '<p style="'.$extraStyle.'">'.__($text).'</p>';
    }
}

if (!function_exists('makeMailButtonWrappedInDiv')) {
    function makeMailButtonWrappedInDiv($label, $url, $color = null, $outlined = false)
    {
        return '<div style="text-align:center"><a href="'.$url.'" class="button '. ($outlined ? 'outlined' : '') .' button-'.( $color ?? 'primary' ).'" target="_blank" rel="noopener" style="text-transform:uppercase">'.__($label).'</a></div>';
    }
}

if (!function_exists('makeQrElement')) {
    function makeQrElement($element, $text = '')
    {
        return '
            <div style="background-color: #fff; border-radius: 1rem; border-color: #f1f2f3; padding: 16px; text-align: center; width: max-content;">
                <div>' . $element . '</div>
                <p style="font-size: 1rem; margin-top: 1rem; font-weight: 500; color: #000; text-align: center;">' . $text . '</p>
            </div>
        ';
    }
}

if (!function_exists('getCenteredGrid')) {
    function getCenteredGrid($elements, $bg = '#003AB3', $tableExtraStyles = "", $tdExtraStyle = "", $spacing = '20px')
    {
        $bg = $bg ?: '#003AB3';

        $chunks = array_chunk($elements, 2);

        $rowsHtml = '';
        foreach ($chunks as $key => $chunk) {
            $tds = '';
            foreach ($chunk as $element) {
                $tds .= '<td class="inner-td" bgcolor="'.$bg.'" style="background-color: inherit; border-color:'. $bg .'!important ;outline: none !important; border-width:0px;'.$tdExtraStyle.'" align="center">'.$element.'</td>';
            }
            $rowsHtml .= '<table class="action table-without-borders" style="table-layout:fixed; margin: 0 !important;'.($key > 0 ? 'margin: 15px 0 0 0 !important;' : '').'" align="center" width="100%" style="margin: 0px !important;'.$tableExtraStyles.'" cellpadding="0" cellspacing="0" role="presentation">
                    <tr>
                        <td align="center" class="outer-td" bgcolor="">
                            <table  class="wrapper-mobile" border="0" cellpadding="0" cellspacing="0" role="presentation" style="border-width:0px; border-spacing:'.$spacing.' 0px;">
                                <tbody><tr>'.$tds.'</tr></tbody>
                            </table>
                        </td>
                    </tr>
                </table>';
        }

        return $rowsHtml;
    }
}

if (!function_exists('getMentionHtml')) {
    function getMentionHtml($type)
    {
        return '<span class="mention" data-mention="'.$type.'">';
    }
}

if (!function_exists('getFullMentionHtml')) {
    function getFullMentionHtml($type, $label)
    {
    	return '<p>'.getMentionHtml($type).__($label).'</span></p>';
    }
}

if (!function_exists('replaceMention')) {
    function replaceMention($subject, $type, $replaceWith)
    {
        $mentionHtml = '<span class="mention" data-mention="' . $type . '"';
        $start = strpos($subject, $mentionHtml);

        while ($start !== false) {
            // Encuentra el final del span
            $end = strpos($subject, '</span>', $start);
            if ($end === false) {
                break; // Si no se encuentra el cierre, salimos del bucle
            }

            // Calcula la longitud del contenido a reemplazar
            $length = $end + strlen('</span>') - $start;

            // Reemplaza el contenido
            $subject = substr_replace($subject, $replaceWith, $start, $length);

            // Busca la siguiente ocurrencia
            $start = strpos($subject, $mentionHtml, $start + strlen($replaceWith));
        }

        return $subject;
    }
}

if (!function_exists('replaceAllMentions')) {
    function replaceAllMentions($text, $mentions = [])
    {
        collect($mentions)->each(function($mention, $type) use (&$text){
            $text = replaceMention($text, $type, $mention);
        });

        return $text;
    }
}

if (!function_exists('adfLinkHtml')) {
    function adfLinkHtml()
    {
    	return '<a href="https://'.coolectoDotCom().'" target="_blank">'.coolectoDotCom().'</a>';
    }
}

/* MAIL HTML ELEMENTS */
if (!function_exists('mailTitle')) {
    function mailTitle($label)
    {
    	return '<div style="font-weight:700;font-size:1.3rem;color:rgb(5, 21, 61)">'.__($label).'</div>';
    }
}

if (!function_exists('mailTitleDark')) {
    function mailTitleDark($label)
    {
    	return '<div style="font-weight:700;font-size:1.3rem;color:rgb(5, 21, 61)">'.__($label).'</div>';
    }
}

if (!function_exists('mailSubtitle')) {
    function mailSubtitle($label)
    {
    	return '<div style="font-size:1.3rem;">'.__($label).'</div>';
    }
}

if (!function_exists('mailMinititle')) {
    function mailMinititle($label, $additionalStyle = 'text-align:center')
    {
    	return '<div style="font-size:0.9rem;font-weight:600;color:black;margin-bottom:0.5rem;'.$additionalStyle.'">'.__($label).'</div>';
    }
}

if (!function_exists('mailMiniLabel')) {
    function mailMiniLabel($label)
    {
    	return '<div style="font-weight:600;font-size:0.7rem;opacity:60%;text-transform:uppercase;margin-bottom:0.2rem;">'.__($label).'</div>';
    }
}

if (!function_exists('mailCurrency')) {
    function mailCurrency($label)
    {
    	return '<div style="font-weight:400;text-align:right">$'.number_format($label, 2).' CAD</div>';
    }
}

if (!function_exists('mailCurrencyBold')) {
    function mailCurrencyBold($label)
    {
    	return '<div style="text-align:right;font-weight:600;">$'.number_format($label, 2).'</div>';
    }
}

if (!function_exists('mailCurrencyBigBold')) {
    function mailCurrencyBigBold($label)
    {
    	return '<div style="font-size:1.5rem;font-weight:800">$'.number_format($label, 2).'</div>';
    }
}

if (!function_exists('mailCurrencyLeft')) {
    function mailCurrencyLeft($label)
    {
    	return '<div style="font-size:0.9rem;font-weight:400;">$'.number_format($label, 2).'</div>';
    }
}

if (!function_exists('mailValue')) {
    function mailValue($label)
    {
    	return '<div style="font-size:0.9rem;font-weight:400">'.__($label).'</div>';
    }
}

if (!function_exists('mailValueBold')) {
    function mailValueBold($label)
    {
    	return '<div style="font-size:0.9rem;font-weight:700">'.__($label).'</div>';
    }
}

if (!function_exists('mailCard')) {
    function mailCard($innerHtml, $backgroundColor = "rgb(247 249 252)", $styles = "")
    {
    	return '<div style="background: '. $backgroundColor .';padding:.9rem;border-radius: 1rem;'. $styles .'">'.$innerHtml.'</div>';
    }
}

if (!function_exists('mailTable')) {
    function mailTable($innerHtml, $style = 'width: 100%')
    {
    	return '<table class="table" style="'.$style.'">'.$innerHtml.'</table>';
    }
}

if (!function_exists('mailTableCard')) {
    function mailTableCard($innerHtml, $style = 'width: 100%', $borderColor = '#EEF2F6')
    {
    	return '<table class="table" style="'.$style.'; background-color: '.$borderColor.'; border-radius:1rem; padding: 8px 8px 8px 8px;">'.$innerHtml.'</table>';
    }
}

if (!function_exists('mailThead')) {
    function mailThead($innerHtml, $style = '')
    {
        return '<thead style="'.$style.'">'.$innerHtml.'</thead>';
    }
}

if (!function_exists('mailTbody')) {
    function mailTbody($innerHtml, $style = '')
    {
        return '<tbody style="'.$style.'">'.$innerHtml.'</tbody>';
    }
}

if (!function_exists('mailTh')) {
    function mailTh($innerHtml, $colspan = null, $style = '', $defautStyle = 'font-size:0.9rem;color:rgb(5, 21, 61);padding:0.5rem 0.5rem')
    {
    	return '<th style="'.$style.$defautStyle.'"'.($colspan ? (' colspan="'.$colspan.'"') : '').'>'.$innerHtml.'</th>';
    }
}

if (!function_exists('mailTr')) {
    function mailTr($innerHtml, $style = '')
    {
        return '<tr style="'.$style.'">'.$innerHtml.'</tr>';
    }
}

if (!function_exists('mailTd')) {
    function mailTd($innerHtml, $colspan = null, $style = '', $defautStyle = ';vertical-align:top;padding:0.5rem 0.5rem')
    {
    	return '<td style="font-size:0.9rem;color:rgb(5, 21, 61);'.$style.$defautStyle.'"'.($colspan ? (' colspan="'.$colspan.'"') : '').'>'.$innerHtml.'</td>';
    }
}

if (!function_exists('mailTdBorderT')) {
    function mailTdBorderT($innerHtml, $colspan = null)
    {
    	return mailTd($innerHtml, $colspan, 'border-top:1px solid gainsboro; border-color:#E6E6F0;font-weight:600;');
    }
}

if (!function_exists('mailPledgeTd')) {
    function mailPledgeTd($icon, $text)
    {
    	$iconHtml = $icon ? _SaxSvg($icon, 40) : '<div style="height:40px"></div>';

    	return mailTd($iconHtml.'<div style="height:10px"></div>'.mailTitle($text), null, 'text-align:center');
    }
}

if (!function_exists('mailIcon')) {
    function mailIcon($icon, $alt = 'icon', $styles = '')
    {
    	return '<img style="width:24px;'. $styles .'" src="'.asset('images/'.$icon.'.svg') .'" alt="'.__($alt).'">';
    }
}

if (!function_exists('mailSvg')) {
    function mailSvg($icon, $size = 24, $styles = '')
    {
    	return '<span style="'. $styles .'">' . _SaxSvg($icon, $size) . '</span>';
    }
}

if (!function_exists('mailProgressBar')) {
    function mailProgressBar($totalSales = 0, $goal = 100)
    {
    	$color = '#007EFF';
        $borderColor = '#EEF2F6';
        $totalSales = number_format($totalSales);
        $goal = number_format($goal);
        $pct = !$goal ? 100 : intval($totalSales / $goal * 100);

        return '<div style="text-align:center; background-color: '.$borderColor.'; border-radius:1rem; padding: 16px 16px 16px 16px;">'.
            '<div style="font-weight:700;font-size:2.5rem;color:'.$color.';margin-bottom:0.5rem">$'.$totalSales.'</div>'.
        	_ProgressBarHtml($pct, $color).
            '<div style="display:flex;justify-content:space-between;font-size:0.85rem;font-weight:bold;margin-top:0.25rem">'.
            	'<div>$'.$totalSales.' / $'.$goal.'</div>'.
            	'<div>'.$pct.'%</div>'.
            '</div>'.
        '</div>';
    }
}


if (!function_exists('mailCampaignProgressBar')) {
    function mailCampaignProgressBar($campaign)
    {
        return mailProgressBar($campaign->goal_sales, $campaign->goal);
    }
}
