<?php

if (!function_exists('_FileUploadLinkAndBox')) {
    /**
     * Generates a file upload link and box for attaching files.
     *
     * @param string $name The name attribute for the file input.
     * @param bool $toggleOnLoad Whether to toggle the panel on page load.
     * @param array $fileIds An array of file IDs to pre-populate the file library.
     * @param int|null $maxFilesSize The maximum size of files allowed, in Megabytes.
     * @return array An array containing the file upload link and box elements.
     */
    function _FileUploadLinkAndBox($name, $toggleOnLoad = true, $fileIds = [], $maxFilesSize = null)
    {
        $panelId = 'file-upload-'.uniqid();

        return [

            _Flex(
                _Link()->icon(_Sax('paperclip-2'))->class('text-level1 text-2xl')
                    ->balloon('files-attach-files', 'up')->attr([
                        'data-balloon-length' => 'medium',
                    ])
                    ->toggleId($panelId, $toggleOnLoad),
                _MultiFileSizeCalculationDiv(),
            ),

            _Rows(
                _FlexBetween(
                    _Rows(
                        _MultiFileWithJs()->placeholder('messaging-browse-files')->name($name)->class('mb-0'),
                        _MaxFileSizeMessage($maxFilesSize),
                    )->class('w-full md:w-5/12'),
                    _Html('messaging-or')
                        ->class('text-sm text-gray-700 my-2 md:my-0'),
                    \Condoedge\Utils\Kompo\Files\FileLibraryAttachmentQuery::libraryFilesPanel($fileIds)
                        ->class('w-full md:w-5/12'),
                )->class('flex-wrap'),
            )->class('bg-white rounded-lg p-2 space-x-2')
            ->id($panelId)

        ];
    }
}

if (!function_exists('attachmentsValidTypes')) {
    function attachmentsValidTypes()
    {
        if (app()->has('attachment-valid-types')) {
            return app('attachment-valid-types');
        }

        return ['jpg','jpeg','png','gif','doc','docx','pdf','txt','zip','rar','xlsx','xls','csv','ppt','pptx'];
    }
}
