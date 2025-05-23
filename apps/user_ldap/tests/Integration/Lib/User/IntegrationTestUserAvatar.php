<?php

/**
 * SPDX-FileCopyrightText: 2017-2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2016 ownCloud, Inc.
 * SPDX-License-Identifier: AGPL-3.0-only
 */
namespace OCA\User_LDAP\Tests\Integration\Lib\User;

use OCA\User_LDAP\Mapping\UserMapping;
use OCA\User_LDAP\Tests\Integration\AbstractIntegrationTest;
use OCA\User_LDAP\User\DeletedUsersIndex;
use OCA\User_LDAP\User\Manager;
use OCA\User_LDAP\User\User;
use OCA\User_LDAP\User_LDAP;
use OCA\User_LDAP\UserPluginManager;
use OCP\IAvatarManager;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Image;
use OCP\IUserManager;
use OCP\Server;
use Psr\Log\LoggerInterface;

require_once __DIR__ . '/../../Bootstrap.php';

class IntegrationTestUserAvatar extends AbstractIntegrationTest {
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
	 * A method that does the common steps of test cases 1 and 2. The evaluation
	 * is not happening here.
	 *
	 * @param string $dn
	 * @param string $username
	 * @param string $image
	 */
	private function execFetchTest($dn, $username, $image) {
		$this->setJpegPhotoAttribute($dn, $image);

		// assigns our self-picked oc username to the dn
		$this->mapping->map($dn, $username, 'fakeUUID-' . $username);

		// initialize home folder and make sure that the user will update
		// also remove an possibly existing avatar
		\OC_Util::tearDownFS();
		\OC_Util::setupFS($username);
		\OC::$server->getUserFolder($username);
		Server::get(IConfig::class)->deleteUserValue($username, 'user_ldap', User::USER_PREFKEY_LASTREFRESH);
		if (Server::get(IAvatarManager::class)->getAvatar($username)->exists()) {
			Server::get(IAvatarManager::class)->getAvatar($username)->remove();
		}

		// finally attempt to get the avatar set
		$user = $this->userManager->get($dn);
		$user->updateAvatar();
	}

	/**
	 * tests whether an avatar can be retrieved from LDAP and stored correctly
	 *
	 * @return bool
	 */
	protected function case1() {
		$image = file_get_contents(__DIR__ . '/../../data/avatar-valid.jpg');
		$dn = 'uid=alice,ou=Users,' . $this->base;
		$username = 'alice1337';

		$this->execFetchTest($dn, $username, $image);

		return Server::get(IAvatarManager::class)->getAvatar($username)->exists();
	}

	/**
	 * tests whether an image received from LDAP which is of an invalid file
	 * type is dealt with properly (i.e. not set and not dying).
	 *
	 * @return bool
	 */
	protected function case2() {
		// gif by Pmspinner from https://commons.wikimedia.org/wiki/File:Avatar2469_3.gif
		$image = file_get_contents(__DIR__ . '/../../data/avatar-invalid.gif');
		$dn = 'uid=boris,ou=Users,' . $this->base;
		$username = 'boris7844';

		$this->execFetchTest($dn, $username, $image);

		return !Server::get(IAvatarManager::class)->getAvatar($username)->exists();
	}

	/**
	 * This writes an image to the 'jpegPhoto' attribute on LDAP.
	 *
	 * @param string $dn
	 * @param string $image An image read via file_get_contents
	 * @throws \OC\ServerNotAvailableException
	 */
	private function setJpegPhotoAttribute($dn, $image) {
		$changeSet = ['jpegphoto' => $image];
		ldap_mod_add($this->connection->getConnectionResource(), $dn, $changeSet);
	}

	protected function initUserManager() {
		$this->userManager = new Manager(
			Server::get(IConfig::class),
			Server::get(LoggerInterface::class),
			Server::get(IAvatarManager::class),
			new Image(),
			Server::get(IDBConnection::class),
			Server::get(IUserManager::class),
			Server::get(\OCP\Notification\IManager::class)
		);
	}

	/**
	 * sets up the LDAP configuration to be used for the test
	 */
	protected function initConnection() {
		parent::initConnection();
		$this->connection->setConfiguration([
			'ldapUserFilter' => 'objectclass=inetOrgPerson',
			'ldapUserDisplayName' => 'displayName',
			'ldapGroupDisplayName' => 'cn',
			'ldapLoginFilter' => 'uid=%uid',
		]);
	}
}

/** @var string $host */
/** @var int $port */
/** @var string $adn */
/** @var string $apwd */
/** @var string $bdn */
$test = new IntegrationTestUserAvatar($host, $port, $adn, $apwd, $bdn);
$test->init();
$test->run();
