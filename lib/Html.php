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
    <link rel="stylesheet" href="/resources/css/bootstrap.min.css">
    <link rel="stylesheet" href="/resources/css/bootstrap-theme.min.css">
    <link rel="stylesheet" href="/resources/css/local.css">
    <script src="/resources/js/jquery-2.1.3.min.js"></script>
    <script src="/resources/js/bootstrap.min.js"></script>
    <script src="/resources/js/local.js?2"></script>
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