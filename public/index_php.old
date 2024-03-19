<?php

$html = file_get_contents('app/index.html');

/**
 * Note! Although discouraged, we use cache busting technique with a query string
 * https://www.codebyamir.com/blog/a-web-developers-guide-to-browser-caching
 *
 * We add a timestamp as query parameter to the resources (css/js files)
 * The timestamp is the Unix-time (seconds after epoch) at the moment of generating the
 * project frontend files.
 *
 * TODO: change cache busting method from query string to fingerprinting. This can
 * be done in Gulp build step, but not for case when project specific resources are not
 * bundled.
 */
$timestamp = file_get_contents('app/project/.timestamp');
if ($timestamp === false) {
    $timestamp = 0;
}

if (strpos($html, '<!--[FRAMEWORK_PLACEHOLDER]-->')) {
    $replace = <<<EOT
        <link href="app/dist/lib.min.css?{$timestamp}" rel="stylesheet" media="screen" type="text/css"/>
        <link href="app/dist/ampersand.min.css?{$timestamp}" rel="stylesheet" media="screen" type="text/css"/>
        <script src="app/dist/lib.min.js?{$timestamp}"></script>
        <script src="app/dist/ampersand.min.js?{$timestamp}"></script>
EOT;
    $html = str_replace('<!--[FRAMEWORK_PLACEHOLDER]-->', $replace, $html);
}

if (strpos($html, '<!--[PROJECT_PLACEHOLDER]-->')) {
    $replace = '';

    // Project style sheet
    if (file_exists('app/dist/project.min.css')) {
        $replace .= "<link href=\"app/dist/project.min.css?{$timestamp}\" rel=\"stylesheet\" media=\"screen\" type=\"text/css\"/>" . PHP_EOL;
    }

    // Project javascript files
    if (file_exists('app/dist/project.min.js')) {
        $replace .= "<script src=\"app/dist/project.min.js?{$timestamp}\"></script>" . PHP_EOL;
    } else {
        if ($files = glob('app/project/*.js')) {
            foreach ($files as $filepath) {
                $replace .= "<script src=\"{$filepath}?{$timestamp}\"></script>" . PHP_EOL;
            }
        }
    }
    $html = str_replace('<!--[PROJECT_PLACEHOLDER]-->', $replace, $html);
}

echo $html;
