<?php
namespace BaseKit;

use Symfony\Component\Console\Application;
use BaseKit\Command;

class Group extends Application {

    public function __construct($manifestDirectory) {
    	parent::__construct('Checking Manifests', '2.0');

    	$this->addCommands(array(
			new Command\Count($manifestDirectory),
			new Command\UpstreamMerge($manifestDirectory),
		));
    }
}