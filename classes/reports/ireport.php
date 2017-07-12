<?php

namespace mod_jazzquiz\reports;

/**
 * Interface ireport
 * @package mod_jazzquiz\reports
 * @author John Hoopes
 * @copyright 2015 John Hoopes
 */
interface ireport {


    public function __construct(\mod_jazzquiz\jazzquiz $jazzquiz);

    /**
     * @param \moodle_url $pageurl
     * @param array $pagevars
     * @return mixed
     */
    public function handle_request($pageurl, $pagevars);

}