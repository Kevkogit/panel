<?php
/*
 * Pterodactyl - Panel
 * Copyright (c) 2015 - 2017 Dane Everitt <dane@daneeveritt.com>.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

namespace Pterodactyl\Services;

use Pterodactyl\Models\DatabaseHost;
use Illuminate\Database\DatabaseManager;
use Pterodactyl\Exceptions\DisplayException;
use Illuminate\Contracts\Encryption\Encrypter;
use Pterodactyl\Extensions\DynamicDatabaseConnection;

class DatabaseHostService
{
    /**
     * @var \Illuminate\Database\DatabaseManager
     */
    protected $database;

    /**
     * @var \Pterodactyl\Extensions\DynamicDatabaseConnection
     */
    protected $dynamic;

    /**
     * @var \Illuminate\Contracts\Encryption\Encrypter
     */
    protected $encrypter;

    /**
     * @var \Pterodactyl\Models\DatabaseHost
     */
    protected $model;

    /**
     * DatabaseHostService constructor.
     *
     * @param \Illuminate\Database\DatabaseManager              $database
     * @param \Pterodactyl\Extensions\DynamicDatabaseConnection $dynamic
     * @param \Illuminate\Contracts\Encryption\Encrypter        $encrypter
     * @param \Pterodactyl\Models\DatabaseHost                  $model
     */
    public function __construct(
        DatabaseManager $database,
        DynamicDatabaseConnection $dynamic,
        Encrypter $encrypter,
        DatabaseHost $model
    ) {
        $this->database = $database;
        $this->dynamic = $dynamic;
        $this->encrypter = $encrypter;
        $this->model = $model;
    }

    /**
     * Create a new database host and persist it to the database.
     *
     * @param  array  $data
     * @return \Pterodactyl\Models\DatabaseHost
     *
     * @throws \Throwable
     * @throws \PDOException
     */
    public function create(array $data)
    {
        $instance = $this->model->newInstance();
        $instance->password = $this->encrypter->encrypt(array_get($data, 'password'));

        $instance->fill([
            'name' => array_get($data, 'name'),
            'host' => array_get($data, 'host'),
            'port' => array_get($data, 'port'),
            'username' => array_get($data, 'username'),
            'max_databases' => null,
            'node_id' => array_get($data, 'node_id'),
        ]);

        // Check Access
        $this->dynamic->set('dynamic', $instance);
        $this->database->connection('dynamic')->select('SELECT 1 FROM dual');

        $instance->saveOrFail();

        return $instance;
    }

    /**
     * Update a database host and persist to the database.
     *
     * @param  int    $id
     * @param  array  $data
     * @return mixed
     *
     * @throws \PDOException
     */
    public function update($id, array $data)
    {
        $model = $this->model->findOrFail($id);

        if (! empty(array_get($data, 'password'))) {
            $model->password = $this->encrypter->encrypt($data['password']);
        }

        $model->fill($data);
        $this->dynamic->set('dynamic', $model);
        $this->database->connection('dynamic')->select('SELECT 1 FROM dual');

        $model->saveOrFail();

        return $model;
    }

    /**
     * Delete a database host if it has no active databases attached to it.
     *
     * @param  int  $id
     * @return bool|null
     *
     * @throws \Pterodactyl\Exceptions\DisplayException
     */
    public function delete($id)
    {
        $model = $this->model->withCount('databases')->findOrFail($id);

        if ($model->databases_count > 0) {
            throw new DisplayException('Cannot delete a database host that has active databases attached to it.');
        }

        return $model->delete();
    }
}
