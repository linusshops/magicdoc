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

        foreach ($config as $mapping) {
            if (!isset($mapping['source'])) {
                $output->writeln(array('<error>Source not specified</error>'));
                return;
            }

            if (!isset($mapping['destination'])) {
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
                            isset($mapping['parameters']) ? $mapping['parameters'] : array()
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
                }
            } else {
                $this->processFileMapping(
                    $mapping['source'],
                    $mapping['destination'],
                    isset($mapping['types']) ? $mapping['types'] : array(),
                    isset($mapping['parameters']) ? $mapping['parameters'] : array()
                );
            }
        }
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
        $res = $client->request($method, $url, array('body'=>$body));
        $decoded = json_decode($res->getBody(), true);

        if (isset($options['bust_wrapper_array']) && $options['bust_wrapper_array']){
            $decoded = array_pop($decoded);
        }

        $this->writeDoc(
            $destination,
            $this->generateDoc($decoded, $types, $parameters)
        );
    }

    private function processFileMapping($source, $destination, $types = array(), $parameters = array())
    {
        $json = file_get_contents($source);

        $decoded = json_decode($json, true);

        $this->writeDoc(
                $destination,
                $this->generateDoc($decoded, $types, $parameters)
            );
    }

    private function generateDoc($decodedJsonData, $types, $parameters)
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
                $params = "...\$parameters";
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
