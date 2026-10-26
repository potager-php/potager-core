<?php

namespace Potager\Sillon\QueryBuilder\Adapters;

class Mysql extends BaseAdapter
{
    /**
     * @var string
     */
    protected $sanitizer = '`';
}
