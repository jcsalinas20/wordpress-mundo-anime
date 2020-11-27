<?php

namespace ADP\BaseVersion\Includes\Rule\Interfaces\Limits;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

interface MaxUsageLimit {
    const MAX_USAGE_KEY = 'max_usage';

    /**
     * @param integer $max_usage
     */
    public function setMaxUsage( $max_usage );

    /**
     * @return integer
     */
    public function getMaxUsage();
}
