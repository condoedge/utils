<?php

$basePath = __DIR__ . '/../../../../';
$appJsPath = $basePath. 'resources/js/app.js';

$loaderCode = "
// Added by Condoedge Utils
import loader from './utils/components-loader';
loader.load();";


if (file_exists($appJsPath)) {
    $content = file_get_contents($appJsPath);

    if (strpos($content, $loaderCode) === false) {
        $postVueKompo = strpos($content, "require('vue-kompo');");
        if ($postVueKompo !== false) {
            $content = str_replace(
                "require('vue-kompo');",
                "require('vue-kompo');$loaderCode",
                $content
            );
            file_put_contents($appJsPath, $content);
            echo "Custom code added to app.js.\n";
        } else {
            echo "vue-kompo not found in app.js.\n";
        }
    } else {
        echo "Custom code already exists in app.js.\n";
    }
} else {
    echo "app.js file not found.\n";
}