<?php
require_once ('vendor/autoload.php');

use Sitebill\DataSourceApi\Xml_Parser;
$xml_parser = new Xml_Parser();
$xml_parser->main();
