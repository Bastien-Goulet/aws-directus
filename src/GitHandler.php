<?php

namespace Polymind\Aws;

use Cz\Git\GitRepository;

class GitHandler {

	public function clone(DTOs\GitCloneDto $gitDto, $destination) {
		$gitCloneUrl = $this->setGitUrl($gitDto);

		GitRepository::cloneRepository($gitCloneUrl, $destination, array('--single-branch', '-b', 'dev'));
	}

	private function setGitUrl(DTOs\GitCloneDto $gitDto) {
		$finalUrl = '';
		if ($gitDto->getRepoAuthType() == 'user_pass') {
			$finalUrl = str_replace('://github', '://' . $gitDto->getRepoUser() . ':' . $gitDto->getRepoPass() . '@github', $gitDto->getRepoUrl());
		} else {
			$finalUrl = $gitDto->getRepoUrl();
		}
		return $finalUrl;
	}
}
