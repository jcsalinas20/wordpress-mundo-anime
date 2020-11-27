<?php

namespace ADP\BaseVersion\Includes\External\LoadStrategies;

use ADP\BaseVersion\Includes\Admin\Settings;
use ADP\BaseVersion\Includes\Context;
use ADP\BaseVersion\Includes\External\AdminPage\AdminPage;
use ADP\BaseVersion\Includes\External\LoadStrategies\Interfaces\LoadStrategy;
use ADP\BaseVersion\Includes\External\Updater\Updater;
use ADP\Factory;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class AdminCommon implements LoadStrategy {
	/**
	 * @var Context
	 */
	protected $context;

	/**
	 * @param Context $context
	 */
	public function __construct( $context ) {
		$this->context = $context;
	}

	public function start() {
		Updater::update();

		/**
		 * @var AdminPage    $admin_page
		 */
		$admin_page           = Factory::get( 'External_AdminPage_AdminPage', $this->context );
		$admin_page->install_register_page_hook();
		if ( $this->context->is_plugin_admin_page() ) {
			$admin_page->init_page();
		}

		new Settings( $this->context );

		/** @see Functions::install() */
		Factory::callStaticMethod( "Functions", 'install', $this->context );
	}
}
