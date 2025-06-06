<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Settings\Sections\Personal;

use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Settings\IIconSection;

class Security implements IIconSection {

	public function __construct(
		private IL10N $l,
		private IURLGenerator $urlGenerator,
	) {
	}

	public function getIcon() {
		return $this->urlGenerator->imagePath('settings', 'password.svg');
	}

	public function getID(): string {
		return 'security';
	}

	public function getName(): string {
		return $this->l->t('Security');
	}

	public function getPriority(): int {
		return 5;
	}
}
