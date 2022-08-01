<?php
namespace Sitebill\DataSourceApi;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Dotenv\Dotenv;


abstract class Parser {
    /*
     * @var \MongoDB\Client
     */
    private $connection;

    /**
     * @var \MongoDB\Collection
     */
    private $collection;
    private $youla_url = 'https://youla.io';

    /**
     * @var Logger
     */
    private $log;

    /**
     * Mapper
     * @var array
     */
    private $map;

    function __construct (  ) {
        $this->log = new Logger('parser');
        $this->log->pushHandler(new StreamHandler(getenv('CRON_LOG') ?: 'parser.log'));
        try {
            $dotenv = Dotenv::createImmutable(__DIR__.'/../../../');
            $dotenv->load();
        } catch (\Exception $e) {
        }
        try {
            $this->connection = $this->get_connection();
            $this->collection = $this->connection->youla->parsed;
        } catch ( \Exception $e) {
            echo $e->getMessage();
            exit;
        }
        $this->map = $this->get_map();
    }

    function get_connection () {
        if ( $_ENV['MONGO_HOST'] ) {
            $MONGO_HOST = $_ENV['MONGO_HOST'] ?: 'not_defined';
            $MONGO_USER = $_ENV['MONGO_USER'] ?: '';
            $MONGO_PASS = $_ENV['MONGO_PASS'] ?: '';
            $MONGO_PORT = $_ENV['MONGO_PORT'] ?: 27017;
        } else {
            $MONGO_HOST = getenv('MONGO_HOST') ?: 'not_defined';
            $MONGO_USER = getenv('MONGO_USER') ?: '';
            $MONGO_PASS = getenv('MONGO_PASS') ?: '';
            $MONGO_PORT = getenv('MONGO_PORT') ?: 27017;
        }

        if ( $MONGO_HOST == 'not_defined' ) {
            $this->error('MONGO_HOST not defined');
            exit;
        } elseif ( $MONGO_HOST == '192.168.1.37') {
            $uri = "mongodb://$MONGO_HOST:$MONGO_PORT";
        } else {
            $uri = "mongodb://$MONGO_USER:$MONGO_PASS@$MONGO_HOST:$MONGO_PORT";
        }
        return new \MongoDB\Client($uri);
    }

    function warning( $message ) {
        echo $message."\n";
        $this->log->warning($message);
    }

    function error( $message ) {
        echo $message."\n";
        $this->log->error($message);
    }


    function map_item ($item) {
        $result['_id'] = $item['_id'];
        $result['id'] = $this->get_integer_id($item);
        $result['url'] = $this->youla_url.$item['product_details']['products'][0]['url'];
        $result['title'] = $item['product_details']['products'][0]['name'];
        $result['time'] = date('Y-m-d H:i:s', $item['product_details']['products'][0]['datePublished']['timestamp']);
        $result['price'] = $item['product_details']['products'][0]['price']/100;
        $result['city'] = $item['product_details']['cities'][0]['name'];
        $result['address'] = $item['product_details']['products'][0]['location']['description'];
        $result['description'] = $item['product_details']['products'][0]['description'];
        $result['source'] = 'youla.io';
        $result['coords'] = [
            'lat' => $item['product_details']['products'][0]['location']['latitude'],
            'lng' => $item['product_details']['products'][0]['location']['longitude'],
        ];
        $result['category'] = $item['product_details']['products'][0]['category'];
        $result['subcategory'] = $item['product_details']['products'][0]['subcategory'];
        if ( is_object($item['product_details']['products'][0]['attributes']) ) {
            foreach ( $item['product_details']['products'][0]['attributes'] as $attribute ) {
                $result[$attribute['slug']] = $attribute['rawValue'];
            }
        }

        $result['cat1_id'] = $this->search_match('cat1_id', $result['category']);
        $result['cat1'] = $this->search_match('cat1', $result['category']);
        $result['cat2_id'] = $this->search_match('cat2_id', $result['subcategory']);
        $result['cat2'] = $this->search_match('cat2', $result['subcategory']);
        if ( isset($result['tip_sdelki']) ) {
            $result['nedvigimost_type'] = $this->search_match('nedvigimost_type', $result['tip_sdelki']);
            $result['param_1943'] = $this->search_match('nedvigimost_type', $result['tip_sdelki']);
            $result['param_3040'] = $this->search_match('nedvigimost_type', $result['tip_sdelki']);
            $result['nedvigimost_type_id'] = $this->search_match('nedvigimost_type_id', $result['tip_sdelki']);
        } else {
            $result['nedvigimost_type'] = $this->search_match('nedvigimost_type', $result['subcategory']);
            $result['param_1943'] = $this->search_match('nedvigimost_type', $result['subcategory']);
            $result['param_3040'] = $this->search_match('nedvigimost_type', $result['subcategory']);
            $result['nedvigimost_type_id'] = $this->search_match('nedvigimost_type_id', $result['subcategory']);
        }
        if ( isset($result['komnat_v_kvartire']) ) {
            $result['param_1945'] = $this->search_match('param_1945', $result['komnat_v_kvartire']);
        }

        $result['images'] = $this->extract_images($item['product_details']['products'][0]['images']);
        return $result;
    }

    function get_integer_id ( $item ) {
        return $item['_id'];
        return substr(preg_replace("/[^0-9]/", "", $item['_id'] ),0, 10 );
    }

    function get_map () {
        return [
            'cat1_id' => [
                'Недвижимость' => 1,
            ],
            'cat1' => [
                'Недвижимость' => 'Недвижимость',
            ],
            'nedvigimost_type_id' => [
                'Продажа' => 1,
                'Аренда' => 2,
            ],
            'nedvigimost_type' => [
                'Продажа' => 'Продам',
                'Аренда' => 'Сдам',
            ],
            'cat2_id' => [
                'квартир' => 2,
                'комнаты' => 3,
                'дом' => 4,
                'участка' => 5,
                // 'гаражи' => 6, ??
                'Коммерческая' => 7,
            ],
            'cat2' => [
                'квартир' => 'Квартиры',
                'комнаты' => 'Комнаты',
                'дом' => 'Дома, дачи, коттеджи',
                'участка' => 'Земельные участки',
                // 'гаражи' => 'Гаражи и машиноместа', ??
                'Коммерческая' => 'Коммерческая недвижимость',
            ],
            'param_1945' => [
                'Студия' => 'Студия',
                '1' => '1',
                '2' => '2',
                '3' => '3',
                '4' => '4',
                '5' => '5',
                '6' => '6',
                '7' => '7',
                '8' => '8',
                '9' => '9',
            ],
        ];
    }

    function search_match ( $key, $value ) {
        foreach ( $this->map[$key] as $map_key => $map_value ) {
            if (preg_match('/'.$map_key.'/', $value)) {
                return $map_value;
            }
        }
    }

    function extract_images ( $src_images ) {
        //$result_images = $src_images;
        if ( is_object($src_images) ) {
            foreach ($src_images as $item) {
                $result_images[]['imgurl'] = $item['url'];
            }
        }
        return $result_images;
    }

    function get_not_empty_items()
    {
        $result = $this->collection->find(
            [
                'product_details' => array('$ne' => null)
            ],
            [
                'limit' => intval($_REQUEST['limit'] ?: 500 ),
                'sort'  => [ 'product_details.products.datePublished.timestamp' => -1 ],
            ]

        );
        if ( $result ) {
            return $result;
        }
        throw new Exception('Cant find records with not null details');
    }


    function main () {
        if ( $_ENV['token'] && $_ENV['token'] != $_REQUEST['token']) {
            echo 'bad token';
            exit;
        }
        $records = $this->get_not_empty_items();
        foreach ( $records as $item ) {
            $result[] = $this->map_item($item);
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($result);
        exit();
    }

}
