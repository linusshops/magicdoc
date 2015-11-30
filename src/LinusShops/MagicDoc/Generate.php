<?php
/**
 *
 *
 * @author Sam Schmidt <samuel@dersam.net>
 * @since 2015-11-17
 * @company Linus Shops
 */

namespace LinusShops\MagicDoc;

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
