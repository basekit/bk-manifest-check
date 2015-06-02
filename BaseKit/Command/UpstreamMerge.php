<?php
namespace BaseKit\Command;

use Symfony\Component\Console;
use Symfony\Component\Process\Process;
use BaseKit\Manifest;

/**
 * Upstream command.
 */
class UpstreamMerge extends Console\Command\Command {
    
    private $manifestDir = '';

    public function __construct($manifestDir) {

        parent::__construct();

        $this->manifestDir = $manifestDir;

    }

    protected function configure() {
        $this
        ->setName('group:upstream-merge')
        ->setDescription('Merges the current branch up to version branches above i.e 57 -> 60')
        ->setHelp(sprintf(
				'%sMerges the current branch up to version branches above i.e 57 -> 60. This merges this branch into all the branches up to `toversion` inclusive. %s', 
				PHP_EOL, 
				PHP_EOL
		))
        ->addOption(
                'currentversion',
                null,
                Console\Input\InputOption::VALUE_REQUIRED,
                'Verison of this branch.'
        )
        ->addOption(
                'toversion',
                null,
                Console\Input\InputOption::VALUE_REQUIRED,
                'Version number of to merge this branch up to.'
        )
        ->addOption(
                'commit',
                null,
                Console\Input\InputOption::VALUE_OPTIONAL,
                'Commits and Pushes - Only do this when you\'re happy with merges'
        );
    }

    protected function execute(Console\Input\InputInterface $input, Console\Output\OutputInterface $output) {

        $manifestDir = $this->manifestDir;
        $currentVersion = $input->getOption('currentversion');
        $toVersion = $input->getOption('toversion');
        $commit = $input->getOption('commit');
        $scope = $this;

        if ($currentVersion > 0 && $toVersion > 0 && $currentVersion < $toVersion) {

            // Checkout next branch
            $moveDir = new Process('cd '.$manifestDir);
            $moveDir->run();

            for($v = $currentVersion+1; $v <= $toVersion; $v++ ) { 

                echo '-------------------------------------'.PHP_EOL;
                echo "Merging $currentVersion => $v".PHP_EOL;
                echo '-------------------------------------'.PHP_EOL.PHP_EOL;

                // Checkout next branch
                $gitCheckout = new Process('git --work-tree='.$manifestDir.'/ --git-dir '.$manifestDir.'/.git checkout release/'.$v);
                $gitCheckout->run(function ($type, $buffer) {
                    $this->displayOutput($type, $buffer);
                });

                // Merge branch into this one.
                $gitMerge = new Process('git --work-tree='.$manifestDir.'/ --git-dir '.$manifestDir.'/.git merge release/'.$currentVersion);
                $gitMerge->run(function ($type, $buffer) {
                    $this->displayOutput($type, $buffer);
                });

                if ($commit === true) {
                    // Merge branch into this one.
                    $gitCommit = new Process('git --work-tree='.$manifestDir.'/ --git-dir '.$manifestDir.'/.git commit -am "merges from release/'.$currentVersion.'"');
                    $gitCommit->run(function ($type, $buffer) {
                        $this->displayOutput($type, $buffer);
                    });

                    $gitPush = new Process('git --work-tree='.$manifestDir.'/ --git-dir '.$manifestDir.'/.git push origin release/'.$currentVersion);
                    $gitPush->run(function ($type, $buffer) {
                        $this->displayOutput($type, $buffer);
                    });
                } else {
                    // Reset the changes, move on to the next
                    $gitReset = new Process('git --work-tree='.$manifestDir.'/ --git-dir '.$manifestDir.'/.git reset --merge ORIG_HEAD');
                    $gitReset->run();   
                }
                
            }
        } else {
            $output->writeln('<error>`currentversion` and `toversion` need to be an integer values. Also `currentversion` < `toversion`</error>');
        }
    }

    private function displayOutput ($type, $buffer) {
        if(stristr($buffer, 'fatal:') !== FALSE) {
            echo 'ERR > '.$buffer;
            exit;
        } else {
            echo $buffer;
        }
    }
}