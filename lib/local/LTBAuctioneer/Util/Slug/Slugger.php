<?php

namespace LTBAuctioneer\Util\Slug;

use LTBAuctioneer\Debug\Debug;
use Exception;

/*
* Slugger
*/
class Slugger
{

    ////////////////////////////////////////////////////////////////////////

    public function __construct() {
    }

    public function buildUniqueSlug($name, $is_unique_func, $max_length=null) {
        $slug_root = $this->buildSlug($name, $max_length);

        $slug = $slug_root;
        $postfix_counter = 1;
        while (true) {
            $is_unique = $is_unique_func($slug);
            if ($is_unique) { return $slug; }

            // the first postfix is 2
            ++$postfix_counter;
            $slug = $slug_root.'-'.($postfix_counter);

            if ($postfix_counter >= 1000000) { throw new Exception("Unable to generate unique slug", 1); }
        }
    }

    public function buildSlug($name, $max_length=null) {
        if ($max_length === null) { $max_length = 52; }
        return 
            trim($this->truncate(
                rtrim(
                    preg_replace('![^a-zA-Z0-9-]+!', '', strtolower(preg_replace('![^a-zA-Z0-9]+!', '-', trim(strip_tags($name))))),
                    '-'
                ),
                $max_length
            ));
    }

    protected function truncate($text, $length = 52) {
        return substr($text, 0, $length);
    }

    ////////////////////////////////////////////////////////////////////////

}

