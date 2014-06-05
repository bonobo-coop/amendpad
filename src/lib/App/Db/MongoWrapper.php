<?php

namespace App\Db;

/**
 * Mongo CRUD operations
 */
class MongoWrapper
{
    /** @var string **/
    protected $_server;
    
    /** @var string **/
    protected $_dbname;
    
    /** @var \Mongo **/    
    protected $_conn;
    
    public function __construct($server = 'localhost:27017', $dbname = 'mydb')
    {
        $this->_server = $server;
        $this->_dbname = $dbname;
    }
    
    public function __get($name) 
    {
        return $this->_getCollection($this->_dbname, $name);
    }
    
    protected function _openConnection()
    {
        if (!$this->_conn) {
            $this->_conn = new \Mongo('mongodb://' . $this->_server);
        } else if (!$this->_conn->connected) {
            $this->_conn->connect();
        }
        
        return $this->_conn;
    }
    
    protected function _closeConnection()
    {
        if ($this->_conn) {
            $this->_conn->close();
        }
    }
    
    protected function _getCollection($collection)
    {
        $db = $this->_openConnection()->{$this->_dbname};
        
        return $db ? $db->{$collection} : null;
    }

    protected function _mapDocument($document)
    {
        if (is_array($document) && is_object($document['_id'])) {
            $document['_id'] = $document['_id']->{'$id'};
        } else if (is_object($document) && is_object($document->_id)) {
            $document->_id = $document->_id->{'$id'};
        }
        
        return $document;
    }
    
    /**
     * Create (insert)
     */
    public function create($collection, $document, array $indexes = array(), $unique = true) 
    {
        $c = $this->_getCollection($collection);
        if (!empty($indexes)) {
            $mongoIndexes = array_fill_keys($indexes, 1);
            $c->ensureIndex($mongoIndexes, array('unique' => $unique));
        }
        $success = $c->insert($document);
        if ($success) {
            $document = $this->_mapDocument($document);
        }
        $this->_closeConnection();
        
        return $success ? $document['_id'] : FALSE;
    }
    
    /**
     * Read (findOne)
     */
    public function read($collection, $id) 
    {
        $c = $this->_getCollection($collection);
        $document = $c->findOne(array(
            '_id' => new \MongoId($id)
        ));
        $this->_closeConnection();            
        
        return $this->_mapDocument($document);
    }
    
    /**
     * Update (modify properties)
     */
    public function update($collection, $document) 
    {
        // Make sure that an _id never gets through
        $id = $document['_id'];
        unset($document['_id']);
        
        $c = $this->_getCollection($collection);
        $success = $c->update(array(
            '_id'   => new \MongoId($id)
        ), array(
            '$set'  => $document
        ));
        $this->_closeConnection();            
        
        return $success;
    }

    /**
     * Delete (remove)
     */
    public function delete($collection, $id) 
    {
        $c = $this->_getCollection($db, $collection);
        $c->remove(array(
            '_id'   => new \MongoId($id)
        ), array(
            'safe'  => true
        ));
        $this->_closeConnection();
        
        return true;
    }
    
    /**
     * Find all documents (find)
     */
    public function find($collection, array $query = array(), array $fields = array())
    {
        $c = $this->_getCollection($collection);
        $cursor = $c->find($query, $fields);
        $this->_closeConnection();
        
        $docs = array();
        
        foreach ($cursor as $document) {
            $docs[] = $this->_mapDocument($document);
        }
        
        return $docs;
    }
    
    /**
     * Find a document (findOne)
     */
    public function findOne($collection, array $query = array(), array $fields = array())
    {
        $c = $this->_getCollection($collection);
        $document = $c->findOne($query, $fields);
        $this->_closeConnection();
        
        return $this->_mapDocument($document);
    }
}