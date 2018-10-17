<?php

class MssqlSchema extends CMssqlSchema
{
    public function __construct($conn) {
        parent::__construct($conn);
        /**
         * Recommended practice.
         */
        $this->columnTypes['text'] = 'nvarchar(max)';
        /**
         * DbLib bugs if no explicit NOT NULL is specified.
         */
        $this->columnTypes['pk'] = 'int IDENTITY PRIMARY KEY NOT NULL';
        /**
         * Varchar cannot store unicode, nvarchar can.
         */
        $this->columnTypes['string'] = 'nvarchar(255)';
        /**
         * Auto increment.
         */
        $this->columnTypes['autoincrement'] = 'integer NOT NULL IDENTITY (1,1)';
        
        $this->columnTypes['longbinary'] = 'varbinary(max)';
    }

    
    public function getColumnType($type)
    {
        $sResult=$type;
        if (isset($this->columnTypes[$type])) {
            $sResult=$this->columnTypes[$type];
        } elseif (preg_match('/^(\w+)\((.+?)\)(.*)$/', $type, $matches)) {
            if (isset($this->columnTypes[$matches[1]])) {
                $sResult=preg_replace('/\(.+\)/', '(' . $matches[2] . ')', $this->columnTypes[$matches[1]]) . $matches[3];
            }
        } elseif (preg_match('/^(\w+)\s+/', $type, $matches)) {
            if (isset($this->columnTypes[$matches[1]])) {
                $sResult=preg_replace('/^\w+/', $this->columnTypes[$matches[1]], $type);
            }
        }
       if (stripos($sResult, 'NULL') === false) {
            $sResult .= ' NULL';}
        return $sResult;
    }
}