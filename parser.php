<?php
require_once ('vendor/autoload.php');

use Sitebill\DataSourceApi\ParserYoula;

$parser_youla = new ParserYoula();
$parser_youla->main();
