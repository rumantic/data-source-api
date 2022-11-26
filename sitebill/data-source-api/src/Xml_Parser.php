<?php
namespace Sitebill\DataSourceApi;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Dotenv\Dotenv;
use Sitebill\DataSourceApi\Parser;

class Xml_Parser extends Parser {
    function __construct (  ) {
        $this->log = new Logger('parser');
        $this->log->pushHandler(new StreamHandler(getenv('CRON_LOG') ?: 'logs.log'));
        try {
            $dotenv = Dotenv::createImmutable(__DIR__.'/../../../');
            $dotenv->load();
        } catch (\Exception $e) {
        }
        try {
            $this->connection = $this->get_connection();
            $this->collection = $this->connection->logs->xml_parser;
        } catch ( \Exception $e) {
            echo $e->getMessage();
            exit;
        }
    }

    function get_not_empty_items()
    {
        $result = $this->collection->find(
            [
                'feedKey' => array('$ne' => null),
                //'product_details.cities' => array('$elemMatch' => ['name' => 'Сургут']),
            ]
        );
        if ( $result ) {
            return $result;
        }
        throw new Exception('Cant find records with not null details');
    }


    function main() {
        $items = $this->get_not_empty_items();
        foreach ( $items as $item ) {
            echo '<pre>';
            print_r($item);
            echo '</pre>';
        }

        echo 'main';
    }
}
