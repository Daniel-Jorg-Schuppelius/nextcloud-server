<?php

/**
 * SPDX-FileCopyrightText: 2016-2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2016 ownCloud, Inc.
 * SPDX-License-Identifier: AGPL-3.0-only
 */
namespace OCA\DAV\DAV;

use Sabre\DAV\Auth\Backend\BackendInterface;
use Sabre\HTTP\RequestInterface;
use Sabre\HTTP\ResponseInterface;

class PublicAuth implements BackendInterface {

	/** @var string[] */
	private $publicURLs;

	public function __construct() {
		$this->publicURLs = [
			'public-calendars',
			'principals/system/public'
		];
	}

	/**
	 * When this method is called, the backend must check if authentication was
	 * successful.
	 *
	 * The returned value must be one of the following
	 *
	 * [true, "principals/username"]
	 * [false, "reason for failure"]
	 *
	 * If authentication was successful, it's expected that the authentication
	 * backend returns a so-called principal url.
	 *
	 * Examples of a principal url:
	 *
	 * principals/admin
	 * principals/user1
	 * principals/users/joe
	 * principals/uid/123457
	 *
	 * If you don't use WebDAV ACL (RFC3744) we recommend that you simply
	 * return a string such as:
	 *
	 * principals/users/[username]
	 *
	 * @param RequestInterface $request
	 * @param ResponseInterface $response
	 * @return array
	 */
	public function check(RequestInterface $request, ResponseInterface $response) {
		if ($this->isRequestPublic($request)) {
			return [true, 'principals/system/public'];
		}
		return [false, 'No public access to this resource.'];
	}

	/**
	 * @inheritdoc
	 */
	public function challenge(RequestInterface $request, ResponseInterface $response) {
	}

	/**
	 * @param RequestInterface $request
	 * @return bool
	 */
	private function isRequestPublic(RequestInterface $request) {
		$url = $request->getPath();
		$matchingUrls = array_filter($this->publicURLs, function ($publicUrl) use ($url) {
			return str_starts_with($url, $publicUrl);
		});
		return !empty($matchingUrls);
	}
}
