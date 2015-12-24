<?php
namespace Todo;
use Parse\ParseClient;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\Console\Prompt;

class SetupController extends AbstractActionController
{
    /**
     * Location of the config file.
     * @var string
     */
    protected $configFile = 'config/autoload/local.php';

    /**
     * Location of the distribution config file.
     * @var string
     */
    protected $configDist = 'config/autoload/local.php.dist';

    /**
     * Field names parse puts in the schema, but can't be sent created the schema.
     * @var array
     */
    protected $parseFields = [
        'createdAt',
        'updatedAt',
        'ACL',
        'authData',
        'username',
        'email',
        'emailVerified',
        'objectId',
        'password'
    ];

    /**
     * Prompt the user for config values, and create the config file.
     */
    public function ConfigAction()
    {
        //does the config file exist?
        if(file_exists($this->configFile) AND !Prompt\Confirm::prompt('Config file exists, continue? (y/n) ')){
            return;
        }

        $dist = require $this->configDist;
        $values = $this->collectValues($dist);

        file_put_contents($this->configFile, '<?php' . PHP_EOL . 'return ' . var_export($values, true) . ';');

        echo "Config saved." . PHP_EOL;
    }

    /**
     * Iterate through some keys and prompt the user for the values, descend if needed.
     *
     * @param $array
     * @param bool|string $title
     * @return array
     */
    protected function collectValues($array, $title = false)
    {
        if($title){
            echo "For " . ucwords($title) . PHP_EOL;
        }

        $result = [];

        foreach($array as $field => $value){
            if(is_array($value)){
                $value = $this->collectValues($value, $field);
            } else {

                $value = Prompt\Line::prompt(ucwords((str_replace('_', ' ', $field))) . ': ', false);
            }

            $result[$field] = $value;
        }

        return $result;
    }

    /**
     * Use schema.json to create new Parse 'classes'. Pulls out fields in the schema that Parse creates by default.
     * @throws \Parse\ParseException
     */
    public function parseAction()
    {
        $schema = file_get_contents('schema.json');
        $schema = json_decode($schema, true);

        foreach($schema as $table){
            foreach($this->parseFields as $field){
                unset($table['fields'][$field]);
            }

            if(empty($table['fields'])){
                unset($table['fields']);
            }

            //will throw exception on error
            ParseClient::_request('POST', 'schemas/' . $table['className'], null, json_encode($table), true);
        }

        echo 'Schema Created' . PHP_EOL;
    }
}