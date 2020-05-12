<?php

namespace Polymind\Aws;

class GitCloneDto {

	private $repoUrl;
	private $repoAuthType;
	private $repoUser = '';
	private $repoPass = '';
	private $repoSshKey = '';

	function __construct($repoUrl = '', $repoAuthType = 'none', $repoUser = '', $repoPass = '', $repoSshKey = '') {
		$this->repoUrl = $repoUrl;

		switch ($repoAuthType) {
			case 'none':
				$this->repoAuthType = $repoAuthType;
				break;
			case 'user_pass':
				$this->repoAuthType = $repoAuthType;
				$this->repoUser = $repoUser;
				$this->repoPass = urlencode($repoPass);
				break;
			case 'ssh':
				$this->repoAuthType = $repoAuthType;
				$this->repoSshKey = $repoSshKey;
				break;
			default:
				$this->repoAuthType = 'none';
		}
	}

	public function getRepoUrl() {
		return $this->repoUrl;
	}

	public function getRepoAuthType() {
		return $this->repoAuthType;
	}

	public function getRepoUser() {
		return $this->repoUser;
	}

	public function getRepoPass() {
		return $this->repoPass;
	}

	public function getRepoSshKey() {
		return $this->repoSshKey;
	}
}
