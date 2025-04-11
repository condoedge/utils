<?php

$basePath = __DIR__ . '/../../../../';
$appJsPath = $basePath. 'resources/js/app.js';

$loaderCode = "\n// Added by Condoedge Utils\n
const loader = require('./utils/components-loader');
loader.load();
\n";


if (file_exists($appJsPath)) {
    $content = file_get_contents($appJsPath);

    if (strpos($content, $extraCode) === false) {
        $postVueKompo = strpos($content, "require('vue-kompo');");
        if ($postVueKompo !== false) {
            $content = substr_replace($content, "require('vue-kompo');" . $loaderCode, $postVueKompo + strlen("require('vue-kompo');"), 0);
        } else {
            echo "vue-kompo not found in app.js.\n";
        }
    } else {
        echo "Custom code already exists in app.js.\n";
    }
} else {
    echo "app.js file not found.\n";
}