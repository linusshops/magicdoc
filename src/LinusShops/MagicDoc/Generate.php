<?php
/**
 *
 *
 * @author Sam Schmidt <samuel@dersam.net>
 * @since 2015-11-17
 * @company Linus Shops
 */

namespace LinusShops\MagicDoc;

use GuzzleHttp\Client;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Generate extends Command
{
    const START_TAG = '{{magicdoc_start}}';
    const END_TAG = '{{magicdoc_end}}';

    protected function configure()
    {
        $this
            ->setName('generate')
            ->setDescription(
                'Generate documentation for magic methods- start in a dir with a magicdoc.json file'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!$this->configIsPresent()) {
            $output->writeln(array('<error>./magicdoc.json does not exist.</error>'));
            return;
        }

        $config = json_decode(file_get_contents('magicdoc.json'), true);
        if (!$config) {
            $output->writeln(array('<error>./magicdoc.json is not valid json.</error>'));
            return;
        }

        $magentoRequests = array();

        foreach ($config as $mapping) {
            if (!isset($mapping['source'])) {
                $output->writeln(array('<error>Source not specified</error>'));
                return;
            }

            if (isset($mapping['source']['type'])
                && !($mapping['source']['type'])=='magento'
                && !isset($mapping['destination'])) {
                $output->writeln(array('<error>Destination not specified</error>'));
                return;
            }

            if (is_array($mapping['source'])) {
                switch($mapping['source']['type']) {
                    case 'file':
                        $this->processFileMapping(
                            $mapping['source']['path'],
                            $mapping['destination'],
                            isset($mapping['types']) ? $mapping['types'] : array(),
                            isset($mapping['parameters']) ? $mapping['parameters'] : array(),
                            isset($mapping['options']) ? $mapping['options']: array()
                        );
                        break;
                    case 'url':
                        $this->processUrlMapping(
                            $mapping['source'],
                            $mapping['destination'],
                            isset($mapping['types']) ? $mapping['types'] : array(),
                            isset($mapping['parameters']) ? $mapping['parameters'] : array(),
                            isset($mapping['options']) ? $mapping['options']: array()
                        );
                        break;
                    case 'magento':
                        $magentoRequests[] = $mapping;
                        break;
                }
            } else {
                $this->processFileMapping(
                    $mapping['source'],
                    $mapping['destination'],
                    isset($mapping['types']) ? $mapping['types'] : array(),
                    isset($mapping['parameters']) ? $mapping['parameters'] : array(),
                    isset($mapping['options']) ? $mapping['options']: array()
                );
            }
        }

        if (!empty($magentoRequests)) {
            $this->processMagentoMappingList(
                $magentoRequests
            );
        }
    }

    private function processMagentoMappingList(array $requests)
    {
        $docbody = "<?php \n";
        Magento::bootstrap();

        foreach ($requests as $mapping) {
            $docbody = $this->processMagentoMapping(
                $docbody,
                $mapping['source'],
                isset($mapping['types']) ? $mapping['types'] : array(),
                isset($mapping['parameters']) ? $mapping['parameters'] : array(),
                isset($mapping['options']) ? $mapping['options'] : array()
            );
        }

        file_put_contents("./__mage_ide_helper.php", $docbody);
    }

    private function processMagentoMapping($docbody, $source, $types = array(), $parameters = array(), $options = array())
    {
        $model = \Mage::getModel($source['model'])->load($source['id']);

        $data = $model->getData();
        $classname = get_class($model);

        $classdoc = "class {$classname} {";

        foreach ($data as $fieldname=>$value) {
            $parts = explode('_', $fieldname);
            $mapped = array_map(function($val){return ucfirst($val);}, $parts);
            $methodName = implode('', $mapped);
            $valueType = gettype($value);
            if ($valueType == 'object') {
                $valueType = get_class($value);
            }

            switch ($valueType) {
                case 'boolean':
                    $returnForm = 'true';
                    break;
                case 'integer':
                    $returnForm = '1';
                    break;
                case 'double':
                    $returnForm = '1.0';
                    break;
                case 'array':
                    $returnForm = array();
                    break;
                case 'string':
                    $returnForm = '""';
                    break;
                case 'null':
                    $returnForm = 'null';
                    break;
                default:
                    $returnForm = "new {$valueType}()";
            }

            $classdoc .= "\n /** \n @return {$valueType} \n */ \n public function get{$methodName}(){return {$returnForm};}";
            $classdoc .= "\n public function set{$methodName}(\$value){}";
        }

        $classdoc .= "\n}";
        //Spy on the neighbours.
        $docbody .= "\n".$classdoc;
        return $docbody;
    }

    private function preprocess($decodedJson, $options)
    {
        if (isset($options['bust_wrapper_array']) && $options['bust_wrapper_array']){
            $decodedJson = array_pop($decodedJson);
        }

        if (isset($options['descend'])) {
            $descended = $decodedJson;
            foreach (explode('|', $options['descend']) as $path) {
                $descended = $descended[$path];
            }
            $decodedJson = $descended;
        }

        return $decodedJson;
    }

    private function processUrlMapping($source, $destination, $types = array(), $parameters = array(), $options = array())
    {
        if (!isset($source['url'])) {
            throw new \Exception("Url is required");
        }

        $url = $source['url'];
        $headers = isset($source['headers']) ? $source['headers'] : array();
        $body = isset($source['body']) ? $source['body'] : null;
        $method = isset($source['method']) ? $source['method'] : 'GET';

        $client = new Client(array('headers' => $headers));
        $res = $client->request($method, $url);
        $decoded = json_decode((string)$res->getBody(), true);

        $decoded = $this->preprocess($decoded, $options);

        $this->writeDoc(
            $destination,
            $this->generateDoc($decoded, $types, $parameters, $options)
        );
    }

    private function processFileMapping($source, $destination, $types = array(), $parameters = array(), $options = array())
    {
        $json = file_get_contents($source);

        $decoded = json_decode($json, true);

        $decoded = $this->preprocess($decoded, $options);

        $this->writeDoc(
                $destination,
                $this->generateDoc($decoded, $types, $parameters, $options)
            );
    }

    private function generateDoc($decodedJsonData, $types, $parameters, $options = array())
    {
        $docblock = "";
        foreach ($decodedJsonData as $key=>$value) {
            if (!isset($types[$key])) {
                $type = gettype($value);
                if ($type == 'NULL') {
                    $type = 'string';
                }
            } else {
                $type = $types[$key];
            }

            if (!isset($parameters[$key])) {
                //Arrays are always descendable
                if($type == 'array') {
                    $params = "...\$parameters";
                }
                else if (isset($options['parameter_default'])) {
                    $params = $options['parameter_default'];
                } else {
                    $params = "";
                }
            } else {
                $params = $parameters[$key];
            }

            $docblock .= " * @method {$type} {$key}({$params})\n";
        }

        return $docblock;
    }

    private function writeDoc($destination, $docblock)
    {
        $contents = file_get_contents($destination);
        $startPosition = strpos($contents, self::START_TAG) + strlen(self::START_TAG);
        $endPosition = strpos($contents, self::END_TAG) - 3; //Subtract 3 to maintain ' * '
        $length = $endPosition - $startPosition;

        $newContents = substr_replace($contents, "\n{$docblock}", $startPosition, $length);
        file_put_contents($destination, $newContents);
    }

    private function configIsPresent()
    {
        $present = false;
        if (is_file('magicdoc.json')) {
            $present = true;
        }

        return $present;
    }
}
