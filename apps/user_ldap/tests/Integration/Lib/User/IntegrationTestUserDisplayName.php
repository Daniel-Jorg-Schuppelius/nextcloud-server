<?php

/**
 * SPDX-FileCopyrightText: 2016-2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2016 ownCloud, Inc.
 * SPDX-License-Identifier: AGPL-3.0-only
 */
namespace OCA\User_LDAP\Tests\Integration\Lib\User;

use OCA\User_LDAP\Mapping\UserMapping;
use OCA\User_LDAP\Tests\Integration\AbstractIntegrationTest;
use OCA\User_LDAP\User\DeletedUsersIndex;
use OCA\User_LDAP\User_LDAP;
use OCA\User_LDAP\UserPluginManager;
use OCP\IDBConnection;
use OCP\IUserManager;
use OCP\Server;
use Psr\Log\LoggerInterface;

require_once __DIR__ . '/../../Bootstrap.php';

class IntegrationTestUserDisplayName extends AbstractIntegrationTest {
	/** @var UserMapping */
	protected $mapping;

	/**
	 * prepares the LDAP environment and sets up a test configuration for
	 * the LDAP backend.
	 */
	public function init() {
		require(__DIR__ . '/../../setup-scripts/createExplicitUsers.php');
		parent::init();
		$this->mapping = new UserMapping(Server::get(IDBConnection::class));
		$this->mapping->clear();
		$this->access->setUserMapper($this->mapping);
		$userBackend = new User_LDAP($this->access, Server::get(\OCP\Notification\IManager::class), Server::get(UserPluginManager::class), Server::get(LoggerInterface::class), Server::get(DeletedUsersIndex::class));
		Server::get(IUserManager::class)->registerBackend($userBackend);
	}

	/**
	 * adds a map entry for the user, so we know the username
	 *
	 * @param $dn
	 * @param $username
	 */
	private function prepareUser($dn, $username) {
		// assigns our self-picked oc username to the dn
		$this->mapping->map($dn, $username, 'fakeUUID-' . $username);
	}

	/**
	 * tests whether a display name consisting of two parts is created correctly
	 *
	 * @return bool
	 */
	protected function case1() {
		$username = 'alice1337';
		$dn = 'uid=alice,ou=Users,' . $this->base;
		$this->prepareUser($dn, $username);
		$displayName = Server::get(IUserManager::class)->get($username)->getDisplayName();

		return str_contains($displayName, '(Alice@example.com)');
	}

	/**
	 * tests whether a display name consisting of one part is created correctly
	 *
	 * @return bool
	 */
	protected function case2() {
		$this->connection->setConfiguration([
			'ldapUserDisplayName2' => '',
		]);
		$username = 'boris23421';
		$dn = 'uid=boris,ou=Users,' . $this->base;
		$this->prepareUser($dn, $username);
		$displayName = Server::get(IUserManager::class)->get($username)->getDisplayName();

		return !str_contains($displayName, '(Boris@example.com)');
	}

	/**
	 * sets up the LDAP configuration to be used for the test
	 */
	protected function initConnection() {
		parent::initConnection();
		$this->connection->setConfiguration([
			'ldapUserDisplayName' => 'displayName',
			'ldapUserDisplayName2' => 'mail',
		]);
	}
}

/** @var string $host */
/** @var int $port */
/** @var string $adn */
/** @var string $apwd */
/** @var string $bdn */
$test = new IntegrationTestUserDisplayName($host, $port, $adn, $apwd, $bdn);
$test->init();
$test->run();
