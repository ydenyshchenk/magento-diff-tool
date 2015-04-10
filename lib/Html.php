<?php

class Html
{
    public function __construct($title = '', $bodyClass = '')
    {
        $this->header($title, $bodyClass);
        return true;
    }

    public function header($title = '', $bodyClass = '')
    {
        $html = '<html>
<head>
    <title>' . ($title ? $title . ' - Diff Tool' : '') . 'Diff Tool</title>
    <link rel="stylesheet" href="' . BU . 'resources/css/bootstrap.min.css">
    <link rel="stylesheet" href="' . BU . 'resources/css/bootstrap-theme.min.css">
    <link rel="stylesheet" href="' . BU . 'resources/css/local.css">
    <script src="' . BU . 'resources/js/jquery-2.1.3.min.js"></script>
    <script src="' . BU . 'resources/js/bootstrap.min.js"></script>
    <script src="' . BU . 'resources/js/local.js"></script>
</head>
<body class="' . $bodyClass .'">';
        echo $html;
        return true;
    }

    public function footer()
    {
        $html = '</body></html>';
        echo $html;
        return true;
    }
}