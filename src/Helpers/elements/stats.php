<?php

function _PieStat($percentage)
{
	return _Html('<div style="height:150px;width:150px" class="flex-center relative">
<svg height="140px" width="140px" viewBox="0 0 20 20" style="position:absolute;z-index:0">
  <circle r="10" cx="10" cy="10" fill="#f0f0f0" />
  <circle r="5" cx="10" cy="10" fill="transparent"
          stroke="#007EFF"
          stroke-width="10"
          stroke-dasharray="'.($percentage*31.4).' 31.4"
          transform="rotate(-90) translate(-20)" />
</svg>
<div class="bg-white rounded-full text-4xl font-semibold z-10 px-4 py-7">'.(round($percentage*100).'%').'</div>
</div>');
}

function _ResizablePieStat($percentage, $size = 150, $color = '#007EFF', $textSize = '2.25rem', $lineWidth = 8, $extraChar = '%')
{
    $percentage = round($percentage * 100);
    $degrees = $percentage * 3.6;

    return _Html('<div style="display: flex; justify-content: center; align-items:center; border-radius: 50%; width:' . $size . 'px !important; height: '. $size .'px !important; background-image: conic-gradient( '. $color . ' ' . $degrees .'deg, #f0f0f0 '. ($degrees > 0 ? $degrees + 2 : 0) .'deg '. 360 - $degrees .'deg );" class="block">
        <div class="bg-white flex-center font-semibold z-10" style="border-radius: 50%; width:'. $size - ($lineWidth * 4) .'px; height: ' . $size - ($lineWidth * 4) . 'px; font-size:' . $textSize .';">'. $percentage . $extraChar .'</div>
    </div>
    ');
}

function _ProgressBar($pct, $bgColor = 'bg-level5', $bgHex = null, $extraStyle = '')
{
    $progressPct = _Html()->class('rounded')->style('height: 8px; width:'.($pct*100).'%;' . $extraStyle);

    if ($bgHex) {
        $progressPct = $progressPct->style('background-color: '.$bgHex.' !important;');
    } else {
        $progressPct = $progressPct->class($bgColor);
    }

    return _Rows($progressPct)->class('bg-level1 bg-opacity-10 rounded')->style('height: 8px');
}

function _ProgressBarHtml($pct, $color)
{
    $pct = intval($pct);

    return '<div style="height:10px; width:100%;max-width: 600px;overflow:hidden;background:gainsboro;border-radius:5px">'.
            '<div style="background:'.$color.';height:100%;width:'.$pct.'%"></div>'.
        '</div>';
}

function _BoxLabelNum($icon, $label, $value)
{
    return _BoxWithIcon($icon, _LabelForBox($label, $value));
}

function _LabelForBox($label, $value)
{
    return _Rows(
        _Html($label)
            ->class('text-sm leading-5 font-medium'),
        $value
            ->class('mt-1 text-3xl leading-9 font-bold whitespace-nowrap box-label-val')
    )->class('text-right rounded-2xl');
}

function _BoxWithIcon($icon, ...$els)
{
    return _FlexBetween(
        _Html()->icon(_Sax($icon, 60)->class('!opacity-50')),
        ...$els,
    )->class('h-24 rounded-2xl text-white p-8 mb-4');
}
