<?php

namespace Polymind\Aws;

class SourceRepository {

	private $fullRepositoryPath;
	private $userDirectoryName;
	private $userDirectoryPath;

	function __construct() {
		$this->userDirectoryName = 'user-' . $this->getUserId();
	}

	public function createZippedRepository(GitCloneDto $gitDto, $downloadFolderPath) {
		$zippedRepositoryInfo = [];
		$zippedRepositoryName = $this->getRepositoryNameFromUrl($gitDto->getRepoUrl()) . '.zip';

		try {
			$this->storeFullRepoPath($gitDto->getRepoUrl(), $downloadFolderPath);
			$this->userDirectoryPath = $downloadFolderPath . '\\' . $this->userDirectoryName;

			$gitHandler = new GitHandler();
			$gitHandler->clone($gitDto, $this->fullRepositoryPath);

			$this->injectBuildFileIfAbsent($this->fullRepositoryPath);
			ExtendedZip::zipTree($this->fullRepositoryPath, $this->userDirectoryPath . '\\' . $zippedRepositoryName, ZipArchive::CREATE);

			$zippedRepositoryInfo = [
				'source' => $this->userDirectoryPath . '\\' . $zippedRepositoryName,
				'filename' => $zippedRepositoryName
			];

		} catch (GitException $e) {
			echo 'The Git repository could not be cloned. Error : ' . $e->getMessage();
		}
		return $zippedRepositoryInfo;
	}

	public function deleteZippedRepository() {
		$deleteResult = true;
		if ($this->userDirectoryPath !== '') {
			$deleteResult = $this->delTree($this->userDirectoryPath);
			$this->userDirectoryPath = '';
		}
		return $deleteResult;
	}

	private function getUserId() {
		$container = \Directus\Application\Application::getInstance()->getContainer();
		$acl = $container->get('acl');

		return $acl->getUserId();
	}

	private function storeFullRepoPath($url, $downloadPath) {
		$repoName = $this->getRepositoryNameFromUrl($url);
		$this->fullRepositoryPath = $downloadPath . '\\' . $this->userDirectoryName . '\\' . $repoName;
	}

	private function getRepositoryNameFromUrl($url) {
		return GitRepository::extractRepositoryNameFromUrl($url);
	}

	private function injectBuildFileIfAbsent($localRepositoryPath) {
		if (!file_exists($localRepositoryPath . '\\buildspec.yml')) {
			$this->injectBuildFile($localRepositoryPath);
		}
	}

	public function injectBuildFile($localRepositoryPath) {
		if (!copy(__DIR__ . '\\buildspec.yml', $localRepositoryPath . '\\buildspec.yml')) {
			$buildFileContent = [
				'version' => 0.2,
				'phases' => [
					'install' => [
						'commands' => [
							'npm i npm@latest -g',
						],
					],
					'pre_build' => [
						'commands' => [
							'npm install',
						],
					],
					'build' => [
						'commands' => [
							'npm run build',
						],
					],
				],
				'artifacts' => [
					'files' => [
						'**/*',
					],
					'base-directory' => 'lib',
				]
			];
			$yaml = Yaml::dump($buildFileContent, 2, 2);
			file_put_contents($localRepositoryPath . '\\buildspec.yml', $yaml);
		}
	}

	private function delTree($dir) {
		$files = array_diff(scandir($dir), array('.', '..'));

		foreach ($files as $file) {
			if (is_dir("$dir/$file")) {
				$this->delTree("$dir/$file");
			} else {
				if (!is_writable("$dir/$file")) {
					chmod("$dir/$file", 0755);
				}
				unlink("$dir/$file");
			}
		}

		return rmdir($dir);
	}
}
