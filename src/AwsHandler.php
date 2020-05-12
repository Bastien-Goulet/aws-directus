<?php

namespace Polymind\Aws;

use Aws\Sdk;
use Exception;

class AwsHandler {

	private $userId;
	private $awsComponentName;
	private $downloadsPath;
	private $sdk;
	private $s3Client;
	private $codePipelineClient;

	public function __construct($userId, $awsComponentName, $gitRepoDownloadsPath) {
		$this->userId = $userId;
		$this->awsComponentName = $awsComponentName;
		$this->downloadsPath = $gitRepoDownloadsPath;

		$sharedConfig = $this->setClientSharedConfig();
		$this->sdk = new Sdk($sharedConfig);
		$this->initializeClients();
	}

	public function createAutomatedCompilationProcess(DTOs\GitCloneDto $gitDto, $envVariables) {

		$deploymentUrl = '';
		$s3 = new S3($this->s3Client);
		$bucketCreated = $s3->createBucket($this->userId, $this->awsComponentName);

		if ($bucketCreated) {

			$source = new SourceRepository();
			$gitRepoInfo = $source->createZippedRepository($gitDto, $this->downloadsPath);

			$s3->addFile($this->awsComponentName, $gitRepoInfo['source'], $gitRepoInfo['filename']);
			$source->deleteZippedRepository();

			$codePipeline = new CodePipeline($this->codePipelineClient);
			$deploymentUrl = $codePipeline->createPipeline($this->userId, $this->awsComponentName, $gitRepoInfo['filename'], $envVariables);
		} else {
			throw new Exception('An error occured while creating the S3 bucket.');
		}

		return $deploymentUrl;
	}

	private function setClientSharedConfig() {
		return [
			'region' => getenv('AWS_REGION'),
			'version' => getenv('AWS_API_VERSION')
		];
	}

	private function initializeClients() {
		$this->s3Client = $this->sdk->createS3();
		$this->codePipelineClient = $this->sdk->createCodePipeline();
	}
}
