<?php

namespace Polymind\Aws;

class S3 {

	private $s3Client;

	function __construct(S3Client $s3Client) {
		$this->s3Client = $s3Client;
	}

	public function createBucket($userId, $bucketName) {
		$bucketCreated = false;

		$this->s3Client->createBucket([
			'Bucket' => $bucketName,
		]);

		$promises = [
			'versioning' => $this->enableVersioning($bucketName),
			'buildFolder' => $this->createBuildsFolder($bucketName),
			'policy' => $this->addBucketPolicy($bucketName),
			'websiteConf' => $this->addWebsiteConfiguration($bucketName),
		];

		$configPromise = all($promises);
		$configPromise
			->then(function () use (&$bucketCreated, $userId, $bucketName) {
				$this->addBucketTags($userId, $bucketName);
				$bucketCreated = true;
			})
			->otherwise(function (\Exception $e) {
				echo "Bucket configuration failed: " . $e . "\n";
			});
		$configPromise->wait();

		return $bucketCreated;
	}

	public function addFile($bucketName, $source, $key) {
		$source = fopen($source,'rb');

		$uploader = new ObjectUploader(
			$this->s3Client,
			$bucketName,
			$key,
			$source
		);

		do {
			try {
				$result = $uploader->upload();
			} catch (MultipartUploadException $e) {
				rewind($source);
				$uploader = new MultipartUploader($this->s3Client, $source, [
					'state' => $e->getState(),
				]);
			}
		} while (!isset($result));
	}

	private function enableVersioning($bucketName) {
		return $this->s3Client->putBucketVersioningAsync([
			'Bucket' => $bucketName,
			'VersioningConfiguration' => [
				'MFADelete' => 'Disabled',
				'Status' => 'Enabled',
			],
		]);
	}

	private function addBucketTags($userId, $bucketName) {
		$this->s3Client->putBucketTagging([
			'Bucket' => $bucketName,
			'Tagging' => [
				'TagSet' => [
					[
						'Key' => 'type',
						'Value' => 'user-component'
					],
					[
						'Key' => 'owner',
						'Value' => "$userId"
					],
				],
			],
		]);
	}

	private function createBuildsFolder($bucketName) {
		return $this->s3Client->putObjectAsync([
			'Bucket' => $bucketName,
			'Key' => 'builds/'
		]);
	}

	private function addBucketPolicy($bucketName) {
		return $this->s3Client->putBucketPolicyAsync([
			'Bucket' => $bucketName,
			'Policy' => '{"Version": "2012-10-17", "Id": "UserComponentBucketPolicy", "Statement": [{ "Sid": "CodeBuildAccess","Effect": "Allow","Principal": {"AWS": "' . getenv('AWS_CODEBUILD_ARN') . '"}, "Action": [ "s3:GetObject", "s3:PutObject" ], "Resource": ["arn:aws:s3:::' . $bucketName . '/*" ] }, { "Sid": "PublicAccessToBuilds","Effect": "Allow","Principal": "*", "Action": "s3:GetObject", "Resource": ["arn:aws:s3:::' . $bucketName . '/builds/*" ] } ]}',
		]);
	}

	private function addWebsiteConfiguration($bucketName) {
		return $this->s3Client->putBucketWebsiteAsync([
			'Bucket' => $bucketName,
			'WebsiteConfiguration' => [
				'IndexDocument' => [
					'Suffix' => 'index.js'
				],
			],
		]);
	}
}
