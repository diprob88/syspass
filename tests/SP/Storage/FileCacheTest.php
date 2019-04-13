<?php
/**
 * sysPass
 *
 * @author    nuxsmin
 * @link      https://syspass.org
 * @copyright 2012-2018, Rubén Domínguez nuxsmin@$syspass.org
 *
 * This file is part of sysPass.
 *
 * sysPass is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * sysPass is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 *  along with sysPass.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace SP\Tests\Storage;

use Faker\Factory;
use PHPUnit\Framework\TestCase;
use SP\Storage\File\FileCache;
use SP\Storage\File\FileException;

/**
 * Class FileCacheTest
 *
 * @package SP\Tests\Storage
 */
class FileCacheTest extends TestCase
{
    const CACHE_FILE = TMP_PATH . DIRECTORY_SEPARATOR . 'test.cache';

    private static $data;

    /**
     * This method is called before the first test of this test class is run.
     */
    public static function setUpBeforeClass()
    {
        self::$data = [];
        $i = 0;

        $faker = Factory::create();

        do {
            $data = new \stdClass();
            $data->id = uniqid();
            $data->name = $faker->name;
            $data->values = [1, 2, 3];
            $data->object = new \stdClass();
            $data->object->uid = uniqid();
            $data->object->type = $faker->address;
            $data->object->notes = $faker->text;

            self::$data[] = $data;

            $i++;
        } while ($i < 100);
    }

    /**
     * @throws \SP\Storage\File\FileException
     */
    public function testDeleteInvalid()
    {
        $this->expectException(FileException::class);
        $cache = new FileCache(self::CACHE_FILE);
        $cache->delete();
    }

    /**
     * @throws \SP\Storage\File\FileException
     */
    public function testSave()
    {
        $cache = new FileCache(self::CACHE_FILE);
        $cache->save(self::$data);

        $this->assertFileExists(self::CACHE_FILE);
    }

    /**
     * @throws \SP\Storage\File\FileException
     */
    public function testLoad()
    {
        $cache = new FileCache(self::CACHE_FILE);
        $data = $cache->load();

        $this->assertEquals(self::$data, $data);
    }

    /**
     * @throws \SP\Storage\File\FileException
     */
    public function testIsExpired()
    {
        // Sleep for 3 seconds before checking whether is expired
        sleep(3);

        $cache = new FileCache(self::CACHE_FILE);
        $this->assertTrue($cache->isExpired(2));
    }

    /**
     * @throws \SP\Storage\File\FileException
     */
    public function testIsExpiredDate()
    {
        // Sleep for 3 seconds before checking whether is expired
        sleep(3);

        $cache = new FileCache(self::CACHE_FILE);
        $this->assertTrue($cache->isExpiredDate(time()));
    }

    /**
     * @throws \SP\Storage\File\FileException
     */
    public function testDelete()
    {
        $cache = new FileCache(self::CACHE_FILE);
        $cache->delete();

        $this->assertTrue(true);
    }
}
