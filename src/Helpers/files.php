<?php

/* FILE UTILITIES METHODS */
if (!function_exists('appendBeforeExtension')) {
    function appendBeforeExtension($path, $appendText)
    {
        return substr($path, 0, strrpos($path, '.')) . $appendText . '.' . substr($path, strrpos($path, '.') + 1);
    }
}

/* STORAGE METHODS */
if (!function_exists('publicUrlFromPath')) {
    function publicUrlFromPath($path, $defaultUrl = null)
    {
        if (\Storage::disk('public')->exists($path)) {
            return \Storage::disk('public')->url($path);
        }

        return $defaultUrl;
    }
}

if (!function_exists('publicUrlFromFileModel')) {
    function publicUrlFromFileModel($file, $defaultUrl = null)
    {
        if ($file->path ?? false) {
            if ($file->disk) {
                return \Storage::disk($file->disk)->url($file->path);
            }

            return publicUrlFromPath($file->path, $defaultUrl);
        }

        return $defaultUrl;
    }
}

/* UTILITIES */
if (!function_exists('avatarFromText')) {
    function avatarFromText($text)
    {
        return 'https://ui-avatars.com/api/?name='.urlencode($text).'&color=7F9CF5&background=EBF4FF';
    }
}

if (!function_exists('_ImgFromText')) {
    function _ImgFromText($text)
    {
        return _Img(avatarFromText($text));
    }
}

/* ELS & STYLES */
if (!function_exists('thumbStyle')) {
    function thumbStyle($komponent)
    {
        return $komponent
            ->class('p-2')
            ->class('bg-gray-100 rounded')
            ->style('width: 100%; height: 3.7rem');
    }
}

if (!function_exists('_ThumbWrapper')) {
    function _ThumbWrapper($arrayEls, $width = '8rem')
    {
        return _Rows($arrayEls)
            ->class('group2 cursor-pointer bg-white rounded-xl mr-2 mt-2')
            ->style('flex:0 0 ' . $width . ';max-width:' . $width);
    }
}

if (!function_exists('_MultiFileWithJs')) {
    function _MultiFileWithJs()
    {
        return _MultiFile()->id('email-attachments-input')->run('calculateTotalFileSize');
    }
}

if (!function_exists('_MultiFileSizeCalculationDiv')) {
    function _MultiFileSizeCalculationDiv()
    {
        return _Rows(
            _Html()->class('text-xs text-gray-700 font-semibold')->id('file-size-div'),
            _Html('messaging-your-files-exceed-max-size')->class('hidden text-danger text-xs')->id('file-size-message'),
        );
    }
}

if (!function_exists('_MaxFileSizeMessage')) {
    function _MaxFileSizeMessage($maxFileSizeInMb = 20)
    {
        return _Html(__('files.with-values-max-files-size-is', ['size' => $maxFileSizeInMb]))->class('text-xs text-gray-500');
    }
}

/* SIZE */
if (!function_exists('getReadableSize')) {
    function getReadableSize($sizeBytes)
    {
        if ($sizeBytes >= 1073741824) {
            return number_format($sizeBytes / 1073741824, 1) . ' GB';
        } elseif ($sizeBytes >= 1048576) {
            return number_format($sizeBytes / 1048576, 1) . ' MB';
        } elseif ($sizeBytes >= 1024) {
            return number_format($sizeBytes / 1024, 1) . ' KB';
        } else {
            return $sizeBytes . ' bytes';
        }
    }
}

/* ICONS */
if (!function_exists('iconMimeTypes')) {
    function iconMimeTypes()
    {
        return [
            'far fa-file-image' => imageMimeTypes(),
            'far fa-file-pdf' => pdfMimeTypes(),
            'far fa-file-archive' => archiveMimeTypes(),
            'far fa-file-word' => docMimeTypes(),
            'far fa-file-excel' => sheetMimeTypes(),
            'far fa-file-audio' => audioMimeTypes(),
            'far fa-file-video' => videoMimeTypes(),
        ];
    }
}

if (!function_exists('getIconFromMimeType')) {
    function getIconFromMimeType($mimeType)
    {
        foreach (iconMimeTypes() as $iconClass => $mimeTypes) {
            if (in_array($mimeType, $mimeTypes))
                return $iconClass;
        }

        return 'far fa-file-alt';
    }
}

/* MIME TYPES */
if (!function_exists('imageMimeTypes')) {
    function imageMimeTypes()
    {
        return ['image/jpeg', 'image/gif', 'image/png', 'image/bmp', 'image/svg+xml', 'image/webp'];
    }
}

if (!function_exists('pdfMimeTypes')) {
    function pdfMimeTypes()
    {
        return ['application/pdf'];
    }
}

if (!function_exists('archiveMimeTypes')) {
    function archiveMimeTypes()
    {
        return ['application/x-rar-compressed', 'application/zip', 'application/x-gzip', 'application/gzip', 'application/vnd.rar', 'application/x-7z-compressed'];
    }
}

if (!function_exists('docMimeTypes')) {
    function docMimeTypes()
    {
        return ['text/plain', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
    }
}

if (!function_exists('sheetMimeTypes')) {
    function sheetMimeTypes()
    {
        return ['application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
    }
}

if (!function_exists('audioMimeTypes')) {
    function audioMimeTypes()
    {
        return ['audio/basic', 'audio/aiff', 'audio/mpeg', 'audio/midi', 'audio/wave', 'audio/ogg'];
    }
}

if (!function_exists('videoMimeTypes')) {
    function videoMimeTypes()
    {
        return ['video/avi', 'video/x-msvideo', 'video/mpeg', 'video/ogg', 'video/x-matroska', 'video/quicktime', 'video/webm', 'video/mp4'];
    }
}
