<?php

require_once("vendor/autoload.php");

define('TEST_FOLDER', '/tmp/heks');

if(!file_exists(TEST_FOLDER))
{
	die('Folder "'.TEST_FOLDER.'" does not exist, cannot start the tool.' . PHP_EOL);
}

use Carbon\Carbon;

class Commit
{
	private static $relevantFiles = ['eval.cpp', 'search.cpp'];

	private $hash, $name, $date, $previousCommit, $relevancyHash;

	public function __construct($hash, $name, Commit $previousCommit = null)
	{
		$this->hash = $hash;
		$this->name = $name;
		$this->date = $this->getDateTime();
		$this->previousCommit = $previousCommit;
	}

	public function getDateTime()
	{
		$output = shell_exec('git show -s --format=%ci ' . $this->hash);
		$this->output = new Carbon($output);
	}

	public function isRelevant()
	{
		$this->checkout();

		$this->relevancyHash = "";
		$cwd = getcwd();
		chdir($this->getCheckoutPath());
		foreach(self::$relevantFiles as $relevantFile)
		{
			$this->relevancyHash .= md5_file($relevantFile);
		}
		
		if(!$this->previousCommit)
		{
			chdir($cwd);
			return true;
		}

		if($this->relevancyHash === $this->previousCommit->relevancyHash)
		{
			chdir($cwd);
			return false;
		}

		shell_exec('make');

		chdir($cwd);
		return file_exists('hex');
	}

	public function getPreviousRelevantCommit()
	{
		$commit = $this;

		while($commit = $commit->previousCommit)
		{
			if(!$commit) return null;
			if($commit->isRelevant()) return $commit;
		}
	}

	public function checkout()
	{
		if(file_exists($this->getCheckoutPath())) return true;


		$cwd = getcwd();
		chdir(TEST_FOLDER);
		$output = shell_exec('wget -q https://github.com/Sander-Kastelein/heks/archive/'.$this->hash.'.zip');
		shell_exec('unzip '.$this->hash.'.zip');
		shell_exec('rm '.$this->hash.'.zip');
		chdir($cwd);

		return file_exists($this->getCheckoutPath());
	}

	public function getCheckoutPath()
	{
		return TEST_FOLDER . '/heks-' . $this->hash;
	}

}


function getVersionsFromGit()
{
	$output = shell_exec('git log --pretty=oneline');
	$lines = explode("\n", $output);

	$commits = [];
	$commit = null;
	foreach($lines as $line)
	{
		$hash = substr($line, 0, 40);
		$name = substr($line, 41);
		if(empty($line)) continue;
		$commit = new Commit($hash, $name, $commit);

		if($commit->isRelevant())
		{
			$commits[] = $commit;
		}
	}

	return $commits;
}


$commits = getVersionsFromGit();

$latestCommit = end($commits);
$previousRelevantCommit = $latestCommit->getPreviousRelevantCommit();

var_dump($previousRelevantCommit);
var_dump($latestCommit);