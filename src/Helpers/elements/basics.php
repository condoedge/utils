<?php

function _Video($src)
{
    return _Html('<video controls src="' . $src . '"></video>');
}

function _Audio($src)
{
    return _Html('<audio controls src="' . $src . '"></audio>');
}