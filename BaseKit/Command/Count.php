<?php
namespace BaseKit\Command;

use Symfony\Component\Console;
use BaseKit\Manifest;
    
/**
 * Count command.
 */
class Count extends Console\Command\Command {
    
    private $manifestDir = '';

    public function __construct($manifestDir) {

        parent::__construct();

        $this->manifestDir = $manifestDir;

    }

    protected function configure() {
        $this
        ->setName('group:count')
        ->setDescription('Counts the amount of themes in manifest groups.')
        ->setHelp(sprintf(
				'%sCounts the amount of themes in manifest groups.%s', 
				PHP_EOL, 
				PHP_EOL
		));
    }

    protected function execute(Console\Input\InputInterface $input, Console\Output\OutputInterface $output) {

        $manifestDir = $this->manifestDir;
        $manifest = new Manifest($manifestDir);
        $templates = $manifest->getTemplates();
        $groups = $manifest->getGroups();
        foreach ($groups as $group) {
            
            // Check hidden
            $hidden = 0;
            foreach ($group['templates'] as $template) {
                if(isset($templates[$template]['hidden'])) {
                   $hidden = $hidden + 1; 
                }
            }

            echo ($group['name'] . ' => ' . (count($group['templates'])-$hidden));
            if ($hidden > 0) {
                echo (' (including hidden = '. count($group['templates']) . ')');
            }
            echo (PHP_EOL);
        }
    }
}