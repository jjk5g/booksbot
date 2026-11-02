<?php
declare(strict_types=1);
ob_start();
require __DIR__.'/contact.php';
$html=ob_get_clean();
$ga=<<<'HTML'
<script async src="https://www.googletagmanager.com/gtag/js?id=G-6WLK1YD519"></script>
<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','G-6WLK1YD519');</script>
HTML;
$html=str_replace('</head>','<meta name="robots" content="index,follow">'.$ga.'</head>',$html);
echo $html;
