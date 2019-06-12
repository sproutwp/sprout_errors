<?php
/**
 * @package Sprout
 * @subpackage Helpers/Errors
 * @since 1.0.0
 *
 * @see Learn more about why this was built at documentation.com/sprout-error-handling
 */
namespace SproutErrors;

use SproutInterfaces\ModuleInterface;

/**
 * Class that handles error logging to the database.
 *
 * @internal+@service Made available to the Sprout eco-system as a service with the name 'sproutErrorHandler'.
 */
final class ErrorHandler implements ModuleInterface
{
    /**
     * Unique module name that's used to identify the module throughout the system.
     *
     * @internal Also used to build filter names, an exmaple is a wp_option to decide if this module loads or not.
     *
     * @var string
     */
    private $module_identifier = 'sprout_errors';

    /**
     * Flag to determine whether or not the cache is frozen.
     *
     * @internal Used by the core functions to determine if they can proceed with functionality such as adding to cache or saving the cache.
     *
     * @var boolean
     */
    private $frozen = False;

    /**
     * Initial array of errors retrieved from the database.
     *
     * @var array
     */
    private $cache;

    /**
     * Function that handles loading of the module's logic. Used as a high-level approach to determine whether the module should
     * load at all.
     *
     * @internal This doesn't handle error checking for the module's inner processes.
     *
     * @return boolean True if the module should load, false if not.
     */
    public function shouldItLoad()
    {
        return True;
    }

    /**
     * Handles the main logic of the module itself. This is where you should start the chain of events.
     */
    public function loadModule()
    {
        /**
         * Initial retrieval of the saved cache.
         */
        $this->cache = get_option( 'sprout_logged_errors' );

        add_action( 'shutdown', [$this, 'updateCache'], 999 );
    }

    /**
     * Updates the cache by merging what was initially retrieved from the database with the (if any) errors
     * pushed to the array by any script together and then updating the cache value with it.
     *
     * @return boolean
     */
    public function updateCache()
    {
        $update_cache_process = update_option( 'sprout_logged_errors', $this->cache, 'no' );

        if( !$update_cache_process ) {
            return False;
        }

        return True;
    }

    /**
     * Save an array of data related to an error to the object's array (in our case, the cache).
     *
     * @internal Functions such as is_wp_error still work. Treat the result of this function as a WP_Error.
     *
     * @see https://developer.wordpress.org/reference/classes/wp_error/
     *
     * @param string|int $code The WP_Error code. This is also stored in the database.
     * @param string $message The WP_Error message.
     * @param mixed $data Optional. Passes additional data to WP_Error
     *
     * @return WP_Error Returns a WP_Error no matter what, but if the object's cache is frozen, it will not save the error.
     */
    public function logError( $code, $message, $data = null )
    {
        if( !$this->frozen ) {
            /**
             * Gets the already stored errors and appends our newly passed error to
             * them and then saves it to the database.
             *
             * @internal This has a cleaning-up process. Only the last 30 errors are saved.
             */
            $time = time();

            $this->cache[] = [
                'hash' => (string) $code . $time,
                'code' => $code,
                'message' => $message,
                'data' => $data,
                'time' => $time
            ];
        }

        return new \WP_Error(
            $code,
            $message,
            $data
        );
    }

    /**
     * Returns the current cache before it will be saved to the database.
     *
     * Do note that when you use this function, you will get an array that was retrieved at action 'init:10' from
     * the database, combined with errors that were passed to the object using 'logError' up to the point
     * of where you call it, as such, unless your code is running on action 'shutdown:11', you will not get
     * the final collection of errors from the request, because that is where the the object saves the errors
     * to the database and then the "latest" collection of errors is made available to you on the next request.
     *
     * @return array The collection of errors.
     */
    public function getCache()
    {
        return $this->cache;
    }

    /**
     * Retrieves a cache item based on code.
     *
     * @see The explanations from getCache() to better understand why you might not get an error back even if it's supposed to happen. In short, you might be running your code too early.
     *
     * @param string|int $code The unique code we're searching for.
     *
     * @return array|boolean Returns an array as a cache item if its code is found or false if it's not.
     */
    public function getCacheItem( $code )
    {
        if( !empty( $this->cache ) ) {
            foreach( $this->cache as $cache_item ) {
                if( isset( $cache_item[$code] ) ) {
                    return $cache_item;
                }
            }
        }

        return False;
    }

    /**
     * Controls the deletion of cache both from memory & database.
     *
     * @return boolean Returns True if everything went well (the database deletion, mainly) and False if not.
     */
    public function clearCache()
    {
        //Freeze the cache, no more operations are allowed.
        $this->frozen = True;

        if( $this->emptyCache() ) {
            $this->removeCacheActions();

            return True;
        }

        return False;
    }

    /**
     * Empties the cache database option.
     *
     * @return boolean
     */
    private function emptyCache()
    {
        $empty_process = update_option( 'sprout_logged_errors', [] );

        if( !$empty_process ) {
            return False;
        }

        return True;
    }

    /**
     * Removes the update cache from the last action in WordPress. This will happen to prevent
     * updating the database option with what we would've saved during a request as errors.
     *
     * @return void
     */
    private function removeCacheActions()
    {
        remove_action( 'shutdown', [$this, 'updateCache'] );
    }

    /**
     * Retrieves the Sprout Module's name.
     *
     * @return string
     */
    public function getModuleName()
    {
        return $this->module_identifier;
    }

    /**
     * Retrieves the action name on which this module should be initialized on.
     *
     * @return string
     */
    public function getStartingAction()
    {
        return 'init';
    }

    /**
     * Retrieves the priority of the module.
     *
     * @internal This is used when loading the module (which always fires on an action).
     *
     * @return int
     */
    public function getPriority()
    {
        return 10;
    }
}