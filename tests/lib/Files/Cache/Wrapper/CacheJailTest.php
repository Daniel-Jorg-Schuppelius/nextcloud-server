<?php
/**
 * SPDX-FileCopyrightText: 2016-2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2016 ownCloud, Inc.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Test\Files\Cache\Wrapper;

use OC\Files\Cache\Wrapper\CacheJail;
use OC\Files\Search\SearchComparison;
use OC\Files\Search\SearchQuery;
use OC\Files\Storage\Wrapper\Jail;
use OC\User\User;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\Search\ISearchComparison;
use Test\Files\Cache\CacheTest;

/**
 * Class CacheJail
 *
 * @group DB
 *
 * @package Test\Files\Cache\Wrapper
 */
class CacheJailTest extends CacheTest {
	/**
	 * @var \OC\Files\Cache\Cache $sourceCache
	 */
	protected $sourceCache;

	protected function setUp(): void {
		parent::setUp();
		$this->storage->mkdir('foo');
		$this->sourceCache = $this->cache;
		$this->cache = new \OC\Files\Cache\Wrapper\CacheJail($this->sourceCache, 'foo');
	}

	public function testSearchOutsideJail(): void {
		$this->storage->getScanner()->scan('');
		$file1 = 'foo/foobar';
		$file2 = 'folder/foobar';
		$data1 = ['size' => 100, 'mtime' => 50, 'mimetype' => 'foo/folder'];

		$this->sourceCache->put($file1, $data1);
		$this->sourceCache->put($file2, $data1);

		$this->assertCount(2, $this->sourceCache->search('%foobar'));

		$result = $this->cache->search('%foobar%');
		$this->assertCount(1, $result);
		$this->assertEquals('foobar', $result[0]['path']);

		$result = $this->cache->search('%foo%');
		$this->assertCount(2, $result);
		usort($result, function ($a, $b) {
			return $a['path'] <=> $b['path'];
		});
		$this->assertEquals('', $result[0]['path']);
		$this->assertEquals('foobar', $result[1]['path']);
	}

	public function testSearchMimeOutsideJail(): void {
		$this->storage->getScanner()->scan('');
		$file1 = 'foo/foobar';
		$file2 = 'folder/foobar';
		$data1 = ['size' => 100, 'mtime' => 50, 'mimetype' => 'foo/folder'];

		$this->sourceCache->put($file1, $data1);
		$this->sourceCache->put($file2, $data1);

		$this->assertCount(2, $this->sourceCache->searchByMime('foo/folder'));

		$result = $this->cache->search('%foobar%');
		$this->assertCount(1, $result);
		$this->assertEquals('foobar', $result[0]['path']);
	}

	public function testSearchQueryOutsideJail(): void {
		$this->storage->getScanner()->scan('');
		$file1 = 'foo/foobar';
		$file2 = 'folder/foobar';
		$data1 = ['size' => 100, 'mtime' => 50, 'mimetype' => 'foo/folder'];

		$this->sourceCache->put($file1, $data1);
		$this->sourceCache->put($file2, $data1);

		$user = new User('foo', null, $this->createMock(IEventDispatcher::class));
		$query = new SearchQuery(new SearchComparison(ISearchComparison::COMPARE_EQUAL, 'name', 'foobar'), 10, 0, [], $user);
		$result = $this->cache->searchQuery($query);

		$this->assertCount(1, $result);
		$this->assertEquals('foobar', $result[0]['path']);

		$query = new SearchQuery(new SearchComparison(ISearchComparison::COMPARE_EQUAL, 'name', 'foo'), 10, 0, [], $user);
		$result = $this->cache->searchQuery($query);
		$this->assertCount(1, $result);
		$this->assertEquals('', $result[0]['path']);
	}

	public function testClearKeepEntriesOutsideJail(): void {
		$file1 = 'foo/foobar';
		$file2 = 'foo/foobar/asd';
		$file3 = 'folder/foobar';
		$data1 = ['size' => 100, 'mtime' => 50, 'mimetype' => 'httpd/unix-directory'];

		$this->sourceCache->put('foo', $data1);
		$this->sourceCache->put($file1, $data1);
		$this->sourceCache->put($file2, $data1);
		$this->sourceCache->put($file3, $data1);

		$this->cache->clear();

		$this->assertFalse($this->cache->inCache('foobar'));
		$this->assertTrue($this->sourceCache->inCache('folder/foobar'));
	}

	public function testGetById(): void {
		$data1 = ['size' => 100, 'mtime' => 50, 'mimetype' => 'httpd/unix-directory'];
		$id = $this->sourceCache->put('foo/bar', $data1);

		// path from jailed foo of foo/bar is bar
		$path = $this->cache->getPathById($id);
		$this->assertEquals('bar', $path);

		// path from jailed '' of foo/bar is foo/bar
		$this->cache = new \OC\Files\Cache\Wrapper\CacheJail($this->sourceCache, '');
		$path = $this->cache->getPathById($id);
		$this->assertEquals('foo/bar', $path);
	}

	public function testGetIncomplete(): void {
		//not supported
		$this->addToAssertionCount(1);
	}

	public function testMoveFromJail(): void {
		$folderData = ['size' => 100, 'mtime' => 50, 'mimetype' => 'httpd/unix-directory'];

		$this->sourceCache->put('source', $folderData);
		$this->sourceCache->put('source/foo', $folderData);
		$this->sourceCache->put('source/foo/bar', $folderData);
		$this->sourceCache->put('target', $folderData);

		$jail = new CacheJail($this->sourceCache, 'source');

		$this->sourceCache->moveFromCache($jail, 'foo', 'target/foo');

		$this->assertTrue($this->sourceCache->inCache('target/foo'));
		$this->assertTrue($this->sourceCache->inCache('target/foo/bar'));
	}

	public function testMoveToJail(): void {
		$folderData = ['size' => 100, 'mtime' => 50, 'mimetype' => 'httpd/unix-directory'];

		$this->sourceCache->put('source', $folderData);
		$this->sourceCache->put('source/foo', $folderData);
		$this->sourceCache->put('source/foo/bar', $folderData);
		$this->sourceCache->put('target', $folderData);

		$jail = new CacheJail($this->sourceCache, 'target');

		$jail->moveFromCache($this->sourceCache, 'source/foo', 'foo');

		$this->assertTrue($this->sourceCache->inCache('target/foo'));
		$this->assertTrue($this->sourceCache->inCache('target/foo/bar'));
	}

	public function testMoveBetweenJail(): void {
		$folderData = ['size' => 100, 'mtime' => 50, 'mimetype' => 'httpd/unix-directory'];

		$this->sourceCache->put('source', $folderData);
		$this->sourceCache->put('source/foo', $folderData);
		$this->sourceCache->put('source/foo/bar', $folderData);
		$this->sourceCache->put('target', $folderData);

		$jail = new CacheJail($this->sourceCache, 'target');
		$sourceJail = new CacheJail($this->sourceCache, 'source');

		$jail->moveFromCache($sourceJail, 'foo', 'foo');

		$this->assertTrue($this->sourceCache->inCache('target/foo'));
		$this->assertTrue($this->sourceCache->inCache('target/foo/bar'));
	}

	public function testSearchNested(): void {
		$this->storage->getScanner()->scan('');
		$file1 = 'foo';
		$file2 = 'foo/bar';
		$file3 = 'foo/bar/asd';
		$data1 = ['size' => 100, 'mtime' => 50, 'mimetype' => 'foo/folder'];

		$this->sourceCache->put($file1, $data1);
		$this->sourceCache->put($file2, $data1);
		$this->sourceCache->put($file3, $data1);

		$nested = new \OC\Files\Cache\Wrapper\CacheJail($this->cache, 'bar');

		$result = $nested->search('%asd%');
		$this->assertCount(1, $result);
		$this->assertEquals('asd', $result[0]['path']);
	}

	public function testRootJail(): void {
		$this->storage->getScanner()->scan('');
		$file1 = 'foo';
		$file2 = 'foo/bar';
		$file3 = 'foo/bar/asd';
		$data1 = ['size' => 100, 'mtime' => 50, 'mimetype' => 'foo/folder'];

		$this->sourceCache->put($file1, $data1);
		$this->sourceCache->put($file2, $data1);
		$this->sourceCache->put($file3, $data1);

		$nested = new \OC\Files\Cache\Wrapper\CacheJail($this->sourceCache, '');

		$result = $nested->search('%asd%');
		$this->assertCount(1, $result);
		$this->assertEquals('foo/bar/asd', $result[0]['path']);
	}

	public function testWatcher(): void {
		$storage = new Jail([
			'storage' => $this->storage,
			'root' => 'foo'
		]);
		$storage->getScanner()->scan('');
		$storage->file_put_contents('bar', 'asd');

		$this->assertFalse($this->cache->inCache('bar'));
		$storage->getWatcher()->update('bar', ['mimetype' => 'text/plain']);
		$this->assertTrue($this->cache->inCache('bar'));
	}

	public function testWatcherAfterInnerWatcher(): void {
		$storage = new Jail([
			'storage' => $this->storage,
			'root' => 'foo'
		]);
		$storage->getScanner()->scan('');
		$storage->file_put_contents('bar', 'asd');

		// let the underlying storage create it's watcher first
		$this->storage->getWatcher();

		$this->assertFalse($this->cache->inCache('bar'));
		$storage->getWatcher()->update('bar', ['mimetype' => 'text/plain']);
		$this->assertTrue($this->cache->inCache('bar'));
	}
}
