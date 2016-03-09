<?php

require_once("vendor/autoload.php");


define('TEST_FOLDER', '/tmp/heks');

if(!file_exists(TEST_FOLDER))
{
	die('Folder "'.TEST_FOLDER.'" does not exist, cannot start the tool.' . PHP_EOL);
}

use Carbon\Carbon;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class Game
{
	private $commit1, $commit2, $hex1, $hex2, $input1, $input2, $input1Path, $input2Path;
	public $winner, $sets, $winnerId;

	public function __construct(Commit $commit1, Commit $commit2)
	{
		$this->commit1 = $commit1;
		$this->commit2 = $commit2;
		$this->input1Path = tempnam('/tmp/', 'heks_');
		$this->input2Path = tempnam('/tmp/', 'heks_');
		$this->input1 = fopen($this->input1Path, 'rw');
		$this->input2 = fopen($this->input2Path, 'rw');
	}

	public function play()
	{

	

		$this->hex1 = new Process($this->commit1->getBinaryPath() . ' < ' . $this->input1Path);
		$this->hex1->start();

		$this->hex2 = new Process($this->commit2->getBinaryPath() . ' < ' . $this->input2Path);
		$this->hex2->start();

		fwrite($this->input1, "");
		sleep(1);
		 // $this->hex1->stop(3, SIGINT);
		echo $this->hex1->getOutput();
		die();
	}
}

class Commit
{
	private static $relevantFiles = ['eval.cpp', 'search.cpp'];

	public $hash, $name, $date, $previousCommit, $relevancyHash;

	public function __construct($hash, $name, $previousCommit)
	{
		$this->hash = $hash;
		$this->name = $name;
		$this->date = $this->getDateTime();
		$this->previousCommit = $previousCommit;
	}

	public function getDateTime()
	{
		$output = shell_exec('git show -s --format=%ci ' . $this->hash);
		return new Carbon($output);
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

		$this->relevancyHash = md5($this->relevancyHash);
		
		if(!$this->previousCommit)
		{
			shell_exec('/usr/bin/make -C '.$this->getCheckoutPath());
			$value = file_exists($this->getBinaryPath());

			chdir($cwd);
			return true;
		}

		if($this->relevancyHash == $this->previousCommit->relevancyHash)
		{
			chdir($cwd);
			return false;
		}

		shell_exec('/usr/bin/make -C '.$this->getCheckoutPath());
		$value = file_exists($this->getBinaryPath());

		chdir($cwd);
		return $value;
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
		echo "Downloading ".$this->hash.PHP_EOL;
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

	public function getBinaryPath()
	{
		return ($this->getCheckoutPath() . "/hex");
	}

	public function __tostring()
	{
		return $this->date->format('d/m/y h:i:s') . ' ' . $this->hash . ' (' .( $this->isRelevant() ? " RELEVANT " : "INRELEVANT") . ')';
	}

}


function getVersionsFromGit()
{
	$output = shell_exec('git log --pretty=oneline');
	$lines = explode("\n", $output);
	$lines = array_reverse($lines);
	$commits = [];
	$commit = null;
	foreach($lines as $line)
	{
		$hash = substr($line, 0, 40);
		$name = substr($line, 41);
		if(empty($line)) continue;
		$commit = new Commit($hash, $name, $commit);
		$commits[] = $commit;
	}

	return $commits;
}


$commits = getVersionsFromGit();

echo PHP_EOL;
echo "-------------------------------[ COMMITS ]-------------------------------" . PHP_EOL;
echo PHP_EOL;

foreach($commits as $commit)
{
	echo ' ' . $commit . PHP_EOL;
}
echo PHP_EOL;
echo "-------------------------------------------------------------------------" . PHP_EOL;
echo PHP_EOL;

$latestCommit = end($commits);
$latestRelevantCommit = null;

if($latestCommit->isRelevant())
{
	$latestRelevantCommit = $latestCommit;
}else
{
	$latestRelevantCommit = $latestCommit->getPreviousRelevantCommit();
}

$previousRelevantCommit = $latestRelevantCommit->getPreviousRelevantCommit();


echo " Going to play these commits:" . PHP_EOL;
echo ' [1] ' . $previousRelevantCommit . PHP_EOL;
echo ' [2] ' . $latestRelevantCommit . PHP_EOL;
echo PHP_EOL;

$game = new Game($previousRelevantCommit, $latestRelevantCommit);
$game->play();

echo ' [' . $game->winnerId . '] won in ' . $game->sets . " sets" . PHP_EOL;

echo PHP_EOL;