<?php
namespace FreePBX\modules\Digium_phones;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;
class Job implements \FreePBX\Job\TaskInterface {
	public static function run(InputInterface $input, OutputInterface $output) {
		\FreePBX::Digium_phones()->cleanupcdr();
		return true;
	}
}
