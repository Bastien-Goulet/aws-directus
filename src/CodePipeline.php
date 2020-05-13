<?php

namespace Polymind\Aws;

use Aws\CodePipeline\CodePipelineClient;
use Aws\Exception\AwsException;
use Directus\Exception\Exception;

class CodePipeline {

	private $codePipelineClient;
	private $artifactNames;

	function __construct(CodePipelineClient $codePipelineClient) {
		$this->codePipelineClient = $codePipelineClient;
	}

	public function createPipeline($userId, $bucketName, $sourceObjectName, $envVariables) {
		$this->setArtifactNames($sourceObjectName);
		$deploymentObjectKey = 'builds/' . date('Y-m-d_H-i-s');
		$deploymentUrl = '';

		$promise = $this->codePipelineClient->createPipelineAsync([
			'pipeline' => [
				'artifactStore' => $this->setArtifactsStore($bucketName),
				'name' => $bucketName,
				'roleArn' => getenv('AWS_COMPONENT_ARN'),
				'stages' => $this->setStages($bucketName, $envVariables, $deploymentObjectKey),
				'version' => 1
			],
			'tags' => $this->setTags($userId)
		]);

		$promise->wait();
		return $this->createDeploymenturl($bucketName, $deploymentObjectKey);
	}

	private function setArtifactNames($sourceObjectName) {
		if (strpos($sourceObjectName, '.zip') == false) {
			throw new Exception('The source object in S3 bucket must be a zipped file.');
		}

		$this->artifactNames = [
			'sourceObjectKey' => $sourceObjectName,
			'sourceOutputName' => str_replace('.zip', '-source', $sourceObjectName),
			'buildOutputName' => str_replace('.zip', '-build', $sourceObjectName),
			'deployOutputName' => str_replace('.zip', '', $sourceObjectName),
		];
	}

	private function setArtifactsStore($bucketName) {
		return [
			'location' => $bucketName,
			'type' => 'S3'
		];
	}

	private function setStages($bucketName, $envVariables, $deploymentObjectKey) {
		$stages = [];
		array_push($stages, [
			'name' => 'Source',
			'actions' => $this->setSourceAction($bucketName),
		]);
		array_push($stages, [
			'name' => 'Build',
			'actions' => $this->setBuildAction($envVariables),
		]);
		array_push($stages, [
			'name' => 'Deploy',
			'actions' => $this->setDeployAction($bucketName, $deploymentObjectKey),
		]);
		return $stages;
	}

	private function setSourceAction($bucketName) {
		return [
			[
				'name' => 'Source',
				'actionTypeId' => [
					'category' => 'Source',
					'owner' => 'AWS',
					'version' => '1',
					'provider' => 'S3',
				],
				'configuration' => [
					'S3Bucket' => $bucketName,
					'S3ObjectKey' => $this->artifactNames['sourceObjectKey'],
					'PollForSourceChanges' => 'false',
				],
				'outputArtifacts' => [
					[
						'name' => $this->artifactNames['sourceOutputName'],
					],
				],
				'region' => getenv('AWS_REGION'),
				'runOrder' => 1,
			],
		];
	}

	private function setBuildAction($envVariables) {
		return [
			[
				'name' => 'Build',
				'actionTypeId' => [
					'category' => 'Build',
					'owner' => 'AWS',
					'version' => '1',
					'provider' => 'CodeBuild',
				],
				'configuration' => [
					'ProjectName' => 'polymind-client',
					'EnvironmentVariables' => $envVariables,
				],
				'inputArtifacts' => [
					[
						'name' => $this->artifactNames['sourceOutputName'],
					],
				],
				'outputArtifacts' => [
					[
						'name' => $this->artifactNames['buildOutputName'],
					],
				],
				'region' => getenv('AWS_REGION'),
				'runOrder' => 2,
			],
		];
	}

	private function setDeployAction($bucketName, $deploymentObjectKey) {
		return [
			[
				'name' => 'Deploy',
				'actionTypeId' => [
					'category' => 'Deploy',
					'owner' => 'AWS',
					'version' => '1',
					'provider' => 'S3',
				],
				'configuration' => [
					'BucketName' => $bucketName,
					'Extract' => 'true',
					'ObjectKey' => $deploymentObjectKey,
				],
				'inputArtifacts' => [
					[
						'name' => $this->artifactNames['buildOutputName'],
					],
				],
				'region' => getenv('AWS_REGION'),
				'runOrder' => 3,
			],
		];
	}

	private function setTags($userId) {
		return [
			[
				'key' => 'type',
				'value' => 'user-component'
			],
			[
				'key' => 'owner',
				'value' => "$userId"
			],
		];
	}

	private function createDeploymenturl($bucketName, $deploymentObjectKey) {
		return 'http://' . $bucketName . '.s3-website.' . getenv('AWS_REGION') . '.amazonaws.com/' . $deploymentObjectKey;
	}
}
