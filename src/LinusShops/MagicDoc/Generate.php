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

        $class = '';
        $json = file_get_contents('');

        $decoded = json_decode($json, true)[0];
        $doc = "";
        foreach ($decoded as $key=>$value) {
            $type = gettype($value);
            if ($type == 'NULL') {
                $type = 'string';
            }
            $doc .= " * @method {$type} {$key}(...\$parameters)\n";
        }

        $contents = file_get_contents($class);
        $startPosition = strpos($contents, self::START_TAG) + strlen(self::START_TAG);
        $endPosition = strpos($contents, self::END_TAG) - 3; //Subtract 3 to maintain ' * '
        $length = $endPosition - $startPosition;

        $newContents = substr_replace($contents, "\n{$doc}", $startPosition, $length);
        file_put_contents($class, $newContents);
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
