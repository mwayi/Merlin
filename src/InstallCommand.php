<?php

namespace Smrtr\Merlin;

use ZipArchive;
use RuntimeException;
use GuzzleHttp\Client as Http;
use Illuminate\Filesystem\Filesystem as File;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class InstallCommand extends Command
{
	/**
	 * @var string $destination
	 */
	protected $destination;

	/**
	 * @var Illuminate\Filesystem\Filesystem $file 
	 */
	protected $file;

	/**
	 * Constructor.
	 *
	 * @return void
	 */
	public function __construct()
	{
		parent::__construct();
		
		$this->file = new File;
	}

	/**
	 * Configure the command options.
	 *
	 * @return void
	 */
	protected function configure()
	{
		$this
			->setName('install')
			->setDescription('Install a new boilerplate project')
			->addArgument('src', InputArgument::REQUIRED, 'The http source of the boilerplate')
			->addArgument('dest', InputArgument::REQUIRED, 'Where to install the project');
	}

	/**
	 * Execute the command.
	 *
	 * @param  InputInterface  $input
	 * @param  OutputInterface  $output
	 * @return void
	 */
	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$this->setDestination($input);
		$this->checkIfProjectExists($directory = $input->getArgument('dest'));
		$this->download($zipFile = $this->makeZipFile(), $input);
		$this->extract($zipFile, $directory);
		$this->transclude();
		
		$this->file->delete($zipFile);

		$output->writeln('<comment>Boilerplate installed</comment>');
	}

	/**
	 * Verify that the application does not already exist.
	 *
	 * @param  string  $directory
	 * @return void
	 */
	protected function checkIfProjectExists($directory)
	{
		if ((is_dir($directory) || is_file($directory)) && $directory != getcwd()) {
			throw new RuntimeException('Project exists. I will not overwrite.');
		}
	}

	/**
	 * Create a temporary file name
	 *
	 * @return void
	 */
	protected function makeZipFile()
	{
		return getcwd() . '/' . $this->getUniqueHash() . '.zip';
	}

	/**
	 * Get a unique hash.
	 *
	 * @return string
	 */
	protected function getUniqueHash()
	{
		return md5(time() . uniqid());
	}

	/**
	 * Download the boilerplate template into a temp directory.
	 *
	 * @param  string  $zipFile
	 * @param  InputInterface  $input
	 * @return $this
	 */
	protected function download($zipFile, InputInterface $input)
	{
		$response = (new Http)->get($input->getArgument('src'));

		$this->file->put($zipFile, $response->getBody());

		return $this;
	}

	/**
	 * Extract the zip file into the given directory.
	 *
	 * @param  string  $zipFile
	 * @param  string  $directory
	 * @return $this
	 */
	protected function getDestination()
	{
		return $this->destination;
	}

	/**
	 * Set the destination of the source.
	 *
	 * @param  InputInterface  $input
	 * @return $this
	 */
	protected function setDestination(InputInterface $input)
	{   
		$destination = $input->getArgument('dest');

		if(! $this->file->isDirectory($basePath = realpath($basePathRaw = dirname($destination)))) {
			throw new RuntimeException("The directory [['$basePath' or '$basePathRaw']] must exist.");
		}

		$this->destination = $basePath . '/' . $this->file->name($destination);
	}

	/**
	 * Extract the zip file into the given directory.
	 *
	 * @param  string  $zipFile
	 * @param  string  $directory
	 * @return $this
	 */
	protected function extract($zipFile, $directory)
	{
		$zip = new ZipArchive;
		$zip->open($zipFile);
		$zip->extractTo($directory);
		$zip->close();

		return $this;
	}

	/**
	 * Reduce the contents of the archive into the parent project.
	 *
	 * @return \Smrtr\Merlin\InstallCommand $this
	 */
	protected function transclude()
	{
		$temp = $this->destination . $this->getUniqueHash();
		$contents = $this->file->directories($this->destination);
		
		if(count($contents) === 1) {
			$main = $this->file->name(array_shift($contents));
			$this->file->move($this->destination, $temp);
			$this->file->move($temp . '/' . $main, $this->destination);
		}

		$this->file->deleteDirectory($temp);
		
		return $this;
	}

	/**
	 * Find and replace tokens
	 *
	 * @return \Smrtr\Merlin\InstallCommand $this
	 */
	protected function replaceTokens()
	{
		$files = $this->file->allFiles($this->destination);

		//  @todo
		
		return $this;
	}
}
