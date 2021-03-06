<?php
/**
 * FullTextSearch - Full text search framework for Nextcloud
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Maxence Lange <maxence@artificial-owl.com>
 * @copyright 2018
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\FullTextSearch\Command;

use Exception;
use OCA\FullTextSearch\IFullTextSearchProvider;
use OCA\FullTextSearch\Model\ExtendedBase;
use OCA\FullTextSearch\Model\Runner;
use OCA\FullTextSearch\Service\IndexService;
use OCA\FullTextSearch\Service\MiscService;
use OCA\FullTextSearch\Service\PlatformService;
use OCA\FullTextSearch\Service\ProviderService;
use OCA\FullTextSearch\Service\RunningService;
use OCP\IUserManager;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;


class Index extends ExtendedBase {

	/** @var IUserManager */
	private $userManager;

	/** @var IndexService */
	private $indexService;

	/** @var PlatformService */
	private $platformService;

	/** @var ProviderService */
	private $providerService;

	/** @var MiscService */
	private $miscService;


	/** @var Runner */
	private $runner;


	/**
	 * Index constructor.
	 *
	 * @param IUserManager $userManager
	 * @param RunningService $runningService
	 * @param IndexService $indexService
	 * @param PlatformService $platformService
	 * @param ProviderService $providerService
	 * @param MiscService $miscService
	 */
	public function __construct(
		IUserManager $userManager, RunningService $runningService, IndexService $indexService,
		PlatformService $platformService, ProviderService $providerService, MiscService $miscService
	) {
		parent::__construct();
		$this->userManager = $userManager;

		$this->runner = new Runner($runningService, 'commandIndex');
		$this->indexService = $indexService;
		$this->indexService->setRunner($this->runner);

		$this->platformService = $platformService;
		$this->providerService = $providerService;
		$this->miscService = $miscService;
	}


	/**
	 *
	 */
	protected function configure() {
		parent::configure();
		$this->setName('fulltextsearch:index')
			 ->setDescription('Index files');
	}


	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 *
	 * @return int|null|void
	 * @throws Exception
	 */
	protected function execute(InputInterface $input, OutputInterface $output) {

		try {
			$this->runner->sourceIsCommandLine($this, $output);
			$this->runner->start();

			$providers = $this->providerService->getProviders();
			foreach ($providers as $provider) {
				$this->runner->output('indexing ' . $provider->getName() . '.');
				$provider->setRunner($this->runner);
				$this->indexProvider($provider);
			}

		} catch (Exception $e) {
			$this->runner->exception($e->getMessage(), true);
			throw $e;
		}

		$this->runner->stop();
	}


	/**
	 * @param IFullTextSearchProvider $provider
	 *
	 * @throws Exception
	 */
	private function indexProvider(IFullTextSearchProvider $provider) {
		$platform = $this->platformService->getPlatform();
		$platform->initializeIndex();
		$provider->onInitializingIndex($platform);

		$platform->setRunner($this->runner);

		$users = $this->userManager->search('');

		foreach ($users as $user) {
			$this->runner->output(' USER: ' . $user->getUID());
			$this->indexService->indexProviderContentFromUser(
				$platform, $provider, $user->getUID()
			);
		}

		$this->providerService->setProviderAsIndexed($provider, true);

	}

}



