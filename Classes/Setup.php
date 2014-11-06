<?php

/**
 * @license GPLv3, http://www.gnu.org/copyleft/gpl.html
 * @copyright Aimeos (aimeos.org), 2014
 * @package TYPO3_Aimeos
 */


require_once dirname( __DIR__ ) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';


/**
 * Aimeos setup class.
 *
 * @package TYPO3_Aimeos
 */
class Tx_Aimeos_Setup
{
	/**
	 * Autoloader for setup tasks.
	 *
	 * @param string $classname Name of the class to load
	 * @return boolean True if class was found, false if not
	 */
	public static function autoload( $classname )
	{
		if( strncmp( $classname, 'MW_Setup_Task_', 14 ) === 0 )
		{
		    $fileName = substr( $classname, 14 ) . '.php';
			$paths = explode( PATH_SEPARATOR, get_include_path() );

			foreach( $paths as $path )
			{
				$file = $path . DIRECTORY_SEPARATOR . $fileName;

				if( file_exists( $file ) === true && ( include_once $file ) !== false ) {
					return true;
				}
			}
		}

		return false;
	}


	/**
	 * Executes the setup tasks for updating the database.
	 *
	 * The setup tasks print their information directly to the standard output.
	 * To avoid this, it's necessary to use the output buffering handler
	 * (ob_start(), ob_get_contents() and ob_end_clean()).
	 */
	public static function execute()
	{
		ini_set( 'max_execution_time', 0 );

		$aimeos = Tx_Aimeos_Base::getAimeos();
		$taskPaths = $aimeos->getSetupPaths( 'default' );

		$includePaths = $taskPaths;
		$includePaths[] = get_include_path();

		if( set_include_path( implode( PATH_SEPARATOR, $includePaths ) ) === false ) {
			throw new Exception( 'Unable to extend include path' );
		}

		if( spl_autoload_register( 'Tx_Aimeos_Setup::autoload' ) === false ) {
			throw new Exception( 'Unable to register Tx_Aimeos_Setup::autoload' );
		}


		$ctx = self::_getContext();

		$dbm = $ctx->getDatabaseManager();
		$config = $ctx->getConfig();
		$local = array();

		if( ( $dbconfig = $config->get( 'resource/db' ) ) === null ) {
			throw new Exception( 'Configuration for database adapter missing' );
		}
		$config->set( 'resource/db/limit', 2 );

		if( Tx_Aimeos_Base::getExtConfig( 'useDemoData', 1 ) == 1 ) {
			$local = array( 'setup' => array( 'default' => array( 'demo' => true ) ) );
		}
		$ctx->setConfig( new MW_Config_Decorator_Memory( $config, $local ) );


		$manager = new MW_Setup_Manager_Default( $dbm->acquire(), $dbconfig, $taskPaths, $ctx );
		$manager->run( $dbconfig['adapter'] );
	}


	/**
	 * Returns a new context object.
	 *
	 * @return MShop_Context_Item_Interface Context object
	 */
	protected static function _getContext()
	{
		$ctx = new MShop_Context_Item_Default();

		$conf = Tx_Aimeos_Base::getConfig();
		$ctx->setConfig( $conf );

		$dbm = new MW_DB_Manager_PDO( $conf );
		$ctx->setDatabaseManager( $dbm );

		$logger = new MW_Logger_Errorlog( MW_Logger_ABSTRACT::INFO );
		$ctx->setLogger( $logger );

		$session = new MW_Session_None();
		$ctx->setSession( $session );

		$cache = new MW_Cache_None();
		$ctx->setCache( $cache );

		return $ctx;
	}
}