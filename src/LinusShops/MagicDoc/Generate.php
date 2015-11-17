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
            $this->processMapping(
                $mapping['source'],
                $mapping['destination'],
                $mapping['interface_name']
            );
        }
    }

    private function processMapping($source, $destination, $interfaceName)
    {
        $json = file_get_contents($source);

        $decoded = json_decode($json, true);
        $interface = "<?php\n/**\n";
        foreach ($decoded as $key=>$value) {
            $type = gettype($value);
            if ($type == 'NULL') {
                $type = 'string';
            }
            $interface .= " * @method {$type} {$key}(...\$parameters)\n";
        }

        $interface .= "*/\ninterface {$interfaceName} {}";

        file_put_contents($destination, $interface);
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
