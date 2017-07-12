<?php

namespace mod_jazzquiz\reports;

/**
 * Class jazzquiz_report_base
 *
 *
 * @package mod_jazzquiz\reports
 * @author John Hoopes
 * @copyright 2015 John Hoopes
 */
class jazzquiz_report_base {


    /**
     * @var \mod_jazzquiz\jazzquiz $jazzquiz
     */
    protected $jazzquiz;


    public function __construct(\mod_jazzquiz\jazzquiz $jazzquiz)
    {
        $this->jazzquiz = $jazzquiz;
    }




}