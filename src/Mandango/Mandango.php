<?php

/*
 * Copyright 2010 Pablo Díez <pablodip@gmail.com>
 *
 * This file is part of Mandango.
 *
 * Mandango is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Mandango is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with Mandango. If not, see <http://www.gnu.org/licenses/>.
 */

namespace Mandango;

use Mandango\Cache\CacheInterface;

/**
 * Mandango.
 *
 * @author Pablo Díez <pablodip@gmail.com>
 */
class Mandango implements MandangoInterface
{
    const VERSION = '1.0.0-DEV';

    private $metadata;
    private $queryCache;
    private $loggerCallable;
    private $unitOfWork;
    private $connections;
    private $defaultConnectionName;
    private $repositories;

    /**
     * Constructor.
     *
     * @param Mandango\Metadata             $metadata       The metadata.
     * @param Mandango\Cache\CacheInterface $queryCache     The query cache.
     * @param mixed                         $loggerCallable The logger callable (optional, null by default).
     */
    public function __construct(Metadata $metadata, CacheInterface $queryCache, $loggerCallable = null)
    {
        $this->metadata = $metadata;
        $this->queryCache = $queryCache;
        $this->loggerCallable = $loggerCallable;
        $this->unitOfWork = new UnitOfWork($this);
        $this->connections = array();
        $this->repositories = array();
    }

    /**
     * {@inheritdoc}
     */
    public function getMetadata()
    {
        return $this->metadata;
    }

    /**
     * {@inheritdoc}
     */
    public function getQueryCache()
    {
        return $this->queryCache;
    }

    /**
     * {@inheritdoc}
     */
    public function getLoggerCallable()
    {
        return $this->loggerCallable;
    }

    /**
     * {@inheritdoc}
     */
    public function getUnitOfWork()
    {
        return $this->unitOfWork;
    }

    /**
     * {@inheritdoc}
     */
    public function setConnection($name, ConnectionInterface $connection)
    {
        if (null !== $this->loggerCallable) {
            $connection->setLoggerCallable($this->loggerCallable);
            $connection->setLogDefault(array('connection' => $name));
        } else {
            $connection->setLoggerCallable(null);
        }

        $this->connections[$name] = $connection;
    }

    /**
     * {@inheritdoc}
     */
    public function setConnections(array $connections)
    {
        $this->connections = array();
        foreach ($connections as $name => $connection) {
            $this->setConnection($name, $connection);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function removeConnection($name)
    {
        if (!$this->hasConnection($name)) {
            throw new \InvalidArgumentException(sprintf('The connection "%s" does not exists.', $name));
        }

        unset($this->connections[$name]);
    }

    /**
     * {@inheritdoc}
     */
    public function clearConnections()
    {
        $this->connections = array();
    }

    /**
     * {@inheritdoc}
     */
    public function hasConnection($name)
    {
        return isset($this->connections[$name]);
    }

    /**
     * {@inheritdoc}
     */
    public function getConnection($name)
    {
        if (!$this->hasConnection($name)) {
            throw new \InvalidArgumentException(sprintf('The connection "%s" does not exist.', $name));
        }

        return $this->connections[$name];
    }

    /**
     * {@inheritdoc}
     */
    public function getConnections()
    {
        return $this->connections;
    }

    /**
     * {@inheritdoc}
     */
    public function setDefaultConnectionName($name)
    {
        $this->defaultConnectionName = $name;
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultConnectionName()
    {
        return $this->defaultConnectionName;
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultConnection()
    {
        if (null === $this->defaultConnectionName) {
            throw new \RuntimeException('There is not default connection name.');
        }

        if (!isset($this->connections[$this->defaultConnectionName])) {
            throw new \RuntimeException(sprintf('The default connection "%s" does not exists.', $this->defaultConnectionName));
        }

        return $this->connections[$this->defaultConnectionName];
    }

    /**
     * {@inheritdoc}
     */
    public function getRepository($documentClass)
    {
        if (!isset($this->repositories[$documentClass])) {
            if (!$this->metadata->hasClass($documentClass) || !$this->metadata->isDocumentClass($documentClass)) {
                throw new \InvalidArgumentException(sprintf('The class "%s" is not a valid document class.', $documentClass));
            }

            $repositoryClass = $documentClass.'Repository';
            if (!class_exists($repositoryClass)) {
                throw new \RuntimeException(sprintf('The class "%s" does not exists.', $repositoryClass));
            }

            $this->repositories[$documentClass] = new $repositoryClass($this);
        }

        return $this->repositories[$documentClass];
    }

    /**
     * {@inheritdoc}
     */
    public function getAllRepositories()
    {
        foreach ($this->getMetadata()->getDocumentClasses() as $class) {
            $this->getRepository($class);
        }

        return $this->repositories;
    }

    /**
     * {@inheritdoc}
     */
    public function ensureAllIndexes()
    {
        foreach ($this->getAllRepositories() as $repository) {
            $repository->ensureIndexes();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function persist($documents)
    {
        $this->unitOfWork->persist($documents);
    }

    /**
     * {@inheritdoc}
     */
    public function remove($document)
    {
        $this->unitOfWork->remove($document);
    }

    /**
     * {@inheritdoc}
     */
    public function flush()
    {
        $this->unitOfWork->commit();
    }
}
