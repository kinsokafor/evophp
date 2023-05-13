<?php

namespace EvoPhp\Resources;

use EvoPhp\Database\Query;

class DbTable extends Query
{
    use JoinRequest;

    public function __construct() {
        parent::__construct();
    }

    public function rows($output = self::OBJECT) {
        $rows = parent::rows(self::OBJECT);
        foreach ($rows as $key => &$value) {
            $value = $this->processJoinRequest($value);
        }
        return $rows;
    }
}

?>