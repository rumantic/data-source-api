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

    function get_items($feedKey)
    {
        $result = $this->collection->find(
            [
                'feedKey' => array('$eq' => intval($feedKey)),
                //'product_details.cities' => array('$elemMatch' => ['name' => 'Сургут']),
            ]
        );
        if ( $result ) {
            return $result;
        }
        throw new Exception('Cant find records with not null details');
    }


    function main() {
        if ( $_ENV['token'] && $_ENV['token'] != $_REQUEST['token']) {
            echo 'bad token';
            exit;
        }

        $items = $this->get_items($_REQUEST['feedKey']);
        $ra = array();
        $log_item = array();
        $total = 0;
        $success = 0;
        $error = 0;
        foreach ( $items as $item ) {
            $tmp = [
                'externalId' => $item['externalId'],
                'internalId' => $item['internalId'],
                'internalUrl' => $item['internalUrl'],
                'success' => $item['success'],
            ];
            $total ++;
            if ($item['success']) {
                $success ++;
            } else {
                $error ++;
                $tmp['error'] = $item['error'];
            }
            $log_item[] = $tmp;

        }
        if ( isset($item['feedTime']) ) {
            $ra['date_updated'] = $item['feedTime'];
        }
        $ra['total'] = $total;
        $ra['success'] = $success;
        $ra['error'] = $error;
        $ra['logs'] = $log_item;

        header('Content-Type: application/json; charset=utf-8');

        echo json_encode($ra);
    }
}
